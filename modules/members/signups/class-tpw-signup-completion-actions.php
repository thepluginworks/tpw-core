<?php
/**
 * Internal completion action handlers for signup attempts.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Completion_Actions {
	/**
	 * Register action handlers.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_tpw_signup_complete_internal', array( __CLASS__, 'handle_complete_internal' ) );
	}

	/**
	 * Handle the internal completion action for a signup attempt.
	 *
	 * @return void
	 */
	public static function handle_complete_internal() {
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( wp_unslash( $_POST['attempt_id'] ) ) : 0;

		if ( $attempt_id <= 0 ) {
			self::redirect_with_status( 0, 'invalid_attempt', false );
		}

		if ( ! self::current_user_can_manage_signups() ) {
			self::redirect_with_status( $attempt_id, 'permission_denied', false );
		}

		check_admin_referer( 'tpw_signup_complete_internal_' . $attempt_id );

		$result = TPW_Signup_Completion_Bridge::get_instance()->complete_attempt(
			$attempt_id,
			array(
				'source'        => 'members_admin',
				'requested_via' => 'admin_post',
				'actor_user_id' => get_current_user_id(),
			)
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_with_status( $attempt_id, $result->get_error_code(), false );
		}

		self::redirect_with_status( $attempt_id, 'completed', true );
	}

	/**
	 * Check whether the current user can manage signup recovery actions.
	 *
	 * @return bool
	 */
	private static function current_user_can_manage_signups() {
		if ( ! class_exists( 'TPW_Member_Access' ) ) {
			$access_file = TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-access.php';
			if ( file_exists( $access_file ) ) {
				require_once $access_file;
			}
		}

		if ( ! class_exists( 'TPW_Member_Access' ) ) {
			return current_user_can( 'manage_options' );
		}

		return TPW_Member_Access::can_manage_members_current();
	}

	/**
	 * Redirect back to the caller with a completion status.
	 *
	 * @param int    $attempt_id Attempt ID.
	 * @param string $status     Status code.
	 * @param bool   $success    Whether the action succeeded.
	 * @return void
	 */
	private static function redirect_with_status( $attempt_id, $status, $success ) {
		$redirect_url = wp_get_referer();

		if ( empty( $redirect_url ) ) {
			$redirect_url = admin_url();
		}

		$redirect_url = remove_query_arg(
			array(
				'tpw_signup_completion',
				'tpw_signup_completion_error',
				'tpw_signup_attempt_id',
			),
			$redirect_url
		);

		$redirect_args = array(
			'tpw_signup_attempt_id' => absint( $attempt_id ),
		);

		if ( $success ) {
			$redirect_args['tpw_signup_completion'] = sanitize_key( $status );
		} else {
			$redirect_args['tpw_signup_completion_error'] = sanitize_key( $status );
		}

		wp_safe_redirect( add_query_arg( $redirect_args, $redirect_url ) );
		exit;
	}
}
