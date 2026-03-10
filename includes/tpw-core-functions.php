<?php
// --- TPW Core Admin Menu helper: keeps top-level menus highlighted for hidden/editor screens ---
if ( is_admin() && ! class_exists( 'TPW_Core_Admin_Menu_Helper' ) ) {
    class TPW_Core_Admin_Menu_Helper {
        public static function init() {
            add_filter( 'parent_file',  [ __CLASS__, 'force_parent' ], 9999 );
            add_filter( 'submenu_file', [ __CLASS__, 'force_submenu' ], 9999 );
            add_action( 'admin_head',   [ __CLASS__, 'force_globals' ], 9999 );
        }

        /**
         * Return the mapping array, allowing add-ons to declare their pages/post types.
         * Each map entry supports:
         *  - 'pages'       => array of ?page= slugs (hidden or visible)
         *  - 'post_types'  => array of post type slugs used on edit.php/post-new.php/post.php
         *  - 'parent_slug' => the top-level menu slug to keep expanded
         *  - 'submenu_slug'=> the submenu slug to highlight
         */
        protected static function get_map() : array {
            $default = [];

            // If FlexiEvent is present, keep its menu open on tpw_event editor screens.
            // Harmless if the CPT isn't registered; checks happen at runtime.
            $default[] = [
                'post_types'  => [ 'tpw_event' ],
                'parent_slug' => 'tpw-flexievent-dashboard',
                'submenu_slug'=> 'edit.php?post_type=tpw_event',
            ];

            /**
             * Filters the core admin menu map so add-ons can extend it.
             *
             * Example entry for Lodge RSVP Meetings:
             * [
             *   'pages'        => [
             *       'tpw-lodge-rsvp-submissions',
             *       'tpw-lodge-rsvp-add-submission',
             *       'tpw-lodge-rsvp-edit-submission',
             *       'tpw-lodge-rsvp-submissions-payments',
             *   ],
             *   'parent_slug'  => 'tpw-flexievent-dashboard',
             *   'submenu_slug' => 'tpw-lodge-rsvp-submissions',
             * ]
             */
            $map = apply_filters( 'tpw_core_menu_map', $default );
            return is_array( $map ) ? $map : [];
        }

        /**
         * Evaluate if current admin request matches a map entry.
         */
        protected static function matches_entry( array $entry ) : bool {
            $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

            if ( ! empty( $entry['pages'] ) && is_array( $entry['pages'] ) ) {
                foreach ( $entry['pages'] as $p ) {
                    if ( $page === $p ) {
                        return true;
                    }
                }
            }

            if ( ! empty( $entry['post_types'] ) && is_array( $entry['post_types'] ) ) {
                // Detect via post_type on edit.php/post-new.php
                if ( isset( $_GET['post_type'] ) ) {
                    $pt = sanitize_key( wp_unslash( $_GET['post_type'] ) );
                    if ( in_array( $pt, $entry['post_types'], true ) ) {
                        return true;
                    }
                }
                // Detect via post ID on post.php
                if ( isset( $_GET['post'] ) ) {
                    $post_id = (int) $_GET['post'];
                    if ( $post_id > 0 ) {
                        $pt = get_post_type( $post_id );
                        if ( $pt && in_array( $pt, $entry['post_types'], true ) ) {
                            return true;
                        }
                    }
                }
                // Fallback to screen object
                $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
                if ( $screen && ! empty( $screen->post_type ) && in_array( $screen->post_type, $entry['post_types'], true ) ) {
                    return true;
                }
            }

            return false;
        }

        public static function force_parent( $parent_file ) {
            foreach ( self::get_map() as $entry ) {
                if ( self::matches_entry( $entry ) ) {
                    if ( ! empty( $entry['parent_slug'] ) ) {
                        return $entry['parent_slug'];
                    }
                }
            }
            return $parent_file;
        }

        public static function force_submenu( $submenu_file ) {
            foreach ( self::get_map() as $entry ) {
                if ( self::matches_entry( $entry ) ) {
                    if ( ! empty( $entry['submenu_slug'] ) ) {
                        return $entry['submenu_slug'];
                    }
                }
            }
            return $submenu_file;
        }

        /**
         * Last resort: directly set the globals so the correct menu stays open/highlighted.
         */
        public static function force_globals() {
            global $parent_file, $submenu_file;
            foreach ( self::get_map() as $entry ) {
                if ( self::matches_entry( $entry ) ) {
                    if ( ! empty( $entry['parent_slug'] ) ) {
                        $parent_file = $entry['parent_slug'];
                    }
                    if ( ! empty( $entry['submenu_slug'] ) ) {
                        $submenu_file = $entry['submenu_slug'];
                    }
                    // First match wins
                    break;
                }
            }
        }
    }

    TPW_Core_Admin_Menu_Helper::init();
}

/**
 * Get the configured currency symbol (defaults to £).
 *
 * @return string
 */
function tpw_core_get_currency_symbol() {
    return get_option( 'flexievent_currency_symbol', '£' );
}

/**
 * Get the configured currency code (defaults to GBP).
 *
 * @return string
 */
function tpw_core_get_currency_code() {
    return get_option( 'flexievent_currency_code', 'GBP' );
}

/**
 * Core permissions bridge (Step 1).
 *
 * This helper centralises *read-only* permission checks for other TPW plugins to
 * depend on, while preserving all existing behaviour in Core.
 *
 * IMPORTANT (staged rollout):
 * - Additive only: this does not replace any checks elsewhere yet.
 * - No role/cap changes, no DB writes, no side effects.
 * - Each ability implemented here MUST delegate 1:1 to an existing runtime
 *   enforcement check already present in Core today.
 *
 * @param string $ability A Core ability/capability key (e.g. 'tpw_members_manage').
 * @param int    $user_id Optional. User ID to evaluate; 0 uses current user.
 * @return bool
 */
if ( ! function_exists( 'tpw_core_user_can' ) ) {
    function tpw_core_user_can( string $ability, int $user_id = 0 ): bool {
        $ability = strtolower( trim( $ability ) );
        if ( $ability === '' ) {
            return false;
        }

        $user_id = $user_id > 0 ? $user_id : (int) get_current_user_id();
        if ( $user_id <= 0 ) {
            // Logged-out users do not possess capabilities.
            return false;
        }

        // --- Local helpers (pure, no global user switching) ---
        $wp_user_can = static function( int $uid, string $cap ): bool {
            return function_exists( 'user_can' ) ? (bool) user_can( $uid, $cap ) : false;
        };

        // Load TPW Members access helper when needed (safe require).
        $ensure_member_access = static function(): void {
            if ( class_exists( 'TPW_Member_Access', false ) ) {
                return;
            }
            $path = defined( 'TPW_CORE_PATH' ) ? TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-access.php' : '';
            if ( $path && file_exists( $path ) ) {
                require_once $path;
            }
        };

        // Mirror TPW_Member_Access::is_admin_current() for a specific user_id.
        // Delegates to:
        // - current_user_can('manage_options') (via user_can)
        // - tpw_members/wp_admin_is_full_admin filter
        // - tpw_members.is_admin flag fallback when filter disables WP-admin override
        $tpw_members_is_admin_user = static function( int $uid ) use ( $wp_user_can, $ensure_member_access ): bool {
            $ensure_member_access();

            if ( ! $wp_user_can( $uid, 'manage_options' ) ) {
                return false;
            }

            $wp_admin_is_enough = (bool) apply_filters( 'tpw_members/wp_admin_is_full_admin', true );
            if ( $wp_admin_is_enough ) {
                return true;
            }

            if ( class_exists( 'TPW_Member_Access', false ) && method_exists( 'TPW_Member_Access', 'get_member_by_user_id' ) ) {
                $member = TPW_Member_Access::get_member_by_user_id( $uid );
                return ( $member && isset( $member->is_admin ) && (int) $member->is_admin === 1 );
            }
            return false;
        };

        // Mirror TPW_Control_UI::is_committee()/is_match_manager()/is_noticeboard_admin() for a given user_id.
        // Delegates to the same filters + tpw_members table lookup used in TPW Control today.
        $tpw_flag_from_members_table = static function( int $uid, string $flag_key, string $filter_name ): bool {
            $is = apply_filters( $filter_name, null, $uid );
            if ( null !== $is ) {
                return (bool) $is;
            }
            global $wpdb;
            if ( ! $wpdb || ! isset( $wpdb->prefix ) ) {
                return false;
            }
            $table = $wpdb->prefix . 'tpw_members';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT {$flag_key} FROM {$table} WHERE user_id = %d LIMIT 1", $uid ) );
            return ( $row && isset( $row->$flag_key ) && (int) $row->$flag_key === 1 );
        };

        // Members module "manager" permission as currently enforced by the manage-members shortcode + export.
        // Delegates to:
        // - current_user_can('manage_options') override
        // - tpw_members_manage_access option allowing committee access
        // - tpw_members.is_committee flag (linked member row)
        $tpw_members_can_manage = static function( int $uid ) use ( $wp_user_can, $ensure_member_access ): bool {
            if ( $wp_user_can( $uid, 'manage_options' ) ) {
                return true;
            }
            $manage_setting = (string) get_option( 'tpw_members_manage_access', 'admins_only' );
            if ( $manage_setting !== 'admins_committee' ) {
                return false;
            }
            $ensure_member_access();
            if ( class_exists( 'TPW_Member_Access', false ) && method_exists( 'TPW_Member_Access', 'get_member_by_user_id' ) ) {
                $member = TPW_Member_Access::get_member_by_user_id( $uid );
                return ( $member && ! empty( $member->is_committee ) && (int) $member->is_committee === 1 );
            }
            return false;
        };

        // Members directory eligibility as currently enforced by the manage-members shortcode.
        // Delegates to:
        // - TPW_Member_Access::get_allowed_statuses()
        // - member.status in tpw_members
        $tpw_members_can_view_directory = static function( int $uid ) use ( $ensure_member_access ): bool {
            $ensure_member_access();
            if ( ! class_exists( 'TPW_Member_Access', false ) || ! method_exists( 'TPW_Member_Access', 'get_member_by_user_id' ) ) {
                return false;
            }
            $member = TPW_Member_Access::get_member_by_user_id( $uid );
            if ( ! $member ) {
                return false;
            }
            $status_norm = strtolower( trim( (string) ( $member->status ?? '' ) ) );
            $allowed = method_exists( 'TPW_Member_Access', 'get_allowed_statuses' )
                ? (array) TPW_Member_Access::get_allowed_statuses()
                : ( defined( 'TPW_Member_Access::ALLOWED_STATUSES' ) ? (array) TPW_Member_Access::ALLOWED_STATUSES : [] );
            $allowed_norm = array_map( 'strtolower', array_map( 'trim', array_map( 'strval', $allowed ) ) );
            return in_array( $status_norm, $allowed_norm, true );
        };

        // --- Ability mapping (Step 1 only; add more only when there is an existing enforcement point) ---
        switch ( $ability ) {
            // === Members ===
            // Existing enforcement point: modules/members/shortcodes/members-admin.php
            // - $can_manage = manage_options OR (admins_committee && member.is_committee)
            case 'tpw_members_manage':
            case 'tpw_members_create':
            case 'tpw_members_import':
            case 'tpw_members_status_manage':
            case 'tpw_members_roles_manage':
            case 'tpw_members_userlink_manage':
                return $tpw_members_can_manage( $user_id );

            // Existing enforcement point: modules/members/shortcodes/members-admin.php
            // - Access allowed when $can_manage OR (member.status is in TPW_Member_Access::get_allowed_statuses())
            case 'tpw_members_view':
                return $tpw_members_can_manage( $user_id ) || $tpw_members_can_view_directory( $user_id );

            // === Payments ===
            // Existing enforcement points: Payments settings/admin pages currently gate on manage_options.
            // (No finer-grained separation exists in Core today; keep behaviour identical.)
            case 'tpw_payments_view':
            case 'tpw_payments_manage':
            case 'tpw_payments_methods_view':
            case 'tpw_payments_methods_manage':
                return $wp_user_can( $user_id, 'manage_options' );

            // === Menus (meal choices library) ===
            // Existing enforcement points: modules/menus/* admin pages gate on manage_options.
            case 'tpw_menus_view':
            case 'tpw_menus_manage':
                return $wp_user_can( $user_id, 'manage_options' );

            // === Notices / Noticeboard ===
            // Existing enforcement points: modules/notices/* gates management actions on manage_options.
            // Note: the TPW member flag is_noticeboard_admin is currently *not* an enforcement path here.
            case 'tpw_notices_manage':
                return $wp_user_can( $user_id, 'manage_options' );

            // === Gallery ===
            // Existing enforcement points: modules/gallery/* gates admin actions on a filterable cap.
            // Delegates to tpw_gallery_user_can_manage() when available.
            case 'tpw_gallery_upload':
            case 'tpw_gallery_manage_own':
            case 'tpw_gallery_manage_all':
            case 'tpw_gallery_settings_manage':
                if ( function_exists( 'tpw_gallery_user_can_manage' ) ) {
                    return tpw_gallery_user_can_manage( $user_id );
                }
                $cap = function_exists( 'tpw_gallery_manage_capability' ) ? (string) tpw_gallery_manage_capability() : 'manage_options';
                $cap = $cap !== '' ? $cap : 'manage_options';
                return $wp_user_can( $user_id, $cap );

            // === TPW Control ===
            // Existing enforcement points:
            // - Menu Manager section requires admin marker (__tpw_control_is_admin__)
            // - Upload Pages section requires committee OR admin marker (__tpw_control_is_committee_or_admin__)
            // Delegates to the same members flags + filters used by TPW Control today.
            case 'tpw_control_menu_view':
            case 'tpw_control_menu_manage':
                return $tpw_members_is_admin_user( $user_id );

            case 'tpw_control_archive_view':
            case 'tpw_control_archive_upload':
            case 'tpw_control_archive_manage':
            case 'tpw_control_archive_settings_manage':
                $is_committee = $tpw_flag_from_members_table( $user_id, 'is_committee', 'tpw_control/is_committee_user' );
                return $tpw_members_is_admin_user( $user_id ) || $is_committee;
        }

        // Unknown ability: conservative default (Step 1).
        return false;
    }
}

/**
 * Get the TPW Core Settings URL for the Payment Methods tab.
 *
 * Used by other TPW plugins to link to the single source of truth for
 * payment method enable/disable and configuration.
 *
 * @since 1.0.0
 */
if ( ! function_exists( 'tpw_core_get_payment_methods_settings_url' ) ) {
    function tpw_core_get_payment_methods_settings_url(): string {
        return admin_url( 'options-general.php?page=tpw-core-settings&tab=payment-methods' );
    }
}

/**
 * Retrieve configured date format from FlexiEvent settings.
 * Falls back to d-m-Y.
 */
function tpw_core_get_date_format(): string {
    // Prefer dedicated option if present
    $opt = get_option( 'flexievent_date_format', '' );
    if ( is_string( $opt ) && $opt !== '' ) {
        return $opt;
    }
    // Fallback to nested settings array
    $settings = get_option( 'flexievent_settings', [] );
    if ( is_array( $settings ) && ! empty( $settings['date_format'] ) ) {
        return (string) $settings['date_format'];
    }
    return 'd-m-Y';
}

/**
 * Retrieve configured time format from FlexiEvent settings.
 * Falls back to H:i.
 */
function tpw_core_get_time_format(): string {
    // Prefer dedicated option if present
    $opt = get_option( 'flexievent_time_format', '' );
    if ( is_string( $opt ) && $opt !== '' ) {
        return $opt;
    }
    // Fallback to nested settings array
    $settings = get_option( 'flexievent_settings', [] );
    if ( is_array( $settings ) && ! empty( $settings['time_format'] ) ) {
        return (string) $settings['time_format'];
    }
    return 'H:i';
}

/**
 * Format a date value (date only) for display using site-configured format.
 * Accepts timestamp, MySQL date/datetime strings, or DateTimeInterface.
 */
function tpw_format_date( $value ): string {
    if ( empty( $value ) ) return '';
    if ( is_string( $value ) ) {
        $trim = trim( $value );
        if ( $trim === '0000-00-00' || $trim === '0000-00-00 00:00:00' ) return '';
    }
    if ( $value instanceof DateTimeInterface ) {
        $ts = $value->getTimestamp();
    } elseif ( is_numeric( $value ) ) {
        $ts = (int) $value;
    } else {
        $ts = strtotime( (string) $value );
    }
    if ( ! $ts ) return is_string( $value ) ? (string) $value : '';
    return date_i18n( tpw_core_get_date_format(), $ts );
}

/**
 * Format a time value (time only) for display using site-configured format.
 * Accepts timestamp, time/datetime string, or DateTimeInterface.
 */
function tpw_format_time( $value ): string {
    if ( empty( $value ) ) return '';
    if ( $value instanceof DateTimeInterface ) {
        $ts = $value->getTimestamp();
    } elseif ( is_numeric( $value ) ) {
        $ts = (int) $value;
    } else {
        $ts = strtotime( (string) $value );
    }
    if ( ! $ts ) return is_string( $value ) ? (string) $value : '';
    return date_i18n( tpw_core_get_time_format(), $ts );
}

/**
 * Format a datetime value for display using site-configured date and time formats.
 */
function tpw_format_datetime( $value ): string {
    if ( empty( $value ) ) return '';
    if ( is_string( $value ) ) {
        $trim = trim( $value );
        if ( $trim === '0000-00-00' || $trim === '0000-00-00 00:00:00' ) return '';
    }
    if ( $value instanceof DateTimeInterface ) {
        $ts = $value->getTimestamp();
    } elseif ( is_numeric( $value ) ) {
        $ts = (int) $value;
    } else {
        $ts = strtotime( (string) $value );
    }
    if ( ! $ts ) return is_string( $value ) ? (string) $value : '';
    $format = trim( tpw_core_get_date_format() . ' ' . tpw_core_get_time_format() );
    return date_i18n( $format, $ts );
}

/**
 * Convert a PHP date format string to a jQuery UI datepicker format string.
 * Covers common tokens used by TPW Core: d, j, m, n, M, F, y, Y.
 */
function tpw_core_php_date_to_jqueryui( string $php_format ): string {
    $map = [
        // Day
        'd' => 'dd', // 01-31
        'j' => 'd',  // 1-31
        // Month
        'm' => 'mm', // 01-12
        'n' => 'm',  // 1-12
        'M' => 'M',  // Jan-Dec
        'F' => 'MM', // January-December
        // Year
        'y' => 'y',  // 00-99
        'Y' => 'yy', // 1900-2099 (datepicker uses yy for 4-digit year)
    ];

    $out = '';
    $len = strlen( $php_format );
    for ( $i = 0; $i < $len; $i++ ) {
        $ch = $php_format[$i];
        // Escape next char when PHP format uses backslash
        if ( $ch === '\\' && ($i + 1) < $len ) {
            // In jQuery UI, literal text should be wrapped in single quotes
            $out .= "'" . $php_format[$i+1] . "'";
            $i++;
            continue;
        }
        $out .= $map[$ch] ?? $ch;
    }
    return $out;
}

/**
 * Return a human-friendly input hint for a given PHP date format.
 * Covers requested mappings; falls back to echoing the raw format.
 */
function tpw_core_human_date_hint( string $php_format ): string {
    $fmt = trim( $php_format );
    switch ( $fmt ) {
        case 'j F Y':
            // Example using 1 September 2025
            $example = date_i18n( $fmt, mktime(0,0,0,9,1,2025) );
            return 'Format: day month year (e.g. ' . $example . ')';

        case 'Y-m-d':
            return 'Format: yyyy-mm-dd';

        case 'd/m/Y':
            return 'Format: dd/mm/yyyy';

        case 'm/d/Y':
            return 'Format: mm/dd/yyyy';

        case 'D, j M Y':
            // Use 1 Sep 2020 to render Tue consistently
            $example = date_i18n( $fmt, mktime(0,0,0,9,1,2020) );
            return 'Format: ' . $example . ' (weekday, day month year)';

        default:
            return 'Format: ' . $fmt;
    }
}

/**
 * Placeholder text for date inputs that matches the instruction hint.
 */
function tpw_core_date_placeholder( string $php_format ): string {
    switch ( trim($php_format) ) {
        case 'j F Y':
            // Match the instruction style
            $example = date_i18n( 'j F Y', mktime(0,0,0,9,1,2025) ); // 1 September 2025
            return 'day month year (e.g. ' . $example . ')';
        case 'Y-m-d':
            return 'yyyy-mm-dd';
        case 'd/m/Y':
            return 'dd/mm/yyyy';
        case 'm/d/Y':
            return 'mm/dd/yyyy';
        case 'D, j M Y':
            $example = date_i18n( 'D, j M Y', mktime(0,0,0,9,1,2020) ); // Tue, 1 Sep 2020
            return $example . ' (weekday, day month year)';
        default:
            // Fallback: render an example using the configured format on a safe sample date
            return date_i18n( $php_format, mktime(0,0,0,9,1,2025) );
    }
}

/**
 * Normalise a free‑text value for menus/options.
 *
 * Behaviour:
 * - Trims leading/trailing whitespace
 * - Collapses runs of multiple spaces to a single space
 *
 * Notes:
 * - Newlines and tabs are preserved; only regular spaces are collapsed.
 * - Always returns a string; null inputs become '' (empty string).
 *
 * @since 1.1.1
 */
if ( ! function_exists( 'tpw_normalise_value' ) ) {
    function tpw_normalise_value( $value ): string {
        if ( is_null( $value ) ) {
            return '';
        }
        if ( ! is_string( $value ) ) {
            $value = (string) $value;
        }
        // Trim and collapse multiple spaces
        $value = trim( $value );
        $collapsed = preg_replace( '/ {2,}/', ' ', $value );
        if ( null === $collapsed ) {
            // preg_replace failure edge case; fall back to trimmed value
            return $value;
        }
        return $collapsed;
    }
}

/**
 * Determine if a group can see a field in the directory/modal context.
 *
 * Purpose:
 * - Central helper for directory and member details modal to check per-group field visibility.
 * - Looks up rules from the tpw_member_field_visibility table and caches results per group.
 *
 * Parameters:
 * - $group string One of: 'admin', 'committee', 'member', 'guest'. Controls which column in the matrix applies.
 * - $field string The member field key (e.g. 'email', 'telephone', 'address1').
 *
 * Returns:
 * - bool True when the field is visible to the given group; false otherwise.
 *
 * Notes:
 * - This does not govern the Member Profile page visibility. Profile visibility is handled separately via
 *   the tpw_member_viewable_fields option and related logic.
 * - The admin edit form intentionally does not consult this helper; admins see all enabled fields when editing.
 */
function tpw_can_group_view_field( string $group, string $field ): bool {
    global $wpdb;
    static $cache = [];

    $group_key = sanitize_key( $group );
    $field_key = sanitize_key( $field );
    if ( '' === $group_key || '' === $field_key ) {
        return false;
    }

    if ( ! isset( $cache[ $group_key ] ) ) {
        $table = $wpdb->prefix . 'tpw_member_field_visibility';
        // Fetch all visible fields for this group into a set for quick lookups
        $sql = $wpdb->prepare( "SELECT field_key FROM {$table} WHERE `group` = %s AND is_visible = 1", $group_key );
        $rows = $wpdb->get_col( $sql );
        if ( is_wp_error( $rows ) || ! is_array( $rows ) ) {
            $cache[ $group_key ] = [];
        } else {
            $keys = array_map( 'sanitize_key', array_filter( array_map( 'strval', $rows ) ) );
            // De-duplicate and make it a set for O(1) checks
            $cache[ $group_key ] = array_fill_keys( $keys, true );
        }
    }

    return ! empty( $cache[ $group_key ][ $field_key ] );
}

// --- TPW Core Module Registry (lightweight, internal) ---
// Global registry storage
if ( ! isset( $GLOBALS['tpw_module_registry'] ) || ! is_array( $GLOBALS['tpw_module_registry'] ) ) {
    $GLOBALS['tpw_module_registry'] = [];
}

if ( ! function_exists( 'tpw_register_module' ) ) {
    /**
     * Register a TPW module in the in-memory registry.
     * Safe no-op beyond storing metadata; enables diagnostics and orchestration.
     *
     * @param string $slug Unique slug (e.g. 'gallery').
     * @param array  $args Module meta: title, version, status, plugin, has_ui, capabilities, description.
     * @return array The stored module definition.
     */
    function tpw_register_module( string $slug, array $args = [] ): array {
        $key = sanitize_key( $slug );
        if ( '' === $key ) {
            return [];
        }

        $defaults = [
            'title'        => $key,
            'version'      => '',
            'status'       => 'active', // scaffold|active|disabled
            'plugin'       => 'tpw-core',
            'has_ui'       => false,
            'capabilities' => [],
            'description'  => '',
            'registered_at'=> time(),
        ];

        $def = array_merge( $defaults, $args );
        $GLOBALS['tpw_module_registry'][ $key ] = $def;

        /**
         * Fires after a TPW module is registered.
         *
         * @param string $key Module slug.
         * @param array  $def Module definition.
         */
        do_action( 'tpw_module_registered', $key, $def );
        return $def;
    }
}

if ( ! function_exists( 'tpw_get_registered_modules' ) ) {
    /**
     * Return all registered TPW modules.
     *
     * @return array<string,array>
     */
    function tpw_get_registered_modules(): array {
        $all = isset( $GLOBALS['tpw_module_registry'] ) && is_array( $GLOBALS['tpw_module_registry'] )
            ? $GLOBALS['tpw_module_registry']
            : [];
        return apply_filters( 'tpw_registered_modules', $all );
    }
}

if ( ! function_exists( 'tpw_is_module_registered' ) ) {
    /**
     * Check whether a module is registered.
     */
    function tpw_is_module_registered( string $slug ): bool {
        $all = tpw_get_registered_modules();
        $key = sanitize_key( $slug );
        return isset( $all[ $key ] );
    }
}

/**
 * Determine if the Members module should be enabled for front-end features.
 *
 * Returns true when TPW_MEMBERS_ACTIVE is defined and truthy. Filterable via
 * 'tpw_members/module_enabled' to support future toggles.
 */
function tpw_members_module_enabled(): bool {
    $enabled = defined('TPW_MEMBERS_ACTIVE') && TPW_MEMBERS_ACTIVE;
    /**
     * Filter: allow products to override Members module enabled flag.
     */
    return (bool) apply_filters( 'tpw_members/module_enabled', $enabled );
}

/**
 * Build a default payments page config for front-end bootstrapping.
 *
 * Includes currency, Square app/location IDs, sandbox flag, and active methods list.
 *
 * @since 1.1.0
 * @return array
 */
if ( ! function_exists( 'tpw_core_get_payments_page_config' ) ) {
    function tpw_core_get_payments_page_config(): array {
        $cfg = [
            'currency' => [
                'code'   => function_exists('tpw_core_get_currency_code') ? tpw_core_get_currency_code() : 'GBP',
                'symbol' => function_exists('tpw_core_get_currency_symbol') ? tpw_core_get_currency_symbol() : '£',
            ],
            'square' => [
                'appId'      => get_option('tpw_square_app_id'),
                'locationId' => get_option('tpw_square_location_id'),
                'sandbox'    => ( get_option('tpw_square_sandbox_mode') === '1' ),
            ],
            'activeMethods' => class_exists('TPW_Payments_Manager') ? TPW_Payments_Manager::get_active_methods() : [],
        ];

        /**
         * Filter the default front-end payments page config before it is returned.
         *
         * @param array $cfg
         */
        return apply_filters( 'tpw_core/payments_page_config', $cfg );
    }
}

/**
 * Enqueue the Square Web Payments SDK (sandbox or production) and the Core payments bootstrap JS.
 * Optionally localize a config array to `tpwPaymentsConfig` if provided; otherwise builds a default.
 *
 * Usage: call from your shortcode/template controller for pages that render a payments form.
 *
 * @since 1.1.0
 * @param array|null $config Optional config to localize. If null, a default will be used.
 */
if ( ! function_exists( 'tpw_core_enqueue_payments_assets' ) ) {
    function tpw_core_enqueue_payments_assets( ?array $config = null, array $context = [] ): void {
        // Decide SDK URL by sandbox flag
        $is_sandbox = ( get_option('tpw_square_sandbox_mode') === '1' );
        $sdk_url = $is_sandbox
            ? 'https://sandbox.web.squarecdn.com/v1/square.js'
            : 'https://web.squarecdn.com/v1/square.js';

        // Enqueue Square Web Payments SDK once
        if ( ! wp_script_is( 'square-web-payments', 'enqueued' ) && ! wp_script_is( 'square-web-payments', 'registered' ) ) {
            wp_register_script( 'square-web-payments', $sdk_url, [], null, true );
        }
        wp_enqueue_script( 'square-web-payments' );

        // Ensure the Core bootstrap is registered; admin-functions.php registers it, but provide a fallback here
        if ( ! wp_script_is( 'tpw-core-payments', 'registered' ) ) {
            if ( defined('TPW_CORE_PATH') && defined('TPW_CORE_URL') ) {
                $file = TPW_CORE_PATH . 'assets/js/tpw-payments.js';
                if ( file_exists( $file ) ) {
                    $url = TPW_CORE_URL . 'assets/js/tpw-payments.js';
                    wp_register_script( 'tpw-core-payments', $url, [], filemtime($file), true );
                }
            }
        }
        if ( wp_script_is( 'tpw-core-payments', 'registered' ) ) {
            // Localize config (merge default if none supplied)
            $cfg = is_array( $config ) ? $config : tpw_core_get_payments_page_config();
            // Provide a minimal context with page/type defaults
            $ctx = array_merge(
                [
                    'page' => is_admin() ? 'admin' : 'front',
                    'type' => 'generic',
                ],
                $context
            );
            // Allow callers (e.g., RSVP) to inject SCA details via filter with context
            $cfg = apply_filters( 'tpw_core/payments_page_config_localized', $cfg, $ctx );
            wp_localize_script( 'tpw-core-payments', 'tpwPaymentsConfig', $cfg );
            wp_enqueue_script( 'tpw-core-payments' );
        }
    }
}