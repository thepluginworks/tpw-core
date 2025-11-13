<?php

class TPW_Course_Choices_Manager {

    public static function get_choices_by_menu($menu_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE menu_id = %d ORDER BY course_number ASC, label ASC",
            $menu_id
        ));
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                if ( isset( $row->label ) ) {
                    $row->label = tpw_normalise_value( (string) $row->label );
                }
                if ( isset( $row->description ) ) {
                    $row->description = tpw_normalise_value( (string) $row->description );
                }
            }
        }
        return $rows;
    }

    public static function get_choices_for_course($menu_id, $course_number) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE menu_id = %d AND course_number = %d ORDER BY label ASC",
            $menu_id, $course_number
        ));
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                if ( isset( $row->label ) ) {
                    $row->label = tpw_normalise_value( (string) $row->label );
                }
                if ( isset( $row->description ) ) {
                    $row->description = tpw_normalise_value( (string) $row->description );
                }
            }
        }
        return $rows;
    }

    public static function get_choice_by_id($choice_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $choice_id
        ));
        if ( $row ) {
            if ( isset( $row->label ) ) {
                $row->label = tpw_normalise_value( (string) $row->label );
            }
            if ( isset( $row->description ) ) {
                $row->description = tpw_normalise_value( (string) $row->description );
            }
        }
        return $row;
    }

    public static function insert_choice($menu_id, $course_number, $label, $description = '', $extra_cost = 0.00) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        $wpdb->insert($table, [
            'menu_id' => $menu_id,
            'course_number' => $course_number,
            'label' => sanitize_textarea_field( tpw_normalise_value( $label ) ),
            'description' => sanitize_textarea_field( tpw_normalise_value( $description ) ),
            'extra_cost' => number_format((float)$extra_cost, 2, '.', ''),
            'created_at' => current_time('mysql')
        ]);
        return $wpdb->insert_id;
    }

    public static function update_choice($id, $label, $description = '', $extra_cost = 0.00) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        return $wpdb->update($table, [
            'label' => sanitize_textarea_field( tpw_normalise_value( $label ) ),
            'description' => sanitize_textarea_field( tpw_normalise_value( $description ) ),
            'extra_cost' => number_format((float)$extra_cost, 2, '.', '')
        ], ['id' => $id]);
    }

    public static function delete_choice($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_menu_choices';
        return $wpdb->delete($table, ['id' => $id]);
    }
}