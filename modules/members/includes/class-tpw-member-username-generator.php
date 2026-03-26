<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Member_Username_Generator {
	const MAX_USER_LOGIN_LENGTH = 60;
	const SYSTEM_USERNAME_BASE  = 'tpwmember';

	/**
	 * Resolve a unique login for a newly-created WordPress user.
	 *
	 * Normal mode ignores any supplied candidate and generates usernames from the
	 * member name data. Import preserve mode may supply a preferred candidate.
	 *
	 * @param string $preferred_candidate Preferred username base for import preserve mode.
	 * @param bool   $preserve_preferred  Whether to preserve the preferred candidate.
	 * @param int    $max_length          Maximum user_login length.
	 * @param string $first_name          Preferred first name for generated usernames.
	 * @param string $surname             Preferred surname for generated usernames.
	 * @return string
	 */
	public static function resolve_new_user_login( $preferred_candidate = '', $preserve_preferred = false, $max_length = self::MAX_USER_LOGIN_LENGTH, $first_name = '', $surname = '' ) {
		$max_length = (int) $max_length;
		if ( $max_length <= 0 ) {
			$max_length = self::MAX_USER_LOGIN_LENGTH;
		}

		$user_login = '';
		if ( $preserve_preferred ) {
			$user_login = self::normalize_candidate( $preferred_candidate, $max_length );
		}

		if ( '' === $user_login ) {
			if ( ! $preserve_preferred ) {
				$user_login = self::normalize_candidate( self::build_name_based_candidate( $first_name, $surname ), $max_length );
			}

			if ( '' === $user_login ) {
				$user_login = self::normalize_candidate( self::SYSTEM_USERNAME_BASE, $max_length );
			}
		}

		if ( '' === $user_login ) {
			return '';
		}

		if ( ! username_exists( $user_login ) ) {
			return $user_login;
		}

		$index = 2;
		while ( $index < 1000 ) {
			$suffix          = (string) $index;
			$base_max_length = max( 1, $max_length - strlen( $suffix ) );
			$base_login      = substr( $user_login, 0, $base_max_length );
			$candidate       = $base_login . $suffix;

			if ( ! username_exists( $candidate ) ) {
				return $candidate;
			}

			++$index;
		}

		return '';
	}

	/**
	 * Normalize a candidate into a WordPress-safe user_login.
	 *
	 * @param string $candidate  Candidate username.
	 * @param int    $max_length Maximum user_login length.
	 * @return string
	 */
	private static function normalize_candidate( $candidate, $max_length ) {
		$user_login = sanitize_user( (string) $candidate, true );
		if ( '' === $user_login ) {
			return '';
		}

		return substr( $user_login, 0, $max_length );
	}

	/**
	 * Build a generated username base from available member name data.
	 *
	 * @param string $first_name First name.
	 * @param string $surname    Surname.
	 * @return string
	 */
	private static function build_name_based_candidate( $first_name, $surname ) {
		$first_name = trim( (string) $first_name );
		$surname = trim( (string) $surname );
		$first_initial = '';

		if ( function_exists( 'mb_substr' ) ) {
			if ( '' !== $first_name ) {
				$first_initial = mb_substr( $first_name, 0, 1 );
			}
		} elseif ( '' !== $first_name ) {
			$first_initial = substr( $first_name, 0, 1 );
		}

		if ( '' !== $first_initial && '' !== $surname ) {
			return strtolower( $first_initial . $surname );
		}

		if ( '' !== $surname ) {
			return strtolower( $surname );
		}

		if ( '' !== $first_initial ) {
			return strtolower( $first_initial );
		}

		return '';
	}
}
