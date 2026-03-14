<?php
/**
 * Sign-up attempts database schema installer.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Attempts_DB {
	/**
	 * Create or upgrade the sign-up attempts table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'tpw_signup_attempts';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_token char(64) NOT NULL,
			flow_key varchar(100) NOT NULL,
			plugin_key varchar(100) NOT NULL,
			status varchar(40) NOT NULL,
			email varchar(190) NOT NULL,
			first_name varchar(100) DEFAULT NULL,
			last_name varchar(100) DEFAULT NULL,
			request_fingerprint char(64) DEFAULT NULL,
			gateway varchar(100) DEFAULT NULL,
			amount bigint(20) unsigned DEFAULT NULL,
			currency_code char(3) DEFAULT NULL,
			request_payload_json longtext DEFAULT NULL,
			retry_payload_json longtext DEFAULT NULL,
			result_payload_json longtext DEFAULT NULL,
			payment_provider varchar(100) DEFAULT NULL,
			payment_reference varchar(190) DEFAULT NULL,
			payment_status varchar(40) DEFAULT NULL,
			payment_receipt_reference varchar(190) DEFAULT NULL,
			payment_result_code varchar(100) DEFAULT NULL,
			last_error_code varchar(100) DEFAULT NULL,
			last_error_message text DEFAULT NULL,
			payment_attempt_count int unsigned NOT NULL DEFAULT 0,
			retry_count int unsigned NOT NULL DEFAULT 0,
			finalization_attempt_count int unsigned NOT NULL DEFAULT 0,
			lock_token char(64) DEFAULT NULL,
			locked_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			last_activity_at datetime DEFAULT NULL,
			payment_started_at datetime DEFAULT NULL,
			payment_completed_at datetime DEFAULT NULL,
			finalization_started_at datetime DEFAULT NULL,
			finalization_completed_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			expired_at datetime DEFAULT NULL,
			abandoned_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_token (public_token),
			KEY flow_key_status (flow_key, status),
			KEY plugin_key_status (plugin_key, status),
			KEY email_status (email, status),
			KEY request_fingerprint (request_fingerprint),
			KEY gateway_status (gateway, status),
			KEY payment_reference (payment_reference),
			KEY payment_provider_status (payment_provider, payment_status),
			KEY lock_token (lock_token),
			KEY expires_at (expires_at),
			KEY last_activity_at (last_activity_at),
			KEY created_at (created_at),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Ensure the schema exists on upgraded installs.
	 *
	 * @return void
	 */
	public static function ensure_core_schema() {
		self::create_table();
	}
}
