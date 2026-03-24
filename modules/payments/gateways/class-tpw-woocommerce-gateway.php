<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;

class TPW_Gateway_WooCommerce {

    public static function create_order($submission_id, $amount, $payment_id = null) {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_missing', 'WooCommerce is not active.');
        }

        if (!session_id()) {
            session_start();
        }

        if (class_exists('WC_Order_Attribution')) {
            WC()->session->set('_order_attribution_source', 'RSVP Checkout');
            WC()->session->set('_order_attribution_medium', 'direct');
            WC()->session->set('_order_attribution_campaign', 'tpw-rsvp-lodge');
            WC()->session->set('_order_attribution_referrer', site_url('/rsvp-checkout/'));
        }

        // Get or create the RSVP Payment product
        $product_id = get_option('tpw_rsvp_wc_product_id');
        if (!$product_id || !get_post($product_id)) {
            $product = new WC_Product_Simple();
            $product->set_name('RSVP Payment');
            $product->set_regular_price(0);
            $product->set_price(0);
            $product->set_virtual(true);
            $product->set_catalog_visibility('hidden');
            $product->save();
            $product_id = $product->get_id();
            update_option('tpw_rsvp_wc_product_id', $product_id);
        }

        $current_user_id = get_current_user_id();

        $order_args = [
            'status'      => 'pending',
            'created_via' => 'tpw_rsvp',
        ];

        if ($current_user_id) {
            $order_args['customer_id'] = $current_user_id;
        }

        // Create WooCommerce order
        if (class_exists(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)) {
            if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                $order_args['type'] = 'shop_order';
            }

            $order = wc_create_order($order_args);
        } else {
            $order = wc_create_order($order_args);
        }

        if (!session_id()) {
            session_start();
        }
        $origin_plugin = $_SESSION['tpw_origin_plugin'] ?? $_POST['tpw_origin_plugin'] ?? '';
        unset($_SESSION['tpw_origin_plugin']);

        if ($origin_plugin === 'tpw-rsvp-lodge-meetings') {
            $amount = isset($_POST['data_additional_payment']) ? floatval($_POST['data_additional_payment']) : floatval($amount);
        } else {
            $amount = floatval($amount);
        }
        // Apply unified surcharge after all external overrides and before fee creation
        if (class_exists('TPW_Core_Payments')) {
            $calc = TPW_Core_Payments::tpw_core_calculate_payable_total($amount, 'woocommerce');
            $amount_with_surcharge = (float) $calc['total_with_surcharge'];
        } else {
            $amount_with_surcharge = $amount;
        }

    $fee = new WC_Order_Item_Fee();
        $fee->set_name('RSVP Payment');
    $fee->set_amount($amount_with_surcharge);
    $fee->set_total($amount_with_surcharge);
        $fee->set_tax_status('none'); // Ensure no tax blocking
        $order->add_item($fee);

        // Store RSVP-related metadata
        $order->update_meta_data('tpw_submission_id', $submission_id);
        // Use RSVP Payment ID from argument or session if available
        $tpw_payment_id = $payment_id ?? ($_SESSION['tpw_rsvp_payment_id'] ?? null);
        if ($tpw_payment_id !== null) {
            $order->update_meta_data('tpw_rsvp_payment_id', intval($tpw_payment_id));
            unset($_SESSION['tpw_rsvp_payment_id']);
        }
    $order->update_meta_data('tpw_payment_method', 'woocommerce');
    // Store surcharge-inclusive amount for confirmation consistency
    $order->update_meta_data('tpw_rsvp_amount', $amount_with_surcharge);

        if ($origin_plugin === 'tpw-rsvp-lodge-meetings') {
            $order->update_meta_data('_order_attribution_source', 'RSVP Checkout');
            $order->update_meta_data('_order_attribution_medium', 'direct');
            $order->update_meta_data('_order_attribution_campaign', 'tpw-rsvp-lodge');
            $order->update_meta_data('_order_attribution_referrer', site_url('/rsvp-checkout/'));
        } else {
            $order->update_meta_data('_order_attribution_source', $origin_plugin ?: 'RSVP Checkout');
        }

        // Add TPW plugin order note for admin visibility
        $note_lines = [];
        $note_lines[] = 'TPW Plugin Order Created';
        $note_lines[] = 'Submission ID: ' . $submission_id;
        $note_lines[] = 'Payment ID: ' . ($tpw_payment_id ?? '—');
        $note_lines[] = 'Source: ' . ($origin_plugin ?: 'Unknown');
        $order->add_order_note(implode("\n", $note_lines), false, true);

        $order->calculate_totals();
        $order->save();

        return $order->get_checkout_payment_url();
    }
    public static function maybe_mark_rsvp_payment_as_confirmed($order_id) {
        $order = wc_get_order($order_id);
        $member_amount = floatval($order->get_meta('tpw_rsvp_amount'));
        if (!$order) return;

        $campaign = $order->get_meta('_order_attribution_campaign');
        if ($campaign !== 'tpw-rsvp-lodge') {
            return;
        }

        $payment_id = $order->get_meta('tpw_rsvp_payment_id');
        if (!$payment_id) {
            return;
        }

        global $wpdb;

        // Confirm the member payment
        if ($member_amount > 0) {
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}tpw_rsvp_payments
                SET confirmed_amount = %f,
                    paid_at = %s
                WHERE id = %d
            ", $member_amount, current_time('mysql'), $payment_id));
        }

        // Confirm guest payments linked to this payment ID
        $wpdb->query($wpdb->prepare("
            UPDATE {$wpdb->prefix}tpw_rsvp_payment_guests
            SET confirmed_amount = amount,
                paid_at = NOW()
            WHERE payment_id = %d
        ", $payment_id));
    }
}

add_action('woocommerce_order_status_completed', ['TPW_Gateway_WooCommerce', 'maybe_mark_rsvp_payment_as_confirmed']);
add_action('woocommerce_order_status_processing', ['TPW_Gateway_WooCommerce', 'maybe_mark_rsvp_payment_as_confirmed']);

// Ensure order attribution appears in WooCommerce UI for RSVP-created orders
add_filter('woocommerce_attribution_data', function($attribution, $order) {
    if (!$order instanceof WC_Order) {
        return $attribution;
    }

    // Only apply if created via our RSVP plugin
    if ($order->get_meta('tpw_payment_method') === 'woocommerce' &&
        $order->get_meta('_order_attribution_source') === 'RSVP Checkout') {

        $attribution['source']   = $order->get_meta('_order_attribution_source');
        $attribution['medium']   = $order->get_meta('_order_attribution_medium');
        $attribution['campaign'] = $order->get_meta('_order_attribution_campaign');
        $attribution['referrer'] = $order->get_meta('_order_attribution_referrer');
    }

    return $attribution;
}, 10, 2);


// Suppress WooCommerce default emails for RSVP plugin orders
foreach ([
    'woocommerce_email_enabled_customer_processing_order',
    'woocommerce_email_enabled_customer_completed_order',
    'woocommerce_email_enabled_new_order'
] as $hook) {
    add_filter($hook, function ($enabled, $order) {
        if ($order && $order->get_meta('_order_attribution_campaign') === 'tpw-rsvp-lodge') {
            return false;
        }
        return $enabled;
    }, 10, 2);
}

// Store redirect parameters in Woo session when order status changes for RSVP orders
add_action('woocommerce_order_status_changed', function ($order_id, $from_status, $to_status) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    // Only process for relevant RSVP orders
    if ($order->get_meta('_order_attribution_campaign') === 'tpw-rsvp-lodge') {
        $submission_id = $order->get_meta('tpw_submission_id');
        $payment_id    = $order->get_meta('tpw_rsvp_payment_id');

        WC()->session->set('tpw_redirect_after_payment', [
            'submission_id' => $submission_id,
            'payment_id'    => $payment_id,
        ]);
    }
}, 10, 3);

