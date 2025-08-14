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
                'parent_slug' => 'flexievent',
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
             *   'parent_slug'  => 'flexievent',
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