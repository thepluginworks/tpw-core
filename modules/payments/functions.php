<?php
/**
 * Create a payment link via the selected gateway.
 *
 * @param array $args {
 *     @type float  $amount
 *     @type string $reference
 *     @type string $email
 *     @type string $return_url
 *     @type string $cancel_url
 * }
 * @return array|WP_Error
 */
/*
function tpw_core_create_payment($args = []) {
    $gateway = $args['payment_method'] ?? null; // e.g. 'sumup', 'square', 'stripe'
    error_log('[TPW_CORE] Gateway selected: ' . $gateway);

    switch ($gateway) {
        case 'sumup':
            require_once plugin_dir_path(__FILE__) . 'gateways/class-tpw-sumup-gateway.php';

            $access_token = get_option('tpw_sumup_access_token');
            if (empty($access_token)) {
                return new WP_Error('sumup_missing_token', 'SumUp access token is not configured.');
            }

            return tpw_sumup_create_checkout(
                $access_token,
                $args['reference'] ?? '',
                $args['amount'] ?? 0,
                $args['email'] ?? '',
                $args['return_url'] ?? '',
                $args['cancel_url'] ?? ''
            );

        case 'square':
            require_once plugin_dir_path(__FILE__) . 'gateways/class-tpw-square-gateway.php';
            return tpw_square_create_checkout(
                $args['reference'] ?? '',
                $args['amount'] ?? 0,
                $args['email'] ?? '',
                $args['return_url'] ?? '',
                $args['cancel_url'] ?? ''
            );

        case 'stripe':
            require_once plugin_dir_path(__FILE__) . 'gateways/stripe.php';
            return tpw_stripe_create_checkout(
                $args['reference'] ?? '',
                $args['amount'] ?? 0,
                $args['email'] ?? '',
                $args['return_url'] ?? '',
                $args['cancel_url'] ?? ''
            );

        default:
            return new WP_Error('no_gateway', 'No valid payment gateway selected.');
    }
}
*/
