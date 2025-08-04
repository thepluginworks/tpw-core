<?php

add_action('woocommerce_payment_complete', 'tpw_core_on_wc_payment_complete');

add_filter('woocommerce_email_enabled_customer_on_hold_order', 'tpw_core_disable_woo_emails_for_rsvp_orders', 10, 2);
add_filter('woocommerce_email_enabled_customer_processing_order', 'tpw_core_disable_woo_emails_for_rsvp_orders', 10, 2);
add_action('woocommerce_thankyou', 'tpw_core_redirect_rsvp_orders_to_confirmation', 1);

function tpw_core_on_wc_payment_complete($order_id) {
    $order = wc_get_order($order_id);

    if (! $order instanceof WC_Order) {
        return;
    }

    $submission_id = $order->get_meta('tpw_submission_id');
    $payment_method = $order->get_meta('tpw_payment_method');

    if ($payment_method !== 'woocommerce' || ! $submission_id) {
        return;
    }

    global $wpdb;
    $payment_id = $order->get_meta('tpw_rsvp_payment_id');

    if ($payment_id) {
        // Get the payment row for amount
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tpw_rsvp_payments WHERE id = %d",
            $payment_id
        ));

        if ($payment) {
            // Confirm the member payment for its amount only
            $wpdb->update(
                "{$wpdb->prefix}tpw_rsvp_payments",
                [
                    'confirmed_amount'  => $payment->amount,
                    'paid_at'           => current_time('mysql'),
                    'payment_reference' => 'Woo Order #' . $order_id
                ],
                ['id' => $payment_id]
            );

            // Confirm each guest payment linked to this payment
            $guest_payments = $wpdb->get_results($wpdb->prepare(
                "SELECT id, amount FROM {$wpdb->prefix}tpw_rsvp_payment_guests WHERE payment_id = %d",
                $payment_id
            ));

            foreach ($guest_payments as $gp) {
                $wpdb->update(
                    "{$wpdb->prefix}tpw_rsvp_payment_guests",
                    [
                        'confirmed_amount' => $gp->amount,
                        'paid_at'          => current_time('mysql')
                    ],
                    ['id' => $gp->id]
                );
            }
        }
    }

    // Optional: log it
    if (class_exists('TPW_Payment_Logger')) {
        TPW_Payment_Logger::log('woocommerce', 'success', 'Payment confirmed via WooCommerce', [
            'order_id' => $order_id,
            'submission_id' => $submission_id,
            'payment_id' => $payment_id,
        ]);
    }

    $origin_plugin = $order->get_meta('tpw_origin_plugin');

    if ($origin_plugin === 'tpw-rsvp-lodge-meetings' && function_exists('tpw_rsvp_lodge_send_confirmation_email')) {
        tpw_rsvp_lodge_send_confirmation_email($submission_id, $payment_id);
    }
}

function tpw_core_disable_woo_emails_for_rsvp_orders($enabled, $order) {
    $origin = $order->get_meta('_order_attribution_source');
    if ($origin === 'tpw-rsvp-lodge-meetings') {
        return false;
    }
    return $enabled;
}

function tpw_core_redirect_rsvp_orders_to_confirmation($order_id) {
    $order = wc_get_order($order_id);
    $origin = $order->get_meta('_order_attribution_source');
    $submission_id = $order->get_meta('tpw_submission_id');
    $payment_id = $order->get_meta('tpw_rsvp_payment_id');

    if ($origin === 'tpw-rsvp-lodge-meetings' && $submission_id && $payment_id) {
        if (function_exists('tpw_rsvp_lodge_send_confirmation_email')) {
            tpw_rsvp_lodge_send_confirmation_email($submission_id, $payment_id);
        }

        wp_safe_redirect(site_url('/rsvp-confirmation/?submission_id=' . $submission_id . '&payment_id=' . $payment_id));
        exit;
    }
}