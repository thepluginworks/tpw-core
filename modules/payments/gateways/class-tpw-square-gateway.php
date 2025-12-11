<?php

error_log('Autoload file exists: ' . (file_exists(WP_PLUGIN_DIR . '/tpw-core/vendor/autoload.php') ? 'yes' : 'no'));


    require_once WP_PLUGIN_DIR . '/tpw-core/vendor/autoload.php';

use Square\SquareClient;
use Square\Environments;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Types\CashPaymentDetails;
use Square\Types\Currency;
use Square\Types\Money;
use Square\Exceptions\SquareApiException;
use Square\Exceptions\SquareException;
use Square\Types\Error;
use Square\Types\ErrorCategory;
use Square\Types\ErrorCode;
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

        // Apply unified surcharge before building the Money object
        if (class_exists('TPW_Core_Payments')) {
            $calc = TPW_Core_Payments::tpw_core_calculate_payable_total((float) ($args['amount'] ?? 0), 'square');
            $charge_amount = (float) $calc['total_with_surcharge'];
        } else {
            $charge_amount = (float) ($args['amount'] ?? 0);
        }

        $money = new Money([
            'amount' => (int) round($charge_amount * 100),
            'currency' => 'GBP'
        ]);

        $note_parts = [];
        if (!empty($args['member_name'])) {
            $note_parts[] = 'Member: ' . $args['member_name'];
        }
        if (!empty($args['payment_id'])) {
            $note_parts[] = '| TPW Payment ID: ' . $args['payment_id'];
        }
        $note_parts[] = '— RSVP payment';
        $built_note = trim(implode(' ', $note_parts));

        $body = new CreatePaymentRequest([
            'sourceId' => $args['nonce'],
            'idempotencyKey' => uniqid('sq_'),
            'amountMoney' => $money,
            'locationId' => $location_id,
            'referenceId' => $args['reference_id'] ?? 'RSVP-' . $args['submission_id'],
            'note' => $built_note
        ]);

        error_log('[TPW DEBUG] Payment Request Body: ' . print_r($body, true));
        error_log('[TPW DEBUG] Source ID (Nonce): ' . $body->getSourceId());
        error_log('[TPW DEBUG] Idempotency Key: ' . $body->getIdempotencyKey());
        error_log('[TPW DEBUG] Amount Money: ' . print_r($body->getAmountMoney(), true));
        error_log('[TPW DEBUG] Location ID in Body: ' . $body->getLocationId());
        try {
            return $client->payments->create(request: $body);
        } catch (SquareApiException $e) {
            // Extract and normalize errors
            $errors = method_exists($e, 'getErrors') ? $e->getErrors() : [];
            $normalized = self::normalize_square_errors($errors);
            $friendly = self::friendly_message_for_errors($normalized);
            error_log('[TPW ERROR] SquareApiException: status=' . $e->getStatusCode() . ' codes=' . implode(',', array_map(fn($n) => $n['code'], $normalized)) . ' category=' . ($normalized[0]['category'] ?? ''));
            return new \WP_Error(
                self::wp_error_code_for_errors($normalized),
                $friendly,
                [
                    'status' => $e->getStatusCode(),
                    'category' => $normalized[0]['category'] ?? null,
                    'codes' => array_map(fn($n) => $n['code'], $normalized),
                    'details' => array_map(fn($n) => $n['detail'], $normalized),
                    'require_new_nonce' => self::require_new_nonce($normalized),
                    'raw_errors' => $normalized,
                ]
            );
        } catch (SquareException $e) {
            error_log('[TPW ERROR] SquareException: ' . $e->getMessage());
            return new \WP_Error('square_payment_error', 'Payment service error. Please try again.', [ 'detail' => $e->getMessage() ]);
        } catch (\Throwable $e) {
            error_log('[TPW ERROR] Unexpected payment error: ' . $e->getMessage());
            return new \WP_Error('square_payment_error', 'Unexpected payment error. Please try again.', [ 'detail' => $e->getMessage() ]);
        }
    }

    /**
     * Normalize SDK Error objects to arrays for safe processing.
     * @param Error[] $errors
     * @return array<int, array{category: string, code: string, detail: ?string, field: ?string}>
     */
    private static function normalize_square_errors(array $errors): array {
        $out = [];
        foreach ($errors as $err) {
            try {
                $out[] = [
                    'category' => method_exists($err, 'getCategory') ? $err->getCategory() : 'API_ERROR',
                    'code' => method_exists($err, 'getCode') ? $err->getCode() : 'Unknown',
                    'detail' => method_exists($err, 'getDetail') ? $err->getDetail() : null,
                    'field' => method_exists($err, 'getField') ? $err->getField() : null,
                ];
            } catch (\Throwable $t) {
                $out[] = [ 'category' => 'API_ERROR', 'code' => 'Unknown', 'detail' => null, 'field' => null ];
            }
        }
        if (empty($out)) {
            $out[] = [ 'category' => 'API_ERROR', 'code' => 'Unknown', 'detail' => null, 'field' => null ];
        }
        return $out;
    }

    /**
     * Map common Square error codes to friendly messages.
     * @param array<int, array{category: string, code: string, detail: ?string, field: ?string}> $errors
     */
    private static function friendly_message_for_errors(array $errors): string {
        $codes = array_map(fn($e) => strtoupper($e['code'] ?? ''), $errors);
        $primary = $codes[0] ?? '';

        // Category-based temporary issue catch (API_ERROR)
        $category = strtoupper($errors[0]['category'] ?? '');
        if ($category === ErrorCategory::ApiError->value) {
            return 'There was a temporary issue processing your payment. Please try again.';
        }

        switch ($primary) {
            case ErrorCode::GenericDecline->value:
            case ErrorCode::CardDeclined->value:
                return 'Your bank has declined this payment. Please try another card or contact your bank.';
            case ErrorCode::InsufficientFunds->value:
                return 'This card does not have enough funds to complete the payment. Please try another card.';
            case ErrorCode::CardDeclinedVerificationRequired->value:
                return 'Your bank requires additional security verification for this payment. Please use a different card or contact your bank.';
            case ErrorCode::CvvFailure->value:
            case ErrorCode::VerifyCvvFailure->value:
                return 'The security code (CVV) was incorrect. Please check the number and try again.';
            case 'AVS_FAILURE':
            case ErrorCode::AddressVerificationFailure->value:
            case ErrorCode::VerifyAvsFailure->value:
                return 'The billing postcode or address does not match the card.';
            case ErrorCode::CardExpired->value:
                return 'This card has expired. Please try a different card.';
            case ErrorCode::InvalidCard->value:
            case ErrorCode::InvalidExpiration->value:
            case ErrorCode::InvalidExpirationDate->value:
            case ErrorCode::BadExpiration->value:
                return 'The card details entered are not valid. Please check and try again.';
            case ErrorCode::CardNotSupported->value:
            case ErrorCode::UnsupportedCardBrand->value:
                return 'This card type is not supported. Please try a different card.';
            case ErrorCode::CardTokenUsed->value:
            case ErrorCode::SourceUsed->value:
            case ErrorCode::CardTokenExpired->value:
            case ErrorCode::SourceExpired->value:
                return 'Your payment session expired. Please re-enter your card details.';
            case ErrorCode::Unauthorized->value:
            case ErrorCode::AccessTokenExpired->value:
            case ErrorCode::RateLimited->value:
                return 'There was a temporary issue processing your payment. Please try again.';
            default:
                $detail = $errors[0]['detail'] ?? null;
                return $detail ? $detail : 'We couldn’t process your payment. Please try again or use a different card.';
        }
    }

    /**
     * Determine WP_Error code based on error category.
     * @param array<int, array{category: string, code: string, detail: ?string, field: ?string}> $errors
     */
    private static function wp_error_code_for_errors(array $errors): string {
        $category = strtoupper($errors[0]['category'] ?? '');
        switch ($category) {
            case ErrorCategory::PaymentMethodError->value:
                return 'square_payment_declined';
            case ErrorCategory::InvalidRequestError->value:
                return 'square_invalid_request';
            case ErrorCategory::AuthenticationError->value:
                return 'square_auth_error';
            case ErrorCategory::RateLimitError->value:
                return 'square_rate_limited';
            default:
                return 'square_payment_error';
        }
    }

    /**
     * Whether a fresh card nonce/token is required before retrying.
     * @param array<int, array{category: string, code: string, detail: ?string, field: ?string}> $errors
     */
    private static function require_new_nonce(array $errors): bool {
        foreach ($errors as $e) {
            $code = strtoupper($e['code'] ?? '');
            if (in_array($code, [
                ErrorCode::CardTokenUsed->value,
                ErrorCode::SourceUsed->value,
                ErrorCode::CardTokenExpired->value,
                ErrorCode::SourceExpired->value,
            ], true)) {
                return true;
            }
        }
        return false;
    }
}