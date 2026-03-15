<?php
/**
 * Finalize eligible signup attempts into WordPress users and TPW members.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Finalizer {
	/**
	 * Singleton instance.
	 *
	 * @var TPW_Signup_Finalizer|null
	 */
	private static $instance = null;

	/**
	 * Lifecycle service.
	 *
	 * @var TPW_Signup_Attempts_Service
	 */
	private $attempts_service;

	/**
	 * Field mapper.
	 *
	 * @var TPW_Signup_Field_Mapper
	 */
	private $field_mapper;

	/**
	 * Get the singleton instance.
	 *
	 * @return TPW_Signup_Finalizer
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
	public function __construct() {
		$this->attempts_service = TPW_Signup_Attempts_Service::get_instance();
		$this->field_mapper     = new TPW_Signup_Field_Mapper();
	}

	/**
	 * Finalize a signup attempt by ID.
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function finalize_attempt( $attempt_id ) {
		$attempt = $this->attempts_service->load_attempt( absint( $attempt_id ) );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		return $this->finalize_loaded_attempt( $attempt );
	}

	/**
	 * Finalize a signup attempt by public token.
	 *
	 * @param string $public_token Public token.
	 * @return array<string, mixed>|WP_Error
	 */
	public function finalize_attempt_by_public_token( $public_token ) {
		$attempt = $this->attempts_service->load_attempt_by_public_token( $public_token );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		return $this->finalize_loaded_attempt( $attempt );
	}

	/**
	 * Run the Branch 4 finalization flow against a loaded attempt.
	 *
	 * @param array $attempt Loaded attempt.
	 * @return array<string, mixed>|WP_Error
	 */
	private function finalize_loaded_attempt( $attempt ) {
		$this->ensure_member_dependencies();

		$attempt = is_array( $attempt ) ? $attempt : array();
		$attempt = $this->validate_attempt_for_finalization( $attempt );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$attempt_id = (int) $attempt['id'];
		$lock_token = $this->attempts_service->acquire_finalization_lock( $attempt_id );
		if ( is_wp_error( $lock_token ) ) {
			return $lock_token;
		}

		$attempt = $this->attempts_service->begin_finalization( $attempt_id, $lock_token );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$partial_result = $this->get_existing_result_payload( $attempt );
		$stage          = 'request_payload';

		$request_payload = $this->get_request_payload( $attempt );
		if ( is_wp_error( $request_payload ) ) {
			return $this->handle_finalization_failure( $attempt, $lock_token, $stage, $request_payload, $partial_result );
		}

		$stage  = 'schema';
		$schema = TPW_Signup_Field_Schema::get_public_signup_schema();
		if ( empty( $schema['nodes'] ) || ! is_array( $schema['nodes'] ) ) {
			return $this->handle_finalization_failure(
				$attempt,
				$lock_token,
				$stage,
				new WP_Error( 'tpw_signup_schema_empty', 'The signup schema could not be loaded for finalization.' ),
				$partial_result
			);
		}

		$stage       = 'field_mapping';
		$mapped_data = $this->field_mapper->map_request_payload( $request_payload, $schema );
		if ( is_wp_error( $mapped_data ) ) {
			return $this->handle_finalization_failure( $attempt, $lock_token, $stage, $mapped_data, $partial_result );
		}

		$stage   = 'wp_user';
		$wp_user = $this->resolve_or_create_wp_user( $attempt, $mapped_data['wp_user_data'] );
		if ( is_wp_error( $wp_user ) ) {
			return $this->handle_finalization_failure( $attempt, $lock_token, $stage, $wp_user, $partial_result );
		}

		$partial_result['wp_user_id'] = (int) $wp_user->ID;
		$updated_attempt              = $this->persist_partial_result( $attempt, $partial_result );
		if ( is_wp_error( $updated_attempt ) ) {
			return $this->handle_finalization_failure( $attempt, $lock_token, 'result_payload_wp_user', $updated_attempt, $partial_result );
		}
		$attempt = $updated_attempt;

		$stage     = 'member';
		$member_id = $this->resolve_or_create_member( $attempt, $mapped_data['member_data'], $wp_user );
		if ( is_wp_error( $member_id ) ) {
			return $this->handle_finalization_failure( $attempt, $lock_token, $stage, $member_id, $partial_result );
		}

		$partial_result['member_id'] = (int) $member_id;
		$updated_attempt             = $this->persist_partial_result( $attempt, $partial_result );
		if ( is_wp_error( $updated_attempt ) ) {
			return $this->handle_finalization_failure( $attempt, $lock_token, 'result_payload_member', $updated_attempt, $partial_result );
		}
		$attempt = $updated_attempt;

		$stage      = 'member_meta';
		$meta_saved = $this->save_member_meta( $member_id, $mapped_data['member_meta_data'] );
		if ( is_wp_error( $meta_saved ) ) {
			return $this->handle_finalization_failure( $attempt, $lock_token, $stage, $meta_saved, $partial_result );
		}

		TPW_Member_Roles::ensure_member_cap( (int) $wp_user->ID );

		$completed_result                       = $partial_result;
		$completed_result['finalization_error'] = null;

		$completed_attempt = $this->attempts_service->mark_completed(
			$attempt_id,
			$lock_token,
			array(
				'result_payload' => $completed_result,
			)
		);
		if ( is_wp_error( $completed_attempt ) ) {
			return $this->handle_finalization_failure( $attempt, $lock_token, 'mark_completed', $completed_attempt, $partial_result );
		}

		return array(
			'success'    => true,
			'attempt_id' => $attempt_id,
			'status'     => isset( $completed_attempt['status'] ) ? $completed_attempt['status'] : 'completed',
			'wp_user_id' => (int) $wp_user->ID,
			'member_id'  => (int) $member_id,
			'attempt'    => $completed_attempt,
		);
	}

	/**
	 * Validate that an attempt is eligible for this finalizer.
	 *
	 * @param array $attempt Loaded attempt.
	 * @return array|WP_Error
	 */
	private function validate_attempt_for_finalization( $attempt ) {
		if ( empty( $attempt['id'] ) ) {
			return new WP_Error( 'tpw_signup_attempt_missing', 'The signup attempt could not be found.' );
		}

		$status = isset( $attempt['status'] ) ? sanitize_key( $attempt['status'] ) : '';
		if ( ! in_array( $status, array( 'payment_succeeded', 'finalization_failed' ), true ) ) {
			return new WP_Error( 'tpw_signup_attempt_not_finalizable', 'This signup attempt is not eligible for finalization.' );
		}

		$flow_key = isset( $attempt['flow_key'] ) ? sanitize_key( $attempt['flow_key'] ) : '';
		if ( 'members_join' !== $flow_key ) {
			return new WP_Error( 'tpw_signup_attempt_flow_unsupported', 'This finalizer currently supports only the members_join flow.' );
		}

		return $attempt;
	}

	/**
	 * Read the decoded request payload from an attempt.
	 *
	 * @param array $attempt Loaded attempt.
	 * @return array|WP_Error
	 */
	private function get_request_payload( $attempt ) {
		if ( ! empty( $attempt['request_payload'] ) && is_array( $attempt['request_payload'] ) ) {
			return $attempt['request_payload'];
		}

		if ( empty( $attempt['request_payload_json'] ) || ! is_string( $attempt['request_payload_json'] ) ) {
			return new WP_Error( 'tpw_signup_request_payload_missing', 'The signup attempt does not contain a request payload.' );
		}

		$decoded = json_decode( $attempt['request_payload_json'], true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'tpw_signup_request_payload_invalid', 'The signup attempt request payload is not valid JSON.' );
		}

		return $decoded;
	}

	/**
	 * Get any already-recorded finalization refs from the attempt.
	 *
	 * @param array $attempt Loaded attempt.
	 * @return array<string, mixed>
	 */
	private function get_existing_result_payload( $attempt ) {
		if ( ! empty( $attempt['result_payload'] ) && is_array( $attempt['result_payload'] ) ) {
			return $attempt['result_payload'];
		}

		if ( empty( $attempt['result_payload_json'] ) || ! is_string( $attempt['result_payload_json'] ) ) {
			return array();
		}

		$decoded = json_decode( $attempt['result_payload_json'], true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Persist partial finalization refs back to the attempt.
	 *
	 * @param array $attempt         Loaded attempt.
	 * @param array $partial_result  Result payload fragment.
	 * @return array|WP_Error
	 */
	private function persist_partial_result( $attempt, $partial_result ) {
		$updated_attempt = $this->attempts_service->update_attempt(
			(int) $attempt['id'],
			array(
				'result_payload' => $partial_result,
			)
		);

		return $updated_attempt;
	}

	/**
	 * Resolve or create the WordPress user for an attempt.
	 *
	 * @param array $attempt       Loaded attempt.
	 * @param array $wp_user_data  Mapped WP user data.
	 * @return WP_User|WP_Error
	 */
	private function resolve_or_create_wp_user( $attempt, $wp_user_data ) {
		$result_payload = $this->get_existing_result_payload( $attempt );
		$stored_user_id = isset( $result_payload['wp_user_id'] ) ? absint( $result_payload['wp_user_id'] ) : 0;

		if ( $stored_user_id > 0 ) {
			$wp_user = get_user_by( 'ID', $stored_user_id );
			if ( $wp_user instanceof WP_User ) {
				return $wp_user;
			}
		}

		$email = isset( $wp_user_data['user_email'] ) ? sanitize_email( $wp_user_data['user_email'] ) : '';
		if ( '' === $email ) {
			$email = isset( $attempt['email'] ) ? sanitize_email( $attempt['email'] ) : '';
		}

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'tpw_signup_user_email_missing', 'A valid email address is required to finalize the signup attempt.' );
		}

		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user instanceof WP_User ) {
			return $existing_user;
		}

		$user_login = $this->ensure_unique_user_login(
			isset( $wp_user_data['user_login'] ) ? (string) $wp_user_data['user_login'] : '',
			$email
		);
		if ( '' === $user_login ) {
			return new WP_Error( 'tpw_signup_user_login_missing', 'Unable to derive a unique username for the signup attempt.' );
		}

		$insert_data = array(
			'user_login'   => $user_login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password(),
			'role'         => 'member',
			'display_name' => isset( $wp_user_data['display_name'] ) ? sanitize_text_field( $wp_user_data['display_name'] ) : '',
			'first_name'   => isset( $wp_user_data['first_name'] ) ? sanitize_text_field( $wp_user_data['first_name'] ) : '',
			'last_name'    => isset( $wp_user_data['last_name'] ) ? sanitize_text_field( $wp_user_data['last_name'] ) : '',
		);

		$user_id = wp_insert_user( $insert_data );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$wp_user = get_user_by( 'ID', (int) $user_id );
		if ( ! ( $wp_user instanceof WP_User ) ) {
			return new WP_Error( 'tpw_signup_user_load_failed', 'The created WordPress user could not be loaded.' );
		}

		return $wp_user;
	}

	/**
	 * Resolve or create the TPW member row for an attempt.
	 *
	 * @param array   $attempt      Loaded attempt.
	 * @param array   $member_data  Mapped member data.
	 * @param WP_User $wp_user      Resolved WP user.
	 * @return int|WP_Error
	 */
	private function resolve_or_create_member( $attempt, $member_data, $wp_user ) {
		$controller       = new TPW_Member_Controller();
		$result_payload   = $this->get_existing_result_payload( $attempt );
		$stored_member_id = isset( $result_payload['member_id'] ) ? absint( $result_payload['member_id'] ) : 0;

		if ( $stored_member_id > 0 ) {
			$stored_member = $controller->get_member( $stored_member_id );
			if ( $stored_member && isset( $stored_member->id ) ) {
				return (int) $stored_member->id;
			}
		}

		$existing_member = $controller->get_member_by_user_id( (int) $wp_user->ID );
		if ( $existing_member && isset( $existing_member->id ) ) {
			return new WP_Error( 'tpw_signup_member_exists', 'A TPW member already exists for the resolved WordPress user.' );
		}

		$insert_data                = is_array( $member_data ) ? $member_data : array();
		$insert_data['society_id']  = $this->resolve_default_society_id();
		$insert_data['user_id']     = (int) $wp_user->ID;
		$insert_data['email']       = isset( $insert_data['email'] ) && '' !== $insert_data['email'] ? sanitize_email( $insert_data['email'] ) : sanitize_email( $wp_user->user_email );
		$insert_data['username']    = isset( $insert_data['username'] ) && '' !== $insert_data['username'] ? sanitize_user( $insert_data['username'], true ) : sanitize_user( $wp_user->user_login, true );
		$insert_data['status']      = $this->get_default_member_status();
		$insert_data['date_joined'] = gmdate( 'Y-m-d' );

		if ( $insert_data['society_id'] <= 0 ) {
			return new WP_Error( 'tpw_signup_society_id_missing', 'Unable to resolve a society_id for member creation.' );
		}

		$member_id = $controller->add_member( $insert_data );
		if ( false === $member_id ) {
			return new WP_Error( 'tpw_signup_member_create_failed', 'The TPW member record could not be created.' );
		}

		return (int) $member_id;
	}

	/**
	 * Save mapped member meta values for the created member.
	 *
	 * @param int   $member_id         Member ID.
	 * @param array $member_meta_data  Meta payload.
	 * @return true|WP_Error
	 */
	private function save_member_meta( $member_id, $member_meta_data ) {
		foreach ( $member_meta_data as $meta_key => $meta_value ) {
			$meta_key = sanitize_key( $meta_key );
			if ( '' === $meta_key ) {
				continue;
			}

			if ( '' === $meta_value || null === $meta_value ) {
				continue;
			}

			$saved = TPW_Member_Meta::save_meta( (int) $member_id, $meta_key, $meta_value );
			if ( false === $saved ) {
				return new WP_Error(
					'tpw_signup_member_meta_save_failed',
					sprintf( 'Unable to save member meta for key: %s', $meta_key )
				);
			}
		}

		return true;
	}

	/**
	 * Mark an in-progress finalization as failed with durable error context.
	 *
	 * @param array    $attempt         Loaded attempt.
	 * @param string   $lock_token      Finalization lock token.
	 * @param string   $stage           Failure stage key.
	 * @param WP_Error $error           Failure error.
	 * @param array    $partial_result  Result payload refs gathered so far.
	 * @return WP_Error
	 */
	private function handle_finalization_failure( $attempt, $lock_token, $stage, $error, $partial_result ) {
		$error = is_wp_error( $error ) ? $error : new WP_Error( 'tpw_signup_finalization_failed', 'Signup finalization failed.' );

		$error_data                       = $partial_result;
		$error_data['finalization_error'] = array(
			'stage'   => sanitize_key( $stage ),
			'code'    => sanitize_key( $error->get_error_code() ),
			'message' => $error->get_error_message(),
		);

		$failed_attempt = $this->attempts_service->mark_finalization_failed(
			(int) $attempt['id'],
			$lock_token,
			array(
				'last_error_code'    => sanitize_key( $error->get_error_code() ),
				'last_error_message' => $error->get_error_message(),
				'result_payload'     => $error_data,
			)
		);

		if ( is_wp_error( $failed_attempt ) ) {
			return new WP_Error(
				'tpw_signup_finalization_failure_unpersisted',
				$failed_attempt->get_error_message(),
				array(
					'cause'          => $error,
					'partial_result' => $error_data,
				)
			);
		}

		return new WP_Error(
			sanitize_key( $error->get_error_code() ),
			$error->get_error_message(),
			array(
				'attempt'        => $failed_attempt,
				'partial_result' => $error_data,
			)
		);
	}

	/**
	 * Resolve a default society ID for new members.
	 *
	 * @return int
	 */
	private function resolve_default_society_id() {
		global $wpdb;

		$option_value = absint( get_option( 'tpw_default_society_id' ) );
		if ( $option_value > 0 ) {
			return $option_value;
		}

		$table_name = $wpdb->prefix . 'tpw_members';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted internal table name with a narrow fallback lookup.
		$resolved = (int) $wpdb->get_var( "SELECT society_id FROM {$table_name} ORDER BY id ASC LIMIT 1" );

		return $resolved > 0 ? $resolved : 0;
	}

	/**
	 * Get the default member status for finalized signups.
	 *
	 * @return string
	 */
	private function get_default_member_status() {
		$default_status = get_option( 'tpw_default_member_status', 'Active' );
		if ( ! is_string( $default_status ) || '' === trim( $default_status ) ) {
			$default_status = 'Active';
		}

		return $this->normalize_member_status( $default_status );
	}

	/**
	 * Normalize member statuses to the existing canonical labels.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	private function normalize_member_status( $status ) {
		$status_map = array(
			'active'      => 'Active',
			'inactive'    => 'Inactive',
			'deceased'    => 'Deceased',
			'honorary'    => 'Honorary',
			'resigned'    => 'Resigned',
			'suspended'   => 'Suspended',
			'pending'     => 'Pending',
			'life'        => 'Life Member',
			'life member' => 'Life Member',
		);

		$status_key = strtolower( trim( (string) $status ) );
		if ( isset( $status_map[ $status_key ] ) ) {
			return $status_map[ $status_key ];
		}

		return sanitize_text_field( (string) $status );
	}

	/**
	 * Ensure a unique WordPress user_login.
	 *
	 * @param string $candidate Initial username candidate.
	 * @param string $email     Email address fallback.
	 * @return string
	 */
	private function ensure_unique_user_login( $candidate, $email ) {
		$user_login = sanitize_user( $candidate, true );
		if ( '' === $user_login && '' !== $email ) {
			$email_parts = explode( '@', $email );
			$user_login  = sanitize_user( (string) current( $email_parts ), true );
		}

		if ( '' === $user_login ) {
			$user_login = 'member_' . strtolower( wp_generate_password( 8, false, false ) );
		}

		$user_login = substr( $user_login, 0, 60 );
		if ( '' === $user_login ) {
			return '';
		}

		if ( ! username_exists( $user_login ) ) {
			return $user_login;
		}

		$base_login = substr( $user_login, 0, 54 );
		if ( '' === $base_login ) {
			$base_login = 'member';
		}

		$index = 2;
		while ( $index < 1000 ) {
			$suffixed_login = substr( $base_login . $index, 0, 60 );
			if ( ! username_exists( $suffixed_login ) ) {
				return $suffixed_login;
			}

			++$index;
		}

		return '';
	}

	/**
	 * Load member dependencies on demand.
	 *
	 * @return void
	 */
	private function ensure_member_dependencies() {
		$dependency_map = array(
			'TPW_Member_Controller' => TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-controller.php',
			'TPW_Member_Meta'       => TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-meta.php',
			'TPW_Member_Roles'      => TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-roles.php',
		);

		foreach ( $dependency_map as $class_name => $file_path ) {
			if ( class_exists( $class_name ) ) {
				continue;
			}

			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}
	}
}
