<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Member_Password_Setup {
	/**
	 * Send a TPW-branded password setup email using a WordPress native reset key.
	 *
	 * @param object|null        $member  TPW member row when available.
	 * @param WP_User|int|object $user    WordPress user object or user ID.
	 * @return array{success:bool,message:string,setup_url?:string}
	 */
	public static function send_password_setup_email( $member, $user ) {
		$wp_user = self::resolve_wp_user( $user );
		if ( ! $wp_user ) {
			return [
				'success' => false,
				'message' => __( 'Linked WordPress user could not be loaded.', 'tpw-core' ),
			];
		}

		$recipient = sanitize_email( (string) $wp_user->user_email );
		if ( '' === $recipient || ! is_email( $recipient ) ) {
			return [
				'success' => false,
				'message' => __( 'Linked WordPress user does not have a valid email address.', 'tpw-core' ),
			];
		}

		if ( ! function_exists( 'get_password_reset_key' ) ) {
			return [
				'success' => false,
				'message' => __( 'Password reset functions are unavailable.', 'tpw-core' ),
			];
		}

		$reset_key = get_password_reset_key( $wp_user );
		if ( is_wp_error( $reset_key ) ) {
			return [
				'success' => false,
				'message' => $reset_key->get_error_message(),
			];
		}

		$setup_url = self::build_password_setup_url( (string) $reset_key, (string) $wp_user->user_login );
		if ( '' === $setup_url ) {
			return [
				'success' => false,
				'message' => __( 'Password setup URL could not be generated.', 'tpw-core' ),
			];
		}

		if ( ! class_exists( 'TPW_Email' ) ) {
			return [
				'success' => false,
				'message' => __( 'TPW email service is unavailable.', 'tpw-core' ),
			];
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$org_name  = (string) get_option( 'tpw_brand_title', '' );
		if ( '' === trim( $org_name ) ) {
			$org_name = $site_name;
		}

		$from = [
			'name'  => $org_name,
			'email' => get_option( 'admin_email' ),
		];

		$result = TPW_Email::send_with_template(
			$recipient,
			$from,
			'member_password_setup',
			[
				'{member_name}'        => self::resolve_member_name( $member, $wp_user ),
				'{member_first_name}'  => self::resolve_member_first_name( $member, $wp_user ),
				'{password_setup_url}' => $setup_url,
				'{setup_reset_url}'    => $setup_url,
				'{member_login_url}'   => self::get_member_login_url(),
				'{site_name}'          => $site_name,
				'{organisation_name}'  => $org_name,
			],
			[],
			false
		);

		if ( ! is_array( $result ) ) {
			return [
				'success' => false,
				'message' => __( 'Password setup email could not be sent.', 'tpw-core' ),
			];
		}

		$result['setup_url'] = $setup_url;
		return $result;
	}

	/**
	 * Build the front-end password setup URL used by the member login shortcode.
	 *
	 * @param string $reset_key  WordPress reset key.
	 * @param string $user_login WordPress user login.
	 * @return string
	 */
	public static function build_password_setup_url( $reset_key, $user_login ) {
		$reset_key  = (string) $reset_key;
		$user_login = (string) $user_login;
		if ( '' === $reset_key || '' === $user_login ) {
			return '';
		}

		$url = add_query_arg(
			[
				'action' => 'rp',
				'key'    => $reset_key,
				'login'  => $user_login,
			],
			self::get_member_login_url()
		);

		return esc_url_raw( $url );
	}

	/**
	 * Resolve the configured front-end member login page URL.
	 *
	 * @return string
	 */
	public static function get_member_login_url() {
		if ( class_exists( 'TPW_Core_System_Pages' ) && method_exists( 'TPW_Core_System_Pages', 'get' ) ) {
			$url = TPW_Core_System_Pages::get( 'member-login' );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return site_url( '/member-login/' );
	}

	/**
	 * @param WP_User|int|object $user WordPress user object or user ID.
	 * @return WP_User|null
	 */
	protected static function resolve_wp_user( $user ) {
		if ( $user instanceof WP_User ) {
			return $user;
		}

		if ( is_object( $user ) && isset( $user->ID ) ) {
			$user = (int) $user->ID;
		}

		$user_id = (int) $user;
		if ( $user_id <= 0 ) {
			return null;
		}

		$user_obj = get_userdata( $user_id );
		return ( $user_obj instanceof WP_User ) ? $user_obj : null;
	}

	/**
	 * @param object|null $member  TPW member row when available.
	 * @param WP_User     $wp_user WordPress user.
	 * @return string
	 */
	protected static function resolve_member_name( $member, WP_User $wp_user ) {
		$first_name = '';
		$last_name  = '';

		if ( is_object( $member ) ) {
			$first_name = isset( $member->first_name ) ? trim( (string) $member->first_name ) : '';
			$last_name  = isset( $member->surname ) ? trim( (string) $member->surname ) : '';
		}

		if ( '' === $first_name ) {
			$first_name = isset( $wp_user->first_name ) ? trim( (string) $wp_user->first_name ) : '';
		}
		if ( '' === $last_name ) {
			$last_name = isset( $wp_user->last_name ) ? trim( (string) $wp_user->last_name ) : '';
		}

		$name = trim( $first_name . ' ' . $last_name );
		if ( '' !== $name ) {
			return $name;
		}

		if ( isset( $wp_user->display_name ) && '' !== trim( (string) $wp_user->display_name ) ) {
			return trim( (string) $wp_user->display_name );
		}

		return isset( $wp_user->user_login ) ? (string) $wp_user->user_login : '';
	}

	/**
	 * @param object|null $member  TPW member row when available.
	 * @param WP_User     $wp_user WordPress user.
	 * @return string
	 */
	protected static function resolve_member_first_name( $member, WP_User $wp_user ) {
		if ( is_object( $member ) && isset( $member->first_name ) && '' !== trim( (string) $member->first_name ) ) {
			return trim( (string) $member->first_name );
		}

		if ( isset( $wp_user->first_name ) && '' !== trim( (string) $wp_user->first_name ) ) {
			return trim( (string) $wp_user->first_name );
		}

		return self::resolve_member_name( $member, $wp_user );
	}
}