<?php
/**
 * Core sign-up section registry.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Sections {
	/**
	 * Get the fixed core sign-up sections.
	 *
	 * @return array<string, string>
	 */
	public static function get_sections() {
		return array(
			'account_details'   => __( 'Account Details', 'tpw-core' ),
			'personal_details'  => __( 'Personal Details', 'tpw-core' ),
			'address'           => __( 'Address', 'tpw-core' ),
			'emergency_contact' => __( 'Emergency Contact', 'tpw-core' ),
		);
	}

	/**
	 * Check whether a section key is valid.
	 *
	 * @param string $section_key Section key.
	 * @return bool
	 */
	public static function is_valid_section( $section_key ) {
		$section_key = sanitize_key( $section_key );

		return isset( self::get_sections()[ $section_key ] );
	}

	/**
	 * Get a section label by key.
	 *
	 * @param string $section_key Section key.
	 * @return string
	 */
	public static function get_section_label( $section_key ) {
		$section_key = sanitize_key( $section_key );
		$sections    = self::get_sections();

		return isset( $sections[ $section_key ] ) ? $sections[ $section_key ] : '';
	}
}