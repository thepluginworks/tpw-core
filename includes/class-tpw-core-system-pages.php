<?php
/**
 * TPW Core – System Pages registry and resolver
 *
 * Responsibilities:
 * - Define default keys and slugs for system pages
 * - Allow modules to register additional pages
 * - Resolve URLs and page IDs with optional overrides from wp_options
 * - Provide safe fallbacks to home_url() when unresolved
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
         * Shape: [ slug => [ 'wp_page_id' => int ] ]
         * @var array<string,array>
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
        public static function get_id( $key ) {
            $slug = sanitize_key( (string) $key );
            if ( $slug === '' ) return 0;

            self::boot_defaults();
            self::load_overrides();

            // 1) Prefer explicit override mapping
            $ov = isset( self::$overrides[ $slug ] ) && is_array( self::$overrides[ $slug ] ) ? self::$overrides[ $slug ] : [];
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
         * Register a system page programmatically.
         * Safe to call multiple times; later calls overwrite fields, not IDs.
         * @param string $slug
         * @param array  $args { title, shortcode, plugin, required }
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
        public static function ensure_page( $slug ) {
            $s = sanitize_key( (string) $slug );
            if ( $s === '' ) return 0;
            self::boot_defaults();
            self::load_overrides();

            // Already linked?
            $id = self::get_id( $s );
            if ( $id > 0 ) return $id;

            // Prepare payload from registry
            $def = self::$registry[ $s ] ?? [];
            $title = isset( $def['title'] ) ? (string) $def['title'] : ucwords( str_replace( '-', ' ', $s ) );
            $shortcode = isset( $def['shortcode'] ) ? (string) $def['shortcode'] : '';

            // Try to find a draft/privately published page by path
            $maybe = get_page_by_path( $s );
            if ( $maybe && $maybe->post_type === 'page' ) {
                // Promote to publish if needed
                if ( $maybe->post_status !== 'publish' ) {
                    wp_update_post( [ 'ID' => (int) $maybe->ID, 'post_status' => 'publish' ] );
                }
                $id = (int) $maybe->ID;
            } else {
                // Create new page
                $id = wp_insert_post( [
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => $title,
                    'post_name'    => $s,
                    'post_content' => $shortcode,
                ], true );
                if ( is_wp_error( $id ) ) {
                    return 0;
                }
            }

            // Persist mapping in option
            self::$overrides[ $s ] = [ 'wp_page_id' => (int) $id ];
            update_option( 'tpw_core_system_pages', self::$overrides );
            return (int) $id;
        }

        /**
         * Return a list of all known system pages with basic details.
         * Each item: (object){ slug, title, wp_page_id, plugin, required }
         * @return array<int,object>
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
                    'wp_page_id' => (int) $pid,
                    'plugin'     => (string) ( $def['plugin'] ?? '' ),
                    'required'   => (int) ( $def['required'] ?? 0 ),
                ];
            }
            return $out;
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
                    if ( $tag && self::content_has_shortcode_tag( $content, $tag ) ) {
                        return (int) $pid;
                    }
                    if ( $content !== '' && false !== strpos( $content, $sc ) ) {
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

?>
