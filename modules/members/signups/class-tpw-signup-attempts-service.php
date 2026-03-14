<?php
/**
 * Generic sign-up attempt lifecycle service.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Attempts_Service {
	/**
	 * Singleton instance.
	 *
	 * @var TPW_Signup_Attempts_Service|null
	 */
	private static $instance = null;

	/**
	 * Allowed lifecycle statuses.
	 *
	 * @var string[]
	 */
	private $statuses = array(
		'draft',
		'payment_pending',
		'payment_failed',
		'payment_succeeded',
		'finalizing',
		'completed',
		'finalization_failed',
		'expired',
		'abandoned',
	);

	/**
	 * Allowed status transitions.
	 *
	 * @var array<string, string[]>
	 */
	private $status_transitions = array(
		'draft'               => array( 'payment_pending', 'expired', 'abandoned' ),
		'payment_pending'     => array( 'payment_failed', 'payment_succeeded', 'expired', 'abandoned' ),
		'payment_failed'      => array( 'payment_pending', 'expired', 'abandoned' ),
		'payment_succeeded'   => array( 'finalizing', 'expired', 'abandoned' ),
		'finalizing'          => array( 'completed', 'finalization_failed' ),
		'finalization_failed' => array( 'finalizing', 'expired', 'abandoned' ),
		'completed'           => array(),
		'expired'             => array(),
		'abandoned'           => array(),
	);

	/**
	 * Get the singleton instance.
	 *
	 * @return TPW_Signup_Attempts_Service
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'tpw_signup_attempts';
	}

	/**
	 * Get the allowed statuses.
	 *
	 * @return string[]
	 */
	public function get_statuses() {
		return $this->statuses;
	}

	/**
	 * Get the allowed status transition map.
	 *
	 * @return array<string, string[]>
	 */
	public function get_allowed_status_transitions() {
		return $this->status_transitions;
	}

	/**
	 * Check whether a transition is allowed.
	 *
	 * @param string $from_status Current status.
	 * @param string $to_status   Requested status.
	 * @return bool
	 */
	public function can_transition_status( $from_status, $to_status ) {
		$from_status = sanitize_key( $from_status );
		$to_status   = sanitize_key( $to_status );

		if ( ! isset( $this->status_transitions[ $from_status ] ) ) {
			return false;
		}

		return in_array( $to_status, $this->status_transitions[ $from_status ], true );
	}

	/**
	 * Create a new sign-up attempt.
	 *
	 * @param array $data Attempt data.
	 * @return array|WP_Error
	 */
	public function create_attempt( $data ) {
		global $wpdb;

		$required_fields = array( 'flow_key', 'plugin_key', 'email' );
		foreach ( $required_fields as $required_field ) {
			if ( empty( $data[ $required_field ] ) || ! is_string( $data[ $required_field ] ) ) {
				return new WP_Error( 'tpw_signup_attempt_missing_field', sprintf( 'Missing required field: %s', $required_field ) );
			}
		}

		$now             = $this->get_current_utc_datetime();
		$request_payload = isset( $data['request_payload'] ) ? $data['request_payload'] : array();
		$retry_payload   = isset( $data['retry_payload'] ) ? $data['retry_payload'] : array();
		$result_payload  = isset( $data['result_payload'] ) ? $data['result_payload'] : array();

		$row = array(
			'public_token'               => $this->generate_public_token(),
			'flow_key'                   => sanitize_text_field( $data['flow_key'] ),
			'plugin_key'                 => sanitize_text_field( $data['plugin_key'] ),
			'status'                     => 'draft',
			'email'                      => sanitize_email( $data['email'] ),
			'first_name'                 => $this->sanitize_nullable_text( $data, 'first_name' ),
			'last_name'                  => $this->sanitize_nullable_text( $data, 'last_name' ),
			'request_fingerprint'        => $this->generate_request_fingerprint( $request_payload ),
			'gateway'                    => $this->sanitize_nullable_text( $data, 'gateway' ),
			'amount'                     => $this->sanitize_nullable_absint( $data, 'amount' ),
			'currency_code'              => $this->sanitize_nullable_currency_code( $data, 'currency_code' ),
			'request_payload_json'       => $this->encode_payload( $request_payload ),
			'retry_payload_json'         => $this->encode_payload( $retry_payload ),
			'result_payload_json'        => $this->encode_payload( $result_payload ),
			'payment_provider'           => $this->sanitize_nullable_text( $data, 'payment_provider' ),
			'payment_reference'          => $this->sanitize_nullable_text( $data, 'payment_reference' ),
			'payment_status'             => null,
			'payment_receipt_reference'  => $this->sanitize_nullable_text( $data, 'payment_receipt_reference' ),
			'payment_result_code'        => $this->sanitize_nullable_text( $data, 'payment_result_code' ),
			'last_error_code'            => $this->sanitize_nullable_text( $data, 'last_error_code' ),
			'last_error_message'         => $this->sanitize_nullable_textarea( $data, 'last_error_message' ),
			'payment_attempt_count'      => 0,
			'retry_count'                => 0,
			'finalization_attempt_count' => 0,
			'lock_token'                 => null,
			'locked_at'                  => null,
			'created_at'                 => $now,
			'updated_at'                 => $now,
			'last_activity_at'           => $now,
			'payment_started_at'         => null,
			'payment_completed_at'       => null,
			'finalization_started_at'    => null,
			'finalization_completed_at'  => null,
			'expires_at'                 => $this->sanitize_nullable_datetime( $data, 'expires_at' ),
			'expired_at'                 => null,
			'abandoned_at'               => null,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Core lifecycle persistence intentionally writes directly to the attempt table.
		$inserted = $wpdb->insert(
			$this->get_table_name(),
			$row,
			$this->get_insert_formats( $row )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'tpw_signup_attempt_insert_failed', $wpdb->last_error );
		}

		$attempt_id = (int) $wpdb->insert_id;
		$this->log_attempt_event( $attempt_id, 'attempt_created', array( 'status' => 'draft' ) );

		return $this->load_attempt( $attempt_id );
	}

	/**
	 * Load an attempt by primary key.
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return array|WP_Error
	 */
	public function load_attempt( $attempt_id ) {
		return $this->load_attempt_by_column( 'id', absint( $attempt_id ), '%d' );
	}

	/**
	 * Load an attempt by public token.
	 *
	 * @param string $public_token Public token.
	 * @return array|WP_Error
	 */
	public function load_attempt_by_public_token( $public_token ) {
		$public_token = sanitize_text_field( $public_token );

		return $this->load_attempt_by_column( 'public_token', $public_token, '%s' );
	}

	/**
	 * Load an attempt by payment reference.
	 *
	 * @param string $payment_reference Payment reference.
	 * @return array|WP_Error
	 */
	public function load_attempt_by_payment_reference( $payment_reference ) {
		$payment_reference = sanitize_text_field( $payment_reference );

		return $this->load_attempt_by_column( 'payment_reference', $payment_reference, '%s' );
	}

	/**
	 * Load an attempt by request fingerprint.
	 *
	 * @param string $request_fingerprint Request fingerprint.
	 * @return array|WP_Error
	 */
	public function load_attempt_by_request_fingerprint( $request_fingerprint ) {
		$request_fingerprint = sanitize_text_field( $request_fingerprint );

		return $this->load_attempt_by_column( 'request_fingerprint', $request_fingerprint, '%s' );
	}

	/**
	 * List attempts using simple filters.
	 *
	 * @param array $args Query arguments.
	 * @return array<int, array>
	 */
	public function load_attempts( $args = array() ) {
		global $wpdb;

		$table        = $this->get_table_name();
		$where_parts  = array( '1=1' );
		$where_values = array();
		$order_by     = 'created_at';
		$order_dir    = 'DESC';

		if ( ! empty( $args['status'] ) && is_string( $args['status'] ) ) {
			$where_parts[]  = 'status = %s';
			$where_values[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['plugin_key'] ) && is_string( $args['plugin_key'] ) ) {
			$where_parts[]  = 'plugin_key = %s';
			$where_values[] = sanitize_text_field( $args['plugin_key'] );
		}

		if ( ! empty( $args['flow_key'] ) && is_string( $args['flow_key'] ) ) {
			$where_parts[]  = 'flow_key = %s';
			$where_values[] = sanitize_text_field( $args['flow_key'] );
		}

		if ( ! empty( $args['email'] ) && is_string( $args['email'] ) ) {
			$where_parts[]  = 'email = %s';
			$where_values[] = sanitize_email( $args['email'] );
		}

		if ( ! empty( $args['status_in'] ) && is_array( $args['status_in'] ) ) {
			$statuses = array_values(
				array_filter(
					array_map( 'sanitize_key', $args['status_in'] ),
					array( $this, 'is_known_status' )
				)
			);

			if ( ! empty( $statuses ) ) {
				$placeholders  = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
				$where_parts[] = "status IN ({$placeholders})";
				$where_values  = array_merge( $where_values, $statuses );
			}
		}

		if ( ! empty( $args['order_by'] ) && is_string( $args['order_by'] ) ) {
			$allowed_order_by = array( 'created_at', 'updated_at', 'last_activity_at', 'expires_at', 'email', 'status' );
			if ( in_array( $args['order_by'], $allowed_order_by, true ) ) {
				$order_by = $args['order_by'];
			}
		}

		if ( ! empty( $args['order'] ) && is_string( $args['order'] ) ) {
			$order = strtoupper( $args['order'] );
			if ( in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
				$order_dir = $order;
			}
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where_parts ) . " ORDER BY {$order_by} {$order_dir}";

		if ( isset( $args['limit'] ) ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', absint( $args['limit'] ) );
		}

		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query string is assembled from trusted internal fragments and prepared here.
			$prepared = $wpdb->prepare( $sql, $where_values );
		} else {
			$prepared = $sql;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is prepared above from trusted internal fragments.
		$rows = $wpdb->get_results( $prepared );

		return array_map( array( $this, 'normalize_attempt_row' ), (array) $rows );
	}

	/**
	 * Update non-status attempt data.
	 *
	 * @param int   $attempt_id Attempt ID.
	 * @param array $data       Data to update.
	 * @return array|WP_Error
	 */
	public function update_attempt( $attempt_id, $data ) {
		$attempt = $this->load_attempt( $attempt_id );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		if ( isset( $data['status'] ) ) {
			return new WP_Error( 'tpw_signup_attempt_status_locked', 'Status updates must use transition_status().' );
		}

		$update_data = $this->prepare_update_data( $data, $attempt );
		if ( is_wp_error( $update_data ) ) {
			return $update_data;
		}

		$updated = $this->update_attempt_row( (int) $attempt['id'], $update_data );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return $this->load_attempt( $attempt_id );
	}

	/**
	 * Transition an attempt to a new lifecycle status.
	 *
	 * @param int    $attempt_id  Attempt ID.
	 * @param string $new_status  Target status.
	 * @param array  $context     Transition context.
	 * @return array|WP_Error
	 */
	public function transition_status( $attempt_id, $new_status, $context = array() ) {
		$attempt = $this->load_attempt( $attempt_id );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$new_status = sanitize_key( $new_status );
		if ( ! $this->is_known_status( $new_status ) ) {
			return new WP_Error( 'tpw_signup_attempt_invalid_status', 'Unknown signup attempt status.' );
		}

		$current_status = $attempt['status'];
		if ( $current_status === $new_status ) {
			return $attempt;
		}

		if ( ! $this->can_transition_status( $current_status, $new_status ) ) {
			return new WP_Error(
				'tpw_signup_attempt_invalid_transition',
				sprintf( 'Transition from %s to %s is not allowed.', $current_status, $new_status )
			);
		}

		$now         = $this->get_current_utc_datetime();
		$update_data = array(
			'status'           => $new_status,
			'updated_at'       => $now,
			'last_activity_at' => $now,
		);

		if ( isset( $context['last_error_code'] ) ) {
			$update_data['last_error_code'] = sanitize_text_field( $context['last_error_code'] );
		}

		if ( isset( $context['last_error_message'] ) ) {
			$update_data['last_error_message'] = sanitize_textarea_field( $context['last_error_message'] );
		}

		if ( array_key_exists( 'expires_at', $context ) ) {
			$update_data['expires_at'] = $this->normalize_datetime_value( $context['expires_at'] );
		}

		switch ( $new_status ) {
			case 'payment_pending':
				$update_data['payment_status']        = 'pending';
				$update_data['payment_started_at']    = $now;
				$update_data['payment_completed_at']  = null;
				$update_data['last_error_code']       = null;
				$update_data['last_error_message']    = null;
				$update_data['payment_attempt_count'] = (int) $attempt['payment_attempt_count'] + 1;
				if ( 'payment_failed' === $current_status ) {
					$update_data['retry_count'] = (int) $attempt['retry_count'] + 1;
				}
				break;

			case 'payment_failed':
				$update_data['payment_status']       = 'failed';
				$update_data['payment_completed_at'] = $now;
				break;

			case 'payment_succeeded':
				$update_data['payment_status']       = 'succeeded';
				$update_data['payment_completed_at'] = $now;
				break;

			case 'finalizing':
				$update_data['finalization_started_at']    = $now;
				$update_data['finalization_completed_at']  = null;
				$update_data['finalization_attempt_count'] = (int) $attempt['finalization_attempt_count'] + 1;
				$update_data['last_error_code']            = null;
				$update_data['last_error_message']         = null;
				break;

			case 'completed':
				$update_data['finalization_completed_at'] = $now;
				$update_data['lock_token']                = null;
				$update_data['locked_at']                 = null;
				break;

			case 'finalization_failed':
				$update_data['lock_token'] = null;
				$update_data['locked_at']  = null;
				break;

			case 'expired':
				$update_data['expired_at'] = $now;
				$update_data['lock_token'] = null;
				$update_data['locked_at']  = null;
				break;

			case 'abandoned':
				$update_data['abandoned_at'] = $now;
				$update_data['lock_token']   = null;
				$update_data['locked_at']    = null;
				break;
		}

		if ( isset( $context['result_payload'] ) ) {
			$update_data['result_payload_json'] = $this->merge_payload_json( $attempt, 'result_payload_json', $context['result_payload'] );
		}

		if ( isset( $context['retry_payload'] ) ) {
			$update_data['retry_payload_json'] = $this->encode_payload( $context['retry_payload'] );
		}

		if ( isset( $context['request_payload'] ) ) {
			$sanitized_request                   = $this->sanitize_payload( $context['request_payload'] );
			$update_data['request_payload_json'] = $this->encode_payload( $sanitized_request );
			$update_data['request_fingerprint']  = $this->generate_request_fingerprint( $sanitized_request );
		}

		if ( isset( $context['payment_provider'] ) ) {
			$update_data['payment_provider'] = sanitize_text_field( $context['payment_provider'] );
		}

		if ( isset( $context['payment_reference'] ) ) {
			$update_data['payment_reference'] = sanitize_text_field( $context['payment_reference'] );
		}

		if ( isset( $context['payment_receipt_reference'] ) ) {
			$update_data['payment_receipt_reference'] = sanitize_text_field( $context['payment_receipt_reference'] );
		}

		if ( isset( $context['payment_result_code'] ) ) {
			$update_data['payment_result_code'] = sanitize_text_field( $context['payment_result_code'] );
		}

		$updated = $this->update_attempt_row( (int) $attempt['id'], $update_data );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$this->log_attempt_event(
			(int) $attempt['id'],
			'status_transition',
			array(
				'from_status' => $current_status,
				'to_status'   => $new_status,
			)
		);

		return $this->load_attempt( $attempt_id );
	}

	/**
	 * Mark an attempt as payment pending.
	 *
	 * @param int   $attempt_id   Attempt ID.
	 * @param array $payment_data Payment context.
	 * @return array|WP_Error
	 */
	public function mark_payment_pending( $attempt_id, $payment_data = array() ) {
		return $this->transition_status( $attempt_id, 'payment_pending', $payment_data );
	}

	/**
	 * Mark an attempt as payment failed.
	 *
	 * @param int   $attempt_id   Attempt ID.
	 * @param array $payment_data Payment context.
	 * @return array|WP_Error
	 */
	public function mark_payment_failed( $attempt_id, $payment_data = array() ) {
		return $this->transition_status( $attempt_id, 'payment_failed', $payment_data );
	}

	/**
	 * Mark an attempt as payment succeeded.
	 *
	 * @param int   $attempt_id   Attempt ID.
	 * @param array $payment_data Payment context.
	 * @return array|WP_Error
	 */
	public function mark_payment_succeeded( $attempt_id, $payment_data = array() ) {
		return $this->transition_status( $attempt_id, 'payment_succeeded', $payment_data );
	}

	/**
	 * Acquire a finalization lock.
	 *
	 * @param int $attempt_id    Attempt ID.
	 * @param int $lock_timeout  Lock timeout in seconds.
	 * @return string|WP_Error
	 */
	public function acquire_finalization_lock( $attempt_id, $lock_timeout = 300 ) {
		global $wpdb;

		$attempt = $this->load_attempt( $attempt_id );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		if ( ! in_array( $attempt['status'], array( 'payment_succeeded', 'finalization_failed' ), true ) ) {
			return new WP_Error( 'tpw_signup_attempt_lock_status', 'Finalization lock can only be acquired after payment success or finalization failure.' );
		}

		$lock_timeout = absint( $lock_timeout );
		if ( $lock_timeout < 1 ) {
			$lock_timeout = 300;
		}

		$lock_token      = $this->generate_public_token();
		$now             = $this->get_current_utc_datetime();
		$stale_threshold = gmdate( 'Y-m-d H:i:s', time() - $lock_timeout );
		$table           = $this->get_table_name();

		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted internal table name.
			"UPDATE {$table}
			SET lock_token = %s, locked_at = %s, updated_at = %s
			WHERE id = %d
			AND ( lock_token IS NULL OR locked_at IS NULL OR locked_at < %s )",
			$lock_token,
			$now,
			$now,
			absint( $attempt_id ),
			$stale_threshold
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock acquisition requires a direct prepared update.
		$updated = $wpdb->query( $sql );
		if ( 1 !== (int) $updated ) {
			return new WP_Error( 'tpw_signup_attempt_lock_unavailable', 'Unable to acquire finalization lock for this attempt.' );
		}

		$this->log_attempt_event( (int) $attempt_id, 'finalization_lock_acquired', array() );

		return $lock_token;
	}

	/**
	 * Begin finalization on a locked attempt.
	 *
	 * @param int    $attempt_id Attempt ID.
	 * @param string $lock_token Lock token.
	 * @param array  $context    Context data.
	 * @return array|WP_Error
	 */
	public function begin_finalization( $attempt_id, $lock_token, $context = array() ) {
		$attempt = $this->load_attempt( $attempt_id );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		if ( sanitize_text_field( $lock_token ) !== $attempt['lock_token'] ) {
			return new WP_Error( 'tpw_signup_attempt_lock_mismatch', 'Invalid finalization lock token.' );
		}

		return $this->transition_status( $attempt_id, 'finalizing', $context );
	}

	/**
	 * Release a finalization lock.
	 *
	 * @param int    $attempt_id Attempt ID.
	 * @param string $lock_token Lock token.
	 * @return bool|WP_Error
	 */
	public function release_finalization_lock( $attempt_id, $lock_token ) {
		global $wpdb;

		$table = $this->get_table_name();
		$now   = $this->get_current_utc_datetime();

		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted internal table name.
			"UPDATE {$table}
			SET lock_token = NULL, locked_at = NULL, updated_at = %s
			WHERE id = %d AND lock_token = %s",
			$now,
			absint( $attempt_id ),
			sanitize_text_field( $lock_token )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock release requires a direct prepared update.
		$updated = $wpdb->query( $sql );
		if ( false === $updated ) {
			return new WP_Error( 'tpw_signup_attempt_release_lock_failed', $wpdb->last_error );
		}

		if ( 0 === (int) $updated ) {
			return new WP_Error( 'tpw_signup_attempt_release_lock_mismatch', 'No matching finalization lock was found.' );
		}

		$this->log_attempt_event( (int) $attempt_id, 'finalization_lock_released', array() );

		return true;
	}

	/**
	 * Mark finalization as failed.
	 *
	 * @param int    $attempt_id  Attempt ID.
	 * @param string $lock_token  Lock token.
	 * @param array  $result_data Result context.
	 * @return array|WP_Error
	 */
	public function mark_finalization_failed( $attempt_id, $lock_token, $result_data = array() ) {
		$attempt = $this->load_attempt( $attempt_id );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		if ( sanitize_text_field( $lock_token ) !== $attempt['lock_token'] ) {
			return new WP_Error( 'tpw_signup_attempt_lock_mismatch', 'Invalid finalization lock token.' );
		}

		return $this->transition_status( $attempt_id, 'finalization_failed', $result_data );
	}

	/**
	 * Mark finalization as completed.
	 *
	 * @param int    $attempt_id  Attempt ID.
	 * @param string $lock_token  Lock token.
	 * @param array  $result_data Result context.
	 * @return array|WP_Error
	 */
	public function mark_completed( $attempt_id, $lock_token, $result_data = array() ) {
		$attempt = $this->load_attempt( $attempt_id );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		if ( sanitize_text_field( $lock_token ) !== $attempt['lock_token'] ) {
			return new WP_Error( 'tpw_signup_attempt_lock_mismatch', 'Invalid finalization lock token.' );
		}

		return $this->transition_status( $attempt_id, 'completed', $result_data );
	}

	/**
	 * Update last activity on an attempt.
	 *
	 * @param int         $attempt_id     Attempt ID.
	 * @param string|null $activity_time  Activity time.
	 * @return array|WP_Error
	 */
	public function touch_attempt( $attempt_id, $activity_time = null ) {
		$timestamp = $this->normalize_datetime_value( $activity_time );
		if ( null === $timestamp ) {
			$timestamp = $this->get_current_utc_datetime();
		}

		$updated = $this->update_attempt_row(
			absint( $attempt_id ),
			array(
				'updated_at'       => $timestamp,
				'last_activity_at' => $timestamp,
			)
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return $this->load_attempt( $attempt_id );
	}

	/**
	 * Mark one attempt as expired.
	 *
	 * @param int   $attempt_id Attempt ID.
	 * @param array $context    Context.
	 * @return array|WP_Error
	 */
	public function mark_attempt_expired( $attempt_id, $context = array() ) {
		return $this->transition_status( $attempt_id, 'expired', $context );
	}

	/**
	 * Mark one attempt as abandoned.
	 *
	 * @param int   $attempt_id Attempt ID.
	 * @param array $context    Context.
	 * @return array|WP_Error
	 */
	public function mark_attempt_abandoned( $attempt_id, $context = array() ) {
		return $this->transition_status( $attempt_id, 'abandoned', $context );
	}

	/**
	 * Expire eligible attempts up to a reference time.
	 *
	 * @param string|null $reference_time    Reference time.
	 * @param array       $eligible_statuses Allowed source statuses.
	 * @return int|WP_Error
	 */
	public function cleanup_expired_attempts( $reference_time = null, $eligible_statuses = array() ) {
		$reference_time = $this->normalize_datetime_value( $reference_time );
		if ( null === $reference_time ) {
			$reference_time = $this->get_current_utc_datetime();
		}

		if ( empty( $eligible_statuses ) ) {
			$eligible_statuses = array( 'draft', 'payment_pending', 'payment_failed' );
		}

		$args = array(
			'status_in' => $eligible_statuses,
			'limit'     => 500,
		);

		$attempts = $this->load_attempts( $args );
		$count    = 0;

		foreach ( $attempts as $attempt ) {
			if ( empty( $attempt['expires_at'] ) || $attempt['expires_at'] > $reference_time ) {
				continue;
			}

			$result = $this->mark_attempt_expired( (int) $attempt['id'], array( 'last_error_code' => 'expired' ) );
			if ( ! is_wp_error( $result ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Mark stale attempts as abandoned.
	 *
	 * @param string|null $inactive_before   Inactivity cutoff.
	 * @param array       $eligible_statuses Allowed source statuses.
	 * @return int|WP_Error
	 */
	public function cleanup_abandoned_attempts( $inactive_before = null, $eligible_statuses = array() ) {
		$inactive_before = $this->normalize_datetime_value( $inactive_before );
		if ( null === $inactive_before ) {
			$inactive_before = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		}

		if ( empty( $eligible_statuses ) ) {
			$eligible_statuses = array( 'draft', 'payment_pending', 'payment_failed' );
		}

		$attempts = $this->load_attempts(
			array(
				'status_in' => $eligible_statuses,
				'limit'     => 500,
			)
		);
		$count    = 0;

		foreach ( $attempts as $attempt ) {
			if ( empty( $attempt['last_activity_at'] ) || $attempt['last_activity_at'] > $inactive_before ) {
				continue;
			}

			$result = $this->mark_attempt_abandoned( (int) $attempt['id'], array( 'last_error_code' => 'abandoned' ) );
			if ( ! is_wp_error( $result ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Append an event to the attempt event log.
	 *
	 * @param int    $attempt_id Attempt ID.
	 * @param string $event_key  Event key.
	 * @param array  $event_data Event data.
	 * @return array|WP_Error
	 */
	public function log_attempt_event( $attempt_id, $event_key, $event_data = array() ) {
		$attempt = $this->load_attempt( $attempt_id );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$result_payload = $attempt['result_payload'];
		if ( ! isset( $result_payload['event_log'] ) || ! is_array( $result_payload['event_log'] ) ) {
			$result_payload['event_log'] = array();
		}

		$result_payload['event_log'][] = array(
			'event_key'   => $this->sanitize_event_key( $event_key ),
			'recorded_at' => $this->get_current_utc_datetime(),
			'data'        => $this->sanitize_payload( $event_data ),
		);

		$updated = $this->update_attempt_row(
			(int) $attempt['id'],
			array(
				'result_payload_json' => $this->encode_payload( $result_payload ),
				'updated_at'          => $this->get_current_utc_datetime(),
			)
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return $this->load_attempt( $attempt_id );
	}

	/**
	 * Generate a public-safe token.
	 *
	 * @return string
	 */
	public function generate_public_token() {
		try {
			return bin2hex( random_bytes( 32 ) );
		} catch ( Exception $exception ) {
			return hash( 'sha256', wp_generate_uuid4() . '|' . microtime( true ) );
		}
	}

	/**
	 * Generate a UUID.
	 *
	 * @return string
	 */
	public function generate_uuid() {
		return strtolower( wp_generate_uuid4() );
	}

	/**
	 * Normalize a UUID string.
	 *
	 * @param string $uuid UUID.
	 * @return string|WP_Error
	 */
	public function normalize_uuid( $uuid ) {
		$uuid = strtolower( sanitize_text_field( $uuid ) );
		if ( ! preg_match( '/^[a-f0-9]{8}\-[a-f0-9]{4}\-[1-5][a-f0-9]{3}\-[89ab][a-f0-9]{3}\-[a-f0-9]{12}$/', $uuid ) ) {
			return new WP_Error( 'tpw_signup_attempt_invalid_uuid', 'Invalid UUID supplied.' );
		}

		return $uuid;
	}

	/**
	 * Generate a stable request fingerprint.
	 *
	 * @param mixed $request_payload Request payload.
	 * @return string
	 */
	public function generate_request_fingerprint( $request_payload ) {
		$normalized = $this->sanitize_payload( $request_payload );
		$normalized = $this->sort_payload_recursively( $normalized );

		return hash( 'sha256', wp_json_encode( $normalized ) );
	}

	/**
	 * Check whether a status is known.
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	private function is_known_status( $status ) {
		return in_array( $status, $this->statuses, true );
	}

	/**
	 * Load one attempt by a column.
	 *
	 * @param string $column Column name.
	 * @param mixed  $value  Column value.
	 * @param string $format Placeholder format.
	 * @return array|WP_Error
	 */
	private function load_attempt_by_column( $column, $value, $format ) {
		global $wpdb;

		$allowed_columns = array( 'id', 'public_token', 'payment_reference', 'request_fingerprint' );
		if ( ! in_array( $column, $allowed_columns, true ) ) {
			return new WP_Error( 'tpw_signup_attempt_invalid_lookup', 'Unsupported lookup column.' );
		}

		$table = $this->get_table_name();

		switch ( $column ) {
			case 'id':
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted internal table name.
				$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $value );
				break;

			case 'public_token':
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted internal table name.
				$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE public_token = %s LIMIT 1", $value );
				break;

			case 'payment_reference':
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted internal table name.
				$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE payment_reference = %s LIMIT 1", $value );
				break;

			case 'request_fingerprint':
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted internal table name.
				$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE request_fingerprint = %s LIMIT 1", $value );
				break;

			default:
				return new WP_Error( 'tpw_signup_attempt_invalid_lookup', 'Unsupported lookup column.' );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is prepared above from a fixed lookup map.
		$row = $wpdb->get_row( $sql );

		if ( null === $row ) {
			return new WP_Error( 'tpw_signup_attempt_not_found', 'Signup attempt not found.' );
		}

		return $this->normalize_attempt_row( $row );
	}

	/**
	 * Normalize a database row.
	 *
	 * @param object $row Row object.
	 * @return array
	 */
	private function normalize_attempt_row( $row ) {
		$data = (array) $row;

		$data['id']                         = (int) $data['id'];
		$data['amount']                     = null !== $data['amount'] ? (int) $data['amount'] : null;
		$data['payment_attempt_count']      = (int) $data['payment_attempt_count'];
		$data['retry_count']                = (int) $data['retry_count'];
		$data['finalization_attempt_count'] = (int) $data['finalization_attempt_count'];
		$data['request_payload']            = $this->decode_payload( $data['request_payload_json'] );
		$data['retry_payload']              = $this->decode_payload( $data['retry_payload_json'] );
		$data['result_payload']             = $this->decode_payload( $data['result_payload_json'] );

		return $data;
	}

	/**
	 * Prepare update data from public input.
	 *
	 * @param array $data    Update data.
	 * @param array $attempt Existing attempt.
	 * @return array|WP_Error
	 */
	private function prepare_update_data( $data, $attempt ) {
		$allowed_updates = array(
			'email',
			'first_name',
			'last_name',
			'gateway',
			'amount',
			'currency_code',
			'payment_provider',
			'payment_reference',
			'payment_receipt_reference',
			'payment_result_code',
			'last_error_code',
			'last_error_message',
			'expires_at',
			'request_payload',
			'retry_payload',
			'result_payload',
		);

		$update_data = array(
			'updated_at' => $this->get_current_utc_datetime(),
		);

		foreach ( $data as $key => $value ) {
			if ( ! in_array( $key, $allowed_updates, true ) ) {
				continue;
			}

			switch ( $key ) {
				case 'email':
					$update_data['email'] = sanitize_email( $value );
					break;

				case 'first_name':
				case 'last_name':
				case 'gateway':
				case 'payment_provider':
				case 'payment_reference':
				case 'payment_receipt_reference':
				case 'payment_result_code':
				case 'last_error_code':
					$update_data[ $key ] = $this->normalize_nullable_text_value( $value );
					break;

				case 'amount':
					$update_data['amount'] = null !== $value ? absint( $value ) : null;
					break;

				case 'currency_code':
					$update_data['currency_code'] = $this->normalize_currency_code_value( $value );
					break;

				case 'last_error_message':
					$update_data['last_error_message'] = null !== $value ? sanitize_textarea_field( $value ) : null;
					break;

				case 'expires_at':
					$update_data['expires_at'] = $this->normalize_datetime_value( $value );
					break;

				case 'request_payload':
					$sanitized_request                   = $this->sanitize_payload( $value );
					$update_data['request_payload_json'] = $this->encode_payload( $sanitized_request );
					$update_data['request_fingerprint']  = $this->generate_request_fingerprint( $sanitized_request );
					break;

				case 'retry_payload':
					$update_data['retry_payload_json'] = $this->encode_payload( $value );
					break;

				case 'result_payload':
					$update_data['result_payload_json'] = $this->merge_payload_json( $attempt, 'result_payload_json', $value );
					break;
			}
		}

		return $update_data;
	}

	/**
	 * Update a row by attempt ID.
	 *
	 * @param int   $attempt_id Attempt ID.
	 * @param array $data       Update data.
	 * @return true|WP_Error
	 */
	private function update_attempt_row( $attempt_id, $data ) {
		global $wpdb;

		if ( empty( $data ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Core lifecycle persistence intentionally writes directly to the attempt table.
		$updated = $wpdb->update(
			$this->get_table_name(),
			$data,
			array( 'id' => absint( $attempt_id ) ),
			$this->get_insert_formats( $data ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'tpw_signup_attempt_update_failed', $wpdb->last_error );
		}

		return true;
	}

	/**
	 * Get placeholder formats for row data.
	 *
	 * @param array $data Row data.
	 * @return array
	 */
	private function get_insert_formats( $data ) {
		$integer_columns = array(
			'id',
			'amount',
			'payment_attempt_count',
			'retry_count',
			'finalization_attempt_count',
		);

		$formats = array();
		foreach ( $data as $column => $value ) {
			$formats[] = in_array( $column, $integer_columns, true ) && null !== $value ? '%d' : '%s';
		}

		return $formats;
	}

	/**
	 * Encode a payload after sanitization.
	 *
	 * @param mixed $payload Payload.
	 * @return string|null
	 */
	private function encode_payload( $payload ) {
		if ( null === $payload ) {
			return null;
		}

		$sanitized = $this->sanitize_payload( $payload );
		$encoded   = wp_json_encode( $sanitized );

		return false !== $encoded ? $encoded : null;
	}

	/**
	 * Decode a JSON payload to an array.
	 *
	 * @param string|null $payload_json Payload JSON.
	 * @return array
	 */
	private function decode_payload( $payload_json ) {
		if ( empty( $payload_json ) || ! is_string( $payload_json ) ) {
			return array();
		}

		$decoded = json_decode( $payload_json, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		return $decoded;
	}

	/**
	 * Merge payload data into an existing JSON column.
	 *
	 * @param array  $attempt    Existing attempt.
	 * @param string $json_key   JSON column key.
	 * @param mixed  $new_data   New payload data.
	 * @return string|null
	 */
	private function merge_payload_json( $attempt, $json_key, $new_data ) {
		$existing = array();
		if ( ! empty( $attempt[ $json_key ] ) && is_string( $attempt[ $json_key ] ) ) {
			$existing = $this->decode_payload( $attempt[ $json_key ] );
		} elseif ( 'result_payload_json' === $json_key && ! empty( $attempt['result_payload'] ) ) {
			$existing = $attempt['result_payload'];
		}

		$merged = array_merge( $existing, $this->sanitize_payload( $new_data ) );

		return $this->encode_payload( $merged );
	}

	/**
	 * Sanitize a payload recursively.
	 *
	 * @param mixed $payload Payload.
	 * @return mixed
	 */
	private function sanitize_payload( $payload ) {
		$blocked_keys = array(
			'password',
			'passwords',
			'user_pass',
			'pass1',
			'pass2',
			'card_token',
			'card_tokens',
			'recaptcha_token',
			'g_recaptcha_response',
			'g-recaptcha-response',
		);

		if ( is_array( $payload ) ) {
			$sanitized = array();
			foreach ( $payload as $key => $value ) {
				$normalized_key = is_string( $key ) ? strtolower( preg_replace( '/[^a-z0-9_\-]/', '_', $key ) ) : $key;
				if ( is_string( $normalized_key ) && in_array( $normalized_key, $blocked_keys, true ) ) {
					continue;
				}

				$sanitized[ $key ] = $this->sanitize_payload( $value );
			}

			return $sanitized;
		}

		if ( is_object( $payload ) ) {
			return $this->sanitize_payload( (array) $payload );
		}

		if ( is_bool( $payload ) || is_int( $payload ) || is_float( $payload ) || null === $payload ) {
			return $payload;
		}

		if ( is_string( $payload ) ) {
			return sanitize_textarea_field( $payload );
		}

		return null;
	}

	/**
	 * Sort a payload recursively for stable fingerprinting.
	 *
	 * @param mixed $payload Payload.
	 * @return mixed
	 */
	private function sort_payload_recursively( $payload ) {
		if ( ! is_array( $payload ) ) {
			return $payload;
		}

		if ( $this->is_assoc_array( $payload ) ) {
			ksort( $payload );
		}

		foreach ( $payload as $key => $value ) {
			$payload[ $key ] = $this->sort_payload_recursively( $value );
		}

		return $payload;
	}

	/**
	 * Determine whether an array is associative.
	 *
	 * @param array $payload Payload.
	 * @return bool
	 */
	private function is_assoc_array( $payload ) {
		if ( empty( $payload ) ) {
			return false;
		}

		return array_keys( $payload ) !== range( 0, count( $payload ) - 1 );
	}

	/**
	 * Sanitize a nullable text value from an input array.
	 *
	 * @param array  $data Input data.
	 * @param string $key  Array key.
	 * @return string|null
	 */
	private function sanitize_nullable_text( $data, $key ) {
		if ( ! array_key_exists( $key, $data ) || '' === $data[ $key ] || null === $data[ $key ] ) {
			return null;
		}

		return sanitize_text_field( $data[ $key ] );
	}

	/**
	 * Sanitize a nullable text area from an input array.
	 *
	 * @param array  $data Input data.
	 * @param string $key  Array key.
	 * @return string|null
	 */
	private function sanitize_nullable_textarea( $data, $key ) {
		if ( ! array_key_exists( $key, $data ) || '' === $data[ $key ] || null === $data[ $key ] ) {
			return null;
		}

		return sanitize_textarea_field( $data[ $key ] );
	}

	/**
	 * Sanitize a nullable integer from an input array.
	 *
	 * @param array  $data Input data.
	 * @param string $key  Array key.
	 * @return int|null
	 */
	private function sanitize_nullable_absint( $data, $key ) {
		if ( ! array_key_exists( $key, $data ) || '' === $data[ $key ] || null === $data[ $key ] ) {
			return null;
		}

		return absint( $data[ $key ] );
	}

	/**
	 * Sanitize a nullable currency code from an input array.
	 *
	 * @param array  $data Input data.
	 * @param string $key  Array key.
	 * @return string|null
	 */
	private function sanitize_nullable_currency_code( $data, $key ) {
		if ( ! array_key_exists( $key, $data ) || '' === $data[ $key ] || null === $data[ $key ] ) {
			return null;
		}

		return $this->normalize_currency_code_value( $data[ $key ] );
	}

	/**
	 * Sanitize a nullable datetime from an input array.
	 *
	 * @param array  $data Input data.
	 * @param string $key  Array key.
	 * @return string|null
	 */
	private function sanitize_nullable_datetime( $data, $key ) {
		if ( ! array_key_exists( $key, $data ) || '' === $data[ $key ] || null === $data[ $key ] ) {
			return null;
		}

		return $this->normalize_datetime_value( $data[ $key ] );
	}

	/**
	 * Normalize a nullable text value.
	 *
	 * @param mixed $value Value.
	 * @return string|null
	 */
	private function normalize_nullable_text_value( $value ) {
		if ( '' === $value || null === $value ) {
			return null;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Normalize a currency code.
	 *
	 * @param mixed $value Value.
	 * @return string|null
	 */
	private function normalize_currency_code_value( $value ) {
		if ( '' === $value || null === $value ) {
			return null;
		}

		$currency_code = strtoupper( sanitize_text_field( $value ) );
		$currency_code = substr( preg_replace( '/[^A-Z]/', '', $currency_code ), 0, 3 );

		return '' !== $currency_code ? $currency_code : null;
	}

	/**
	 * Normalize a datetime string.
	 *
	 * @param mixed $value Value.
	 * @return string|null
	 */
	private function normalize_datetime_value( $value ) {
		if ( '' === $value || null === $value ) {
			return null;
		}

		if ( $value instanceof DateTimeInterface ) {
			return gmdate( 'Y-m-d H:i:s', $value->getTimestamp() );
		}

		$timestamp = strtotime( (string) $value );
		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Sanitize an event key.
	 *
	 * @param string $event_key Event key.
	 * @return string
	 */
	private function sanitize_event_key( $event_key ) {
		$event_key = strtolower( sanitize_text_field( $event_key ) );
		$event_key = preg_replace( '/[^a-z0-9_\-:.]/', '', $event_key );

		return '' !== $event_key ? $event_key : 'event';
	}

	/**
	 * Get the current UTC datetime string.
	 *
	 * @return string
	 */
	private function get_current_utc_datetime() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
	}
}
