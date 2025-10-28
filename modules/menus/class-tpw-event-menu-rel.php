<?php

/**
 * Event to Menu relationship helpers.
 *
 * Manages a compact mapping table between events and menus and basic queries
 * to assign and fetch relationships.
 *
 * @since 1.0.0
 */
class TPW_Event_Menu_Rel {

    /**
     * Create the relationship table if missing.
     *
     * @since 1.0.0
     * @return void
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tpw_event_menu_relationship';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            event_id INT NOT NULL,
            menu_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_menu (event_id, menu_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Assign or update a menu for an event.
     *
     * @since 1.0.0
     * @param int $event_id
     * @param int $menu_id
     * @return void
     */
    public static function assign_menu_to_event($event_id, $menu_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_event_menu_relationship';

        $wpdb->replace($table_name, [
            'event_id' => $event_id,
            'menu_id' => $menu_id,
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Get the assigned menu ID for an event.
     *
     * @since 1.0.0
     * @param int $event_id
     * @return int|null
     */
    public static function get_menu_for_event($event_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_event_menu_relationship';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT menu_id FROM $table_name WHERE event_id = %d",
            $event_id
        ));
    }
}
