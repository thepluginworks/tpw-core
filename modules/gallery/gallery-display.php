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
 * [tpw_gallery id="123" category="slug" columns="4" view="grid|list" show_categories="0|1"]
 */
add_shortcode( 'tpw_gallery', function( $atts ){
    $atts = shortcode_atts([
        'id'              => '',
        'category'        => '',
        'columns'         => '3',
        'view'            => 'grid',
        'show_categories' => '0', // optional toolbar above grid
    ], $atts, 'tpw_gallery' );

    $id       = (int) $atts['id'];
    $category = sanitize_title( (string) $atts['category'] );
    $columns  = max( 1, min( 6, (int) $atts['columns'] ) );
    $view     = in_array( strtolower( (string) $atts['view'] ), ['grid','list','story'], true ) ? strtolower( (string) $atts['view'] ) : 'grid';
    $showCats = (string) $atts['show_categories'] === '1';

    // Cache key
    $ckey = 'tpw_gallery_sc_' . md5( serialize( [ $id, $category, $columns, $view, $showCats ] ) );
    $cached = wp_cache_get( $ckey, 'tpw' );
    if ( is_string( $cached ) ) return $cached;

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

    // Enqueue assets once per request
    tpw_gallery_enqueue_public_assets();

    ob_start();
    $tpl = __DIR__ . '/templates/' . ( $view === 'list' ? 'list-view.php' : ( $view === 'story' ? 'story-view.php' : 'grid.php' ) );
    $data = [ 'items' => $items, 'columns' => $columns, 'show_categories' => $showCats, 'view' => $view ];
    include $tpl;
    $out = ob_get_clean();

    $out = apply_filters( 'tpw_gallery_display', $out, $atts );
    wp_cache_set( $ckey, $out, 'tpw', HOUR_IN_SECONDS );
    return $out;
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
