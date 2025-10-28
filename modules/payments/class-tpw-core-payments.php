<?php

/**
 * Core payment helpers.
 *
 * Provides surcharge calculation and persistence helpers used by RSVP flows
 * and integrations. No gateway side‑effects occur here.
 *
 * @since 1.0.0
 */
class TPW_Core_Payments {

    /**
     * Calculate payable total including surcharge for a given payment method.
     *
     * No side-effects: only reads options and returns computed values.
     *
     * @param float  $amount Base amount before surcharge.
     * @param string $method Payment method slug (e.g., 'woocommerce','square','sumup','bacs','cheque','cash').
     * @return array{base_amount:float,surcharge_amount:float,total_with_surcharge:float}
     */
    /**
     * @since 1.0.0
     */
    public static function tpw_core_calculate_payable_total( $amount, $method ) {
        $base = (float) $amount;
        $method = is_string($method) ? strtolower(trim($method)) : '';

        // Read per-method surcharge configuration from options; default to 0.
        $percent = (float) get_option( 'tpw_surcharge_' . $method . '_percent', 0 );
        $fixed   = (float) get_option( 'tpw_surcharge_' . $method . '_fixed', 0 );

        if ( $percent < 0 ) { $percent = 0; }
        if ( $fixed < 0 ) { $fixed = 0; }

        $surcharge = ($base * ($percent / 100.0)) + $fixed;
        $total     = $base + $surcharge;

        return [
            'base_amount'           => $base,
            'surcharge_amount'      => $surcharge,
            'total_with_surcharge'  => $total,
        ];
    }

    /**
     * Create a new payment entry in the tpw_rsvp_payments table.
     *
     * @param array $args {
     *     @type int    $submission_id
     *     @type int    $guest_id
     *     @type float  $amount
     *     @type string $payment_method
     *     @type string $paid_by
     *     @type string $payment_reference (optional)
     *     @type string $checkout_url (optional)
     *     @type string $notes (optional)
     * }
     * @return array {
     *     @type bool   $success
     *     @type int    $payment_id
     *     @type string $payment_reference
     *     @type string $checkout_url
     *     @type string $error (only if success is false)
     * }
     */
    /**
     * @since 1.0.0
     */
    public static function create_payment($args = []) {
        global $wpdb;

        $defaults = [
            'submission_id'     => 0,
            'guest_id'          => null,
            'amount'            => 0,
            'payment_method'    => '',
            'paid_by'           => '',
            'payment_reference' => '',
            'checkout_url'      => '',
            'notes'             => '',
        ];

        $data = wp_parse_args($args, $defaults);

        // Conditionally include the Square SDK if it's enabled
        $active_methods = get_option('tpw_active_payment_methods', []);
        if (in_array('square', $active_methods, true)) {
            $autoload_path = plugin_dir_path(__FILE__) . '/vendor/autoload.php';
            if (file_exists($autoload_path)) {
                require_once $autoload_path;
            }
        }

        $allow_zero_amount = (isset($data['guest_id']) && !empty($data['guest_id']));

        if (
            empty($data['submission_id']) ||
            $data['payment_method'] === '' ||
            (!isset($data['amount']) && !$allow_zero_amount)
        ) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }

        if (in_array($data['payment_method'], ['bacs', 'cash']) && empty($data['payment_reference'])) {
            $data['payment_reference'] = 'rsvp_' . uniqid();
        }

        // Allow forced creation of new payment record
        $force_new = isset($args['force_new']) && $args['force_new'];

        if (!$force_new) {
            error_log('[TPW DEBUG] create_payment called with: ' . print_r($data, true));
            // Prevent inserting exact duplicate payment
            if (is_null($data['guest_id'])) {
                $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}tpw_rsvp_payments
                        WHERE submission_id = %d
                          AND guest_id IS NULL
                          AND payment_method = %s
                          AND paid_by = %s
                          AND payment_reference = %s";
                error_log('[TPW DEBUG] Preparing SQL: ' . $sql . ' with guest_id=' . intval($data['guest_id']) . ', payment_id=' . $data['payment_reference']);
                $existing = $wpdb->get_var($wpdb->prepare(
                    $sql,
                    $data['submission_id'],
                    $data['payment_method'],
                    $data['paid_by'],
                    $data['payment_reference']
                ));
            } else {
                $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}tpw_rsvp_payments
                        WHERE submission_id = %d
                          AND guest_id = %d
                          AND payment_method = %s
                          AND paid_by = %s
                          AND payment_reference = %s";
                $existing = $wpdb->get_var($wpdb->prepare(
                    $sql,
                    $data['submission_id'],
                    intval($data['guest_id']),
                    $data['payment_method'],
                    $data['paid_by'],
                    $data['payment_reference']
                ));
            }

            if ($existing) {
                return ['success' => false, 'error' => 'Duplicate payment exists'];
            }
        }

        // Apply surcharge for offline methods only (BACS, Cheque, Cash) right before persisting.
        if ( isset($data['payment_method']) && in_array($data['payment_method'], ['bacs','cheque','cash'], true) ) {
            $calc = self::tpw_core_calculate_payable_total( (float) $data['amount'], $data['payment_method'] );
            $data['amount'] = $calc['total_with_surcharge'];
        }

        error_log('[TPW DEBUG] Inserting payment with data: ' . print_r([
            'submission_id'     => $data['submission_id'],
            'guest_id'          => $data['guest_id'],
            'amount'            => $data['amount'],
            'payment_method'    => $data['payment_method'],
            'payment_reference' => $data['payment_reference'],
            'checkout_url'      => $data['checkout_url'],
            'paid_by'           => $data['paid_by'],
            'notes'             => $data['notes']
        ], true));

        $result = $wpdb->insert(
            $wpdb->prefix . 'tpw_rsvp_payments',
            [
                'submission_id'     => $data['submission_id'],
                'guest_id'          => $data['guest_id'],
                'amount'            => $data['amount'],
                'payment_method'    => $data['payment_method'],
                'payment_reference' => $data['payment_reference'],
                'checkout_url'      => $data['checkout_url'],
                'paid_by'           => $data['paid_by'],
                'notes'             => $data['notes'],
                'amount_breakdown'  => $data['amount_breakdown'],
                'confirmed_amount'  => isset($data['confirmed_amount']) ? $data['confirmed_amount'] : null,
                'paid_at'           => isset($data['paid_at']) ? $data['paid_at'] : null,
                'created_at'        => current_time('mysql'),
            ],
            ['%d','%d','%f','%s','%s','%s','%s','%s','%s','%f','%s','%s']
        );

        if ($result === false) {
            return ['success' => false, 'error' => $wpdb->last_error];
        }

        return [
            'success'        => true,
            'payment_id'     => $wpdb->insert_id,
            'payment_reference' => $data['payment_reference'],
            'checkout_url'   => $data['checkout_url'],
        ];
    }
}