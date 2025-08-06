<?php

class TPW_Payment_DB {
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_payment_methods';
        $charset_collate = $wpdb->get_charset_collate();
        error_log('✅ TPW_Payment_DB::create_table() was called');

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL UNIQUE,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        error_log('🔧 SQL to be run: ' . $sql);
        

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        if ($wpdb->last_error) {
            error_log('❌ DB Error: ' . $wpdb->last_error);
        }

        // Insert default methods if not already present
        $default_methods = [
            ['name' => 'BACS', 'slug' => 'bacs'],
            ['name' => 'Cheque', 'slug' => 'cheque'],
            ['name' => 'Cash', 'slug' => 'cash'],
            ['name' => 'Pay by Card (via SumUp)', 'slug' => 'sumup'],
            ['name' => 'Pay by Card (via Square)', 'slug' => 'square'],
            ['name' => 'WooCommerce', 'slug' => 'woocommerce'],
        ];

        foreach ($default_methods as $method) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE slug = %s", $method['slug']));
            if (!$exists) {
                $wpdb->insert($table_name, [
                    'name' => $method['name'],
                    'slug' => $method['slug'],
                    'active' => 1,
                    'created_at' => current_time('mysql'),
                ]);
            }
        }
    }
}
