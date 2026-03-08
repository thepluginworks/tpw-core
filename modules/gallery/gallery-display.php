<?php
/**
 * TPW Core – Gallery Public Display (Shortcodes)
 * @since 0.5.0 Public Display
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enqueue lightbox assets for front-end display (scoped to shortcode renders).
 */
function tpw_gallery_enqueue_public_assets() {
    static $done = false; if ( $done ) return; $done = true;
    $base = trailingslashit( TPW_CORE_URL ) . 'modules/gallery/';
    wp_enqueue_style( 'tpw-buttons', trailingslashit( TPW_CORE_URL ) . 'assets/css/tpw-buttons.css', [], '0.6.0' );
    wp_enqueue_style( 'tpw-gallery-public', $base . 'assets/gallery.css', [], '0.7.4' );
    // Minimal built-in lightbox if TPW Control lightbox not present
    wp_enqueue_script( 'tpw-gallery-lightbox', $base . 'assets/lightbox.js', [ 'jquery' ], '0.7.2', true );
    wp_enqueue_style( 'tpw-gallery-lightbox', $base . 'assets/lightbox.css', [], '0.7.2' );
}

/**
 * Query arg used by the public gallery index to resolve the selected gallery.
 */
function tpw_gallery_selection_query_arg(): string {
    return 'tpw_gallery_id';
}

/**
 * Resolve the currently selected public gallery.
 */
function tpw_gallery_get_selected_public_gallery_id( array $args = [] ): int {
    $selected_id = isset( $args['selected_id'] ) ? (int) $args['selected_id'] : 0;
    if ( $selected_id > 0 ) {
        return $selected_id;
    }

    $query_arg = tpw_gallery_selection_query_arg();
    if ( isset( $_GET[ $query_arg ] ) ) {
        return max( 0, (int) $_GET[ $query_arg ] );
    }

    return 0;
}

/**
 * Return gallery index cards with cover image metadata.
 */
function tpw_gallery_get_index_items(): array {
    if ( ! function_exists( 'tpw_gallery_all_with_counts' ) ) {
        return [];
    }

    $rows = (array) tpw_gallery_all_with_counts();
    foreach ( $rows as &$row ) {
        $attachment_id = isset( $row['cover_attachment_id'] ) ? (int) $row['cover_attachment_id'] : 0;
        $row['cover_attachment_id'] = $attachment_id;
        $row['cover_image_url'] = '';
        $row['cover_image_alt'] = isset( $row['title'] ) ? (string) $row['title'] : '';

        if ( $attachment_id > 0 ) {
            $cover = wp_get_attachment_image_src( $attachment_id, 'medium' );
            $row['cover_image_url'] = is_array( $cover ) ? (string) $cover[0] : '';

            $alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
            if ( $alt === '' ) {
                $alt = (string) get_the_title( $attachment_id );
            }
            if ( $alt !== '' ) {
                $row['cover_image_alt'] = $alt;
            }
        }
    }
    unset( $row );

    return $rows;
}

/**
 * Build a same-page public selection URL for a gallery card.
 */
function tpw_gallery_get_selection_url( int $gallery_id ): string {
    $base_url = tpw_gallery_get_index_url();

    $url = add_query_arg( tpw_gallery_selection_query_arg(), $gallery_id, $base_url );
    return $url . '#tpw-gallery-selected';
}

/**
 * Build the same-page URL that returns the gallery browser to its index state.
 */
function tpw_gallery_get_index_url(): string {
    $base_url = add_query_arg( null, null );
    $base_url = remove_query_arg( tpw_gallery_selection_query_arg(), $base_url );

    foreach ( array_keys( $_GET ) as $key ) {
        if ( strpos( (string) $key, 'tpw_gallery_page_' ) === 0 ) {
            $base_url = remove_query_arg( $key, $base_url );
        }
    }

    return $base_url . '#tpw-gallery-browser';
}

/**
 * Render an index of all galleries and, when selected, the chosen gallery beneath it.
 *
 * Accepted args:
 * - layout: grid|list
 * - columns: int 1..6 for grid layout
 * - detail_view: grid|list|story
 * - detail_columns: int 1..6 when detail view is grid
 * - show_gallery_heading: bool|string
 */
function tpw_gallery_render_index( array $args = [] ): string {
    $layout_in = isset( $args['layout'] ) ? strtolower( (string) $args['layout'] ) : 'grid';
    $layout = in_array( $layout_in, [ 'grid', 'list' ], true ) ? $layout_in : 'grid';
    $columns = isset( $args['columns'] ) ? max( 1, min( 6, (int) $args['columns'] ) ) : 3;

    $detail_view_in = isset( $args['detail_view'] ) ? strtolower( (string) $args['detail_view'] ) : 'grid';
    $detail_view = in_array( $detail_view_in, [ 'grid', 'list', 'story' ], true ) ? $detail_view_in : 'grid';
    $detail_columns = isset( $args['detail_columns'] ) ? max( 1, min( 6, (int) $args['detail_columns'] ) ) : 3;

    $show_gallery_heading = true;
    if ( isset( $args['show_gallery_heading'] ) ) {
        $value = $args['show_gallery_heading'];
        $show_gallery_heading = ( $value === true || $value === 1 || $value === '1' || $value === 'true' );
    }

    $items = tpw_gallery_get_index_items();
    if ( empty( $items ) ) {
        return '';
    }

    $selected_id = tpw_gallery_get_selected_public_gallery_id( $args );
    $selected_gallery_html = '';
    if ( $selected_id > 0 ) {
        $selected_gallery_html = tpw_gallery_render([
            'id'           => $selected_id,
            'view'         => $detail_view,
            'columns'      => $detail_columns,
            'show_categories' => '0',
            'show_heading' => $show_gallery_heading ? '1' : '0',
        ]);
    }

    tpw_gallery_enqueue_public_assets();

    ob_start();
    include __DIR__ . '/templates/index.php';
    return (string) ob_get_clean();
}

/**
 * Render a TPW gallery (shared renderer for shortcode + integrations).
 *
 * Accepted args (strings/ints are tolerated; will be normalized):
 * - id (int) Gallery ID
 * - category (string) Category slug
 * - columns (int) 1..6
 * - view (string) grid|list|story
 * - show_categories (bool|"0"|"1")
 * - show_heading (bool|"0"|"1")
 */
function tpw_gallery_render( array $args = [] ): string {
    $id       = isset( $args['id'] ) ? (int) $args['id'] : 0;
    $category = isset( $args['category'] ) ? sanitize_title( (string) $args['category'] ) : '';
    $columns  = isset( $args['columns'] ) ? max( 1, min( 6, (int) $args['columns'] ) ) : 3;
    $view_in  = isset( $args['view'] ) ? strtolower( (string) $args['view'] ) : 'grid';
    $view     = in_array( $view_in, [ 'grid', 'list', 'story' ], true ) ? $view_in : 'grid';

    // Performance safeguard: optional pagination for grid/list.
    // - per_page: max tiles per page (0 disables)
    // - paginate: "1" enables with default per_page when per_page not set
    $per_page = isset( $args['per_page'] ) ? max( 0, (int) $args['per_page'] ) : 0;
    $paginate = false;
    if ( isset( $args['paginate'] ) ) {
        $pg = $args['paginate'];
        $paginate = ( $pg === true || $pg === 1 || $pg === '1' || $pg === 'true' );
    }
    if ( $paginate && $per_page <= 0 ) {
        // Conservative default; keeps large galleries from rendering hundreds of tiles.
        $per_page = 60;
    }

    $showCats = false;
    if ( isset( $args['show_categories'] ) ) {
        $sc = $args['show_categories'];
        $showCats = ( $sc === true || $sc === 1 || $sc === '1' || $sc === 'true' );
    }

    $showHeading = true;
    if ( isset( $args['show_heading'] ) ) {
        $sh = $args['show_heading'];
        $showHeading = ( $sh === true || $sh === 1 || $sh === '1' || $sh === 'true' );
    }

    $normalized_atts = [
        'id'              => (string) $id,
        'category'        => (string) $category,
        'columns'         => (string) $columns,
        'view'            => (string) $view,
        'show_categories' => $showCats ? '1' : '0',
        'show_heading'    => $showHeading ? '1' : '0',
        'per_page'        => (string) $per_page,
        'paginate'        => $paginate ? '1' : '0',
    ];

    // Resolve data
    $items = [];
    if ( $id > 0 && function_exists('tpw_gallery_get') ) {
        $g = tpw_gallery_get( $id );
        if ( $g && is_array( $g ) ) $items = [ $g ];
    } elseif ( $category !== '' && function_exists('tpw_gallery_list_by_category') ) {
        // Need to map galleries for category slug
        global $wpdb; $cattbl = $wpdb->prefix . 'tpw_gallery_categories';
        $cat_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT category_id FROM {$cattbl} WHERE slug=%s", $category ) );
        $gals = $cat_id ? (array) tpw_gallery_list_by_category( $cat_id ) : [];
        foreach ( $gals as $g ) {
            $full = tpw_gallery_get( (int) $g['gallery_id'] );
            if ( $full ) $items[] = $full;
        }
    }

    if ( empty( $items ) ) return '';

    // Cache key (must vary by pagination query args, otherwise page 2 may show page 1)
    $page_map = [];
    $paging_enabled = ( $per_page > 0 && in_array( $view, [ 'grid', 'list' ], true ) );
    if ( $paging_enabled ) {
        foreach ( $items as $it ) {
            $gid = isset( $it['gallery']['gallery_id'] ) ? (int) $it['gallery']['gallery_id'] : 0;
            if ( $gid <= 0 ) continue;
            $qv = 'tpw_gallery_page_' . $gid;
            $page_map[ $gid ] = isset( $_GET[ $qv ] ) ? max( 1, (int) $_GET[ $qv ] ) : 1;
        }
        ksort( $page_map );
    }
    $ckey = 'tpw_gallery_sc_' . md5( serialize( [ $id, $category, $columns, $view, $showCats, $showHeading, $per_page, $paginate, $page_map ] ) );
    $cached = wp_cache_get( $ckey, 'tpw' );
    if ( is_string( $cached ) ) return $cached;

    // Enqueue assets once per request
    tpw_gallery_enqueue_public_assets();

    // Provide template-friendly variables.
    // (Templates use snake_case names; keep logic unchanged elsewhere.)
    $show_heading = $showHeading;

    ob_start();
    $tpl = __DIR__ . '/templates/' . ( $view === 'list' ? 'list-view.php' : ( $view === 'story' ? 'story-view.php' : 'grid.php' ) );
    $data = [
        'items'           => $items,
        'columns'         => $columns,
        'show_categories' => $showCats,
        'show_heading'    => $showHeading,
        'view'            => $view,
        'per_page'        => $per_page,
        'paginate'        => $paginate ? true : false,
    ];
    include $tpl;
    $out = ob_get_clean();

    $out = apply_filters( 'tpw_gallery_display', $out, $normalized_atts );
    wp_cache_set( $ckey, $out, 'tpw', HOUR_IN_SECONDS );
    return (string) $out;
}

/**
 * [tpw_gallery id="123" category="slug" columns="4" view="grid|list|story" show_categories="0|1" show_heading="0|1" per_page="0" paginate="0|1"]
 */
add_shortcode( 'tpw_gallery', function( $atts ){
    $atts = shortcode_atts([
        'id'              => '',
        'category'        => '',
        'columns'         => '3',
        'view'            => 'grid',
        'show_categories' => '0', // optional toolbar above grid
        'show_heading'    => '1', // show gallery title/description above images
        'per_page'        => '0', // optional pagination safeguard for large galleries (grid/list)
        'paginate'        => '0', // set to 1 to enable pagination with default per_page
    ], $atts, 'tpw_gallery' );

    if ( function_exists( 'tpw_gallery_render' ) ) {
        return tpw_gallery_render( $atts );
    }
    return '';
});

/**
 * [tpw_gallery_index layout="grid|list" columns="3" detail_view="grid|list|story" detail_columns="3" show_gallery_heading="1"]
 */
add_shortcode( 'tpw_gallery_index', function( $atts ) {
    $atts = shortcode_atts([
        'layout'               => 'grid',
        'columns'              => '3',
        'detail_view'          => 'grid',
        'detail_columns'       => '3',
        'show_gallery_heading' => '1',
    ], $atts, 'tpw_gallery_index' );

    if ( function_exists( 'tpw_gallery_render_index' ) ) {
        return tpw_gallery_render_index( $atts );
    }

    return '';
});

/**
 * [tpw_gallery_categories]
 */
add_shortcode( 'tpw_gallery_categories', function( $atts ){
    $cats = function_exists('tpw_gallery_get_categories') ? tpw_gallery_get_categories() : [];
    if ( empty( $cats ) ) return '';
    tpw_gallery_enqueue_public_assets();
    $base = add_query_arg( null, null );
    $out = '<div class="tpw-gallery-categories">';
    foreach ( $cats as $c ) {
        $url = esc_url( add_query_arg( 'tpw_gallery_cat', sanitize_title( $c['slug'] ), $base ) );
        $out .= '<a class="tpw-btn tpw-btn-light" href="' . $url . '">' . esc_html( $c['name'] ) . '</a> ';
    }
    $out .= '</div>';
    return $out;
});
