<?php
/**
 * Controlled internal completion bridge for signup attempts.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Completion_Bridge {
	/**
	 * Singleton instance.
	 *
	 * @var TPW_Signup_Completion_Bridge|null
	 */
	private static $instance = null;

	/**
	 * Lifecycle service.
	 *
	 * @var TPW_Signup_Attempts_Service
	 */
	private $attempts_service;

	/**
	 * Finalizer.
	 *
	 * @var TPW_Signup_Finalizer
	 */
	private $finalizer;

	/**
	 * Get the singleton instance.
	 *
	 * @return TPW_Signup_Completion_Bridge
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->attempts_service = TPW_Signup_Attempts_Service::get_instance();
		$this->finalizer        = TPW_Signup_Finalizer::get_instance();
	}

	/**
	 * Complete a draft attempt through the internal non-gateway path.
	 *
	 * @param int   $attempt_id Attempt ID.
	 * @param array $context    Completion context.
	 * @return array<string, mixed>|WP_Error
	 */
	public function complete_attempt( $attempt_id, $context = array() ) {
		$attempt = $this->attempts_service->load_attempt( absint( $attempt_id ) );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$attempt = $this->validate_attempt_for_completion( $attempt );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$completion_context = $this->build_completion_context( $attempt, $context );

		$attempt = $this->attempts_service->log_attempt_event(
			(int) $attempt['id'],
			'internal_completion_requested',
			$completion_context['event_data']
		);
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$attempt = $this->attempts_service->mark_payment_pending(
			(int) $attempt['id'],
			array(
				'payment_result_code' => 'internal_completion_pending',
				'result_payload'      => $completion_context['result_payload'],
			)
		);
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$attempt = $this->attempts_service->log_attempt_event(
			(int) $attempt['id'],
			'internal_completion_payment_pending',
			$completion_context['event_data']
		);
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$attempt = $this->attempts_service->mark_payment_succeeded(
			(int) $attempt['id'],
			array(
				'payment_reference'   => $completion_context['payment_reference'],
				'payment_result_code' => 'internal_completion_success',
				'result_payload'      => $completion_context['result_payload'],
			)
		);
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$attempt = $this->attempts_service->log_attempt_event(
			(int) $attempt['id'],
			'internal_completion_payment_succeeded',
			$completion_context['event_data']
		);
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$result = $this->finalizer->finalize_attempt( (int) $attempt['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'             => true,
			'attempt_id'          => (int) $attempt['id'],
			'completion_context'  => $completion_context['result_payload']['internal_completion'],
			'finalization_result' => $result,
			'attempt'             => isset( $result['attempt'] ) && is_array( $result['attempt'] ) ? $result['attempt'] : $attempt,
		);
	}

	/**
	 * Validate that an attempt is eligible for the internal completion bridge.
	 *
	 * @param array $attempt Loaded attempt.
	 * @return array|WP_Error
	 */
	private function validate_attempt_for_completion( $attempt ) {
		if ( empty( $attempt['id'] ) ) {
			return new WP_Error( 'tpw_signup_attempt_missing', 'The signup attempt could not be found.' );
		}

		$status = isset( $attempt['status'] ) ? sanitize_key( $attempt['status'] ) : '';
		if ( 'draft' !== $status ) {
			return new WP_Error( 'tpw_signup_attempt_not_bridgeable', 'Only draft signup attempts can use the internal completion bridge.' );
		}

		if ( ! empty( $attempt['lock_token'] ) ) {
			return new WP_Error( 'tpw_signup_attempt_locked', 'Locked signup attempts cannot use the internal completion bridge.' );
		}

		$supported = $this->finalizer->supports_attempt( $attempt );
		if ( is_wp_error( $supported ) ) {
			return $supported;
		}

		return $attempt;
	}

	/**
	 * Build audit context for the internal completion path.
	 *
	 * @param array $attempt Loaded attempt.
	 * @param array $context Completion context.
	 * @return array<string, mixed>
	 */
	private function build_completion_context( $attempt, $context ) {
		$recorded_at       = gmdate( 'Y-m-d H:i:s' );
		$source            = isset( $context['source'] ) ? sanitize_key( $context['source'] ) : 'internal';
		$status_reason     = isset( $context['status_reason'] ) ? sanitize_text_field( $context['status_reason'] ) : 'manual_internal_completion';
		$requested_via     = isset( $context['requested_via'] ) ? sanitize_key( $context['requested_via'] ) : 'service';
		$requested_by_user = isset( $context['actor_user_id'] ) ? absint( $context['actor_user_id'] ) : 0;
		$payment_reference = sprintf( 'internal-attempt-%d', (int) $attempt['id'] );

		$internal_completion = array(
			'source'          => $source,
			'status_reason'   => $status_reason,
			'recorded_at_utc' => $recorded_at,
			'requested_via'   => $requested_via,
			'payment_context' => array(
				'type'      => 'synthetic_non_gateway_success',
				'reference' => $payment_reference,
			),
		);

		if ( $requested_by_user > 0 ) {
			$internal_completion['requested_by_user_id'] = $requested_by_user;
		}

		return array(
			'payment_reference' => $payment_reference,
			'result_payload'    => array(
				'internal_completion' => $internal_completion,
			),
			'event_data'        => $internal_completion,
		);
	}
}
