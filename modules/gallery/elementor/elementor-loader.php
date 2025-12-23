<?php
/**
 * TPW Core – Gallery Elementor Integration (optional)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the TPW Gallery Elementor widget.
 *
 * This file is only loaded after Elementor is confirmed loaded.
 */
function tpw_gallery_elementor_register_widget( $widgets_manager ) {
    if ( ! is_object( $widgets_manager ) ) {
        return;
    }

    require_once __DIR__ . '/class-tpw-elementor-widget-gallery.php';

    if ( class_exists( 'TPW_Elementor_Widget_Gallery' ) ) {
        $widgets_manager->register( new TPW_Elementor_Widget_Gallery() );
    }
}

/**
 * Enqueue Elementor editor-only assets.
 */
function tpw_gallery_elementor_editor_assets() {
    $handle = 'tpw-gallery-elementor-editor';
    $src    = trailingslashit( TPW_CORE_URL ) . 'modules/gallery/elementor/editor.js';
    wp_enqueue_script( $handle, $src, [ 'jquery' ], TPW_CORE_VERSION, true );

    wp_localize_script( $handle, 'tpwGalleryElementor', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'tpw_gallery_elementor' ),
        'action'  => 'tpw_gallery_elementor_search',
        'i18n'    => [
            'searchPlaceholder' => __( 'Type to search galleries…', 'tpw-core' ),
        ],
    ] );
}

/**
 * Register AJAX handlers.
 */
function tpw_gallery_elementor_register_ajax() {
    require_once __DIR__ . '/ajax.php';
}

/**
 * Init integration.
 */
function tpw_gallery_elementor_init() {
    // Elementor 3.x recommended hook for widget registration.
    add_action( 'elementor/widgets/register', 'tpw_gallery_elementor_register_widget' );

    // Editor-only assets (Select2 hookup for AJAX search).
    add_action( 'elementor/editor/after_enqueue_scripts', 'tpw_gallery_elementor_editor_assets' );

    // AJAX endpoints for editor control.
    tpw_gallery_elementor_register_ajax();
}
