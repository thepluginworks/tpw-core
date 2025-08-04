<?php

error_log('Autoload file exists: ' . (file_exists(WP_PLUGIN_DIR . '/tpw-core/vendor/autoload.php') ? 'yes' : 'no'));


    require_once WP_PLUGIN_DIR . '/tpw-core/vendor/autoload.php';

use Square\SquareClient;
use Square\Environments;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Types\CashPaymentDetails;
use Square\Types\Currency;
use Square\Types\Money;
error_log('Money class exists: ' . (class_exists('\Square\Types\Money') ? 'yes' : 'no'));
class TPW_Square_Gateway {

    public static function is_enabled(): bool {
        $methods = get_option('tpw_active_payment_methods', []);
        return in_array('square', $methods, true);
    }

    public static function label(): string {
        return 'Pay by Card (via Square)';
    }

    public static function process_payment(array $args) {

        $access_token = get_option('tpw_square_access_token');
        $is_sandbox = get_option('tpw_square_sandbox_mode') === '1';
        error_log('[TPW DEBUG] Square environment: ' . ($is_sandbox ? 'sandbox' : 'production'));
        $location_id = get_option('tpw_square_location_id');
        error_log('[TPW DEBUG] location_id: ' . $location_id);
        error_log('[TPW DEBUG] access_token passed to SquareClient: ' . substr($access_token ?? 'null', 0, 10) . '...');

        if (empty($access_token)) {
            throw new \Exception('Square access token is missing.');
        }
        putenv('SQUARE_ENVIRONMENT=' . ($is_sandbox ? 'sandbox' : 'production'));
        error_log('[TPW DEBUG] args: ' . print_r($args, true));
        error_log('[TPW DEBUG] Nonce: ' . ($args['nonce'] ?? 'null'));
        error_log('[TPW DEBUG] Token Length: ' . strlen($access_token ?? ''));
        error_log('[TPW DEBUG] Location ID: ' . $location_id);
        $client = new SquareClient(
            token: $access_token,
            options: [
                'baseUrl' => $is_sandbox ? Environments::Sandbox->value : Environments::Production->value,
            ]
        );

        $money = new Money([
            'amount' => (int) round($args['amount'] * 100),
            'currency' => 'GBP'
        ]);

        $body = new CreatePaymentRequest([
            'sourceId' => $args['nonce'],
            'idempotencyKey' => uniqid('sq_'),
            'amountMoney' => $money,
            'locationId' => $location_id,
            'referenceId' => $args['reference_id'] ?? 'RSVP-' . $args['submission_id'],
            'note' => $args['note'] ?? 'RSVP payment'
        ]);

        error_log('[TPW DEBUG] Payment Request Body: ' . print_r($body, true));
        error_log('[TPW DEBUG] Source ID (Nonce): ' . $body->getSourceId());
        error_log('[TPW DEBUG] Idempotency Key: ' . $body->getIdempotencyKey());
        error_log('[TPW DEBUG] Amount Money: ' . print_r($body->getAmountMoney(), true));
        error_log('[TPW DEBUG] Location ID in Body: ' . $body->getLocationId());
        return $client->payments->create(request: $body);
    }
}