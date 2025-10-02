<?php

class TPW_Member_Access {
    const ALLOWED_STATUSES = [ 'Active', 'Honorary', 'Life Member' ];

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

    public static function get_member_by_user_id( $user_id ) {
        if ( ! $user_id ) return null;
        require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
        $controller = new TPW_Member_Controller();
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $user_id ) );
    }

    public static function get_member_by_email( $email ) {
        if ( ! $email ) return null;
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email ) );
    }

    public static function get_member_by_username( $username ) {
        if ( ! $username ) return null;
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE username = %s LIMIT 1", $username ) );
    }

    public static function is_admin_current() {
        if ( ! is_user_logged_in() ) return false;
        // WordPress admins have full access by default.
        // Sites can force requiring tpw_members.is_admin via filter.
        $wp_admin_is_enough = apply_filters( 'tpw_members/wp_admin_is_full_admin', true );
        if ( current_user_can( 'manage_options' ) && $wp_admin_is_enough ) {
            return true;
        }

        if ( ! current_user_can( 'manage_options' ) ) return false;

        // Fallback to stricter check when filter disables WP-admin override
        $user = wp_get_current_user();
        $member = self::get_member_by_user_id( (int) $user->ID );
        return ( $member && (int)$member->is_admin === 1 );
    }

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
