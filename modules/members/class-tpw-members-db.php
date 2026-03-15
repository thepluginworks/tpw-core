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

            dob DATE NULL,
            date_joined DATE NULL,
            status VARCHAR(50),
            is_committee TINYINT(1) DEFAULT 0,
            is_match_manager TINYINT(1) DEFAULT 0,
            is_admin TINYINT(1) DEFAULT 0,
            is_noticeboard_admin TINYINT(1) DEFAULT 0,
            is_gallery_admin TINYINT(1) DEFAULT 0,
            is_manage_members TINYINT(1) DEFAULT 0,
            is_volunteer TINYINT(1) DEFAULT 0,

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

        self::ensure_members_table_columns( $table_name );

        // New table: members household
        $sql_household = "CREATE TABLE {$wpdb->prefix}tpw_members_household (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            society_id INT(11) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY society_id (society_id)
        ) $charset_collate;";
        dbDelta( $sql_household );

        // New table: members household membership
        $sql_household_member = "CREATE TABLE {$wpdb->prefix}tpw_members_household_member (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            household_id INT(11) UNSIGNED NOT NULL,
            member_id INT(11) UNSIGNED NOT NULL,
            role VARCHAR(50) NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY member_id_unique (member_id),
            KEY household_id (household_id),
            KEY household_primary (household_id, is_primary)
        ) $charset_collate;";
        dbDelta( $sql_household_member );

        // Field settings table
        $sql_settings = "CREATE TABLE {$wpdb->prefix}tpw_field_settings (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            society_id INT(11) UNSIGNED NOT NULL,
            field_key VARCHAR(100) NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            custom_label VARCHAR(255) DEFAULT NULL,
            field_type VARCHAR(50) DEFAULT 'text',
            basic_search TINYINT(1) NOT NULL DEFAULT 0,
            signup_safe TINYINT(1) NOT NULL DEFAULT 0,
            signup_enabled TINYINT(1) NOT NULL DEFAULT 0,
            signup_required TINYINT(1) NOT NULL DEFAULT 0,
            signup_section VARCHAR(100) DEFAULT NULL,
            signup_order INT(11) UNSIGNED NOT NULL DEFAULT 999,
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

        // Safety net: ensure the field_options column exists on field settings table for select option lists
        $has_field_options = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'field_options'",
            $fs_table
        ) );
        if ( ! $has_field_options ) {
            $wpdb->query( "ALTER TABLE {$fs_table} ADD COLUMN field_options TEXT NULL" );
        }

        $has_signup_safe = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'signup_safe'",
            $fs_table
        ) );
        if ( ! $has_signup_safe ) {
            $wpdb->query( "ALTER TABLE {$fs_table} ADD COLUMN signup_safe TINYINT(1) NOT NULL DEFAULT 0 AFTER basic_search" );
        }

        $has_signup_enabled = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'signup_enabled'",
            $fs_table
        ) );
        if ( ! $has_signup_enabled ) {
            $wpdb->query( "ALTER TABLE {$fs_table} ADD COLUMN signup_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER signup_safe" );
        }

        $has_signup_required = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'signup_required'",
            $fs_table
        ) );
        if ( ! $has_signup_required ) {
            $wpdb->query( "ALTER TABLE {$fs_table} ADD COLUMN signup_required TINYINT(1) NOT NULL DEFAULT 0 AFTER signup_enabled" );
        }

        $has_signup_section = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'signup_section'",
            $fs_table
        ) );
        if ( ! $has_signup_section ) {
            $wpdb->query( "ALTER TABLE {$fs_table} ADD COLUMN signup_section VARCHAR(100) NULL AFTER signup_required" );
        }

        $has_signup_order = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'signup_order'",
            $fs_table
        ) );
        if ( ! $has_signup_order ) {
            $wpdb->query( "ALTER TABLE {$fs_table} ADD COLUMN signup_order INT(11) UNSIGNED NOT NULL DEFAULT 999 AFTER signup_section" );
        }

        self::ensure_member_field_settings_rows( $fs_table );

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

    public static function ensure_core_schema() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tpw_members';
        $exists_members = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
        if ( $exists_members === $table_name ) {
            self::ensure_members_table_columns( $table_name );
        }

        $field_settings_table = $wpdb->prefix . 'tpw_field_settings';
        $exists_field_settings = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $field_settings_table ) );
        if ( $exists_field_settings === $field_settings_table ) {
            self::ensure_member_field_settings_rows( $field_settings_table );
        }
    }

    private static function ensure_members_table_columns( $table_name ) {
        global $wpdb;

        // Safety net: ensure the member_photo column exists even if dbDelta skipped it
        $has_member_photo = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'member_photo'",
            $table_name
        ) );
        if ( ! $has_member_photo ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN member_photo VARCHAR(255) NULL AFTER landline" );
        }

        // Safety net: ensure the dob column exists even if dbDelta skipped it
        $has_dob = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'dob'",
            $table_name
        ) );
        if ( ! $has_dob ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN dob DATE NULL AFTER country" );
        }

        // Safety net: ensure the is_gallery_admin column exists for upgraded installs
        $has_is_gallery_admin = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'is_gallery_admin'",
            $table_name
        ) );
        if ( ! $has_is_gallery_admin ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN is_gallery_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_noticeboard_admin" );
        }

        // Safety net: ensure the is_manage_members column exists for upgraded installs
        $has_is_manage_members = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'is_manage_members'",
            $table_name
        ) );
        if ( ! $has_is_manage_members ) {
            $after_column = $has_is_gallery_admin ? 'is_gallery_admin' : 'is_noticeboard_admin';
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN is_manage_members TINYINT(1) NOT NULL DEFAULT 0 AFTER {$after_column}" );
        }

        // Safety net: ensure the is_volunteer column exists for upgraded installs
        $has_is_volunteer = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'is_volunteer'",
            $table_name
        ) );
        if ( ! $has_is_volunteer ) {
            $after_column = $has_is_manage_members ? 'is_manage_members' : ( $has_is_gallery_admin ? 'is_gallery_admin' : 'is_noticeboard_admin' );
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN is_volunteer TINYINT(1) NOT NULL DEFAULT 0 AFTER {$after_column}" );
        }
    }

    private static function ensure_member_field_settings_rows( $table_name ) {
        global $wpdb;

        $rows = $wpdb->get_results( "SELECT field_key, sort_order FROM {$table_name}" );
        $sort_map = [];
        foreach ( (array) $rows as $row ) {
            $sort_map[ $row->field_key ] = (int) $row->sort_order;
        }

        $ensure_fields = [
            [
                'field_key'    => 'is_gallery_admin',
                'custom_label' => 'Gallery Admin',
                'insert_after' => [ 'is_noticeboard_admin' ],
            ],
            [
                'field_key'    => 'is_manage_members',
                'custom_label' => 'Members Manager',
                'insert_after' => [ 'is_gallery_admin', 'is_noticeboard_admin' ],
            ],
        ];

        $max_sort = (int) $wpdb->get_var( "SELECT COALESCE(MAX(sort_order), -1) FROM {$table_name}" );

        foreach ( $ensure_fields as $field ) {
            $field_key = $field['field_key'];
            if ( isset( $sort_map[ $field_key ] ) ) {
                continue;
            }

            $insert_sort = $max_sort + 1;
            foreach ( $field['insert_after'] as $anchor_key ) {
                if ( isset( $sort_map[ $anchor_key ] ) ) {
                    $insert_sort = (int) $sort_map[ $anchor_key ] + 1;
                    break;
                }
            }

            $wpdb->query( $wpdb->prepare( "UPDATE {$table_name} SET sort_order = sort_order + 1 WHERE sort_order >= %d", $insert_sort ) );
            foreach ( $sort_map as $existing_key => $existing_sort ) {
                if ( $existing_sort >= $insert_sort ) {
                    $sort_map[ $existing_key ] = $existing_sort + 1;
                }
            }

            $wpdb->insert(
                $table_name,
                [
                    'field_key'    => $field_key,
                    'is_enabled'   => 1,
                    'custom_label' => $field['custom_label'],
                    'field_type'   => 'checkbox',
                    'sort_order'   => $insert_sort,
                ],
                [ '%s', '%d', '%s', '%s', '%d' ]
            );

            $sort_map[ $field_key ] = $insert_sort;
            $max_sort++;
        }
    }
}