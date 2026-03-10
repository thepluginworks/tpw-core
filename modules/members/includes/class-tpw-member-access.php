<?php

/**
 * Member access helpers for TPW Core.
 *
 * Provides utility methods to resolve the current user's TPW member record
 * and evaluate common permission checks used across front‑end UIs and AJAX
 * endpoints (e.g., admin, valid member). Also exposes the canonical list of
 * allowed statuses for member visibility, filterable via hooks.
 *
 * @since 1.0.0
 */
class TPW_Member_Access {
    const ALLOWED_STATUSES = [ 'Active', 'Honorary', 'Life Member' ];

    /**
     * Protected member permission fields.
     *
     * These fields may only be edited by WordPress admins or TPW Core admins.
     * Members Managers can view them, but cannot modify them.
     *
     * @return string[]
     */
    public static function get_protected_member_permission_fields() {
        return [ 'is_admin', 'is_manage_members' ];
    }

    /**
     * Check whether a field key is a protected member permission field.
     *
     * @param string $field_key Field key to check.
     * @return bool
     */
    public static function is_protected_member_permission_field( $field_key ) {
        return in_array( sanitize_key( (string) $field_key ), self::get_protected_member_permission_fields(), true );
    }

    /**
     * Resolve a user ID, defaulting to the current user.
     *
     * @param int $user_id Optional user ID.
     * @return int
     */
    protected static function normalize_user_id( $user_id = 0 ) {
        $user_id = (int) $user_id;
        if ( $user_id > 0 ) {
            return $user_id;
        }

        return is_user_logged_in() ? (int) get_current_user_id() : 0;
    }

    /**
     * Check whether the user is a WordPress admin.
     *
     * @param int $user_id Optional user ID.
     * @return bool
     */
    public static function user_is_wp_admin( $user_id = 0 ) {
        $user_id = self::normalize_user_id( $user_id );
        if ( $user_id <= 0 ) {
            return false;
        }

        return function_exists( 'user_can' ) ? (bool) user_can( $user_id, 'manage_options' ) : false;
    }

    /**
     * Check whether a linked TPW member row has a specific boolean flag enabled.
     *
     * @param string $flag_key Member table flag key.
     * @param int    $user_id  Optional user ID.
     * @return bool
     */
    public static function user_has_member_flag( $flag_key, $user_id = 0 ) {
        $flag_key = sanitize_key( (string) $flag_key );
        $allowed_flags = [
            'is_admin',
            'is_committee',
            'is_match_manager',
            'is_noticeboard_admin',
            'is_gallery_admin',
            'is_manage_members',
            'is_volunteer',
        ];
        if ( ! in_array( $flag_key, $allowed_flags, true ) ) {
            return false;
        }

        $user_id = self::normalize_user_id( $user_id );
        if ( $user_id <= 0 ) {
            return false;
        }

        $member = self::get_member_by_user_id( $user_id );
        return ( $member && isset( $member->$flag_key ) && (int) $member->$flag_key === 1 );
    }

    /**
     * Check if a user is a TPW Core admin in members context.
     *
     * @param int $user_id Optional user ID.
     * @return bool
     */
    public static function is_admin_user( $user_id = 0 ) {
        $user_id = self::normalize_user_id( $user_id );
        if ( $user_id <= 0 ) {
            return false;
        }

        $wp_admin_is_enough = (bool) apply_filters( 'tpw_members/wp_admin_is_full_admin', true );
        if ( self::user_is_wp_admin( $user_id ) && $wp_admin_is_enough ) {
            return true;
        }

        return self::user_has_member_flag( 'is_admin', $user_id );
    }

    /**
     * Check if a user can manage the members admin interface.
     *
     * Access is granted to:
     * - WordPress admins
     * - TPW Core admins via tpw_members.is_admin
     * - Members managers via tpw_members.is_manage_members
     *
     * @param int $user_id Optional user ID.
     * @return bool
     */
    public static function can_manage_members_user( $user_id = 0 ) {
        $user_id = self::normalize_user_id( $user_id );
        if ( $user_id <= 0 ) {
            return false;
        }

        if ( self::user_is_wp_admin( $user_id ) ) {
            return true;
        }

        return self::user_has_member_flag( 'is_admin', $user_id )
            || self::user_has_member_flag( 'is_manage_members', $user_id );
    }

    /**
     * Check whether the current user can manage members.
     *
     * @return bool
     */
    public static function can_manage_members_current() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        return self::can_manage_members_user( (int) get_current_user_id() );
    }

    /**
     * Check whether a user can edit protected member permission fields.
     *
     * @param int $user_id Optional user ID.
     * @return bool
     */
    public static function can_edit_protected_member_permission_fields_user( $user_id = 0 ) {
        return self::is_admin_user( $user_id );
    }

    /**
     * Check whether the current user can edit protected member permission fields.
     *
     * @return bool
     */
    public static function can_edit_protected_member_permission_fields_current() {
        return self::can_edit_protected_member_permission_fields_user();
    }

    /**
     * Apply protected member permission field rules to submitted member data.
     *
     * If the current actor cannot edit protected permission fields, any attempted
     * changes to those fields are ignored. Existing values are preserved on
     * updates, and forced to 0 for new records.
     *
     * @param array            $data            Submitted member data.
     * @param object|array|null $existing_member Optional existing member row for updates.
     * @param int              $user_id         Optional user ID.
     * @return array
     */
    public static function apply_protected_member_permission_field_rules( array $data, $existing_member = null, $user_id = 0 ) {
        if ( self::can_edit_protected_member_permission_fields_user( $user_id ) ) {
            return $data;
        }

        foreach ( self::get_protected_member_permission_fields() as $field_key ) {
            if ( ! array_key_exists( $field_key, $data ) ) {
                continue;
            }

            $fallback = 0;
            if ( is_object( $existing_member ) && isset( $existing_member->$field_key ) ) {
                $fallback = (int) $existing_member->$field_key;
            } elseif ( is_array( $existing_member ) && array_key_exists( $field_key, $existing_member ) ) {
                $fallback = (int) $existing_member[ $field_key ];
            }

            $data[ $field_key ] = $fallback;
        }

        return $data;
    }

    /**
     * Get normalized list of allowed statuses (lowercased, trimmed).
     *
     * @since 1.0.0
     * @return string[]
     */
    protected static function allowed_statuses_norm() {
        $statuses = apply_filters( 'tpw_members/allowed_statuses', self::ALLOWED_STATUSES );
        $statuses = is_array($statuses) ? $statuses : self::ALLOWED_STATUSES;
        return array_map( function($s){ return strtolower( trim( (string)$s ) ); }, $statuses );
    }

    /**
     * Returns the allowed member statuses for directory visibility, preserving
     * original case. Filterable via 'tpw_members/allowed_statuses'.
     *
     * @return string[]
     */
    /**
     * Get the allowed member statuses for directory visibility.
     *
     * Filter: tpw_members/allowed_statuses
     *
     * @since 1.0.0
     * @return string[] Canonical status labels (original case)
     */
    public static function get_allowed_statuses() {
        $statuses = apply_filters( 'tpw_members/allowed_statuses', self::ALLOWED_STATUSES );
        if ( ! is_array( $statuses ) ) {
            $statuses = self::ALLOWED_STATUSES;
        }
        // Normalize to strings and remove empties
        $statuses = array_values( array_filter( array_map( function( $s ) {
            return (string) $s;
        }, $statuses ), function( $s ) { return $s !== ''; } ) );
        return $statuses;
    }

    /**
     * Resolve a TPW member row by linked WordPress user ID.
     *
     * @since 1.0.0
     * @param int $user_id WP user ID
     * @return object|null Member row or null when not found
     */
    public static function get_member_by_user_id( $user_id ) {
        if ( ! $user_id ) return null;
        require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
        $controller = new TPW_Member_Controller();
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $user_id ) );
    }

    /**
     * Resolve a TPW member row by email address.
     *
     * @since 1.0.0
     * @param string $email Email address
     * @return object|null Member row or null when not found
     */
    public static function get_member_by_email( $email ) {
        if ( ! $email ) return null;
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email ) );
    }

    /**
     * Resolve a TPW member row by username.
     *
     * @since 1.0.0
     * @param string $username WordPress username
     * @return object|null Member row or null when not found
     */
    public static function get_member_by_username( $username ) {
        if ( ! $username ) return null;
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE username = %s LIMIT 1", $username ) );
    }

    /**
     * Check if current user is considered an admin in TPW members context.
     *
     * Filter: tpw_members/wp_admin_is_full_admin — when true (default), WP admins
     * are treated as full admins without requiring the tpw_members.is_admin flag.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_admin_current() {
        return self::is_admin_user();
    }

    /**
     * Check if the current user has a valid member record with an allowed status.
     *
     * Filters:
     * - tpw_members/allow_email_match_for_member — allow fallback by email
     * - tpw_members/allow_username_match_for_member — allow fallback by username
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_member_current() {
        if ( ! is_user_logged_in() ) return false;
        $user = wp_get_current_user();
        $member = self::get_member_by_user_id( (int) $user->ID );

        // Optional fallback: match by email if user_id linkage missing
        if ( ! $member && apply_filters( 'tpw_members/allow_email_match_for_member', true ) ) {
            $member = self::get_member_by_email( $user->user_email );
        }
        // Optional fallback: match by username if still not found
        if ( ! $member && apply_filters( 'tpw_members/allow_username_match_for_member', true ) ) {
            $member = self::get_member_by_username( $user->user_login );
        }

        if ( ! $member ) return false;

        $status = strtolower( trim( (string) $member->status ) );
        $allowed = self::allowed_statuses_norm();
        $ok = in_array( $status, $allowed, true );
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('[TPW_Member_Access] is_member_current user_id='.(int)$user->ID.' member_id='.( $member ? (int)$member->id : 0 ).' status='.( $member ? $member->status : 'n/a').' allowed='.( $ok ? '1' : '0' ) );
        }
        return $ok;
    }
}
