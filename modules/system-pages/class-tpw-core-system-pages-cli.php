<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( defined('WP_CLI') && WP_CLI ) {
    /**
     * Manage TPW System Pages.
     */
    class TPW_System_Pages_CLI {
        /**
         * Repair all registered TPW system pages.
         *
         * ## EXAMPLES
         *     wp tpw system-pages repair
         */
        public function repair( $args, $assoc_args ) {
            global $wpdb;
            if ( ! class_exists('TPW_Core_System_Pages') ) {
                WP_CLI::error( 'System Pages manager not loaded.' );
                return;
            }
            TPW_Core_System_Pages::ensure_tables();
            $table = $wpdb->prefix . 'tpw_system_pages';
            $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY slug ASC" );
            if ( empty( $rows ) ) {
                WP_CLI::success( 'No system pages registered.' );
                return;
            }
            $debug = defined('TPW_DEBUG_SYSTEM_PAGES') && TPW_DEBUG_SYSTEM_PAGES;
            foreach ( $rows as $row ) {
                $slug = $row->slug;
                $shortcode = $row->shortcode;
                $page_id_before = (int) $row->wp_page_id;
                $tag = method_exists('TPW_Core_System_Pages','parse_shortcode_tag') ? TPW_Core_System_Pages::parse_shortcode_tag( $shortcode ) : '';

                $page_id_after = TPW_Core_System_Pages::get_page_id( $slug ); // triggers self-heal
                $title = $page_id_after ? get_the_title( $page_id_after ) : '';
                $has_sc = false;
                if ( $page_id_after ) {
                    $content = (string) get_post_field( 'post_content', $page_id_after );
                    $has_sc = $tag ? TPW_Core_System_Pages::content_has_shortcode_tag( $content, $tag ) : true;
                }
                if ( $debug ) {
                    WP_CLI::log( sprintf( 'slug=%s shortcode=%s linked_id(before)=%d linked_id(after)=%d title=%s shortcode_found=%s',
                        $slug, $shortcode, $page_id_before, $page_id_after, $title ?: '-', $has_sc ? 'yes' : 'no'
                    ) );
                }
            }
            WP_CLI::success( 'System pages repair pass completed.' );
        }
    }

    if ( class_exists( 'WP_CLI' ) ) {
        WP_CLI::add_command( 'tpw system-pages', 'TPW_System_Pages_CLI' );
    }
}
