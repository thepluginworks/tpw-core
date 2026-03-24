<?php

class TPW_SumUp_Gateway {
    private $client_id;
    private $client_secret;
    private $access_token;

    public function __construct() {
        // Load credentials from wp-config.php constants if defined, otherwise fall back to database options
        $this->client_id = defined('TPW_SUMUP_CLIENT_ID') ? TPW_SUMUP_CLIENT_ID : get_option('tpw_sumup_client_id');
        $this->client_secret = defined('TPW_SUMUP_CLIENT_SECRET') ? TPW_SUMUP_CLIENT_SECRET : get_option('tpw_sumup_client_secret');
        $this->access_token = defined('TPW_SUMUP_ACCESS_TOKEN') ? TPW_SUMUP_ACCESS_TOKEN : get_option('tpw_sumup_access_token');
    }

    /**
     * Create a checkout link for a given RSVP submission.
     */
    public function create_checkout($amount, $email, $return_url, $currency = 'GBP') {
        $endpoint = 'https://api.sumup.com/v0.1/checkouts';

        // Apply unified surcharge before formatting
        if (class_exists('TPW_Core_Payments')) {
            $calc = TPW_Core_Payments::tpw_core_calculate_payable_total((float) $amount, 'sumup');
            $amount = $calc['total_with_surcharge'];
        }
        // Ensure $amount is a string formatted with two decimals
        $amount = number_format((float) $amount, 2, '.', '');

        $merchant_code = get_option('tpw_sumup_merchant_code');
        $body = wp_json_encode([
            'merchant_code'      => $merchant_code,
            'amount'             => (string) $amount,
            'currency'           => $currency,
            'checkout_reference' => 'rsvp-' . uniqid(),
            'return_url'         => esc_url_raw($return_url),
            'pay_to_email'       => $email,
        ]);

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
        ]);
        $raw_response = wp_remote_retrieve_body($response);

        if (is_wp_error($response)) {
            // Removed error_log
            return new WP_Error('sumup_error', 'Failed to connect to SumUp API');
        }

        $data = json_decode($raw_response, true);

        if (!empty($data['checkout_url'])) {
            return esc_url_raw($data['checkout_url']);
        }
        return new WP_Error('sumup_error', 'Failed to create checkout — missing checkout_url in response.');
    }

    /**
     * Set the access token manually (for testing or dynamic flows).
     */
    public function set_access_token($token) {
        $this->access_token = $token;
    }

    /**
     * Save the token to the database (optional).
     */
    public function save_access_token($token) {
        $this->access_token = $token;
    }

    /**
     * Request an access token using saved email, client ID, and secret.
     */
    public function request_access_token() {
        $email = get_option('tpw_sumup_email');
        $password = get_option('tpw_sumup_password'); // Add to your settings if not yet saved
        $client_id = defined('TPW_SUMUP_CLIENT_ID') ? TPW_SUMUP_CLIENT_ID : null;
        $client_secret = defined('TPW_SUMUP_CLIENT_SECRET') ? TPW_SUMUP_CLIENT_SECRET : null;

        if (!$email || !$password || !$client_id || !$client_secret) {
            return new WP_Error('sumup_missing_settings', 'Missing SumUp credentials.');
        }

        $response = wp_remote_post('https://api.sumup.com/token', [
            'body' => [
                'grant_type'    => 'password',
                'username'      => $email,
                'password'      => $password,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
            ]
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('sumup_connection_failed', 'Failed to connect to SumUp token endpoint.');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($data['access_token'])) {
            $this->save_access_token($data['access_token']);
            return true;
        }

        return new WP_Error('sumup_token_failed', isset($data['error_description']) ? $data['error_description'] : 'Unknown error requesting token.');
    }

    /**
     * Exchange the OAuth code for an access token.
     */
    public function exchange_code_for_token($code, $redirect_uri) {
        $client_id = defined('TPW_SUMUP_CLIENT_ID') ? TPW_SUMUP_CLIENT_ID : get_option('tpw_sumup_client_id');
        $client_secret = defined('TPW_SUMUP_CLIENT_SECRET') ? TPW_SUMUP_CLIENT_SECRET : get_option('tpw_sumup_client_secret');

        if (!$client_id || !$client_secret) {
            return new WP_Error('sumup_missing_settings', 'Missing SumUp client credentials.');
        }

        $response = wp_remote_post('https://api.sumup.com/token', [
            'body' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'code'          => $code,
                'redirect_uri'  => $redirect_uri,
            ]
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('sumup_token_request_failed', 'Failed to connect to SumUp token endpoint.');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($data['access_token'])) {
            $this->access_token = $data['access_token'];
            update_option('tpw_sumup_access_token', sanitize_text_field($data['access_token']));
            $this->save_access_token($data['access_token']);

            // Immediately fetch and store merchant_code
            $me_response = wp_remote_get('https://api.sumup.com/v0.1/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->access_token,
                ],
                'timeout' => 15
            ]);

            if (!is_wp_error($me_response)) {
                $me_data = json_decode(wp_remote_retrieve_body($me_response), true);
                if (!empty($me_data['merchant_profile']['merchant_code'])) {
                    $merchant_code = sanitize_text_field($me_data['merchant_profile']['merchant_code']);
                    update_option('tpw_sumup_merchant_code', $merchant_code);
                }
            }
            return true;
        }

        return new WP_Error('sumup_token_exchange_failed', $data['error_description'] ?? 'Unknown error during code exchange.');
    }

}

function tpw_sumup_create_checkout($access_token, $reference, $amount, $email, $return_url, $cancel_url) {
    $gateway = new TPW_SumUp_Gateway();
    $gateway->set_access_token($access_token);
    // Pass only the expected arguments in correct order: amount, email, return_url, currency
    return $gateway->create_checkout($amount, $email, $return_url, 'GBP');
}
