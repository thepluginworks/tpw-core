<?php

class TPW_Members_DB {
    public static function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Members table
        $table_name = $wpdb->prefix . 'tpw_members';
        $sql = "CREATE TABLE $table_name (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            society_id INT UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED,

            first_name VARCHAR(100),
            surname VARCHAR(100),
            initials VARCHAR(10),
            title VARCHAR(20),
            decoration VARCHAR(50),

            email VARCHAR(255),
            mobile VARCHAR(30),
            landline VARCHAR(30),
            member_photo VARCHAR(255),

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

            PRIMARY KEY  (id),
            UNIQUE KEY user_id_unique (user_id),
            KEY society_id (society_id)
        ) $charset_collate;";
        // Avoid running dbDelta on an existing members table to prevent parser edge-cases on some environments
        $exists_members = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
        if ( $exists_members !== $table_name ) {
            dbDelta($sql);
        }

        // Safety net: ensure the member_photo column exists even if dbDelta skipped it
        $has_member_photo = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'member_photo'",
            $table_name
        ) );
        if ( ! $has_member_photo ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN member_photo VARCHAR(255) NULL AFTER landline" );
        }

        // Field settings table
        $sql_settings = "CREATE TABLE {$wpdb->prefix}tpw_field_settings (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            society_id INT(11) UNSIGNED NOT NULL,
            field_key VARCHAR(100) NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            custom_label VARCHAR(255) DEFAULT NULL,
            field_type VARCHAR(50) DEFAULT 'text',
            basic_search TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT(11) UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY society_field (society_id, field_key),
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_settings);

        // Safety net: ensure the basic_search column exists on field settings table for upgrades
        $fs_table = $wpdb->prefix . 'tpw_field_settings';
        $has_basic_search = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'basic_search'",
            $fs_table
        ) );
        if ( ! $has_basic_search ) {
            // Silent fail on error to avoid fatal during activation on constrained environments
            $wpdb->query( "ALTER TABLE {$fs_table} ADD COLUMN basic_search TINYINT(1) NOT NULL DEFAULT 0 AFTER field_type" );
        }

        // New dependency column (one-level parent) – minimal, nullable.
        $has_depends_on = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'depends_on'",
            $fs_table
        ) );
        if ( ! $has_depends_on ) {
            $wpdb->query( "ALTER TABLE {$fs_table} ADD COLUMN depends_on VARCHAR(100) NULL AFTER basic_search" );
        }

        // New table: member field visibility per group
        $sql_visibility = "CREATE TABLE {$wpdb->prefix}tpw_member_field_visibility (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            field_key VARCHAR(100) NOT NULL,
            `group` VARCHAR(100) NOT NULL,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY field_key_idx (field_key),
            KEY group_idx (`group`),
            UNIQUE KEY field_group (field_key, `group`)
        ) $charset_collate;";
        dbDelta($sql_visibility);

        // Member meta table
        $table_name_meta = $wpdb->prefix . 'tpw_member_meta';
        $sql_meta = "CREATE TABLE $table_name_meta (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id INT UNSIGNED NOT NULL,
            meta_key VARCHAR(100) NOT NULL,
            meta_value TEXT,
            PRIMARY KEY (id),
            KEY member_id_idx (member_id)
        ) $charset_collate;";
        dbDelta($sql_meta);

        // Optional performance index for future dependency lookups on meta table (meta_key + meta_value)
        $has_meta_kv_index = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = 'meta_key_value'",
            $table_name_meta
        ) );
        if ( ! $has_meta_kv_index ) {
            // Best-effort – ignore errors silently
            $wpdb->query( "ALTER TABLE {$table_name_meta} ADD INDEX meta_key_value (meta_key, meta_value(50))" );
        }
    }
}