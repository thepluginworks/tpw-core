<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Centralised TPW Core email settings manager.
 *
 * Storage: Single option row 'tpw_core_email_settings' (array)
 * Access:  TPW_Core_Email_Settings::get( $key = null )
 * Update:  TPW_Core_Email_Settings::update( $new_settings_array )
 */
class TPW_Core_Email_Settings {
	/**
	 * Option key for storing settings
	 */
	const OPTION_KEY = 'tpw_core_email_settings';

	/**
	 * Retrieve settings.
	 * - When $key is null: return the full array merged with defaults.
	 * - When $key is provided: return that specific value (or default if missing).
	 *
	 * @param string|null $key
	 * @return mixed Array when $key is null, otherwise scalar value for the key
	 */
	public static function get( $key = null ) {
		$defaults = self::get_default_settings();
		$current  = get_option( self::OPTION_KEY, [] );
		// Merge user settings over defaults to ensure all keys are present
		$merged   = wp_parse_args( is_array($current) ? $current : [], $defaults );
		// Back-compat: if old 'default_logo_url' exists and new 'fallback_logo_url' is empty, map it at read time.
		if ( empty( $merged['fallback_logo_url'] ) && ! empty( $merged['default_logo_url'] ) ) {
			$merged['fallback_logo_url'] = (string) $merged['default_logo_url'];
		}
		if ( $key === null ) {
			return $merged;
		}
		$key = (string) $key;
		return array_key_exists( $key, $merged ) ? $merged[ $key ] : ( array_key_exists( $key, $defaults ) ? $defaults[ $key ] : null );
	}

	/**
	 * Update settings by merging with existing values and defaults.
	 * Performs light validation/coercion for data types.
	 *
	 * @param array $new_settings
	 * @return array The saved, merged settings.
	 */
	public static function update( $new_settings ) {
		$defaults = self::get_default_settings();
		$current  = get_option( self::OPTION_KEY, [] );
		$current  = is_array($current) ? $current : [];
		$incoming = is_array($new_settings) ? $new_settings : [];

		// Coerce and sanitise known keys
		$clean = [];
		if ( array_key_exists('enable_throttling', $incoming) ) {
			$clean['enable_throttling'] = (bool) $incoming['enable_throttling'];
		}
		if ( array_key_exists('max_emails_per_minute', $incoming) ) {
			$clean['max_emails_per_minute'] = max( 1, (int) $incoming['max_emails_per_minute'] );
		}
		if ( array_key_exists('delay_between_emails', $incoming) ) {
			$clean['delay_between_emails'] = max( 0, (int) $incoming['delay_between_emails'] );
		}
		if ( array_key_exists('enable_logging', $incoming) ) {
			$clean['enable_logging'] = (bool) $incoming['enable_logging'];
		}
		if ( array_key_exists('send_test_mode', $incoming) ) {
			$clean['send_test_mode'] = (bool) $incoming['send_test_mode'];
		}
		if ( array_key_exists('test_mode_recipient', $incoming) ) {
			$clean['test_mode_recipient'] = sanitize_text_field( (string) $incoming['test_mode_recipient'] );
		}
		if ( array_key_exists('default_logo_url', $incoming) ) {
			$clean['default_logo_url'] = esc_url_raw( (string) $incoming['default_logo_url'] );
		}
		if ( array_key_exists('fallback_logo_url', $incoming) ) {
			$clean['fallback_logo_url'] = esc_url_raw( (string) $incoming['fallback_logo_url'] );
		}
		if ( array_key_exists('fallback_logo_base64', $incoming) ) {
			$val = (string) $incoming['fallback_logo_base64'];
			// Accept only data URI for images (basic validation)
			if ( $val === '' || preg_match( '#^data:image\/(png|jpeg);base64,#i', $val ) ) {
				$clean['fallback_logo_base64'] = $val;
			}
		}
		if ( array_key_exists('embed_logo_base64', $incoming) ) {
			$clean['embed_logo_base64'] = (bool) $incoming['embed_logo_base64'];
		}

		$merged = wp_parse_args( $clean, wp_parse_args( $current, $defaults ) );
		update_option( self::OPTION_KEY, $merged );
		return $merged;
	}

	/**
	 * Default settings for TPW Core email.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return [
			'enable_throttling'    => true,
			'max_emails_per_minute'=> 60,
			'delay_between_emails' => 1,
			'enable_logging'       => true,
			'send_test_mode'       => false,
			'test_mode_recipient'  => '',
			'default_logo_url'     => '', // legacy key (read-only for back-compat)
			'fallback_logo_url'    => '',
			'fallback_logo_base64' => '',
			'embed_logo_base64'    => false,
		];
	}
}
