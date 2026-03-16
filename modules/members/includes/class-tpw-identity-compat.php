<?php
/**
 * Compatibility helper layer for legacy TPW identity-adjacent access.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes compatibility-era flag and role helpers.
 */
class TPW_Identity_Compat {
	/**
	 * Check whether a linked member record has a legacy flag enabled.
	 *
	 * This intentionally reads the current member-table compatibility flags via
	 * the central identity lookup rather than reintroducing direct raw table reads
	 * into callers.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $flag    Legacy flag alias.
	 * @return bool
	 */
	public static function has_member_flag( $user_id, $flag ) {
		$member_field = self::map_member_flag_to_field( $flag );

		if ( '' === $member_field ) {
			return false;
		}

		$member = TPW_Identity::get_member_record( $user_id );

		return ( $member && isset( $member->$member_field ) && 1 === (int) $member->$member_field );
	}

	/**
	 * Check whether a user currently has a legacy WordPress role slug.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $role    Role slug.
	 * @return bool
	 */
	public static function has_legacy_role( $user_id, $role ) {
		$role = self::normalize_key( $role );

		if ( '' === $role ) {
			return false;
		}

		return in_array( $role, self::get_legacy_roles( $user_id ), true );
	}

	/**
	 * Get the current WordPress roles assigned to a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string[]
	 */
	public static function get_legacy_roles( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 || ! function_exists( 'get_userdata' ) ) {
			return array();
		}

		$user = get_userdata( $user_id );

		if ( ! is_object( $user ) || ! isset( $user->roles ) || ! is_array( $user->roles ) ) {
			return array();
		}

		$roles = array();

		foreach ( $user->roles as $role ) {
			$normalized = self::normalize_key( $role );

			if ( '' !== $normalized ) {
				$roles[ $normalized ] = $normalized;
			}
		}

		$roles = array_values( $roles );
		sort( $roles, SORT_STRING );

		return $roles;
	}

	/**
	 * Check a limited set of compatibility identity aliases.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $alias   Identity alias.
	 * @return bool
	 */
	public static function matches_identity_alias( $user_id, $alias ) {
		$alias = self::normalize_identity_alias( $alias );

		if ( null === $alias ) {
			return false;
		}

		return TPW_Identity::get_identity_role( $user_id ) === $alias;
	}

	/**
	 * Map a legacy flag alias to the current member-table field name.
	 *
	 * @param string $flag Legacy flag alias.
	 * @return string
	 */
	protected static function map_member_flag_to_field( $flag ) {
		$flag = self::normalize_key( $flag );

		$map = array(
			'admin'                => 'is_admin',
			'is_admin'             => 'is_admin',
			'committee'            => 'is_committee',
			'is_committee'         => 'is_committee',
			'match_manager'        => 'is_match_manager',
			'is_match_manager'     => 'is_match_manager',
			'noticeboard_admin'    => 'is_noticeboard_admin',
			'is_noticeboard_admin' => 'is_noticeboard_admin',
			'gallery_admin'        => 'is_gallery_admin',
			'is_gallery_admin'     => 'is_gallery_admin',
			'manage_members'       => 'is_manage_members',
			'is_manage_members'    => 'is_manage_members',
			'volunteer'            => 'is_volunteer',
			'is_volunteer'         => 'is_volunteer',
		);

		return isset( $map[ $flag ] ) ? $map[ $flag ] : '';
	}

	/**
	 * Normalize a compatibility identity alias.
	 *
	 * @param string $alias Identity alias.
	 * @return string|null
	 */
	protected static function normalize_identity_alias( $alias ) {
		$alias = self::normalize_key( $alias );

		if ( '' === $alias ) {
			return null;
		}

		$map = array(
			'member'     => 'member',
			'tpw_member' => 'member',
		);

		return isset( $map[ $alias ] ) ? $map[ $alias ] : null;
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
