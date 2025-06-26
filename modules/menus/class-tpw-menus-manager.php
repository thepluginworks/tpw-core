<?php

class TPW_Menus_Manager {

    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tpw_menus';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            number_of_courses TINYINT UNSIGNED NOT NULL DEFAULT 3,
            price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Create the menu choices table as well
        self::create_menu_choices_table();
        // Create the menu courses table as well
        self::create_menu_courses_table();
    }

    public static function create_menu_choices_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tpw_menu_choices';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            menu_id INT NOT NULL,
            course_number TINYINT UNSIGNED NOT NULL,
            label VARCHAR(255) NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (menu_id) REFERENCES {$wpdb->prefix}tpw_menus(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function get_all_menus() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menus';
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    }

    public static function get_menu_by_id($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menus';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
    }

    public static function get_menu($menu_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menus';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $menu_id));
    }

    public static function insert_menu($name, $description = '', $number_of_courses = 3, $price = 0.00) {
        //error_log('insert_menu() is executing from: ' . __FILE__);
        //error_log('insert_menu() args: ' . print_r(func_get_args(), true));
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menus';
        $wpdb->insert($table_name, [
            'name' => sanitize_text_field($name),
            'description' => sanitize_textarea_field($description),
            'number_of_courses' => intval($number_of_courses),
            'price' => floatval($price),
            'created_at' => current_time('mysql')
        ]);
        return $wpdb->insert_id;
    }

    public static function delete_menu($menu_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menus';
        return $wpdb->delete($table_name, ['id' => $menu_id], ['%d']);
    }

    public static function update_menu($id, $name, $description, $number_of_courses, $price) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menus';

        return $wpdb->update(
            $table_name,
            [
                'name' => sanitize_text_field($name),
                'description' => sanitize_textarea_field($description),
                'number_of_courses' => intval($number_of_courses),
                'price' => floatval($price)
            ],
            [ 'id' => intval($id) ]
        );
    }

    public static function create_menu_courses_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tpw_menu_courses';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            menu_id INT NOT NULL,
            course_number TINYINT UNSIGNED NOT NULL,
            course_name VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY menu_course_unique (menu_id, course_number),
            FOREIGN KEY (menu_id) REFERENCES {$wpdb->prefix}tpw_menus(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    public static function get_courses_for_menu( $menu_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menu_courses';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE menu_id = %d ORDER BY course_number ASC",
            $menu_id
        ), ARRAY_A );

        return $results ?: [];
    }
}