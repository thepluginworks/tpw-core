<?php
/**
 * TPW Core – Gallery Module Self-Test (internal only)
 *
 * Usage: include and call tpw_gallery_self_test($test_image_path)
 * Requirements: $test_image_path must be a local path to a small image file.
 * No UI output; returns structured results. Do not load in production.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function tpw_gallery_self_test( string $test_image_path ) : array {
    $results = [ 'created_gallery' => null, 'uploaded_image' => null, 'fetched' => null, 'deleted' => null, 'errors' => [] ];

    if ( ! file_exists( $test_image_path ) ) {
        $results['errors'][] = 'Test image does not exist: ' . $test_image_path;
        return $results;
    }

    // 1) Create dummy gallery
    $g = tpw_gallery_create([ 'title' => 'Self-Test Gallery', 'description' => 'Temporary test', 'category_id' => 0 ]);
    if ( is_wp_error( $g ) ) {
        $results['errors'][] = $g->get_error_message();
        return $results;
    }
    $results['created_gallery'] = $g;

    // 2) Fake an upload array for the test image
    $file = [
        'name'     => basename( $test_image_path ),
        'type'     => mime_content_type( $test_image_path ) ?: 'image/jpeg',
        'tmp_name' => $test_image_path,
        'error'    => 0,
        'size'     => filesize( $test_image_path ),
    ];

    $img = tpw_gallery_add_image( (int) $g['gallery_id'], $file );
    if ( is_wp_error( $img ) ) {
        $results['errors'][] = $img->get_error_message();
    } else {
        $results['uploaded_image'] = $img;
    }

    // 3) Fetch gallery
    $fetched = tpw_gallery_get( (int) $g['gallery_id'] );
    $results['fetched'] = $fetched;

    // 4) Delete gallery (cleanup)
    $del = tpw_gallery_delete( (int) $g['gallery_id'] );
    if ( is_wp_error( $del ) ) {
        $results['errors'][] = $del->get_error_message();
        $results['deleted'] = false;
    } else {
        $results['deleted'] = (bool) $del;
    }

    return $results;
}
