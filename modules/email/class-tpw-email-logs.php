<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Email_Logs {
	const TABLE = 'tpw_email_logs';
	const DB_VERSION = '1.0.0';
	const DB_VERSION_OPTION = 'tpw_email_logs_db_version';
	const CLEANUP_HOOK = 'tpw_email_logs_cleanup';
	const DEFAULT_RETENTION_DAYS = 30;
	const DEFAULT_LIMIT = 100;

	public static function init() {
		add_action( 'tpw_email/log', [ __CLASS__, 'handle_log' ], 10, 1 );
		add_action( 'init', [ __CLASS__, 'maybe_install' ], 20 );
		add_action( 'init', [ __CLASS__, 'schedule_cleanup' ], 20 );
		add_action( self::CLEANUP_HOOK, [ __CLASS__, 'cleanup_old_logs' ] );
	}

	public static function maybe_install() {
		$current = (string) get_option( self::DB_VERSION_OPTION, '' );

		if ( version_compare( $current, self::DB_VERSION, '>=' ) ) {
			return;
		}

		self::create_table();
	}

	public static function create_table() {
		global $wpdb;

		$table_name = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			timestamp DATETIME NOT NULL,
			recipient VARCHAR(255) NOT NULL DEFAULT '',
			subject VARCHAR(255) NOT NULL DEFAULT '',
			context VARCHAR(191) DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'sent',
			error_message TEXT DEFAULT NULL,
			duration_ms INT UNSIGNED DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY timestamp (timestamp),
			KEY status (status),
			KEY recipient (recipient)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function schedule_cleanup() {
		if ( wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
	}

	public static function unschedule_cleanup() {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	public static function handle_log( $details ) {
		if ( ! is_array( $details ) ) {
			return;
		}

		self::insert( $details );
	}

	public static function insert( array $details ) {
		global $wpdb;

		$table_name = self::table_name();
		$timestamp = isset( $details['timestamp'] ) && is_string( $details['timestamp'] ) && $details['timestamp'] !== ''
			? $details['timestamp']
			: gmdate( 'Y-m-d H:i:s' );
		$duration_ms = isset( $details['duration_ms'] ) && is_numeric( $details['duration_ms'] )
			? max( 0, (int) $details['duration_ms'] )
			: null;
		$error_message = self::normalise_error_message( isset( $details['error_message'] ) ? $details['error_message'] : '' );
		$status = isset( $details['sent'] )
			? ( $details['sent'] ? 'sent' : 'failed' )
			: ( isset( $details['status'] ) ? sanitize_key( (string) $details['status'] ) : 'sent' );

		$row = [
			'timestamp' => $timestamp,
			'recipient' => self::normalise_recipient( isset( $details['to'] ) ? $details['to'] : ( isset( $details['recipient'] ) ? $details['recipient'] : '' ) ),
			'subject' => self::truncate_string( wp_strip_all_tags( (string) ( $details['subject'] ?? '' ) ), 255 ),
			'context' => self::normalise_context( $details ),
			'status' => in_array( $status, [ 'sent', 'failed' ], true ) ? $status : 'sent',
			'error_message' => $error_message,
			'duration_ms' => $duration_ms,
		];

		$formats = [ '%s', '%s', '%s', '%s', '%s', '%s', '%d' ];

		try {
			$wpdb->insert( $table_name, $row, $formats );
		} catch ( \Throwable $exception ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'TPW Core email logging failed: ' . $exception->getMessage() );
			}
		}
	}

	public static function get_recent( $limit = self::DEFAULT_LIMIT ) {
		global $wpdb;

		$limit = max( 1, min( 500, (int) $limit ) );
		$table_name = self::table_name();

		return $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY timestamp DESC, id DESC LIMIT {$limit}" );
	}

	public static function clear_all() {
		global $wpdb;

		$table_name = self::table_name();
		return (bool) $wpdb->query( "TRUNCATE TABLE {$table_name}" );
	}

	public static function cleanup_old_logs() {
		global $wpdb;

		$retention_days = max( 1, (int) apply_filters( 'tpw_email_logs/retention_days', self::DEFAULT_RETENTION_DAYS ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );
		$table_name = self::table_name();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE timestamp < %s",
				$cutoff
			)
		);
	}

	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	public static function format_display_timestamp( $timestamp ) {
		if ( ! is_string( $timestamp ) || $timestamp === '' ) {
			return '';
		}

		return get_date_from_gmt( $timestamp, 'Y-m-d H:i:s' );
	}

	protected static function normalise_recipient( $recipient ) {
		if ( is_array( $recipient ) ) {
			$values = array_map( [ __CLASS__, 'normalise_recipient' ], $recipient );
			$values = array_values( array_filter( array_map( 'trim', $values ) ) );
			return self::truncate_string( implode( ', ', $values ), 255 );
		}

		if ( ! is_scalar( $recipient ) ) {
			return '';
		}

		$recipient = trim( wp_strip_all_tags( (string) $recipient ) );
		if ( is_email( $recipient ) ) {
			return $recipient;
		}

		return self::truncate_string( $recipient, 255 );
	}

	protected static function normalise_context( array $details ) {
		if ( isset( $details['context'] ) && is_string( $details['context'] ) ) {
			return self::truncate_string( sanitize_text_field( $details['context'] ), 191 );
		}

		if ( isset( $details['source'] ) && is_string( $details['source'] ) ) {
			return self::truncate_string( sanitize_text_field( $details['source'] ), 191 );
		}

		return '';
	}

	protected static function normalise_error_message( $message ) {
		if ( is_wp_error( $message ) ) {
			$message = implode( '; ', $message->get_error_messages() );
		}

		if ( ! is_scalar( $message ) ) {
			return null;
		}

		$message = sanitize_textarea_field( (string) $message );
		return $message === '' ? null : $message;
	}

	protected static function truncate_string( $value, $max_length ) {
		$value = (string) $value;
		if ( strlen( $value ) <= $max_length ) {
			return $value;
		}

		return substr( $value, 0, $max_length );
	}
}
