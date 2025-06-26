<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

echo '<div class="wrap"><h1>SumUp Test Connection</h1>';

$access_token = get_option('tpw_sumup_access_token');

if (empty($access_token)) {
    echo '<div class="notice notice-error"><p>No access token found. Please connect to SumUp first.</p></div>';
} else {
    $response = wp_remote_get(
        'https://api.sumup.com/v0.1/me',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ]
        ]
    );

    if (is_wp_error($response)) {
        echo '<div class="notice notice-error"><p>Error: ' . $response->get_error_message() . '</p></div>';
    } else {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['account'])) {
            echo '<div class="notice notice-success"><p>✅ Connection Successful!</p></div>';
            echo '<ul>';
            echo '<li><strong>Username:</strong> ' . esc_html($data['account']['username'] ?? 'N/A') . '</li>';
            echo '<li><strong>Account Type:</strong> ' . esc_html($data['account']['type'] ?? 'N/A') . '</li>';
            if (!empty($data['merchant_profile']['business_name'])) {
                echo '<li><strong>Merchant Profile:</strong> ' . esc_html($data['merchant_profile']['business_name']) . '</li>';
            }
            echo '</ul>';
            echo '<details style="margin-top:1em;"><summary><strong>Debug Info (JSON)</strong></summary>';
            echo '<pre>' . esc_html(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
            echo '</details>';
            error_log('SumUp response: ' . print_r($data, true));
        } elseif (json_last_error() !== JSON_ERROR_NONE) {
            echo '<div class="notice notice-error"><p>❌ Failed to decode response JSON.</p></div>';
            echo '<pre>' . esc_html($body) . '</pre>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Invalid response from SumUp API.</p></div>';
            echo '<pre>' . esc_html($body) . '</pre>';
        }
    }
}

echo '</div>';