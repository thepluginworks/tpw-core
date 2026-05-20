<?php
/**
 * TPW Core – System Pages registry and resolver.
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Protective guard: if already loaded elsewhere, bail out to prevent redeclaration
if ( class_exists( 'TPW_Core_System_Pages' ) ) { return; }

if ( ! class_exists( 'TPW_Core_System_Pages' ) ) {
    class TPW_Core_System_Pages {
        /**
         * In-memory registry for system pages (keyed by slug).
         * Shape: [ slug => [ 'title' => string, 'shortcode' => string, 'plugin' => string, 'required' => int ] ]
         * @var array<string,array>
         */
        protected static $registry = [];

        /**
         * Cached overrides read from the tpw_core_system_pages option.
         * Shape: [ slug => [ 'wp_page_id' => int, 'is_unlinked' => int ] ]
         *
         * @var array<string,array<string,mixed>>|null
         */
        protected static $overrides = null;

        /**
         * Initialize default pages (idempotent).
         */
        protected static function boot_defaults() {
            if ( ! empty( self::$registry ) ) return;
            // Known core system pages (extend over time)
            self::$registry = [
                'member-login' => [
                    'title'     => __( 'Member Login', 'tpw-core' ),
                    'shortcode' => '[tpw_member_login]',
                    'plugin'    => 'tpw-core',
                    'required'  => 0,
                ],
            ];
            /**
             * Allow other modules to amend defaults at load time.
             * Expected to return the same shape as self::$registry
             */
            self::$registry = apply_filters( 'tpw/system_pages/defaults', self::$registry );
        }

        /**
         * Lazy load overrides from options table.
         */
        protected static function load_overrides() {
            if ( is_array( self::$overrides ) ) return;
            $opt = get_option( 'tpw_core_system_pages', [] );
            self::$overrides = is_array( $opt ) ? $opt : [];
        }

        /**
         * Public: get permalink for a system page key.
         * - Resolves to linked WP Page if mapped/found
         * - Falls back to conventional path /{slug}/
         * - Applies filter 'tpw_system_page_url'
         */
    /**
     * Get permalink for a system page key.
     *
     * @since 1.0.0
     */
    public static function get( $key ) {
            $slug = sanitize_key( (string) $key );
            if ( $slug === '' ) return home_url();

            self::boot_defaults();
            self::load_overrides();

            $url = '';
            $page_id = self::get_id( $slug );
            if ( $page_id > 0 ) {
                $permalink = get_permalink( $page_id );
                if ( is_string( $permalink ) && $permalink !== '' ) {
                    $url = $permalink;
                }
            }

            if ( $url === '' ) {
                // Conventional path fallback e.g. /member-login/
                $url = site_url( '/' . $slug . '/' );
            }

            // Final guard: prefer a valid URL, else home
            if ( ! is_string( $url ) || $url === '' ) {
                $url = home_url();
            }

            /**
             * Filter the resolved system page URL.
             *
             * @param string $url Resolved URL (may be a permalink or fallback)
             * @param string $key System page key
             */
            $url = apply_filters( 'tpw_system_page_url', $url, $slug );
            return $url ?: home_url();
        }

        /**
         * Public: get a WP Page ID for a system page key, or 0 if not found.
         */
    /**
     * Get a WP Page ID for a system page key, or 0 if not found.
     *
     * @since 1.0.0
     */
    public static function get_id( $key ) {
            $slug = sanitize_key( (string) $key );
            if ( $slug === '' ) return 0;

            self::boot_defaults();
            self::load_overrides();

            // 1) Prefer explicit override mapping
            $ov = self::get_override_entry( $slug );
            if ( ! empty( $ov['is_unlinked'] ) && empty( $ov['wp_page_id'] ) ) {
                return 0;
            }

            $mapped = isset( $ov['wp_page_id'] ) ? (int) $ov['wp_page_id'] : 0;
            if ( $mapped > 0 ) {
                $p = get_post( $mapped );
                if ( $p && $p->post_type === 'page' && $p->post_status === 'publish' ) {
                    return (int) $p->ID;
                }
            }

            // 2) Discover by path (get_page_by_path prefers hierarchical slugs)
            $page = get_page_by_path( $slug );
            if ( $page && $page->post_type === 'page' && $page->post_status === 'publish' ) {
                return (int) $page->ID;
            }

            // 3) Optionally discover by shortcode content if default has one
            $def = self::$registry[ $slug ] ?? [];
            if ( ! empty( $def['shortcode'] ) ) {
                $found = self::find_page_with_shortcode( (string) $def['shortcode'] );
                if ( $found > 0 ) return $found;
            }

            return 0;
        }

        // Compatibility aliases (existing code references)
        public static function get_permalink( $key ) { return self::get( $key ); }
        public static function get_page_id( $key ) { return self::get_id( $key ); }

        /**
         * Persist or remove a stored page ID override for a slug.
         *
         * @param string $slug        System page slug.
         * @param int    $page_id     Linked page ID, or 0 to clear the mapping.
         * @param bool   $is_unlinked Whether the mapping was intentionally cleared.
         * @return bool True when the option write completed.
         */
        protected static function persist_override( $slug, $page_id, $is_unlinked = false ) {
            $s = sanitize_key( (string) $slug );
            if ( '' === $s ) {
                return false;
            }

            self::load_overrides();
            $page_id     = absint( $page_id );
            $is_unlinked = (bool) $is_unlinked;

            if ( 0 < $page_id ) {
                self::$overrides[ $s ] = array( 'wp_page_id' => $page_id );
            } elseif ( $is_unlinked ) {
                self::$overrides[ $s ] = array(
                    'wp_page_id'  => 0,
                    'is_unlinked' => 1,
                );
            } else {
                unset( self::$overrides[ $s ] );
            }

            update_option( 'tpw_core_system_pages', self::$overrides );
            return true;
        }

        /**
         * Read a single stored override entry.
         *
         * @param string $slug System page slug.
         * @return array<string,mixed>
         */
        protected static function get_override_entry( $slug ) {
            $s = sanitize_key( (string) $slug );
            if ( '' === $s ) {
                return array();
            }

            self::load_overrides();

            return isset( self::$overrides[ $s ] ) && is_array( self::$overrides[ $s ] ) ? self::$overrides[ $s ] : array();
        }

        /**
         * Determine whether a slug has been explicitly unlinked.
         *
         * @param string $slug System page slug.
         * @return bool
         */
        public static function is_explicitly_unlinked( $slug ) {
            $override = self::get_override_entry( $slug );

            return ! empty( $override['is_unlinked'] ) && empty( $override['wp_page_id'] );
        }

        /**
         * Internal ensure helper that preserves WP_Error details for callers that need them.
         *
         * @param string $slug System page slug.
         * @return int|WP_Error Page ID on success, or WP_Error on failure.
         */
        protected static function ensure_page_result( $slug ) {
            $s = sanitize_key( (string) $slug );
            if ( '' === $s ) {
                return new WP_Error( 'tpw_system_page_missing_slug', __( 'Missing system page slug.', 'tpw-core' ) );
            }

            self::boot_defaults();
            self::load_overrides();

            $id = self::get_id( $s );
            if ( 0 < $id ) {
                return (int) $id;
            }

            $def = array();
            if ( isset( self::$registry[ $s ] ) && is_array( self::$registry[ $s ] ) ) {
                $def = self::$registry[ $s ];
            }

            $title = ucwords( str_replace( '-', ' ', $s ) );
            if ( isset( $def['title'] ) ) {
                $title = (string) $def['title'];
            }

            $shortcode = '';
            if ( isset( $def['shortcode'] ) ) {
                $shortcode = (string) $def['shortcode'];
            }

            $maybe = get_page_by_path( $s );
            if ( $maybe && 'page' === $maybe->post_type ) {
                if ( 'trash' === $maybe->post_status ) {
                    return new WP_Error(
                        'tpw_system_page_in_trash',
                        sprintf(
                            /* translators: %s: system page slug */
                            __( 'The page for slug "%s" is currently in Trash. Empty Trash or restore that page before recreating it.', 'tpw-core' ),
                            $s
                        )
                    );
                }

                if ( 'publish' !== $maybe->post_status ) {
                    $updated = wp_update_post(
                        array(
                            'ID'          => (int) $maybe->ID,
                            'post_status' => 'publish',
                        ),
                        true
                    );

                    if ( is_wp_error( $updated ) ) {
                        return $updated;
                    }
                }

                $id = (int) $maybe->ID;
            } else {
                $id = wp_insert_post(
                    array(
                        'post_type'    => 'page',
                        'post_status'  => 'publish',
                        'post_title'   => $title,
                        'post_name'    => $s,
                        'post_content' => $shortcode,
                    ),
                    true
                );

                if ( is_wp_error( $id ) ) {
                    return $id;
                }
            }

            self::persist_override( $s, (int) $id );
            return (int) $id;
        }

        /**
         * Register a system page programmatically.
         * Safe to call multiple times; later calls overwrite fields, not IDs.
         * @param string $slug
         * @param array  $args { title, shortcode, plugin, required }
         */
    /**
     * @since 1.0.0
     */
    public static function register_page( $slug, $args = [] ) {
            $s = sanitize_key( (string) $slug );
            if ( $s === '' ) return;
            self::boot_defaults();
            $current = isset( self::$registry[ $s ] ) && is_array( self::$registry[ $s ] ) ? self::$registry[ $s ] : [];
            $title = isset( $args['title'] ) ? (string) $args['title'] : ( $current['title'] ?? ucwords( str_replace( '-', ' ', $s ) ) );
            $shortcode = isset( $args['shortcode'] ) ? (string) $args['shortcode'] : ( $current['shortcode'] ?? '' );
            $plugin = isset( $args['plugin'] ) ? (string) $args['plugin'] : ( $current['plugin'] ?? '' );
            $required = isset( $args['required'] ) ? (int) $args['required'] : (int) ( $current['required'] ?? 0 );
            self::$registry[ $s ] = [ 'title' => $title, 'shortcode' => $shortcode, 'plugin' => $plugin, 'required' => $required ];
        }

        /**
         * Ensure the WP page exists for the given slug.
         * - Creates a published page with sensible defaults if missing.
         * - Stores mapping in tpw_core_system_pages option.
         * Returns the page ID or 0.
         */
    /**
     * @since 1.0.0
     */
    public static function ensure_page( $slug ) {
        if ( self::is_explicitly_unlinked( $slug ) ) {
            return 0;
        }

            $result = self::ensure_page_result( $slug );
            if ( is_wp_error( $result ) ) {
                return 0;
            }

            return (int) $result;
        }

        /**
         * Clear any stored page mapping for a slug.
         *
         * @param string $slug System page slug.
         * @return bool True when the mapping was cleared or did not exist.
         */
        public static function unlink( $slug ) {
            $s = sanitize_key( (string) $slug );
            if ( '' === $s ) {
                return false;
            }

            return self::persist_override( $s, 0, true );
        }

        /**
         * Recreate a system page using the canonical option-backed implementation.
         *
         * @param string $slug System page slug.
         * @return int|WP_Error Page ID on success, or WP_Error on failure.
         */
        public static function recreate_page( $slug ) {
            $s = sanitize_key( (string) $slug );
            if ( '' === $s ) {
                return new WP_Error( 'tpw_system_page_missing_slug', __( 'Missing system page slug.', 'tpw-core' ) );
            }

            self::unlink( $s );

            return self::ensure_page_result( $s );
        }

        /**
         * Return a list of all known system pages with basic details.
         * Each item: (object){ slug, title, wp_page_id, plugin, required }
         * @return array<int,object>
         */
    /**
     * @since 1.0.0
     */
    public static function get_all() {
            self::boot_defaults();
            self::load_overrides();
            $out = [];
            foreach ( self::$registry as $slug => $def ) {
                $pid = self::get_id( $slug );
                $out[] = (object) [
                    'slug'       => (string) $slug,
                    'title'      => (string) ( $def['title'] ?? ucwords( str_replace( '-', ' ', $slug ) ) ),
                    'shortcode'  => (string) ( $def['shortcode'] ?? '' ),
                    'wp_page_id' => (int) $pid,
                    'is_unlinked'=> self::is_explicitly_unlinked( $slug ),
                    'plugin'     => (string) ( $def['plugin'] ?? '' ),
                    'required'   => (int) ( $def['required'] ?? 0 ),
                ];
            }
            return $out;
        }

        /**
         * Determine whether the current user may manage System Pages actions.
         *
         * Preserves wp-admin administrator access while allowing the
         * compatibility-era FlexiClub front-end admin gate for FE workspace actions.
         *
         * @return bool
         */
        public static function current_user_can_manage() {
            if ( current_user_can( 'manage_options' ) ) {
                return true;
            }

            if ( function_exists( 'tpw_core_user_can' ) ) {
                return tpw_core_user_can( 'tpw_members_manage' );
            }

            if ( class_exists( 'TPW_Member_Access', false ) && method_exists( 'TPW_Member_Access', 'can_manage_members_current' ) ) {
                return TPW_Member_Access::can_manage_members_current();
            }

            return false;
        }

        /**
         * Read a System Pages notice from the current request.
         *
         * @return array{tone:string,message:string}
         */
        public static function get_request_notice() {
            $tone    = '';
            $message = '';

            if ( isset( $_GET['tpw_system_pages_notice'] ) ) {
                $tone = sanitize_key( wp_unslash( $_GET['tpw_system_pages_notice'] ) );
            }

            if ( isset( $_GET['tpw_system_pages_message'] ) ) {
                $message = sanitize_text_field( wp_unslash( $_GET['tpw_system_pages_message'] ) );
            }

            if ( '' === $message || ! in_array( $tone, array( 'success', 'error', 'warning', 'info' ), true ) ) {
                return array(
                    'tone'    => '',
                    'message' => '',
                );
            }

            return array(
                'tone'    => $tone,
                'message' => $message,
            );
        }

        // --- Helpers for discovery ---------------------------------------------------------

        /**
         * Find a page ID that contains the given shortcode string, e.g. "[tpw_member_login]".
         * Cheap scan limited to published pages.
         * @return int
         */
        protected static function find_page_with_shortcode( $shortcode ) {
            $sc = is_string( $shortcode ) ? trim( $shortcode ) : '';
            if ( $sc === '' ) return 0;

            // Extract tag for has_shortcode check if possible
            $tag = self::parse_shortcode_tag( $sc );
			$requires_exact_match = false !== strpos( trim( $sc, '[]' ), ' ' );
            $q = new \WP_Query( [
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'fields'         => 'ids',
                's'              => '[' . $tag, // heuristic to narrow search
            ] );
            if ( $q->have_posts() ) {
                foreach ( $q->posts as $pid ) {
                    $content = (string) get_post_field( 'post_content', (int) $pid );
					if ( $content !== '' && false !== strpos( $content, $sc ) ) {
						return (int) $pid;
					}

					if ( $requires_exact_match ) {
						continue;
					}

                    if ( $tag && self::content_has_shortcode_tag( $content, $tag ) ) {
                        return (int) $pid;
                    }
                }
            }
            return 0;
        }

        /**
         * Extract a shortcode tag from a shortcode string like "[tag attr=\"\"]".
         * @return string
         */
    /**
     * @since 1.0.0
     */
    public static function parse_shortcode_tag( $shortcode ) {
            $s = is_string( $shortcode ) ? trim( $shortcode ) : '';
            if ( $s === '' ) return '';
            if ( $s[0] !== '[' ) return '';
            // Strip [ and ] then split by space
            $inner = trim( preg_replace( '/^\[|\]$/', '', $s ) );
            $parts = preg_split( '/\s+/', $inner );
            $tag = is_array( $parts ) && ! empty( $parts ) ? (string) $parts[0] : '';
            return sanitize_key( $tag );
        }

        /**
         * Check content for a given shortcode tag using core has_shortcode.
         */
    /**
     * @since 1.0.0
     */
    public static function content_has_shortcode_tag( $content, $tag ) {
            $c = (string) $content; $t = sanitize_key( (string) $tag );
            if ( $c === '' || $t === '' ) return false;
            if ( function_exists( 'has_shortcode' ) ) {
                return has_shortcode( $c, $t );
            }
            // Fallback naive search
            return false !== strpos( $c, '[' . $t );
        }

        // --- No-op placeholders for future DB-backed implementation -----------------------

        /**
         * Placeholder to keep CLI happy; no tables required for this scaffold.
         */
        public static function ensure_tables() { /* no-op in scaffold */ }
    }
}

if ( ! function_exists( 'tpw_core_system_pages_ajax_error' ) ) {
    /**
     * Send a structured JSON error for System Pages AJAX requests.
     *
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     * @return void
     */
    function tpw_core_system_pages_ajax_error( $message, $status = 500 ) {
        wp_send_json_error(
            array(
                'message' => (string) $message,
            ),
            (int) $status
        );
    }
}

if ( ! has_action( 'wp_ajax_tpw_system_page_unlink' ) ) {
    add_action(
        'wp_ajax_tpw_system_page_unlink',
        function() {
            if ( ! TPW_Core_System_Pages::current_user_can_manage() ) {
                tpw_core_system_pages_ajax_error( __( 'Permission denied', 'tpw-core' ), 403 );
            }

            $nonce = '';
            if ( isset( $_POST['nonce'] ) ) {
                $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
            }

            if ( ! wp_verify_nonce( $nonce, 'tpw_system_pages_ajax' ) ) {
                tpw_core_system_pages_ajax_error( __( 'Bad nonce', 'tpw-core' ), 400 );
            }

            $slug = '';
            if ( isset( $_POST['slug'] ) ) {
                $slug = sanitize_key( wp_unslash( $_POST['slug'] ) );
            }

            if ( '' === $slug ) {
                tpw_core_system_pages_ajax_error( __( 'Missing slug', 'tpw-core' ), 400 );
            }

            if ( false === TPW_Core_System_Pages::unlink( $slug ) ) {
                tpw_core_system_pages_ajax_error( __( 'Failed to unlink page mapping.', 'tpw-core' ) );
            }

            wp_send_json_success(
                array(
                    'message' => __( 'System page link cleared. The registered slug remains available to repair or recreate.', 'tpw-core' ),
                    'reload'  => true,
                )
            );
        }
    );
}

if ( ! has_action( 'wp_ajax_tpw_system_page_recreate' ) ) {
    add_action(
        'wp_ajax_tpw_system_page_recreate',
        function() {
            if ( ! TPW_Core_System_Pages::current_user_can_manage() ) {
                tpw_core_system_pages_ajax_error( __( 'Permission denied', 'tpw-core' ), 403 );
            }

            $nonce = '';
            if ( isset( $_POST['nonce'] ) ) {
                $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
            }

            if ( ! wp_verify_nonce( $nonce, 'tpw_system_pages_ajax' ) ) {
                tpw_core_system_pages_ajax_error( __( 'Bad nonce', 'tpw-core' ), 400 );
            }

            $slug = '';
            if ( isset( $_POST['slug'] ) ) {
                $slug = sanitize_key( wp_unslash( $_POST['slug'] ) );
            }

            if ( '' === $slug ) {
                tpw_core_system_pages_ajax_error( __( 'Missing slug', 'tpw-core' ), 400 );
            }

            $result = TPW_Core_System_Pages::recreate_page( $slug );
            if ( is_wp_error( $result ) ) {
                tpw_core_system_pages_ajax_error( $result->get_error_message() );
            }

            wp_send_json_success(
                array(
                    'message' => sprintf(
                        /* translators: %s: system page slug */
                        __( 'System page "%s" is now linked and ready.', 'tpw-core' ),
                        $slug
                    ),
                    'pageId'  => (int) $result,
                    'reload'  => true,
                )
            );
        }
    );
}

?>
