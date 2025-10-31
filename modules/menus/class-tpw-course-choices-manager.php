<?php

class TPW_Course_Choices_Manager {

    public static function get_choices_by_menu($menu_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE menu_id = %d ORDER BY course_number ASC, label ASC",
            $menu_id
        ));
    }

    public static function get_choices_for_course($menu_id, $course_number) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE menu_id = %d AND course_number = %d ORDER BY label ASC",
            $menu_id, $course_number
        ));
    }

    public static function get_choice_by_id($choice_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $choice_id
        ));
    }

    public static function insert_choice($menu_id, $course_number, $label, $description = '', $extra_cost = 0.00) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        $wpdb->insert($table, [
            'menu_id' => $menu_id,
            'course_number' => $course_number,
            'label' => sanitize_textarea_field($label),
            'description' => sanitize_textarea_field($description),
            'extra_cost' => number_format((float)$extra_cost, 2, '.', ''),
            'created_at' => current_time('mysql')
        ]);
        return $wpdb->insert_id;
    }

    public static function update_choice($id, $label, $description = '', $extra_cost = 0.00) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        return $wpdb->update($table, [
            'label' => sanitize_textarea_field($label),
            'description' => sanitize_textarea_field($description),
            'extra_cost' => number_format((float)$extra_cost, 2, '.', '')
        ], ['id' => $id]);
    }

    public static function delete_choice($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        return $wpdb->delete($table, ['id' => $id]);
    }
}