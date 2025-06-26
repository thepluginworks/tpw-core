<?php

class TPW_Guests_Table {

    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tpw_rsvp_guests';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            submission_id INT NOT NULL,
            title VARCHAR(20),
            first_name VARCHAR(100),
            surname VARCHAR(100),
            is_dining VARCHAR(10),
            dietary TEXT,
            seating_preferences TEXT,
            meal_choices LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY submission_id (submission_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
