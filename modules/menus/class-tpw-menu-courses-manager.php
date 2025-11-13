<?php

class TPW_Menu_Courses_Manager {

    public static function get_course_name($menu_id, $course_number) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_courses';
        $name = $wpdb->get_var($wpdb->prepare(
            "SELECT course_name FROM $table WHERE menu_id = %d AND course_number = %d",
            $menu_id,
            $course_number
        ));
        if ( null === $name ) {
            return null;
        }
        return tpw_normalise_value( (string) $name );
    }

    public static function set_course_name($menu_id, $course_number, $course_name) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_courses';

        $existing = self::get_course_name($menu_id, $course_number);

        if ($existing !== null) {
            return $wpdb->update(
                $table,
                ['course_name' => sanitize_text_field( tpw_normalise_value( $course_name ) )],
                ['menu_id' => $menu_id, 'course_number' => $course_number]
            );
        } else {
            return $wpdb->insert(
                $table,
                [
                    'menu_id' => $menu_id,
                    'course_number' => $course_number,
                    'course_name' => sanitize_text_field( tpw_normalise_value( $course_name ) ),
                    'created_at' => current_time('mysql')
                ]
            );
        }
    }
}