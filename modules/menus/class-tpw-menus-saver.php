<?php

/**
 * Persistence helpers for TPW Menus admin UIs.
 *
 * Wraps inserts/updates for menus and their courses, and provides a utility
 * to save a simple per-course option (legacy compatibility).
 *
 * @since 1.0.0
 */
class TPW_Menus_Saver {

    /**
     * Create a menu with basic fields.
     *
     * @since 1.0.0
     * @param string $name
     * @param string $description
     * @param int    $number_of_courses
     * @param float  $price
     * @return int Insert ID
     */
    public static function save_menu($name, $description = '', $number_of_courses = 3, $price = 0.00) {
        $menu_id = TPW_Menus_Manager::insert_menu($name, $description, $number_of_courses, $price);
        // Ensure default courses exist immediately after creation so dependents can resolve names
        if ( $menu_id ) {
            $defaults = [ 'Starter', 'Main Course', 'Dessert' ];
            // Normalisation is applied inside assign_courses_to_menu() when saving
            self::assign_courses_to_menu( $menu_id, $defaults );
        }
        return $menu_id;
    }

    /**
     * Replace all courses for a menu with the provided list.
     *
     * @since 1.0.0
     * @param int   $menu_id
     * @param array $courses List of course name strings
     * @return void
     */
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
                    'course_name'   => sanitize_text_field( tpw_normalise_value( $course ) ),
                    'created_at'    => current_time('mysql'),
                ],
                [ '%d', '%d', '%s', '%s' ]
            );
        }
    }

    /**
     * Save an option text for a given course (legacy helper).
     *
     * @since 1.0.0
     * @param int    $menu_id
     * @param string $course_name
     * @param string $option_text
     * @return void
     */
    public static function save_course_option($menu_id, $course_name, $option_text) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpw_menu_options';

        $wpdb->insert($table_name, [
            'menu_id'     => $menu_id,
            'course_name' => sanitize_text_field( tpw_normalise_value( $course_name ) ),
            'option_text' => sanitize_text_field( tpw_normalise_value( $option_text ) )
        ]);
    }
}
