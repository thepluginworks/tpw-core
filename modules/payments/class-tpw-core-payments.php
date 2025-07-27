<?php

class TPW_Core_Payments {

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
                'created_at'        => current_time('mysql'),
            ],
            ['%d','%d','%f','%s','%s','%s','%s','%s','%s']
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