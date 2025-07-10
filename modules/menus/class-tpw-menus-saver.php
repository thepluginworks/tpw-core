<?php

class TPW_Menus_Saver {

    public static function save_menu($name, $description = '', $number_of_courses = 3, $price = 0.00) {
        return TPW_Menus_Manager::insert_menu($name, $description, $number_of_courses, $price);
    }

    public static function assign_courses_to_menu($menu_id, $courses = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menu_courses';

        // Remove existing courses for this menu
        $wpdb->delete($table_name, ['menu_id' => $menu_id], ['%d']);

        // Insert courses with incremental course numbers
        $course_number = 1;
        foreach ($courses as $course) {
            $wpdb->insert(
                $table_name,
                [
                    'menu_id'       => $menu_id,
                    'course_number' => $course_number++,
                    'course_name'   => sanitize_text_field($course),
                    'created_at'    => current_time('mysql'),
                ],
                [ '%d', '%d', '%s', '%s' ]
            );
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
