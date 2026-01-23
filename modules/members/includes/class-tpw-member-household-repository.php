<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Member_Household_Repository {

	/**
	 * @var string
	 */
	private $table_household;

	/**
	 * @var string
	 */
	private $table_member;

	/**
	 * @var string
	 */
	private $table_members;

	/**
	 * Allowed household roles.
	 *
	 * @var string[]
	 */
	private $allowed_roles = array( 'primary', 'partner', 'child' );

	public function __construct() {
		global $wpdb;
		$this->table_household = $wpdb->prefix . 'tpw_members_household';
		$this->table_member    = $wpdb->prefix . 'tpw_members_household_member';
		$this->table_members   = $wpdb->prefix . 'tpw_members';
	}

	/**
	 * Create a new household.
	 *
	 * @param int $society_id
	 * @return int Household ID (0 on failure)
	 */
	public function create_household( $society_id ) {
		global $wpdb;
		$society_id = (int) $society_id;
		if ( $society_id <= 0 ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->insert(
			$this->table_household,
			array( 'society_id' => $society_id ),
			array( '%d' )
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Assign a member to a household.
	 *
	 * Business rules:
	 * - A member can belong to ONLY ONE household (enforced by UNIQUE(member_id)).
	 * - If already assigned elsewhere, they are MOVED.
	 * - Roles limited to: primary, partner, child.
	 * - One primary contact per household (enforced when $is_primary is true).
	 *
	 * @param int         $household_id
	 * @param int         $member_id
	 * @param string|null $role
	 * @param bool        $is_primary
	 * @return bool
	 */
	public function assign_member( $household_id, $member_id, $role = '', $is_primary = false ) {
		global $wpdb;

		$household_id = (int) $household_id;
		$member_id    = (int) $member_id;
		$is_primary   = (bool) $is_primary;

		if ( $household_id <= 0 || $member_id <= 0 ) {
			return false;
		}

		$role = is_string( $role ) ? strtolower( trim( $role ) ) : '';
		if ( '' !== $role && ! in_array( $role, $this->allowed_roles, true ) ) {
			return false;
		}

		$existing = $this->get_household_for_member( $member_id );
		if ( $existing && isset( $existing->household_id ) && (int) $existing->household_id !== $household_id ) {
			$this->remove_member( $member_id );
		}

		if ( $is_primary ) {
			$role = 'primary';
		}

		$data = array(
			'household_id' => $household_id,
			'member_id'    => $member_id,
			'role'         => ( '' !== $role ? $role : null ),
			'is_primary'   => $is_primary ? 1 : 0,
		);

		$formats = array( '%d', '%d', '%s', '%d' );

		// Replace ensures UNIQUE(member_id) is honored (move/update in one operation).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->replace( $this->table_member, $data, $formats );
		if ( ! $ok ) {
			return false;
		}

		if ( $is_primary ) {
			return $this->set_primary_contact( $household_id, $member_id );
		}

		return true;
	}

	/**
	 * Set a member as the primary contact for a household.
	 * Clears any existing primary in that household.
	 *
	 * @param int $household_id
	 * @param int $member_id
	 * @return bool
	 */
	public function set_primary_contact( $household_id, $member_id ) {
		global $wpdb;

		$household_id = (int) $household_id;
		$member_id    = (int) $member_id;
		if ( $household_id <= 0 || $member_id <= 0 ) {
			return false;
		}

		// Ensure membership exists in this household (will move if needed).
		$membership = $this->get_household_for_member( $member_id );
		if ( ! $membership || (int) $membership->household_id !== $household_id ) {
			return $this->assign_member( $household_id, $member_id, 'primary', true );
		}

		// Best-effort transaction (only if supported by the underlying DB engine).
		$in_txn = false;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$txn_started = $wpdb->query( 'START TRANSACTION' );
		if ( false !== $txn_started ) {
			$in_txn = true;
		}

		// Clear any existing primary in the household.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$prepared = $wpdb->prepare( "UPDATE {$this->table_member} SET is_primary = 0 WHERE household_id = %d", $household_id );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cleared = $wpdb->query( $prepared );
		if ( false === $cleared ) {
			if ( $in_txn ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->query( 'ROLLBACK' );
			}
			return false;
		}

		// Set this member as primary (and normalise role).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$this->table_member,
			array(
				'is_primary' => 1,
				'role'       => 'primary',
			),
			array(
				'household_id' => $household_id,
				'member_id'    => $member_id,
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);
		if ( false === $updated ) {
			if ( $in_txn ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->query( 'ROLLBACK' );
			}
			return false;
		}

		// Normalise roles: only the new primary may retain role='primary'.
		// Downgrade any other stale primary-role rows to partner, but do not touch children.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$prepared = $wpdb->prepare(
			"UPDATE {$this->table_member}
			 SET role = 'partner'
			 WHERE household_id = %d
			   AND member_id <> %d
			   AND role = 'primary'",
			$household_id,
			$member_id
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$downgraded = $wpdb->query( $prepared );
		if ( false === $downgraded ) {
			if ( $in_txn ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->query( 'ROLLBACK' );
			}
			return false;
		}

		if ( $in_txn ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( 'COMMIT' );
		}

		return true;
	}

	/**
	 * Remove a member from whichever household they are in.
	 *
	 * @param int $member_id
	 * @return bool
	 */
	public function remove_member( $member_id ) {
		global $wpdb;
		$member_id = (int) $member_id;
		if ( $member_id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( $this->table_member, array( 'member_id' => $member_id ), array( '%d' ) );
		return false !== $deleted;
	}

	/**
	 * Get the household membership row for a member.
	 *
	 * @param int $member_id
	 * @return object|null
	 */
	public function get_household_for_member( $member_id ) {
		global $wpdb;
		$member_id = (int) $member_id;
		if ( $member_id <= 0 ) {
			return null;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$prepared = $wpdb->prepare( "SELECT * FROM {$this->table_member} WHERE member_id = %d LIMIT 1", $member_id );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row( $prepared );
	}

	/**
	 * Get a household row.
	 *
	 * @param int $household_id
	 * @return object|null
	 */
	public function get_household( $household_id ) {
		global $wpdb;
		$household_id = (int) $household_id;
		if ( $household_id <= 0 ) {
			return null;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$prepared = $wpdb->prepare( "SELECT * FROM {$this->table_household} WHERE id = %d LIMIT 1", $household_id );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row( $prepared );
	}

	/**
	 * Get all members of a household.
	 *
	 * Includes membership role/is_primary, and member name/email.
	 *
	 * @param int $household_id
	 * @return array<object>
	 */
	public function get_household_members( $household_id ) {
		global $wpdb;
		$household_id = (int) $household_id;
		if ( $household_id <= 0 ) {
			return array();
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$prepared = $wpdb->prepare(
			"SELECT
				hm.member_id,
				hm.role,
				hm.is_primary,
				m.first_name,
				m.surname,
				m.email
			FROM {$this->table_member} AS hm
			LEFT JOIN {$this->table_members} AS m ON m.id = hm.member_id
			WHERE hm.household_id = %d
			ORDER BY hm.is_primary DESC, hm.id ASC",
			$household_id
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results( $prepared );
	}
}
