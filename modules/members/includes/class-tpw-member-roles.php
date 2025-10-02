<?php

class TPW_Member_Roles {
    /**
     * Ensure the given user has the 'member' capability/role without removing existing roles.
     * This updates the {prefix}_capabilities meta array non-destructively.
     */
    public static function ensure_member_cap( $user_id ) {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) return;
        global $wpdb;
        $meta_key = $wpdb->prefix . 'capabilities'; // e.g., wp_capabilities
        $caps = get_user_meta( $user_id, $meta_key, true );
        if ( ! is_array( $caps ) ) {
            // Attempt to unserialize if stored as string for any reason
            if ( is_string( $caps ) && $caps !== '' ) {
                $maybe = @unserialize( $caps );
                if ( is_array( $maybe ) ) {
                    $caps = $maybe;
                } else {
                    $caps = [];
                }
            } else {
                $caps = [];
            }
        }
        if ( empty( $caps['member'] ) ) {
            $caps['member'] = true;
            update_user_meta( $user_id, $meta_key, $caps );
        }
    }

    /**
     * Add a role non-destructively using WP API (keeps existing roles).
     */
    public static function add_role( $user_id, $role ) {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 || ! is_string( $role ) || $role === '' ) return;
        $user = new WP_User( $user_id );
        if ( $user && $user->exists() ) {
            $user->add_role( $role );
        }
    }
}
