<?php

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class TPW_WooCommerce_Display {

    public static function init() {
        // Display custom Thank You page content for RSVP Lodge orders
        add_action('woocommerce_thankyou', [__CLASS__, 'maybe_show_custom_thank_you'], 20);

        // Override Woo email content for RSVP Lodge orders
        add_filter('woocommerce_email_order_details', [__CLASS__, 'maybe_show_custom_email_content'], 10, 4);
    }

    public static function maybe_show_custom_thank_you($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_order_attribution_campaign') !== 'tpw-rsvp-lodge') return;

        $submission_id = $order->get_meta('tpw_submission_id');
        $payment_id = $order->get_meta('tpw_rsvp_payment_id');
        global $wpdb;

        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tpw_rsvp_submissions WHERE id = %d", $submission_id));
        $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tpw_rsvp_payments WHERE id = %d", $payment_id));
        $guests = $wpdb->get_results($wpdb->prepare("SELECT g.*, pg.amount, pg.guest_dining FROM {$wpdb->prefix}tpw_rsvp_payment_guests pg INNER JOIN {$wpdb->prefix}tpw_rsvp_guests g ON pg.guest_id = g.id WHERE pg.payment_id = %d", $payment_id));

        // Calculate totals
        $member_amount = $payment ? (float) $payment->amount : 0.0;
        $guest_amounts = $wpdb->get_col($wpdb->prepare("SELECT amount FROM {$wpdb->prefix}tpw_rsvp_payment_guests WHERE payment_id = %d", $payment_id));
        $guest_total = array_sum(array_map('floatval', $guest_amounts));
        $total_paid = $member_amount + $guest_total;

        $currency_symbol = function_exists( 'tpw_core_get_currency_symbol' ) ? tpw_core_get_currency_symbol() : '£';

        echo '<div class="tpw-rsvp-thankyou">';
        echo '<h2>Thank you for your RSVP!</h2>';
        echo '<p><strong>Submission ID:</strong> ' . esc_html($submission_id) . '</p>';
        error_log('TPW: Checking for payment ID ' . $payment_id . ', Payment result: ' . print_r($payment, true));

        if ($payment) {
            echo '<p><strong>Total Amount Paid:</strong> ' . esc_html( $currency_symbol . number_format($total_paid, 2) ) . '</p>';
            echo '<p><strong>Payment Method:</strong> ' . esc_html(ucfirst($payment->payment_method)) . '</p>';
            echo '<p><strong>Payment Reference:</strong> ' . esc_html($payment->payment_reference) . '</p>';
        } else {
            echo '<p><strong>Payment record not found.</strong></p>';
        }
        echo '<p><strong>Dining:</strong> ' . ($submission->dining ? 'Yes' : 'No') . '</p>';

        if (!empty($submission->dietary)) {
            echo '<p><strong>Dietary Requirements:</strong> ' . esc_html($submission->dietary) . '</p>';
        }
        if (!empty($submission->seating)) {
            echo '<p><strong>Seating Preferences:</strong> ' . esc_html($submission->seating) . '</p>';
        }

        // Member Meal Choices
        if (!empty($submission->meal_choices)) {
            $meals = json_decode($submission->meal_choices, true);
            if (is_array($meals)) {
                echo '<h3>Member Meal Choices</h3><ul>';
                foreach ($meals as $course => $choice) {
                    echo '<li>' . esc_html(ucfirst($course)) . ': ' . esc_html($choice) . '</li>';
                }
                echo '</ul>';
            }
        }

        // Guests
        if (!empty($guests)) {
            echo '<h3>Guest Information</h3>';
            foreach ($guests as $guest) {
                echo '<p><strong>Name:</strong> ' . esc_html("{$guest->title} {$guest->first_name} {$guest->surname}") . '</p>';
                echo '<p><strong>Dining:</strong> ' . ($guest->guest_dining ? 'Yes' : 'No') . '</p>';
                echo '<p><strong>Amount:</strong> ' . esc_html( $currency_symbol . number_format($guest->amount, 2) ) . '</p>';
                if (!empty($guest->dietary)) {
                    echo '<p><strong>Dietary:</strong> ' . esc_html($guest->dietary) . '</p>';
                }
                if (!empty($guest->meal_choices)) {
                    $meals = json_decode($guest->meal_choices, true);
                    if (is_array($meals)) {
                        echo '<ul>';
                        foreach ($meals as $course => $choice) {
                            echo '<li>' . esc_html(ucfirst($course)) . ': ' . esc_html($choice) . '</li>';
                        }
                        echo '</ul>';
                    }
                }
                echo '<hr>';
            }
        }

        echo '</div>';
    }

    public static function maybe_show_custom_email_content($order, $sent_to_admin, $plain_text, $email) {
        if ($sent_to_admin || $plain_text || $order->get_meta('_order_attribution_campaign') !== 'tpw-rsvp-lodge') return;

        ob_start();
        self::maybe_show_custom_thank_you($order->get_id());
        echo ob_get_clean();
    }
}

if (class_exists('WooCommerce') && did_action('woocommerce_init')) {
    TPW_WooCommerce_Display::init();
} else {
    add_action('woocommerce_init', ['TPW_WooCommerce_Display', 'init']);
}