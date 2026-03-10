<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX: Select2 search for TPW galleries (Elementor editor).
 *
 * Supports:
 * - id (int): returns a single result (used to display existing selected value)
 * - term (string) + page (int): paginated search (20 per page)
 */
add_action( 'wp_ajax_tpw_gallery_elementor_search', function() {
    if ( ! function_exists( 'tpw_gallery_user_can_manage' ) || ! tpw_gallery_user_can_manage() ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied', 'tpw-core' ) ] );
    }

    check_ajax_referer( 'tpw_gallery_elementor', 'nonce' );

    global $wpdb;
    $tbl_g = $wpdb->prefix . 'tpw_galleries';
    $tbl_c = $wpdb->prefix . 'tpw_gallery_categories';

    $id = isset( $_GET['id'] ) ? (int) $_GET['id'] : ( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
    if ( $id > 0 ) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT g.gallery_id, g.title, c.name AS category_name\n                 FROM {$tbl_g} g\n                 LEFT JOIN {$tbl_c} c ON c.category_id = g.category_id\n                 WHERE g.gallery_id = %d\n                 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        $results = [];
        if ( $row && isset( $row['gallery_id'] ) ) {
            $title = isset( $row['title'] ) ? (string) $row['title'] : '';
            $cat   = isset( $row['category_name'] ) ? (string) $row['category_name'] : '';
            $text  = $cat !== '' ? sprintf( '%s (%s)', $title, $cat ) : $title;
            $results[] = [
                'id'   => (int) $row['gallery_id'],
                'text' => $text,
            ];
        }

        wp_send_json([
            'results'    => $results,
            'pagination' => [ 'more' => false ],
        ]);
    }

    $term = '';
    if ( isset( $_GET['term'] ) ) {
        $term = sanitize_text_field( wp_unslash( (string) $_GET['term'] ) );
    } elseif ( isset( $_POST['term'] ) ) {
        $term = sanitize_text_field( wp_unslash( (string) $_POST['term'] ) );
    }

    $page = isset( $_GET['page'] ) ? (int) $_GET['page'] : ( isset( $_POST['page'] ) ? (int) $_POST['page'] : 1 );
    $page = max( 1, $page );

    $per_page = 20;

    // Do not preload all galleries.
    if ( $term === '' ) {
        wp_send_json([
            'results'    => [],
            'pagination' => [ 'more' => false ],
        ]);
    }

    $like = '%' . $wpdb->esc_like( $term ) . '%';
    $offset = ( $page - 1 ) * $per_page;

    // Fetch one extra row to determine if more pages exist.
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT g.gallery_id, g.title, c.name AS category_name\n             FROM {$tbl_g} g\n             LEFT JOIN {$tbl_c} c ON c.category_id = g.category_id\n             WHERE g.title LIKE %s\n             ORDER BY g.title ASC\n             LIMIT %d OFFSET %d",
            $like,
            $per_page + 1,
            $offset
        ),
        ARRAY_A
    );

    $more = false;
    if ( is_array( $rows ) && count( $rows ) > $per_page ) {
        $more = true;
        $rows = array_slice( $rows, 0, $per_page );
    }

    $results = [];
    foreach ( (array) $rows as $row ) {
        $title = isset( $row['title'] ) ? (string) $row['title'] : '';
        $cat   = isset( $row['category_name'] ) ? (string) $row['category_name'] : '';
        $text  = $cat !== '' ? sprintf( '%s (%s)', $title, $cat ) : $title;
        $results[] = [
            'id'   => (int) $row['gallery_id'],
            'text' => $text,
        ];
    }

    wp_send_json([
        'results'    => $results,
        'pagination' => [ 'more' => $more ],
    ]);
} );
