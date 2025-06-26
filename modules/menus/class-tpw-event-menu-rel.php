<?php

class TPW_Event_Menu_Rel {

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

    public static function assign_menu_to_event($event_id, $menu_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_event_menu_relationship';

        $wpdb->replace($table_name, [
            'event_id' => $event_id,
            'menu_id' => $menu_id,
            'created_at' => current_time('mysql')
        ]);
    }

    public static function get_menu_for_event($event_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_event_menu_relationship';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT menu_id FROM $table_name WHERE event_id = %d",
            $event_id
        ));
    }
}
