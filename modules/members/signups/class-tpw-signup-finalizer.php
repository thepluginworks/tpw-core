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
	 * Check whether this finalizer supports the supplied attempt.
	 *
	 * @param array $attempt Loaded attempt.
	 * @return true|WP_Error
	 */
	public function supports_attempt( $attempt ) {
		if ( empty( $attempt['id'] ) ) {
			return new WP_Error( 'tpw_signup_attempt_missing', 'The signup attempt could not be found.' );
		}

		$flow_key = isset( $attempt['flow_key'] ) ? sanitize_key( $attempt['flow_key'] ) : '';
		if ( 'members_join' !== $flow_key ) {
			return new WP_Error( 'tpw_signup_attempt_flow_unsupported', 'This finalizer currently supports only the members_join flow.' );
		}

		return true;
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
		$completed_result = $this->maybe_return_completed_result( $attempt );
		if ( is_wp_error( $completed_result ) ) {
			return $completed_result;
		}

		if ( is_array( $completed_result ) ) {
			return $completed_result;
		}

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
		$member_result = $this->resolve_or_create_member( $attempt, $mapped_data['member_data'], $wp_user );
		if ( is_wp_error( $member_result ) ) {
			return $this->handle_finalization_failure( $attempt, $lock_token, $stage, $member_result, $partial_result );
		}

		$member_id                    = (int) $member_result['member_id'];
		$partial_result['member_id']  = $member_id;
		$partial_result['member_status'] = $member_result['member_status'];
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

		$identity_role = TPW_Member_Roles::sync_identity_projection( (int) $wp_user->ID, $member_result['member_status'] );

		$completed_result                       = $partial_result;
		$completed_result['finalization_error'] = null;
		$completed_result['identity_role']      = $identity_role;

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

		return $this->build_success_result(
			$completed_attempt,
			(int) $wp_user->ID,
			$member_id,
			$member_result['member_status'],
			$identity_role
		);
	}

	/**
	 * Validate that an attempt is eligible for this finalizer.
	 *
	 * @param array $attempt Loaded attempt.
	 * @return array|WP_Error
	 */
	private function validate_attempt_for_finalization( $attempt ) {
		$supported = $this->supports_attempt( $attempt );
		if ( is_wp_error( $supported ) ) {
			return $supported;
		}

		$status = isset( $attempt['status'] ) ? sanitize_key( $attempt['status'] ) : '';
		if ( ! in_array( $status, array( 'payment_succeeded', 'finalization_failed' ), true ) ) {
			return new WP_Error( 'tpw_signup_attempt_not_finalizable', 'This signup attempt is not eligible for finalization.' );
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

		$user_login = TPW_Member_Username_Generator::resolve_new_user_login(
			'',
			false,
			TPW_Member_Username_Generator::MAX_USER_LOGIN_LENGTH,
			isset( $wp_user_data['first_name'] ) ? (string) $wp_user_data['first_name'] : '',
			isset( $wp_user_data['last_name'] ) ? (string) $wp_user_data['last_name'] : ''
		);
		if ( '' === $user_login ) {
			return new WP_Error( 'tpw_signup_user_login_missing', 'Unable to derive a unique username for the signup attempt.' );
		}

		$insert_data = array(
			'user_login'   => $user_login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password(),
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
	 * @return array<string, mixed>|WP_Error
	 */
	private function resolve_or_create_member( $attempt, $member_data, $wp_user ) {
		$controller       = new TPW_Member_Controller();
		$result_payload   = $this->get_existing_result_payload( $attempt );
		$stored_member_id = isset( $result_payload['member_id'] ) ? absint( $result_payload['member_id'] ) : 0;
		$member_data      = is_array( $member_data ) ? $member_data : array();
		$matched_member   = $this->find_existing_member_record( $controller, $stored_member_id, $member_data, $wp_user );
		if ( is_wp_error( $matched_member ) ) {
			return $matched_member;
		}

		if ( $matched_member ) {
			$updated_member = $this->update_existing_member_record( $controller, $matched_member, $member_data, $wp_user );
			if ( is_wp_error( $updated_member ) ) {
				return $updated_member;
			}

			return array(
				'member_id'     => (int) $updated_member->id,
				'member_status' => $this->resolve_member_status_for_existing_record( $updated_member ),
				'action'        => 'updated',
			);
		}

		$insert_data                = $member_data;
		$insert_data['society_id']  = $this->resolve_society_id_for_attempt( $attempt );
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

		return array(
			'member_id'     => (int) $member_id,
			'member_status' => $insert_data['status'],
			'action'        => 'created',
		);
	}

	/**
	 * Return an idempotent success result for already-completed attempts.
	 *
	 * @param array $attempt Loaded attempt.
	 * @return array<string, mixed>|WP_Error|null
	 */
	private function maybe_return_completed_result( $attempt ) {
		$status = isset( $attempt['status'] ) ? sanitize_key( $attempt['status'] ) : '';
		if ( 'completed' !== $status ) {
			return null;
		}

		$result_payload = $this->get_existing_result_payload( $attempt );
		$wp_user_id     = isset( $result_payload['wp_user_id'] ) ? absint( $result_payload['wp_user_id'] ) : 0;
		$member_id      = isset( $result_payload['member_id'] ) ? absint( $result_payload['member_id'] ) : 0;

		if ( $wp_user_id <= 0 || $member_id <= 0 ) {
			return new WP_Error( 'tpw_signup_attempt_completed_incomplete', 'The signup attempt is marked completed but its finalization references are incomplete.' );
		}

		$member_controller = new TPW_Member_Controller();
		$member            = $member_controller->get_member( $member_id );
		$wp_user           = get_user_by( 'ID', $wp_user_id );

		if ( ! $member || ! isset( $member->id ) || ! ( $wp_user instanceof WP_User ) ) {
			return new WP_Error( 'tpw_signup_attempt_completed_missing_refs', 'The signup attempt is marked completed but its finalized user or member record could not be loaded.' );
		}

		$member_status = isset( $member->status ) ? $this->normalize_member_status( $member->status ) : '';
		$identity_role = TPW_Member_Roles::get_identity_role_for_status( $member_status );

		return $this->build_success_result( $attempt, $wp_user_id, $member_id, $member_status, $identity_role );
	}

	/**
	 * Build a normalized finalization success payload.
	 *
	 * @param array       $attempt        Loaded attempt.
	 * @param int         $wp_user_id     Resolved WordPress user ID.
	 * @param int         $member_id      Canonical TPW member ID.
	 * @param string      $member_status  Canonical TPW member status.
	 * @param string|null $identity_role  Projected Core identity role.
	 * @return array<string, mixed>
	 */
	private function build_success_result( $attempt, $wp_user_id, $member_id, $member_status, $identity_role ) {
		return array(
			'success'       => true,
			'attempt_id'    => isset( $attempt['id'] ) ? (int) $attempt['id'] : 0,
			'status'        => isset( $attempt['status'] ) ? $attempt['status'] : 'completed',
			'wp_user_id'    => (int) $wp_user_id,
			'member_id'     => (int) $member_id,
			'member_status' => $member_status,
			'identity_role' => $identity_role,
			'attempt'       => $attempt,
		);
	}

	/**
	 * Find an existing member row that should be reused for this attempt.
	 *
	 * @param TPW_Member_Controller $controller       Member controller.
	 * @param int                   $stored_member_id Previously stored member ID.
	 * @param array                 $member_data      Mapped member data.
	 * @param WP_User               $wp_user          Resolved WordPress user.
	 * @return object|WP_Error|null
	 */
	private function find_existing_member_record( $controller, $stored_member_id, $member_data, $wp_user ) {
		if ( $stored_member_id > 0 ) {
			$stored_member = $controller->get_member( $stored_member_id );
			if ( $stored_member && isset( $stored_member->id ) ) {
				if ( ! empty( $stored_member->user_id ) && (int) $stored_member->user_id !== (int) $wp_user->ID ) {
					return new WP_Error( 'tpw_signup_member_conflict', 'The stored member reference is linked to a different WordPress user.' );
				}

				return $stored_member;
			}
		}

		$existing_member = $controller->get_member_by_user_id( (int) $wp_user->ID );
		if ( $existing_member && isset( $existing_member->id ) ) {
			return $existing_member;
		}

		$matches = array();
		$email   = isset( $member_data['email'] ) && '' !== $member_data['email'] ? sanitize_email( $member_data['email'] ) : sanitize_email( $wp_user->user_email );
		if ( '' !== $email ) {
			$email_match = TPW_Member_Access::get_member_by_email( $email );
			if ( $email_match && isset( $email_match->id ) ) {
				$matches[ (int) $email_match->id ] = $email_match;
			}
		}

		$username = isset( $member_data['username'] ) && '' !== $member_data['username'] ? sanitize_user( $member_data['username'], true ) : sanitize_user( $wp_user->user_login, true );
		if ( '' !== $username ) {
			$username_match = TPW_Member_Access::get_member_by_username( $username );
			if ( $username_match && isset( $username_match->id ) ) {
				$matches[ (int) $username_match->id ] = $username_match;
			}
		}

		if ( count( $matches ) > 1 ) {
			return new WP_Error( 'tpw_signup_member_ambiguous', 'Multiple existing TPW member records match this signup attempt.' );
		}

		if ( empty( $matches ) ) {
			return null;
		}

		$matched_member = reset( $matches );
		if ( ! empty( $matched_member->user_id ) && (int) $matched_member->user_id !== (int) $wp_user->ID ) {
			return new WP_Error( 'tpw_signup_member_link_conflict', 'The matched TPW member record is already linked to a different WordPress user.' );
		}

		return $matched_member;
	}

	/**
	 * Update an existing member row without creating duplicates.
	 *
	 * @param TPW_Member_Controller $controller      Member controller.
	 * @param object                $existing_member Existing member row.
	 * @param array                 $member_data     Mapped member data.
	 * @param WP_User               $wp_user         Resolved WordPress user.
	 * @return object|WP_Error
	 */
	private function update_existing_member_record( $controller, $existing_member, $member_data, $wp_user ) {
		$update_data = array(
			'user_id'   => (int) $wp_user->ID,
			'email'     => isset( $member_data['email'] ) && '' !== $member_data['email'] ? sanitize_email( $member_data['email'] ) : sanitize_email( $wp_user->user_email ),
			'username'  => isset( $member_data['username'] ) && '' !== $member_data['username'] ? sanitize_user( $member_data['username'], true ) : sanitize_user( $wp_user->user_login, true ),
			'status'    => $this->resolve_member_status_for_existing_record( $existing_member ),
		);

		$fields_to_fill = array(
			'first_name',
			'surname',
			'initials',
			'title',
			'decoration',
			'mobile',
			'landline',
			'member_photo',
			'address1',
			'address2',
			'town',
			'county',
			'postcode',
			'country',
			'dob',
		);

		foreach ( $fields_to_fill as $field_key ) {
			if ( ! isset( $member_data[ $field_key ] ) || '' === $member_data[ $field_key ] || null === $member_data[ $field_key ] ) {
				continue;
			}

			$update_data[ $field_key ] = $member_data[ $field_key ];
		}

		if ( empty( $existing_member->date_joined ) ) {
			$update_data['date_joined'] = gmdate( 'Y-m-d' );
		}

		if ( ! empty( $existing_member->user_id ) ) {
			$sync_result = TPW_Member_Email_Sync::sync_linked_member_email(
				$controller,
				$existing_member,
				(string) $update_data['email'],
				array( 'source' => 'signup_finalizer' )
			);
			if ( is_wp_error( $sync_result ) ) {
				return $sync_result;
			}

			unset( $update_data['email'] );
		}

		$updated = $controller->update_member( (int) $existing_member->id, $update_data );
		if ( false === $updated ) {
			return new WP_Error( 'tpw_signup_member_update_failed', 'The TPW member record could not be updated.' );
		}

		$reloaded_member = $controller->get_member( (int) $existing_member->id );
		if ( ! $reloaded_member || ! isset( $reloaded_member->id ) ) {
			return new WP_Error( 'tpw_signup_member_reload_failed', 'The updated TPW member record could not be reloaded.' );
		}

		return $reloaded_member;
	}

	/**
	 * Resolve the canonical member status for an existing record.
	 *
	 * @param object $existing_member Existing member row.
	 * @return string
	 */
	private function resolve_member_status_for_existing_record( $existing_member ) {
		$current_status = isset( $existing_member->status ) ? $this->normalize_member_status( $existing_member->status ) : '';
		if ( '' !== $current_status ) {
			return $current_status;
		}

		return $this->get_default_member_status();
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
	 * Resolve the society ID for a finalized attempt.
	 *
	 * Prefers an explicit provider-carried value from the attempt payload before
	 * falling back to the site-level resolver.
	 *
	 * @param array $attempt Loaded attempt.
	 * @return int
	 */
	private function resolve_society_id_for_attempt( $attempt ) {
		$payload_sources = array(
			$this->get_existing_result_payload( $attempt ),
			$this->get_request_payload( $attempt ),
		);

		foreach ( $payload_sources as $payload ) {
			if ( is_wp_error( $payload ) || ! is_array( $payload ) ) {
				continue;
			}

			if ( ! empty( $payload['subscriptions_join']['society_id'] ) ) {
				return absint( $payload['subscriptions_join']['society_id'] );
			}

			if ( ! empty( $payload['context']['society_id'] ) ) {
				return absint( $payload['context']['society_id'] );
			}
		}

		return $this->resolve_default_society_id();
	}

	/**
	 * Resolve a default society ID for new members.
	 *
	 * @return int
	 */
	private function resolve_default_society_id() {
		return tpw_core_get_site_society_id();
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
	 * Load member dependencies on demand.
	 *
	 * @return void
	 */
	private function ensure_member_dependencies() {
		$dependency_map = array(
			'TPW_Identity'          => TPW_CORE_PATH . 'modules/members/includes/class-tpw-identity.php',
			'TPW_Member_Access'     => TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-access.php',
			'TPW_Member_Controller' => TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-controller.php',
			'TPW_Member_Email_Sync' => TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-email-sync.php',
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
