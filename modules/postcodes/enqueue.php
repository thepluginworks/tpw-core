<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Front-end enqueue for postcode lookup script on TPW pages.
 *
 * Enqueues the lightweight JS and localizes ajaxUrl and nonce only on pages
 * that include relevant TPW shortcodes to avoid site-wide overhead.
 *
 * @since 1.0.0
 */

add_action('wp_enqueue_scripts', function(){
    if ( ! class_exists( 'TPW_Postcode_Helper' ) || ! TPW_Postcode_Helper::should_render_lookup_ui() ) {
        return;
    }

    // Enqueue on front-end contexts where TPW pages may appear
    if (!is_page()) return;
    global $post; if (!$post) return;
    $has_tpw = has_shortcode($post->post_content, 'tpw_manage_members')
        || has_shortcode($post->post_content, 'tpw_manage_courses')
        || has_shortcode($post->post_content, 'tpw_fixtures_manage')
        || has_shortcode($post->post_content, 'tpw_apply_fixture')
        || has_shortcode($post->post_content, 'tpw_apply_special_fixture')
        || has_shortcode($post->post_content, 'tpw_apply_multiday_fixture');
    if (!$has_tpw) return;

    $script = TPW_CORE_PATH . 'modules/postcodes/assets/js/tpw-core-postcode.js';
    if (file_exists($script)) {
        $config = TPW_Postcode_Helper::get_frontend_config();

        wp_enqueue_script(
            'tpw-core-postcode',
            TPW_CORE_URL . 'modules/postcodes/assets/js/tpw-core-postcode.js',
            [],
            filemtime($script),
            true
        );
        wp_localize_script('tpw-core-postcode', 'tpwCorePostcode', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tpw_lookup_postcode'),
            'enabled' => ! empty( $config['enabled'] ),
            'provider' => isset( $config['provider'] ) ? $config['provider'] : 'none',
            'providerLabel' => isset( $config['providerLabel'] ) ? $config['providerLabel'] : '',
            'supportsFull' => ! empty( $config['supportsFull'] ),
        ]);
    }
});
