<?php

class TPW_Payment_Logs_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
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
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_payment_logs';

        if (current_user_can('administrator')) {
            if (isset($_POST['tpw_clear_logs']) && check_admin_referer('tpw_clear_logs_action')) {
                $wpdb->query("TRUNCATE TABLE $table_name");
                echo '<div class="notice notice-success is-dismissible"><p>All payment logs have been cleared.</p></div>';
            }

            echo '<form method="post" style="margin-bottom: 1em;">';
            wp_nonce_field('tpw_clear_logs_action');
            echo '<input type="submit" name="tpw_clear_logs" class="button button-danger" value="Clear All Logs" onclick="return confirm(\'Are you sure you want to delete all payment logs?\');" />';
            echo '</form>';
        }

        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100");

        echo '<div class="wrap">';
        echo '<h1>TPW Payment Logs</h1>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Status</th><th>Message</th></tr></thead>';
        echo '<tbody>';
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log->created_at) . '</td>';
            echo '<td>' . esc_html($log->method) . '</td>';
            echo '<td>' . esc_html($log->reference) . '</td>';
            echo '<td>' . esc_html($log->status) . '</td>';
            echo '<td>' . esc_html($log->message) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }
}
