<?php

class TPW_Menus_Saver {

    public static function save_menu($name, $description = '', $number_of_courses = 3, $price = 0.00) {
        return TPW_Menus_Manager::insert_menu($name, $description, $number_of_courses, $price);
    }

    public static function assign_courses_to_menu($menu_id, $courses = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menu_courses';

        // First delete existing assignments
        $wpdb->delete($table_name, ['menu_id' => $menu_id], ['%d']);

        // Insert new assignments
        foreach ($courses as $course) {
            $wpdb->insert($table_name, [
                'menu_id'    => $menu_id,
                'course_name'=> sanitize_text_field($course),
                'sort_order' => 0
            ]);
        }
    }

    public static function save_course_option($menu_id, $course_name, $option_text) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menu_options';

        $wpdb->insert($table_name, [
            'menu_id'     => $menu_id,
            'course_name' => sanitize_text_field($course_name),
            'option_text' => sanitize_text_field($option_text)
        ]);
    }
}
