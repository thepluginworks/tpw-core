<?php
/**
 * Synchronize linked member email changes between tpw_members and wp_users.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Member_Email_Sync {
	/**
	 * Synchronize a linked member email without changing user_login.
	 *
	 * @param TPW_Member_Controller $controller      Member controller.
	 * @param object                $member          Member row.
	 * @param string                $requested_email Requested email.
	 * @param array                 $context         Optional call context.
	 * @return array|WP_Error
	 */
	public static function sync_linked_member_email( TPW_Member_Controller $controller, $member, $requested_email, array $context = array() ) {
		$source           = isset( $context['source'] ) ? sanitize_key( (string) $context['source'] ) : '';
		$requested_email  = trim( (string) $requested_email );
		$normalized_email = sanitize_email( $requested_email );

		if ( '' === $normalized_email || ! is_email( $normalized_email ) ) {
			return new WP_Error( 'tpw_member_email_invalid', 'Please enter a valid email address.' );
		}

		if ( ! is_object( $member ) || empty( $member->id ) ) {
			return new WP_Error( 'tpw_member_email_member_missing', 'The member record could not be loaded.' );
		}

		$user_id = isset( $member->user_id ) ? (int) $member->user_id : 0;
		if ( $user_id <= 0 ) {
			return new WP_Error( 'tpw_member_email_unlinked', 'This member is not linked to a WordPress user.' );
		}

		$wp_user = get_user_by( 'id', $user_id );
		if ( ! $wp_user ) {
			error_log(
				'[TPW Members] Linked email sync broken link source=' . $source .
				' member_id=' . (int) $member->id .
				' user_id=' . $user_id .
				' requested_email=' . $normalized_email
			);

			return new WP_Error(
				'tpw_member_email_broken_link',
				'This member is linked to a WordPress user that could not be loaded. Repair the account link before changing the email.'
			);
		}

		$member_email_before = isset( $member->email ) ? (string) $member->email : '';
		$wp_email_before     = isset( $wp_user->user_email ) ? (string) $wp_user->user_email : '';
		$member_compare      = self::normalize_for_compare( $member_email_before );
		$wp_compare          = self::normalize_for_compare( $wp_email_before );
		$target_compare      = self::normalize_for_compare( $normalized_email );

		if ( $member_compare === $target_compare && $wp_compare === $target_compare ) {
			return self::build_success_result(
				$member,
				$wp_user,
				$source,
				$requested_email,
				$normalized_email,
				$member_email_before,
				$wp_email_before,
				$member_email_before,
				$wp_email_before,
				false,
				false,
				'noop'
			);
		}

		if ( $wp_compare !== $target_compare ) {
			$existing_user = get_user_by( 'email', $normalized_email );
			if ( $existing_user && (int) $existing_user->ID !== (int) $wp_user->ID ) {
				return new WP_Error( 'tpw_member_email_conflict', 'That email address is already in use by another WordPress account. Use a different email or link the correct account first.' );
			}
		}

		if ( $member_compare !== $target_compare && $wp_compare === $target_compare ) {
			$updated = $controller->update_member( (int) $member->id, array( 'email' => $normalized_email ) );
			if ( false === $updated ) {
				return new WP_Error( 'tpw_member_email_member_update_failed', 'The member email could not be synchronized with the linked WordPress account. No email change was saved.' );
			}

			$result = self::build_success_result(
				$member,
				$wp_user,
				$source,
				$requested_email,
				$normalized_email,
				$member_email_before,
				$wp_email_before,
				$normalized_email,
				$wp_email_before,
				true,
				false,
				'drift_heal_member'
			);

			do_action( 'tpw_members_linked_email_synced', $result, $member, $wp_user, $context );

			return $result;
		}

		if ( $member_compare === $target_compare && $wp_compare !== $target_compare ) {
			$wp_updated = wp_update_user(
				array(
					'ID'         => (int) $wp_user->ID,
					'user_email' => $normalized_email,
				)
			);
			if ( is_wp_error( $wp_updated ) ) {
				return new WP_Error( 'tpw_member_email_wp_update_failed', 'The member email could not be synchronized with the linked WordPress account. No email change was saved.' );
			}

			$result = self::build_success_result(
				$member,
				$wp_user,
				$source,
				$requested_email,
				$normalized_email,
				$member_email_before,
				$wp_email_before,
				$member_email_before,
				$normalized_email,
				false,
				true,
				'drift_heal_wp'
			);

			do_action( 'tpw_members_linked_email_synced', $result, $member, $wp_user, $context );

			return $result;
		}

		$updated = $controller->update_member( (int) $member->id, array( 'email' => $normalized_email ) );
		if ( false === $updated ) {
			return new WP_Error( 'tpw_member_email_member_update_failed', 'The member email could not be synchronized with the linked WordPress account. No email change was saved.' );
		}

		$wp_updated = wp_update_user(
			array(
				'ID'         => (int) $wp_user->ID,
				'user_email' => $normalized_email,
			)
		);
		if ( is_wp_error( $wp_updated ) ) {
			$rolled_back = $controller->update_member( (int) $member->id, array( 'email' => $member_email_before ) );
			if ( false === $rolled_back ) {
				error_log(
					'[TPW Members] Linked email sync rollback failed source=' . $source .
					' member_id=' . (int) $member->id .
					' user_id=' . (int) $wp_user->ID .
					' requested_email=' . $normalized_email .
					' member_email_before=' . $member_email_before .
					' wp_email_before=' . $wp_email_before .
					' wp_error_code=' . $wp_updated->get_error_code() .
					' wp_error_message=' . $wp_updated->get_error_message()
				);

				return new WP_Error(
					'tpw_member_email_rollback_failed',
					'The member email could not be synchronized with the linked WordPress account. No email change was saved.',
					array(
						'cause_code'    => $wp_updated->get_error_code(),
						'cause_message' => $wp_updated->get_error_message(),
					)
				);
			}

			return new WP_Error( 'tpw_member_email_wp_update_failed', 'The member email could not be synchronized with the linked WordPress account. No email change was saved.' );
		}

		$result = self::build_success_result(
			$member,
			$wp_user,
			$source,
			$requested_email,
			$normalized_email,
			$member_email_before,
			$wp_email_before,
			$normalized_email,
			$normalized_email,
			true,
			true,
			'synced_both'
		);

		do_action( 'tpw_members_linked_email_synced', $result, $member, $wp_user, $context );

		return $result;
	}

	/**
	 * Normalize email values for equality checks.
	 *
	 * @param string $email Raw email value.
	 * @return string
	 */
	private static function normalize_for_compare( $email ) {
		$email = trim( (string) $email );
		if ( '' === $email ) {
			return '';
		}

		return strtolower( $email );
	}

	/**
	 * Build a standard success payload.
	 *
	 * @param object  $member               Member row.
	 * @param WP_User $wp_user              WordPress user.
	 * @param string  $source               Call source.
	 * @param string  $requested_email      Raw requested email.
	 * @param string  $normalized_email     Sanitized email.
	 * @param string  $member_email_before  Previous member email.
	 * @param string  $wp_email_before      Previous WordPress email.
	 * @param string  $member_email_after   Final member email.
	 * @param string  $wp_email_after       Final WordPress email.
	 * @param bool    $did_update_member    Whether tpw_members changed.
	 * @param bool    $did_update_wp_user   Whether wp_users changed.
	 * @param string  $outcome              Result outcome.
	 * @return array
	 */
	private static function build_success_result( $member, $wp_user, $source, $requested_email, $normalized_email, $member_email_before, $wp_email_before, $member_email_after, $wp_email_after, $did_update_member, $did_update_wp_user, $outcome ) {
		return array(
			'member_id'           => (int) $member->id,
			'user_id'             => (int) $wp_user->ID,
			'source'              => $source,
			'requested_email'     => $requested_email,
			'normalized_email'    => $normalized_email,
			'member_email_before' => $member_email_before,
			'wp_email_before'     => $wp_email_before,
			'member_email_after'  => $member_email_after,
			'wp_email_after'      => $wp_email_after,
			'did_update_member'   => (bool) $did_update_member,
			'did_update_wp_user'  => (bool) $did_update_wp_user,
			'outcome'             => $outcome,
		);
	}
}