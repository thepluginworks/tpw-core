<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class TPW_Member_Admin_Actions {
    public static function init() {
        // Secure admin-post handler (logged-in only)
        add_action( 'admin_post_tpw_create_wp_user', [ __CLASS__, 'handle_create_wp_user' ] );
    }

    /**
     * Handle manual creation/linking of a WP user for a member.
     * Preconditions:
     * - Nonce: tpw_create_wp_user
     * - Capability: manage_options (aligns with Members admin UI security)
     * - Member exists, has email, and has no linked user_id
     */
    public static function handle_create_wp_user() {
        // Basic auth/cap checks
        if ( ! is_user_logged_in() ) {
            wp_die( 'Permission denied.', 403 );
        }
        // Align capability with Members admin UI: WP admins always; optionally committee managers
        $can_manage = current_user_can( 'manage_options' );
        if ( ! $can_manage ) {
            $manage_setting = get_option('tpw_members_manage_access', 'admins_only');
            if ( $manage_setting === 'admins_committee' ) {
                require_once plugin_dir_path(__FILE__) . 'class-tpw-member-access.php';
                $m = TPW_Member_Access::get_member_by_user_id( get_current_user_id() );
                $can_manage = $m && ! empty($m->is_committee) && (int) $m->is_committee === 1;
            }
        }
        if ( ! $can_manage ) { wp_die( 'Permission denied.', 403 ); }
        check_admin_referer( 'tpw_create_wp_user' );

        $member_id = isset($_REQUEST['member_id']) ? (int) $_REQUEST['member_id'] : 0;
        if ( $member_id <= 0 ) {
            wp_die( 'Invalid member.', 400 );
        }

        require_once plugin_dir_path(__FILE__) . 'class-tpw-member-controller.php';
        require_once plugin_dir_path(__FILE__) . 'class-tpw-member-roles.php';

        $controller = new TPW_Member_Controller();
        $member = $controller->get_member( $member_id );
        if ( ! $member ) {
            wp_die( 'Invalid member.', 404 );
        }
        $email = isset($member->email) ? trim((string) $member->email) : '';
        if ( $email === '' || ! is_email( $email ) ) {
            wp_die( 'Invalid member or email.', 400 );
        }
        if ( ! empty( $member->user_id ) ) {
            wp_die( 'Member already linked to a WordPress user.', 400 );
        }

        // If a WP user already exists for this email, link it; else create a new one
        $existing = get_user_by( 'email', $email );
        if ( $existing && isset($existing->ID) ) {
            $user_id = (int) $existing->ID;
        } else {
            // Derive username from member.username or email local part; ensure uniqueness
            $desired = isset($member->username) ? (string) $member->username : '';
            $user_login = $desired;
            if ( $user_login === '' || username_exists( $user_login ) || strlen( $user_login ) > 60 ) {
                $user_login = sanitize_user( current( explode( '@', $email ) ), true );
            }
            if ( $user_login === '' ) {
                $user_login = 'member_' . wp_generate_password( 8, false, false );
            }
            // Prepare display names
            $first = isset($member->first_name) ? (string) $member->first_name : '';
            $last  = isset($member->surname) ? (string) $member->surname : '';
            $display_name = trim( $first . ' ' . $last );

            $user_id = wp_insert_user( [
                'user_login'   => $user_login,
                'user_email'   => $email,
                'user_pass'    => wp_generate_password(),
                'display_name' => $display_name,
                'first_name'   => $first,
                'last_name'    => $last,
                // Keep role assignment minimal; ensure caps via TPW_Member_Roles below
                'role'         => 'member',
            ] );

            if ( is_wp_error( $user_id ) ) {
                wp_die( 'Failed to create user: ' . esc_html( $user_id->get_error_message() ) );
            }

            // Ensure member capabilities are applied
            TPW_Member_Roles::ensure_member_cap( (int) $user_id );
        }

        // Link the WP user to this member
        $updated = $controller->update_member( $member_id, [ 'user_id' => (int) $user_id ] );
        if ( $updated === false ) {
            wp_die( 'Failed to link user to member.', 500 );
        }
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            @error_log('[TPW Members] WP user manually created/linked for Member #' . (int) $member_id . ' (' . $email . ') user_id=' . (int) $user_id );
        }

        // Optionally send credentials email using template system
        $send_creds = isset($_REQUEST['send_credentials']) && $_REQUEST['send_credentials'] === '1';
        if ( $send_creds && class_exists('TPW_Email') && class_exists('TPW_Email_Template_Registry') ) {
            // Resolve friendly member login URLs (no tokens to append; both identical)
            $member_login_url = site_url( '/member-login/' );
            $org = (string) get_option( 'tpw_brand_title', '' );
            if ( $org === '' ) { $org = wp_specialchars_decode( get_bloginfo('name'), ENT_QUOTES ); }
            $tokens = [
                '{member_first_name}'  => isset($member->first_name) ? (string) $member->first_name : '',
                '{member_last_name}'   => isset($member->surname) ? (string) $member->surname : '',
                '{site_name}'          => wp_specialchars_decode( get_bloginfo('name'), ENT_QUOTES ),
                '{member_login_url}'   => $member_login_url,
                '{password_reset_url}' => $member_login_url,
                '{organisation_name}'  => $org,
            ];
            // Sender details from site settings
            $from = [
                'name'  => $org,
                'email' => get_option( 'admin_email' ),
            ];
            // Do not send copy to sender by default for credential emails
            TPW_Email::send_with_template( $email, $from, 'member_new_wp_user_created', $tokens, [], false );
        }

        // Redirect back with success flag
        $ref = wp_get_referer();
        if ( ! $ref ) {
            $ref = site_url( '/manage-members/?action=edit_form&id=' . $member_id );
        }
        $url = add_query_arg( 'wp_user_created', '1', $ref );
        wp_safe_redirect( $url );
        exit;
    }
}
