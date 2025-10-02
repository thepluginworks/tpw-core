<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class TPW_Email_Form {
    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
        add_action( 'wp_ajax_tpw_email_send', [ __CLASS__, 'handle_send' ] );
        add_action( 'wp_ajax_nopriv_tpw_email_send', [ __CLASS__, 'handle_send' ] );
    }

    public static function register_assets() {
        $base = plugins_url( '', TPW_CORE_PATH . 'tpw-core.php' ); // not used directly; we will use plugins_url with __FILE__ analogues
        wp_register_style( 'tpw-email-css', plugins_url( 'assets/email.css', __FILE__ ), [], filemtime( plugin_dir_path(__FILE__) . 'assets/email.css' ) );
        wp_register_script( 'tpw-email-js', plugins_url( 'assets/email.js', __FILE__ ), [ 'jquery' ], filemtime( plugin_dir_path(__FILE__) . 'assets/email.js' ), true );
        $policy_max = 5 * 1024 * 1024; // 5MB policy cap
        $server_max = (int) wp_max_upload_size();
        $max_bytes  = ( $server_max > 0 ) ? min( $policy_max, $server_max ) : $policy_max;
        wp_localize_script( 'tpw-email-js', 'TPW_EMAIL', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'tpw_email_send' ),
            'maxBytes'=> $max_bytes,
            'i18n'    => [
                'sending' => __( 'Sending…', 'tpw-core' ),
                'send'    => __( 'Send', 'tpw-core' ),
            ],
        ] );
    }

    public static function render( $args = [] ) {
        // Ensure assets available
        wp_enqueue_style( 'tpw-email-css' );
        wp_enqueue_script( 'tpw-email-js' );

        $policy_max = 5 * 1024 * 1024; // 5MB policy cap
        $server_max = (int) wp_max_upload_size();
        $max_bytes  = ( $server_max > 0 ) ? min( $policy_max, $server_max ) : $policy_max;

        $defaults = [
            'recipient_name'  => '',
            'recipient_email' => '',
            'from_name'       => '',
            'from_email'      => '',
            'subject'         => '',
            'message'         => '',
            'modal_id'        => 'tpw-email-generic-modal',
            'plugin_slug'     => 'tpw-core',
            'send_copy'       => true,
            'from_readonly'   => false,
            'max_bytes'       => $max_bytes,
        ];
        $data = wp_parse_args( $args, $defaults );

        // Nonce for CSRF protection
        $nonce = wp_create_nonce( 'tpw_email_send' );

        ob_start();
        include plugin_dir_path( __FILE__ ) . 'templates/email-form.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler: validate inputs and call TPW_Email::send_email
     */
    public static function handle_send() {
        // Basic perms: require logged-in user by default; filter can relax
        $require_login = apply_filters( 'tpw_email/require_login', true );
        if ( $require_login && ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Access denied.', 'tpw-core' ) ], 403 );
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field( $_POST['_wpnonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, 'tpw_email_send' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'tpw-core' ) ], 403 );
        }

        $to_name    = isset($_POST['recipient_name'])  ? sanitize_text_field( wp_unslash($_POST['recipient_name']) )  : '';
        $to_email   = isset($_POST['recipient_email']) ? sanitize_email( wp_unslash($_POST['recipient_email']) )      : '';
        $from_name  = isset($_POST['from_name'])       ? sanitize_text_field( wp_unslash($_POST['from_name']) )       : '';
        $from_email = isset($_POST['from_email'])      ? sanitize_email( wp_unslash($_POST['from_email']) )           : '';
        $subject    = isset($_POST['subject'])         ? sanitize_text_field( wp_unslash($_POST['subject']) )         : '';
        $message    = isset($_POST['message'])         ? wp_kses_post( wp_unslash($_POST['message']) )                : '';
        $send_copy  = isset($_POST['send_copy'])       ? (bool) $_POST['send_copy'] : true;

        if ( empty($to_name) || empty($to_email) || empty($from_name) || empty($from_email) ) {
            wp_send_json_error( [ 'message' => __( 'Missing required fields.', 'tpw-core' ) ], 400 );
        }

        $attachments = [];
        if ( ! empty( $_FILES['attachments'] ) ) {
            $validation = TPW_Email::validate_attachments( $_FILES['attachments'] );
            if ( ! $validation['success'] ) {
                wp_send_json_error( [ 'message' => $validation['message'] ], 400 );
            }
            $attachments = $validation['paths'];
        }

        $result = TPW_Email::send_email( $to_email, [ 'name' => $from_name, 'email' => $from_email ], $subject, $message, $attachments, $send_copy );
        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['message'] ], 500 );
        }

        wp_send_json_success( [ 'message' => __( 'Email sent.', 'tpw-core' ) ] );
    }
}
