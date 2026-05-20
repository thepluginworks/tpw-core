<?php

class TPW_Payment_Logs_Admin {
    const CLEAR_NONCE_ACTION = 'tpw_clear_logs_action';
    const CLEAR_NONCE_FIELD  = 'tpw_payment_logs_nonce';
    const CLEAR_ACTION       = 'tpw_payment_clear_logs';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action( 'admin_post_' . self::CLEAR_ACTION, [ __CLASS__, 'handle_clear_logs' ] );
    }

    public static function add_menu_page() {
        add_submenu_page(
            'tools.php',
            'Payment Logs',
            'Payment Logs',
            'manage_options',
            'tpw-payment-logs',
            [__CLASS__, 'render_logs_page']
        );
    }

    public static function render_logs_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'tpw-core' ) );
        }

        $logs = self::get_page( 1, 100 );

        echo '<div class="tpw-admin-ui"><div class="wrap">';
        self::render_request_notice();
        echo '<h1>' . esc_html__( 'TPW Payment Logs', 'tpw-core' ) . '</h1>';
        self::render_clear_form();
        self::render_logs_table( $logs );
        echo '</div></div>';
    }

    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'tpw_payment_logs';
    }

    public static function count_all() {
        global $wpdb;

        $table_name = self::get_table_name();

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    }

    public static function get_page( $page = 1, $per_page = 20 ) {
        global $wpdb;

        $page       = max( 1, (int) $page );
        $per_page   = max( 1, min( 100, (int) $per_page ) );
        $offset     = ( $page - 1 ) * $per_page;
        $table_name = self::get_table_name();
        $query      = $wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        return $wpdb->get_results( $query );
    }

    public static function clear_all() {
        global $wpdb;

        $table_name = self::get_table_name();

        return false !== $wpdb->query( "TRUNCATE TABLE {$table_name}" );
    }

    public static function current_user_can_clear_logs() {
        return current_user_can( 'manage_options' );
    }

    public static function handle_clear_logs() {
        if ( ! self::current_user_can_clear_logs() ) {
            wp_die( esc_html__( 'Access denied.', 'tpw-core' ) );
        }

        check_admin_referer( self::CLEAR_NONCE_ACTION, self::CLEAR_NONCE_FIELD );

        $notice = self::clear_all() ? 'cleared' : 'failed';
        $url    = add_query_arg(
            'tpw_payment_logs_notice',
            $notice,
            self::get_clear_redirect_url()
        );

        wp_safe_redirect( $url );
        exit;
    }

    public static function render_clear_form( $redirect_url = '' ) {
        if ( ! self::current_user_can_clear_logs() ) {
            return;
        }

        $redirect_url = is_string( $redirect_url ) && '' !== $redirect_url ? $redirect_url : self::get_default_page_url();

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom: 1em;">';
        wp_nonce_field( self::CLEAR_NONCE_ACTION, self::CLEAR_NONCE_FIELD );
        echo '<input type="hidden" name="action" value="' . esc_attr( self::CLEAR_ACTION ) . '" />';
        echo '<input type="hidden" name="tpw_payment_logs_redirect" value="' . esc_url( $redirect_url ) . '" />';
        echo '<input type="submit" class="button button-danger" value="' . esc_attr__( 'Clear All Logs', 'tpw-core' ) . '" onclick="return confirm(\'Are you sure you want to delete all payment logs?\');" />';
        echo '</form>';
    }

    public static function render_logs_table( $logs ) {
        $logs = is_array( $logs ) ? $logs : [];

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__( 'Date', 'tpw-core' ) . '</th><th>' . esc_html__( 'Method', 'tpw-core' ) . '</th><th>' . esc_html__( 'Reference', 'tpw-core' ) . '</th><th>' . esc_html__( 'Status', 'tpw-core' ) . '</th><th>' . esc_html__( 'Message', 'tpw-core' ) . '</th></tr></thead>';
        echo '<tbody>';

        if ( empty( $logs ) ) {
            echo '<tr><td colspan="5">' . esc_html__( 'No payment log entries found.', 'tpw-core' ) . '</td></tr>';
        } else {
            foreach ( $logs as $log ) {
                echo '<tr>';
                echo '<td>' . esc_html( isset( $log->created_at ) ? (string) $log->created_at : '' ) . '</td>';
                echo '<td>' . esc_html( isset( $log->method ) ? (string) $log->method : '' ) . '</td>';
                echo '<td>' . esc_html( isset( $log->reference ) ? (string) $log->reference : '' ) . '</td>';
                echo '<td>' . esc_html( isset( $log->status ) ? (string) $log->status : '' ) . '</td>';
                echo '<td>' . esc_html( isset( $log->message ) ? (string) $log->message : '' ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    protected static function render_request_notice() {
        $notice = isset( $_GET['tpw_payment_logs_notice'] ) ? sanitize_key( wp_unslash( $_GET['tpw_payment_logs_notice'] ) ) : '';

        if ( 'cleared' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All payment logs have been cleared.', 'tpw-core' ) . '</p></div>';
        } elseif ( 'failed' === $notice ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Payment logs could not be cleared.', 'tpw-core' ) . '</p></div>';
        }
    }

    protected static function get_default_page_url() {
        return admin_url( 'tools.php?page=tpw-payment-logs' );
    }

    protected static function get_clear_redirect_url() {
        $redirect_url = isset( $_POST['tpw_payment_logs_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['tpw_payment_logs_redirect'] ) ) : '';

        if ( '' !== $redirect_url ) {
            $validated = wp_validate_redirect( $redirect_url, '' );

            if ( is_string( $validated ) && '' !== $validated ) {
                return remove_query_arg( 'tpw_payment_logs_notice', $validated );
            }
        }

        return self::get_default_page_url();
    }
}
