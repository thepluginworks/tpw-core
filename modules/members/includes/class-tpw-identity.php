<?php
/**
 * Canonical identity helper layer for TPW Core members.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes canonical member lookup and membership-state reads.
 */
class TPW_Identity {
	/**
	 * Cache resolved member lookups per user ID for the current request.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected static $resolution_cache = array();

	/**
	 * Check whether a user resolves to a TPW member record.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	public static function has_member_record( $user_id ) {
		return null !== self::get_member_record( $user_id );
	}

	/**
	 * Resolve the effective TPW member record for a user.
	 *
	 * This preserves the current Core compatibility path: direct `user_id`
	 * linkage first, then the existing optional email and username fallbacks.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return object|null
	 */
	public static function get_member_record( $user_id ) {
		$resolution = self::resolve_member_record( $user_id );

		if ( isset( $resolution['member'] ) && is_object( $resolution['member'] ) ) {
			return $resolution['member'];
		}

		return null;
	}

	/**
	 * Get the linked TPW member ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int|null
	 */
	public static function get_member_id( $user_id ) {
		$member = self::get_member_record( $user_id );

		if ( ! $member || ! isset( $member->id ) ) {
			return null;
		}

		return (int) $member->id;
	}

	/**
	 * Get the raw stored member status.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string|null
	 */
	public static function get_member_status( $user_id ) {
		$member = self::get_member_record( $user_id );

		if ( ! $member || ! isset( $member->status ) ) {
			return null;
		}

		$status = trim( (string) $member->status );

		return '' !== $status ? $status : null;
	}

	/**
	 * Get a conservative normalized status key.
	 *
	 * Known historical variants are collapsed deliberately. Unknown statuses keep
	 * a stable lowercase underscore key so callers can compare safely without
	 * rewriting stored data.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string|null
	 */
	public static function get_normalized_member_status( $user_id ) {
		return self::normalize_status_key( self::get_member_status( $user_id ) );
	}

	/**
	 * Check whether a status currently confers membership.
	 *
	 * This follows the current Core allowed-status behaviour while normalizing
	 * terminology mismatches such as `Life` versus `Life Member`.
	 *
	 * @param string|null $status Raw or normalized member status.
	 * @return bool
	 */
	public static function is_membership_bearing_status( $status ) {
		$normalized_status = self::normalize_status_key( $status );

		if ( null === $normalized_status ) {
			return false;
		}

		return in_array( $normalized_status, self::get_membership_bearing_status_keys(), true );
	}

	/**
	 * Check whether a user is canonically a TPW member.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	public static function is_member( $user_id ) {
		if ( ! self::has_member_record( $user_id ) ) {
			return false;
		}

		return self::is_membership_bearing_status( self::get_member_status( $user_id ) );
	}

	/**
	 * Describe how a member record was resolved.
	 *
	 * Values are one of: `user_id`, `email_fallback`, `username_fallback`, `none`.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string
	 */
	public static function get_linkage_mode( $user_id ) {
		$resolution = self::resolve_member_record( $user_id );

		if ( isset( $resolution['linkage_mode'] ) && is_string( $resolution['linkage_mode'] ) ) {
			return $resolution['linkage_mode'];
		}

		return 'none';
	}

	/**
	 * Get the current canonical identity role projection.
	 *
	 * For Phase 2A this is limited to membership identity only.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string|null
	 */
	public static function get_identity_role( $user_id ) {
		return self::is_member( $user_id ) ? 'member' : null;
	}

	/**
	 * Resolve the current member lookup result for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string, mixed>
	 */
	protected static function resolve_member_record( $user_id ) {
		$user_id = self::normalize_user_id( $user_id );

		if ( isset( self::$resolution_cache[ $user_id ] ) ) {
			return self::$resolution_cache[ $user_id ];
		}

		$resolution = array(
			'user_id'      => $user_id,
			'member'       => null,
			'linkage_mode' => 'none',
		);

		if ( $user_id <= 0 || ! self::ensure_member_access_loaded() ) {
			self::$resolution_cache[ $user_id ] = $resolution;

			return $resolution;
		}

		if ( method_exists( 'TPW_Member_Access', 'get_member_by_user_id' ) ) {
			$member = TPW_Member_Access::get_member_by_user_id( $user_id );

			if ( $member ) {
				$resolution['member']       = $member;
				$resolution['linkage_mode'] = 'user_id';

				self::$resolution_cache[ $user_id ] = $resolution;

				return $resolution;
			}
		}

		$user = self::get_user_object( $user_id );

		if ( ! $user ) {
			self::$resolution_cache[ $user_id ] = $resolution;

			return $resolution;
		}

		if ( self::is_email_fallback_enabled() && ! empty( $user->user_email ) && method_exists( 'TPW_Member_Access', 'get_member_by_email' ) ) {
			$member = TPW_Member_Access::get_member_by_email( (string) $user->user_email );

			if ( $member ) {
				$resolution['member']       = $member;
				$resolution['linkage_mode'] = 'email_fallback';

				self::$resolution_cache[ $user_id ] = $resolution;

				return $resolution;
			}
		}

		if ( self::is_username_fallback_enabled() && ! empty( $user->user_login ) && method_exists( 'TPW_Member_Access', 'get_member_by_username' ) ) {
			$member = TPW_Member_Access::get_member_by_username( (string) $user->user_login );

			if ( $member ) {
				$resolution['member']       = $member;
				$resolution['linkage_mode'] = 'username_fallback';
			}
		}

		self::$resolution_cache[ $user_id ] = $resolution;

		return $resolution;
	}

	/**
	 * Ensure the legacy member-access helper is available.
	 *
	 * @return bool
	 */
	protected static function ensure_member_access_loaded() {
		if ( class_exists( 'TPW_Member_Access', false ) ) {
			return true;
		}

		$access_file = plugin_dir_path( __FILE__ ) . 'class-tpw-member-access.php';

		if ( file_exists( $access_file ) ) {
			require_once $access_file;
		}

		return class_exists( 'TPW_Member_Access', false );
	}

	/**
	 * Normalize a user ID and optionally fall back to the current user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int
	 */
	protected static function normalize_user_id( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id > 0 ) {
			return $user_id;
		}

		if ( function_exists( 'is_user_logged_in' ) && function_exists( 'get_current_user_id' ) && is_user_logged_in() ) {
			return (int) get_current_user_id();
		}

		return 0;
	}

	/**
	 * Load a WordPress user object.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return object|null
	 */
	protected static function get_user_object( $user_id ) {
		if ( $user_id <= 0 || ! function_exists( 'get_userdata' ) ) {
			return null;
		}

		$user = get_userdata( $user_id );

		return is_object( $user ) ? $user : null;
	}

	/**
	 * Determine whether email fallback is enabled.
	 *
	 * @return bool
	 */
	protected static function is_email_fallback_enabled() {
		if ( function_exists( 'apply_filters' ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Preserves the existing public compatibility hook.
			return (bool) apply_filters( 'tpw_members/allow_email_match_for_member', true );
		}

		return true;
	}

	/**
	 * Determine whether username fallback is enabled.
	 *
	 * @return bool
	 */
	protected static function is_username_fallback_enabled() {
		if ( function_exists( 'apply_filters' ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Preserves the existing public compatibility hook.
			return (bool) apply_filters( 'tpw_members/allow_username_match_for_member', true );
		}

		return true;
	}

	/**
	 * Get the current membership-bearing status keys.
	 *
	 * @return string[]
	 */
	protected static function get_membership_bearing_status_keys() {
		$statuses = array( 'Active', 'Honorary', 'Life Member' );

		if ( self::ensure_member_access_loaded() && method_exists( 'TPW_Member_Access', 'get_allowed_statuses' ) ) {
			$statuses = TPW_Member_Access::get_allowed_statuses();
		}

		$normalized = array();

		foreach ( (array) $statuses as $status ) {
			$status_key = self::normalize_status_key( $status );

			if ( null !== $status_key ) {
				$normalized[ $status_key ] = $status_key;
			}
		}

		return array_values( $normalized );
	}

	/**
	 * Normalize a member status into a stable comparison key.
	 *
	 * @param string|null $status Raw status label.
	 * @return string|null
	 */
	protected static function normalize_status_key( $status ) {
		$status = trim( (string) $status );

		if ( '' === $status ) {
			return null;
		}

		$normalized = strtolower( preg_replace( '/\s+/', ' ', $status ) );
		$map        = array(
			'active'      => 'active',
			'honorary'    => 'honorary',
			'life'        => 'life_member',
			'life member' => 'life_member',
			'pending'     => 'pending',
			'inactive'    => 'inactive',
			'resigned'    => 'resigned',
			'deceased'    => 'deceased',
			'junior'      => 'junior',
			'student'     => 'student',
		);

		if ( isset( $map[ $normalized ] ) ) {
			return $map[ $normalized ];
		}

		$normalized = self::normalize_key( $normalized );

		return '' !== $normalized ? $normalized : null;
	}

	/**
	 * Normalize a string into a lowercase underscore key.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected static function normalize_key( $value ) {
		$value = strtolower( trim( (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		$value = str_replace( '-', '_', $value );
		$value = preg_replace( '/[^a-z0-9_]+/', '_', $value );
		$value = preg_replace( '/_+/', '_', $value );

		return trim( $value, '_' );
	}
}
