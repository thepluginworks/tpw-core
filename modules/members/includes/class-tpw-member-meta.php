<?php

class TPW_Member_Meta {

    /**
     * Get a specific meta value for a member.
     */
    public static function get_meta( $member_id, $key ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_member_meta';

        return $wpdb->get_var(
            $wpdb->prepare( "SELECT meta_value FROM $table WHERE member_id = %d AND meta_key = %s", $member_id, $key )
        );
    }

    /**
     * Get all meta values for a member as an associative array.
     */
    public static function get_all_meta( $member_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_member_meta';

        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT meta_key, meta_value FROM $table WHERE member_id = %d", $member_id ),
            ARRAY_A
        );

        $meta = [];
        foreach ( $rows as $row ) {
            $meta[ $row['meta_key'] ] = $row['meta_value'];
        }

        return $meta;
    }

    /**
     * Save or update a single meta key/value pair for a member.
     */
    public static function save_meta( $member_id, $key, $value ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_member_meta';

        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE member_id = %d AND meta_key = %s", $member_id, $key )
        );

        if ( $exists ) {
            return $wpdb->update(
                $table,
                [ 'meta_value' => $value ],
                [ 'member_id' => $member_id, 'meta_key' => $key ]
            );
        } else {
            return $wpdb->insert( $table, [
                'member_id'  => $member_id,
                'meta_key'   => $key,
                'meta_value' => $value,
            ] );
        }
    }

    /**
     * Delete a specific meta key for a member.
     */
    public static function delete_meta( $member_id, $key ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_member_meta';

        return $wpdb->delete( $table, [
            'member_id' => $member_id,
            'meta_key'  => $key,
        ] );
    }

    /**
     * Delete all meta for a member.
     */
    public static function delete_all_meta( $member_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_member_meta';

        return $wpdb->delete( $table, [ 'member_id' => $member_id ] );
    }
}