<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class TPW_Costs
 * 
 * Handles fetching and saving event costs across all TPW plugins.
 */
class TPW_Costs {

    /**
     * Get costs for a specific event.
     *
     * @param int $event_id
     * @return array|null
     */
    public static function get_costs( $event_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'tpw_event_costs';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT meeting_cost, dining_cost FROM $table WHERE event_id = %d",
                $event_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Get costs by post ID (fallback for new events before event_id exists).
     *
     * @param int $post_id
     * @return array|null
     */
    public static function get_costs_by_post_id( $post_id ) {
        global $wpdb;

        $event_table = $wpdb->prefix . 'tpw_events';
        $cost_table = $wpdb->prefix . 'tpw_event_costs';

        // Find event_id from post_id
        $event_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT event_id FROM $event_table WHERE post_id = %d",
                $post_id
            )
        );

        if ( $event_id ) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT meeting_cost, dining_cost FROM $cost_table WHERE event_id = %d",
                    $event_id
                ),
                ARRAY_A
            );

            return $row ?: null;
        }

        return null;
    }

    /**
     * Save or update costs for a specific event.
     *
     * @param int $event_id
     * @param float $meeting_cost
     * @param float $dining_cost
     * @return bool|int
     */
    public static function save_costs( $event_id, $meeting_cost, $dining_cost ) {
        global $wpdb;

        $table = $wpdb->prefix . 'tpw_event_costs';

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT event_id FROM $table WHERE event_id = %d",
                $event_id
            )
        );

        if ( $existing ) {
            return $wpdb->update(
                $table,
                [
                    'meeting_cost' => $meeting_cost,
                    'dining_cost'  => $dining_cost,
                    'updated_at'   => current_time( 'mysql' ),
                ],
                [ 'event_id' => $event_id ],
                [ '%f', '%f', '%s' ],
                [ '%d' ]
            );
        } else {
            return $wpdb->insert(
                $table,
                [
                    'event_id'     => $event_id,
                    'meeting_cost' => $meeting_cost,
                    'dining_cost'  => $dining_cost,
                    'created_at'   => current_time( 'mysql' ),
                    'updated_at'   => current_time( 'mysql' ),
                ],
                [ '%d', '%f', '%f', '%s', '%s' ]
            );
        }
    }
}