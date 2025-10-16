<?php
/**
 * TPW Core – Gallery Upload API (Phase 3)
 *
 * Purpose:
 * - Provide a hybrid upload flow (filesystem + Media Library) for gallery images.
 * - Callable only by other modules; no UI or AJAX in this file.
 *
 * Next phases:
 * - Phase 4: Admin UI forms (no changes here, just callers);
 * - Phase 5: Shortcodes for front-end display.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Upload an image for a gallery using hybrid storage with Media Library.
 *
 * Contract:
 * - Input: $gallery_id (int), $file (array like $_FILES['field'])
 * - Output: array{id:int, url:string, caption:string, sort_order:int, attachment_id:int}
 * - Error: WP_Error on any failure (never fatal)
 */
function tpw_gallery_upload_image( int $gallery_id, array $file ) {
    if ( $gallery_id <= 0 || empty( $file ) ) {
        return new WP_Error( 'tpw_gallery_upload_invalid', 'Invalid gallery or file.' );
    }

    global $wpdb;

    // Resolve gallery slug for path
    $tbl_galleries = $wpdb->prefix . 'tpw_galleries';
    $slug = (string) $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$tbl_galleries} WHERE gallery_id = %d", $gallery_id ) );
    if ( '' === $slug ) {
        return new WP_Error( 'tpw_gallery_not_found', 'Gallery not found.' );
    }

    // Ensure uploads base and gallery folder; filter upload_dir so wp_handle_upload targets the gallery path
    $uploads = wp_upload_dir();
    if ( ! empty( $uploads['error'] ) ) {
        return new WP_Error( 'tpw_upload_dir_error', $uploads['error'] );
    }
    $subdir    = '/tpw-galleries/' . sanitize_title( $slug );
    $base_dir  = trailingslashit( $uploads['basedir'] ) . ltrim( $subdir, '/' ) . '/';
    if ( ! wp_mkdir_p( $base_dir ) ) {
        return new WP_Error( 'tpw_mkdir_failed', 'Failed to create gallery folder.' );
    }

    // Use WordPress upload handling for safety; temporarily force upload_dir to the gallery folder
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $overrides = [ 'test_form' => false, 'mimes' => null ];

    $filter = function( $dirs ) use ( $subdir ) {
        $dirs['subdir'] = $subdir;
        $dirs['path']   = trailingslashit( $dirs['basedir'] ) . ltrim( $dirs['subdir'], '/' );
        $dirs['url']    = trailingslashit( $dirs['baseurl'] ) . ltrim( $dirs['subdir'], '/' );
        return $dirs;
    };
    add_filter( 'upload_dir', $filter, 99 );
    $uploaded = wp_handle_upload( $file, $overrides );
    remove_filter( 'upload_dir', $filter, 99 );
    if ( ! is_array( $uploaded ) || ! empty( $uploaded['error'] ) ) {
        $msg = is_array( $uploaded ) && ! empty( $uploaded['error'] ) ? $uploaded['error'] : 'Upload failed.';
        return new WP_Error( 'tpw_handle_upload_failed', $msg );
    }

    $file_path = $uploaded['file'];
    $url       = $uploaded['url'];
    $type      = $uploaded['type'] ?? '';

    // Register attachment in Media Library
    $filetype = wp_check_filetype( basename( $file_path ), null );
    $attachment = [
        'guid'           => $url,
        'post_mime_type' => $filetype['type'] ?: $type,
        'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_path ) ),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];

    $attach_id = wp_insert_attachment( $attachment, $file_path );
    if ( is_wp_error( $attach_id ) || ! $attach_id ) {
        return new WP_Error( 'tpw_insert_attachment_failed', 'Failed to add attachment.' );
    }

    // Generate metadata (thumbnails, etc.)
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
    if ( is_array( $attach_data ) ) {
        wp_update_attachment_metadata( $attach_id, $attach_data );
    }

    // Link attachment to gallery
    add_post_meta( $attach_id, '_tpw_gallery_id', (int) $gallery_id, true );

    // Insert DB row into tpw_gallery_images with next sort order
    $tbl_images = $wpdb->prefix . 'tpw_gallery_images';
    $next_sort  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$tbl_images} WHERE gallery_id = %d", $gallery_id ) );
    $ok = $wpdb->insert( $tbl_images, [
        'gallery_id'    => (int) $gallery_id,
        'attachment_id' => (int) $attach_id,
        'caption'       => '',
        'sort_order'    => max( 0, $next_sort ),
    ], [ '%d','%d','%s','%d' ] );

    if ( false === $ok ) {
        // Roll back best-effort: keep attachment but signal error
        return new WP_Error( 'tpw_insert_image_failed', 'Failed to record gallery image.' );
    }

    $image_id = (int) $wpdb->insert_id;

    // Build URLs for immediate UI rendering
    $src_full  = wp_get_attachment_image_src( $attach_id, 'full' );
    $src_thumb = wp_get_attachment_image_src( $attach_id, 'thumbnail' );

    return [
        'image_id'      => $image_id,
        'id'            => $image_id, // alias for older callers
        'attachment_id' => (int) $attach_id,
        'url'           => is_array( $src_full )  ? (string) $src_full[0]  : (string) $url,
        'thumb_url'     => is_array( $src_thumb ) ? (string) $src_thumb[0] : ( is_array( $src_full ) ? (string) $src_full[0] : (string) $url ),
        'caption'       => get_the_title( $attach_id ),
        'sort_order'    => max( 0, $next_sort ),
    ];
}
