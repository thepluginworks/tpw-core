<?php

class TPW_Member_Roles {
    /**
     * Identity roles managed by TPW Core.
     *
     * @var string[]
     */
    const IDENTITY_ROLES = array( 'member', 'tpw_member' );

    /**
     * Ensure the given user has the 'member' capability/role without removing existing roles.
     * This updates the {prefix}_capabilities meta array non-destructively.
     */
    public static function ensure_member_cap( $user_id ) {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return;
        }

        global $wpdb;
        $meta_key = $wpdb->prefix . 'capabilities';
        $caps     = get_user_meta( $user_id, $meta_key, true );

        if ( ! is_array( $caps ) ) {
            if ( is_string( $caps ) && '' !== $caps ) {
                $maybe = @unserialize( $caps );
                if ( is_array( $maybe ) ) {
                    $caps = $maybe;
                } else {
                    $caps = array();
                }
            } else {
                $caps = array();
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
        if ( $user_id <= 0 || ! is_string( $role ) || '' === $role ) {
            return;
        }

        $user = new WP_User( $user_id );
        if ( $user && $user->exists() ) {
            $user->add_role( $role );
        }
    }

    /**
     * Sync the Core-owned identity projection for a user from canonical member status.
     *
     * @param int    $user_id User ID.
     * @param string $status  Canonical member status.
     * @return string|null
     */
    public static function sync_identity_projection( $user_id, $status ) {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return null;
        }

        $user = new WP_User( $user_id );
        if ( ! $user || ! $user->exists() ) {
            return null;
        }

        $identity_role = self::get_identity_role_for_status( $status );

        foreach ( self::IDENTITY_ROLES as $role ) {
            if ( $role === $identity_role ) {
                continue;
            }

            if ( in_array( $role, (array) $user->roles, true ) ) {
                $user->remove_role( $role );
            }
        }

        if ( null !== $identity_role && ! in_array( $identity_role, (array) $user->roles, true ) ) {
            $user->add_role( $identity_role );
        }

        if ( 'member' === $identity_role ) {
            self::ensure_member_cap( $user_id );
        }

        return $identity_role;
    }

    /**
     * Resolve the current Core identity role for a canonical member status.
     *
     * @param string $status Canonical member status.
     * @return string|null
     */
    public static function get_identity_role_for_status( $status ) {
        if ( ! class_exists( 'TPW_Identity', false ) ) {
            $identity_file = plugin_dir_path( __FILE__ ) . 'class-tpw-identity.php';
            if ( file_exists( $identity_file ) ) {
                require_once $identity_file;
            }
        }

        if ( class_exists( 'TPW_Identity', false ) && TPW_Identity::is_membership_bearing_status( $status ) ) {
            return 'member';
        }

        return null;
    }
}
