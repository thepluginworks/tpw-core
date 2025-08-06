<?php

class TPW_Members_DB {
    public static function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Members table
        $table_name = $wpdb->prefix . 'tpw_members';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            society_id INT UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED UNIQUE,

            first_name VARCHAR(100),
            surname VARCHAR(100),
            initials VARCHAR(10),
            title VARCHAR(20),
            decoration VARCHAR(50),

            email VARCHAR(255),
            mobile VARCHAR(30),
            landline VARCHAR(30),

            address1 VARCHAR(255),
            address2 VARCHAR(255),
            town VARCHAR(100),
            county VARCHAR(100),
            postcode VARCHAR(20),
            country VARCHAR(100),

            date_joined DATE NULL,
            status VARCHAR(50),
            is_committee TINYINT(1) DEFAULT 0,
            is_match_manager TINYINT(1) DEFAULT 0,
            is_admin TINYINT(1) DEFAULT 0,
            is_noticeboard_admin TINYINT(1) DEFAULT 0,

            username VARCHAR(100),
            password_hash VARCHAR(255),

            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX (society_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Field settings table
        $table_name_settings = $wpdb->prefix . 'tpw_field_settings';
        $sql_settings = "CREATE TABLE IF NOT EXISTS $table_name_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            society_id INT UNSIGNED NOT NULL,
            field_key VARCHAR(100) NOT NULL,
            is_enabled TINYINT(1) DEFAULT 1,
            custom_label VARCHAR(255) DEFAULT NULL,
            UNIQUE (society_id, field_key)
        ) $charset_collate;";
        dbDelta($sql_settings);

        // Member meta table
        $table_name_meta = $wpdb->prefix . 'tpw_member_meta';
        $sql_meta = "CREATE TABLE IF NOT EXISTS $table_name_meta (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            member_id INT UNSIGNED NOT NULL,
            meta_key VARCHAR(100) NOT NULL,
            meta_value TEXT,
            INDEX (member_id),
            FOREIGN KEY (member_id) REFERENCES {$wpdb->prefix}tpw_members(id) ON DELETE CASCADE
        ) $charset_collate;";
        dbDelta($sql_meta);
    }
}