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