<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class TPW_Control_UI {
    // Capability markers understood by section registry (legacy / convenience)
    public static function check_capability_marker( $marker ) {
        switch ( $marker ) {
            case '__tpw_control_is_member__': return self::is_member();
            case '__tpw_control_is_admin__': return self::is_admin();
            case '__tpw_control_is_committee_or_admin__': return ( self::is_admin() || self::is_committee() );
            case '__tpw_control_is_committee__': return self::is_committee();
            case '__tpw_control_is_match_manager__': return self::is_match_manager();
            case '__tpw_control_is_noticeboard_admin__': return self::is_noticeboard_admin();
        }
        return false;
    }

    public static function is_member() {
        if ( class_exists( 'TPW_Member_Access' ) ) {
            require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-access.php';
            return TPW_Member_Access::is_member_current();
        }
        return is_user_logged_in();
    }

    public static function is_admin() {
        if ( class_exists( 'TPW_Member_Access' ) ) {
            require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-access.php';
            return TPW_Member_Access::is_admin_current();
        }
        return current_user_can( 'manage_options' );
    }

    public static function is_committee() {
        $user_id = get_current_user_id();
        $is = apply_filters( 'tpw_control/is_committee_user', null, $user_id );
        if ( null !== $is ) return (bool) $is;
        global $wpdb; $table = $wpdb->prefix . 'tpw_members';
        $member = $wpdb->get_row( $wpdb->prepare( "SELECT is_committee FROM {$table} WHERE user_id = %d LIMIT 1", $user_id ) );
        return ( $member && intval( $member->is_committee ) === 1 );
    }

    public static function is_match_manager() {
        $user_id = get_current_user_id();
        $is = apply_filters( 'tpw_control/is_match_manager_user', null, $user_id );
        if ( null !== $is ) return (bool) $is;
        global $wpdb; $table = $wpdb->prefix . 'tpw_members';
        $member = $wpdb->get_row( $wpdb->prepare( "SELECT is_match_manager FROM {$table} WHERE user_id = %d LIMIT 1", $user_id ) );
        return ( $member && intval( $member->is_match_manager ) === 1 );
    }

    public static function is_noticeboard_admin() {
        $user_id = get_current_user_id();
        $is = apply_filters( 'tpw_control/is_noticeboard_admin_user', null, $user_id );
        if ( null !== $is ) return (bool) $is;
        global $wpdb; $table = $wpdb->prefix . 'tpw_members';
        $member = $wpdb->get_row( $wpdb->prepare( "SELECT is_noticeboard_admin FROM {$table} WHERE user_id = %d LIMIT 1", $user_id ) );
        return ( $member && intval( $member->is_noticeboard_admin ) === 1 );
    }

    public static function section_is_visible( $section ) {
        if ( empty( $section ) || ! is_array( $section ) ) return false;
        if ( self::is_admin() ) return true; // admins always allowed
        if ( isset( $section['visibility'] ) ) return self::user_has_access( $section['visibility'] );
        $cap = $section['capability'] ?? true; // legacy
        if ( $cap === true ) return is_user_logged_in();
        if ( $cap === false ) return true;
        if ( is_string( $cap ) && 0 === strpos( $cap, '__tpw_control_' ) ) return self::check_capability_marker( $cap );
        if ( is_callable( $cap ) ) return (bool) call_user_func( $cap, $section );
        if ( is_string( $cap ) ) return current_user_can( $cap );
        return false;
    }

    // Visibility JSON access check
    public static function user_has_access( $visibility, $member = null ) {
        if ( self::is_admin() ) return true;
        if ( is_string( $visibility ) ) { $decoded = json_decode( $visibility, true ); if ( json_last_error() === JSON_ERROR_NONE ) $visibility = $decoded; else return false; }
        if ( ! is_array( $visibility ) ) $visibility = [];
        if ( isset( $visibility['public'] ) && $visibility['public'] ) return true;
        if ( isset( $visibility['logged_in'] ) && $visibility['logged_in'] ) { if ( ! is_user_logged_in() ) return false; }
        if ( null === $member ) {
            if ( ! is_user_logged_in() ) return false;
            if ( class_exists( 'TPW_Member_Access' ) ) { require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-access.php'; $member = TPW_Member_Access::get_member_by_user_id( get_current_user_id() ); }
        }
        $flag_val = function( $flag ) use ( $member ) {
            $allowed_flags = [ 'is_admin', 'is_committee', 'is_match_manager', 'is_noticeboard_admin' ];
            if ( ! in_array( $flag, $allowed_flags, true ) ) return false;
            if ( isset( $member->$flag ) ) return (int) $member->$flag === 1;
            return false;
        };
        if ( ! empty( $visibility['flags_all'] ) && is_array( $visibility['flags_all'] ) ) { if ( ! $member ) return false; foreach ( $visibility['flags_all'] as $f ) { if ( ! $flag_val( $f ) ) return false; } }
        if ( ! empty( $visibility['flags_any'] ) && is_array( $visibility['flags_any'] ) ) { if ( ! $member ) return false; $any = false; foreach ( $visibility['flags_any'] as $f ) { if ( $flag_val( $f ) ) { $any = true; break; } } if ( ! $any ) return false; }
        if ( ! empty( $visibility['flags_not'] ) && is_array( $visibility['flags_not'] ) ) { if ( $member ) { foreach ( $visibility['flags_not'] as $f ) { if ( $flag_val( $f ) ) return false; } } }
        $has_flag_constraints = ! empty( $visibility['flags_all'] ) || ! empty( $visibility['flags_any'] ) || ! empty( $visibility['flags_not'] );
        $statuses = null;
        if ( array_key_exists( 'allowed_statuses', $visibility ) && is_array( $visibility['allowed_statuses'] ) ) $statuses = $visibility['allowed_statuses'];
        elseif ( ! $has_flag_constraints ) { if ( class_exists( 'TPW_Member_Access' ) ) { $statuses = TPW_Member_Access::get_allowed_statuses(); } }
        if ( is_array( $statuses ) ) { if ( ! $member ) return false; $allowed_norm = array_map( function( $s ) { return strtolower( trim( (string) $s ) ); }, $statuses ); $status = strtolower( trim( (string) ( $member->status ?? '' ) ) ); if ( ! in_array( $status, $allowed_norm, true ) ) return false; }
        if ( empty( $visibility ) ) return self::is_member();
        return is_user_logged_in();
    }

    public static function menu_url( $key ) {
        $page = get_permalink();
        $sep = strpos( $page, '?' ) === false ? '?' : '&';
        return esc_url( $page . $sep . TPW_Control::ACTION_QUERY_VAR . '=' . urlencode( $key ) );
    }
}

if ( ! function_exists( 'tpw_control_user_has_access' ) ) {
    function tpw_control_user_has_access( $visibility, $member = null ) { return TPW_Control_UI::user_has_access( $visibility, $member ); }
}

if ( ! function_exists( 'tpw_control_section_url' ) ) {
    /**
     * Build a TPW Control section URL for the current page.
     * Example: tpw_control_section_url('upload-pages') => /tpw-control/?action=upload-pages
     */
    function tpw_control_section_url( $slug ) {
        return TPW_Control_UI::menu_url( $slug );
    }
}
