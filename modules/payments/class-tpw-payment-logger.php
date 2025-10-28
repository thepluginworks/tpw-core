<?php

/**
 * Payment activity logger.
 *
 * Writes payment gateway activity and internal events to a dedicated table
 * for audit and support. Use the static log() method to append entries.
 *
 * @since 1.0.0
 */
class TPW_Payment_Logger {

    /**
     * Create the payment logs table if missing.
     *
     * Idempotent; safe to call on activation or upgrades.
     *
     * @since 1.0.0
     * @return void
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tpw_payment_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            method VARCHAR(50) NOT NULL,
            reference VARCHAR(100),
            status VARCHAR(50),
            message TEXT,
            payload LONGTEXT,
            plugin VARCHAR(50),
            plugin_payment_id BIGINT,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Append an entry to the payment logs table.
     *
     * @since 1.0.0
     * @param string     $method             Payment method or integration key (e.g., 'bacs','sumup').
     * @param string     $status             Status label (e.g., 'success','error').
     * @param string     $message            Short human‑readable message.
     * @param array|null $payload            Optional context array; JSON encoded.
     * @param string     $reference          External reference or internal token.
     * @param string     $plugin             Owning plugin or scope label.
     * @param int|null   $plugin_payment_id  Optional related payment id in plugin scope.
     * @return void
     */
    public static function log($method, $status, $message, $payload = null, $reference = '', $plugin = '', $plugin_payment_id = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tpw_payment_logs';

        $wpdb->insert(
            $table_name,
            [
                'method'             => sanitize_text_field($method),
                'reference'          => sanitize_text_field($reference),
                'status'             => sanitize_text_field($status),
                'message'            => sanitize_text_field($message),
                'payload'            => is_array($payload) ? wp_json_encode($payload) : $payload,
                'plugin'             => sanitize_text_field($plugin),
                'plugin_payment_id'  => $plugin_payment_id,
                'created_at'         => current_time('mysql'),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );
    }
}
