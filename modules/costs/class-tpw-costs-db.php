<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class TPW_Costs_DB {

    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tpw_event_costs';
        $charset_collate = $wpdb->get_charset_collate();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            cost_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT(20) UNSIGNED NOT NULL,
            meeting_cost DECIMAL(10,2) DEFAULT NULL,
            dining_cost DECIMAL(10,2) DEFAULT NULL,
            has_dining TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (cost_id),
            KEY event_id (event_id)
        ) $charset_collate;";

        dbDelta($sql);
    }
}