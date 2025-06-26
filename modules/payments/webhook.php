<?php
// Load WordPress
require_once '../../../../../../wp-load.php';

header('Content-Type: application/json');

// Read incoming JSON
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

$log_file = __DIR__ . '/webhook.log';
file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Received payload:\n" . print_r($data, true) . "\n\n", FILE_APPEND);

// Optional: Log to debug
// file_put_contents(__DIR__ . '/webhook.log', print_r($data, true));

// Basic check for SumUp structure
if (isset($data['event']) && $data['event'] === 'checkout.completed' && isset($data['checkout_reference'])) {
    $reference = sanitize_text_field($data['checkout_reference']);
    $total = isset($data['amount']['amount']) ? floatval($data['amount']['amount']) : 0;
    $email = sanitize_email($data['checkout']['email'] ?? '');

    if (class_exists('TPW_Payment_Logger')) {
        TPW_Payment_Logger::log(
            'sumup',
            'completed',
            'Webhook received and action triggered',
            $data,
            $reference
        );
    }

    do_action('tpw_payment_completed', [
        'gateway' => 'sumup',
        'reference' => $reference,
        'email' => $email,
        'amount' => $total,
        'payload' => $data
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Payment hook triggered (SumUp).']);
    exit;
}

// Square Webhook example
if (isset($data['type']) && $data['type'] === 'payment.updated' && isset($data['data']['object']['payment'])) {
    $payment = $data['data']['object']['payment'];
    $status = $payment['status'] ?? '';
    $reference = sanitize_text_field($payment['note'] ?? '');
    $email = sanitize_email($payment['buyer_email_address'] ?? '');
    $total = isset($payment['amount_money']['amount']) ? floatval($payment['amount_money']['amount'] / 100) : 0;

    if (strtolower($status) === 'completed' || strtolower($status) === 'approved') {
        if (class_exists('TPW_Payment_Logger')) {
            TPW_Payment_Logger::log(
                'square',
                'completed',
                'Webhook received and action triggered',
                $data,
                $reference
            );
        }

        do_action('tpw_payment_completed', [
            'gateway' => 'square',
            'reference' => $reference,
            'email' => $email,
            'amount' => $total,
            'payload' => $data
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Payment hook triggered (Square).']);
        exit;
    }

    echo json_encode(['status' => 'ignored', 'message' => 'Square payment not completed.']);
    http_response_code(200);
    exit;
}

echo json_encode(['status' => 'ignored', 'message' => 'Not a valid SumUp webhook.']);
http_response_code(400);
exit;