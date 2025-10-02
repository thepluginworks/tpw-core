<?php

class TPW_Feedback_Model {

    public static function get_chart_data() {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_rsvp_feedback';

        // Ease rating (1–5)
        $ease = $wpdb->get_results(
            "SELECT ease_rating, COUNT(*) as total FROM $table WHERE ease_rating IS NOT NULL GROUP BY ease_rating",
            OBJECT_K
        );

        // Clarity (Yes/No)
        $clarity = $wpdb->get_results(
            "SELECT clarity_ok, COUNT(*) as total FROM $table WHERE clarity_ok IS NOT NULL GROUP BY clarity_ok",
            OBJECT_K
        );

        // Time buckets
        $time = $wpdb->get_results(
            "SELECT time_under_2min, COUNT(*) as total FROM $table WHERE time_under_2min IS NOT NULL GROUP BY time_under_2min",
            OBJECT_K
        );

        return [
            'ease'    => array_map(fn($o) => intval($o->total), $ease),
            'clarity' => array_map(fn($o) => intval($o->total), $clarity),
            'time'    => array_map(fn($o) => intval($o->total), $time),
        ];
    }

}