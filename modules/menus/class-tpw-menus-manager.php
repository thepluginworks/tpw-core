<?php

/**
 * Data access layer for TPW Menus (admin-defined menus for events).
 *
 * Creates and manages the tpw_menus, tpw_menu_courses, and tpw_menu_choices
 * tables and provides CRUD helpers used by admin UIs and renderers.
 *
 * @since 1.0.0
 */
class TPW_Menus_Manager {

    /**
     * Create the base menus table and related tables.
     *
     * @since 1.0.0
     * @return void
     */
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

    /**
     * Create the menu choices table.
     *
     * @since 1.0.0
     * @return void
     */
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
            extra_cost DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (menu_id) REFERENCES {$wpdb->prefix}tpw_menus(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Safe incremental upgrade: ensure the extra_cost column exists for older installs
        self::ensure_column_exists(
            $table_name,
            'extra_cost',
            'ADD COLUMN extra_cost DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER description'
        );
    }

    /**
     * Get all menus ordered by name.
     *
     * @since 1.0.0
     * @return array<object>
     */
    public static function get_all_menus() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menus';
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    }

    /**
     * Get a menu row by ID.
     *
     * @since 1.0.0
     * @param int $id
     * @return object|null
     */
    public static function get_menu_by_id($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menus';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
    }

    /**
     * Alias for get_menu_by_id().
     *
     * @since 1.0.0
     * @param int $menu_id
     * @return object|null
     */
    public static function get_menu($menu_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menus';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $menu_id));
    }

    /**
     * Insert a new menu.
     *
     * @since 1.0.0
     * @param string $name
     * @param string $description
     * @param int    $number_of_courses
     * @param float  $price
     * @return int Insert ID
     */
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

    /**
     * Delete a menu by ID.
     *
     * @since 1.0.0
     * @param int $menu_id
     * @return int|false Rows affected or false on error
     */
    public static function delete_menu($menu_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menus';
        return $wpdb->delete($table_name, ['id' => $menu_id], ['%d']);
    }

    /**
     * Update a menu row.
     *
     * @since 1.0.0
     * @param int    $id
     * @param string $name
     * @param string $description
     * @param int    $number_of_courses
     * @param float  $price
     * @return int|false Rows affected or false on error
     */
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

    /**
     * Create the menu courses table.
     *
     * @since 1.0.0
     * @return void
     */
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
    /**
     * Get all courses for a menu (ordered by course_number).
     *
     * @since 1.0.0
     * @param int $menu_id
     * @return array<int,array>
     */
    public static function get_courses_for_menu( $menu_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menu_courses';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE menu_id = %d ORDER BY course_number ASC",
            $menu_id
        ), ARRAY_A );

        return $results ?: [];
    }

    /**
     * Ensure a column exists on a given table; add it if missing.
     *
     * @param string $table_name Fully-qualified table name (including $wpdb->prefix)
     * @param string $column     Column name to check
     * @param string $alter_sql  ALTER TABLE fragment to add the column (without the table name)
     * @return void
     */
    protected static function ensure_column_exists( $table_name, $column, $alter_sql ) {
        global $wpdb;
        // Check if column exists
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", $column ) );
        if ( $exists ) {
            return;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table and statement constructed safely above
        $wpdb->query( "ALTER TABLE {$table_name} {$alter_sql}" );
    }
}