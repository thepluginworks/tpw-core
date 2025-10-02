<?php
if (!defined('ABSPATH')) { exit; }

add_action('wp_enqueue_scripts', function(){
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
        ]);
    }
});
