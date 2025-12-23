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
    wp_enqueue_style( 'tpw-gallery-public', $base . 'assets/gallery.css', [], '0.7.4' );
    // Minimal built-in lightbox if TPW Control lightbox not present
    wp_enqueue_script( 'tpw-gallery-lightbox', $base . 'assets/lightbox.js', [ 'jquery' ], '0.7.2', true );
    wp_enqueue_style( 'tpw-gallery-lightbox', $base . 'assets/lightbox.css', [], '0.7.2' );
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

    $normalized_atts = [
        'id'              => (string) $id,
        'category'        => (string) $category,
        'columns'         => (string) $columns,
        'view'            => (string) $view,
        'show_categories' => $showCats ? '1' : '0',
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
    $ckey = 'tpw_gallery_sc_' . md5( serialize( [ $id, $category, $columns, $view, $showCats, $per_page, $paginate, $page_map ] ) );
    $cached = wp_cache_get( $ckey, 'tpw' );
    if ( is_string( $cached ) ) return $cached;

    // Enqueue assets once per request
    tpw_gallery_enqueue_public_assets();

    ob_start();
    $tpl = __DIR__ . '/templates/' . ( $view === 'list' ? 'list-view.php' : ( $view === 'story' ? 'story-view.php' : 'grid.php' ) );
    $data = [
        'items'           => $items,
        'columns'         => $columns,
        'show_categories' => $showCats,
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
 * [tpw_gallery id="123" category="slug" columns="4" view="grid|list|story" show_categories="0|1" per_page="0" paginate="0|1"]
 */
add_shortcode( 'tpw_gallery', function( $atts ){
    $atts = shortcode_atts([
        'id'              => '',
        'category'        => '',
        'columns'         => '3',
        'view'            => 'grid',
        'show_categories' => '0', // optional toolbar above grid
        'per_page'        => '0', // optional pagination safeguard for large galleries (grid/list)
        'paginate'        => '0', // set to 1 to enable pagination with default per_page
    ], $atts, 'tpw_gallery' );

    if ( function_exists( 'tpw_gallery_render' ) ) {
        return tpw_gallery_render( $atts );
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
