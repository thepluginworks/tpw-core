<?php
/**
 * TPW Core – Gallery WP-CLI Commands
 * @since 0.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'WP_CLI' ) ) {
    /**
     * List registered gallery sources as JSON.
     */
    WP_CLI::add_command( 'tpw gallery list-sources', function( $args, $assoc_args ){
        $sources = function_exists('tpw_gallery_get_sources') ? tpw_gallery_get_sources() : [];
        WP_CLI::line( wp_json_encode( $sources, JSON_PRETTY_PRINT ) );
    } );
}
