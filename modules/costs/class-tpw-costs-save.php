<?php
/**
 * Class TPW_Costs_Save
 *
 * Handles saving of event costs to the tpw_event_costs table.
 */

class TPW_Costs_Save {

    /**
     * Save or update costs for a given event.
     *
     * @param int $event_id
     * @param float|null $meeting_cost
     * @param float|null $dining_cost
     * @return void
     */
    public static function save($event_id, $meeting_cost = null, $dining_cost = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tpw_event_costs';

        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT cost_id FROM $table_name WHERE event_id = %d",
            $event_id
        ));

        $data = [
            'meeting_cost' => isset($meeting_cost) ? (float) $meeting_cost : 0.00,
            'dining_cost'  => is_null($dining_cost) ? 0.00 : (float) $dining_cost,
            'updated_at'   => current_time('mysql'),
        ];

        if ($existing_id) {
            $wpdb->update(
                $table_name,
                $data,
                ['event_id' => $event_id]
            );
        } else {
            $data['event_id'] = $event_id;
            $data['created_at'] = current_time('mysql');
            $wpdb->insert(
                $table_name,
                $data
            );
        }
    }
}