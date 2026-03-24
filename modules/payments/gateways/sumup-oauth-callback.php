<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

require_once dirname(__FILE__) . '/class-tpw-sumup-gateway.php';

function tpw_handle_sumup_callback() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }

    $code = $_GET['code'] ?? '';
    if (empty($code)) {
        wp_die('No authorization code received.');
    }

    $gateway = new TPW_SumUp_Gateway();

    if ( ! function_exists( 'tpw_core_get_payment_methods_settings_url' ) ) {
        wp_die( 'Payment Methods settings URL helper is unavailable.' );
    }

    $redirect_uri = admin_url('admin-post.php?action=tpw_sumup_callback');
    $settings_url = tpw_core_get_payment_methods_settings_url();

    $result = $gateway->exchange_code_for_token($code, $redirect_uri);

    if (is_wp_error($result)) {
    } else {
        if (isset($result['access_token'])) {
            update_option('tpw_sumup_access_token', sanitize_text_field($result['access_token']));
        }
        if (isset($result['merchant_code'])) {
            update_option('tpw_sumup_merchant_code', sanitize_text_field($result['merchant_code']));
        }
    }

    wp_safe_redirect(add_query_arg('sumup_connected', '1', $settings_url));
    exit;
}

add_action('admin_post_tpw_sumup_callback', 'tpw_handle_sumup_callback');

// SumUp OAuth redirect handler
function tpw_sumup_redirect_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }

    $client_id = get_option('tpw_sumup_client_id');
    if (empty($client_id)) {
        wp_die('Client ID is missing. Please configure your SumUp settings.');
    }

    $redirect_uri = urlencode(admin_url('admin-post.php?action=tpw_sumup_callback'));
    $auth_url = "https://api.sumup.com/authorize?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}&scope=payments user.app-settings";

    wp_redirect($auth_url);
    exit;
}
add_action('admin_post_tpw_sumup_redirect', 'tpw_sumup_redirect_handler');
?>
