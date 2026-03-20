<?php

class TPW_Core_Checkout_Handler {

    public static function handle_checkout() {
        if ( ! isset($_GET['submission_id']) || ! isset($_GET['payment_method']) ) {
            wp_die('Missing required parameters.');
        }

        $submission_id = absint($_GET['submission_id']);
        $payment_method = sanitize_text_field($_GET['payment_method']);

        if ( class_exists( 'TPW_Payments_Manager' ) && method_exists( 'TPW_Payments_Manager', 'is_method_available' ) ) {
            if ( ! TPW_Payments_Manager::is_method_available( $payment_method ) ) {
                if ( 'square' === $payment_method ) {
                    wp_die(
                        esc_html__( 'Square payments are not available because the TPW Square Gateway add-on is not active. Please choose another payment method.', 'tpw-core' ),
                        esc_html__( 'Square Unavailable', 'tpw-core' ),
                        array( 'response' => 503 )
                    );
                }

                wp_die(
                    esc_html__( 'The selected payment method is not currently available. Please choose another payment method.', 'tpw-core' ),
                    esc_html__( 'Payment Method Unavailable', 'tpw-core' ),
                    array( 'response' => 400 )
                );
            }
        }

        if ( $payment_method === 'woocommerce' ) {
            if ( ! class_exists('TPW_Gateway_WooCommerce') ) {
                require_once plugin_dir_path(__FILE__) . 'gateways/class-tpw-woocommerce-gateway.php';
            }

            $amount = self::get_submission_total_amount($submission_id);

            $checkout_url = TPW_Gateway_WooCommerce::create_order($submission_id, $amount);

            if ( is_wp_error($checkout_url) ) {
                wp_die('Unable to create WooCommerce order: ' . $checkout_url->get_error_message());
            }

            wp_redirect($checkout_url);
            exit;
        }

        // Add fallback logic for other methods as needed.
        wp_die('Unsupported payment method.');
    }

    private static function get_submission_total_amount($submission_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'tpw_rsvp_submissions';
        $amount = $wpdb->get_var($wpdb->prepare("SELECT total_cost FROM $table WHERE id = %d", $submission_id));

        return $amount ? floatval($amount) : 0.00;
    }
}
