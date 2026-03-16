<?php
/**
 * Read-only identity audit tooling for TPW Core.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Identity_Audit_Admin {
	/**
	 * Settings tab slug.
	 *
	 * @var string
	 */
	const TAB_SLUG = 'identity-audit';

	/**
	 * Identity role slugs currently tracked by the audit.
	 *
	 * @var string[]
	 */
	const IDENTITY_ROLES = array( 'member', 'tpw_member' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'tpw_core_settings_tabs', array( __CLASS__, 'register_tab' ) );
		add_action( 'tpw_core_settings_tab_content_' . self::TAB_SLUG, array( __CLASS__, 'render_tab' ) );
	}

	/**
	 * Add the Identity Audit tab to TPW Core settings.
	 *
	 * @param array $tabs Existing settings tabs.
	 * @return array
	 */
	public static function register_tab( $tabs ) {
		if ( ! is_array( $tabs ) ) {
			$tabs = array();
		}

		$tabs[ self::TAB_SLUG ] = __( 'Identity Audit', 'tpw-core' );

		return $tabs;
	}

	/**
	 * Render the Identity Audit settings tab.
	 *
	 * @return void
	 */
	public static function render_tab() {
		if ( ! self::current_user_can_view() ) {
			echo '<p>' . esc_html__( 'You do not have permission to view this page.', 'tpw-core' ) . '</p>';
			return;
		}

		$report = self::build_report();

		echo '<div class="tpw-identity-audit">';
		echo '<h2>' . esc_html__( 'Identity Audit', 'tpw-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'Phase 1 safety tooling for the TPW identity architecture. This screen is read-only and reports current linkage, identity projection, role drift, and member status data without modifying any records.', 'tpw-core' ) . '</p>';

		if ( ! empty( $report['error'] ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( $report['error'] ) . '</p></div>';
			echo '</div>';
			return;
		}

		echo '<p><strong>' . esc_html__( 'Snapshot:', 'tpw-core' ) . '</strong> ' . esc_html( $report['snapshot_time'] ) . '</p>';

		self::render_linkage_section( $report );
		self::render_weak_linkage_section( $report );
		self::render_identity_role_projection_section( $report );
		self::render_unknown_role_section( $report );
		self::render_status_distribution_section( $report );
		self::render_drift_section( $report );

		echo '</div>';
	}

	/**
	 * Build the full report payload.
	 *
	 * @return array
	 */
	private static function build_report() {
		global $wpdb;

		$member_table = $wpdb->prefix . 'tpw_members';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off schema presence check for a read-only admin diagnostics screen.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $member_table ) );

		if ( $table_exists !== $member_table ) {
			return array(
				'error' => __( 'The tpw_members table is not available on this site, so the identity audit cannot run yet.', 'tpw-core' ),
			);
		}

		$users   = self::load_users();
		$members = self::load_members( $member_table );

		$member_indexes = self::build_member_indexes( $members );
		$user_indexes   = self::build_user_indexes( $users );
		$user_analysis  = self::analyze_users( $users, $member_indexes );
		$member_gaps    = self::find_members_without_corresponding_users( $members, $user_indexes );
		$identity_roles = self::get_identity_roles();

		return array(
			'snapshot_time'  => current_time( 'mysql' ),
			'users_total'    => count( $users ),
			'members_total'  => count( $members ),
			'linkage'        => array(
				'direct'               => $user_analysis['direct'],
				'weak'                 => $user_analysis['weak'],
				'unlinked_users'       => $user_analysis['none'],
				'members_without_user' => $member_gaps,
			),
			'weak_linkage'   => array(
				'rows'      => $user_analysis['weak'],
				'breakdown' => self::build_weak_linkage_breakdown( $user_analysis['weak'] ),
			),
			'identity_roles' => self::build_identity_role_projection_report( $users, $user_analysis['by_user_id'], $identity_roles ),
			'unknown_roles'  => self::build_unknown_role_report( $users, $identity_roles ),
			'statuses'       => self::build_status_distribution( $members ),
			'drift'          => self::build_drift_report( $users, $members, $user_analysis['by_user_id'], $member_gaps, $identity_roles ),
		);
	}

	/**
	 * Load all WordPress users for the current site.
	 *
	 * @return WP_User[]
	 */
	private static function load_users() {
		$users = get_users(
			array(
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		return is_array( $users ) ? $users : array();
	}

	/**
	 * Load the member rows used for the audit.
	 *
	 * @param string $member_table Member table name.
	 * @return array
	 */
	private static function load_members( $member_table ) {
		global $wpdb;

		$query = "SELECT id, user_id, first_name, surname, email, username, status FROM {$member_table} ORDER BY surname ASC, first_name ASC, id ASC";
		$rows  = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted internal table name only for a read-only snapshot report.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Build member lookup indexes used by the audit.
	 *
	 * @param array $members Member rows.
	 * @return array
	 */
	private static function build_member_indexes( array $members ) {
		$direct_by_user_id = array();
		$by_email          = array();
		$by_username       = array();

		foreach ( $members as $member ) {
			$user_id = isset( $member->user_id ) ? (int) $member->user_id : 0;
			if ( $user_id > 0 ) {
				$direct_by_user_id[ $user_id ] = $member;
			}

			$email_key = self::normalize_email_key( isset( $member->email ) ? (string) $member->email : '' );
			if ( '' !== $email_key ) {
				if ( ! isset( $by_email[ $email_key ] ) ) {
					$by_email[ $email_key ] = array();
				}
				$by_email[ $email_key ][] = $member;
			}

			$username_key = self::normalize_username_key( isset( $member->username ) ? (string) $member->username : '' );
			if ( '' !== $username_key ) {
				if ( ! isset( $by_username[ $username_key ] ) ) {
					$by_username[ $username_key ] = array();
				}
				$by_username[ $username_key ][] = $member;
			}
		}

		return array(
			'direct_by_user_id' => $direct_by_user_id,
			'by_email'          => $by_email,
			'by_username'       => $by_username,
		);
	}

	/**
	 * Build user lookup indexes used by the audit.
	 *
	 * @param WP_User[] $users WordPress users.
	 * @return array
	 */
	private static function build_user_indexes( array $users ) {
		$by_id       = array();
		$by_email    = array();
		$by_username = array();

		foreach ( $users as $user ) {
			if ( ! ( $user instanceof WP_User ) ) {
				continue;
			}

			$by_id[ (int) $user->ID ] = $user;

			$email_key = self::normalize_email_key( (string) $user->user_email );
			if ( '' !== $email_key ) {
				if ( ! isset( $by_email[ $email_key ] ) ) {
					$by_email[ $email_key ] = array();
				}
				$by_email[ $email_key ][] = $user;
			}

			$username_key = self::normalize_username_key( (string) $user->user_login );
			if ( '' !== $username_key ) {
				if ( ! isset( $by_username[ $username_key ] ) ) {
					$by_username[ $username_key ] = array();
				}
				$by_username[ $username_key ][] = $user;
			}
		}

		return array(
			'by_id'       => $by_id,
			'by_email'    => $by_email,
			'by_username' => $by_username,
		);
	}

	/**
	 * Analyze how WordPress users currently map to member rows.
	 *
	 * @param WP_User[] $users User objects.
	 * @param array     $member_indexes Member lookup indexes.
	 * @return array
	 */
	private static function analyze_users( array $users, array $member_indexes ) {
		$direct     = array();
		$weak       = array();
		$none       = array();
		$by_user_id = array();

		foreach ( $users as $user ) {
			if ( ! ( $user instanceof WP_User ) ) {
				continue;
			}

			$user_id       = (int) $user->ID;
			$direct_member = isset( $member_indexes['direct_by_user_id'][ $user_id ] )
				? $member_indexes['direct_by_user_id'][ $user_id ]
				: null;

			$row = array(
				'user'          => $user,
				'direct_member' => $direct_member,
				'weak_matches'  => array(),
				'linkage_type'  => 'none',
			);

			if ( null !== $direct_member ) {
				$row['linkage_type']    = 'direct';
				$direct[]               = $row;
				$by_user_id[ $user_id ] = $row;
				continue;
			}

			$weak_matches = self::find_weak_matches_for_user( $user, $member_indexes );
			if ( ! empty( $weak_matches ) ) {
				$row['linkage_type']    = 'weak';
				$row['weak_matches']    = $weak_matches;
				$weak[]                 = $row;
				$by_user_id[ $user_id ] = $row;
				continue;
			}

			$none[]                 = $row;
			$by_user_id[ $user_id ] = $row;
		}

		return array(
			'direct'     => $direct,
			'weak'       => $weak,
			'none'       => $none,
			'by_user_id' => $by_user_id,
		);
	}

	/**
	 * Find members that do not currently have a corresponding user.
	 *
	 * @param array $members Member rows.
	 * @param array $user_indexes User lookup indexes.
	 * @return array
	 */
	private static function find_members_without_corresponding_users( array $members, array $user_indexes ) {
		$rows = array();

		foreach ( $members as $member ) {
			$user_id = isset( $member->user_id ) ? (int) $member->user_id : 0;
			if ( $user_id > 0 ) {
				if ( ! isset( $user_indexes['by_id'][ $user_id ] ) ) {
					$rows[] = array(
						'member' => $member,
						'reason' => __( 'Linked user_id points to no existing WordPress user.', 'tpw-core' ),
					);
				}
				continue;
			}

			$weak_user_matches = self::find_weak_matches_for_member( $member, $user_indexes );
			if ( empty( $weak_user_matches ) ) {
				$rows[] = array(
					'member' => $member,
					'reason' => __( 'No linked user_id and no email or username fallback match.', 'tpw-core' ),
				);
			}
		}

		return $rows;
	}

	/**
	 * Build a weak-linkage breakdown grouped by match type.
	 *
	 * @param array $weak_rows Weak-linkage rows.
	 * @return array
	 */
	private static function build_weak_linkage_breakdown( array $weak_rows ) {
		$breakdown = array(
			'email_only'      => 0,
			'username_only'   => 0,
			'email_username'  => 0,
			'ambiguous_match' => 0,
		);

		foreach ( $weak_rows as $row ) {
			$method_keys = array();
			foreach ( $row['weak_matches'] as $match ) {
				foreach ( $match['methods'] as $method ) {
					$method_keys[ $method ] = true;
				}
			}

			if ( count( $row['weak_matches'] ) > 1 ) {
				++$breakdown['ambiguous_match'];
			}

			if ( isset( $method_keys['email'] ) && isset( $method_keys['username'] ) ) {
				++$breakdown['email_username'];
			} elseif ( isset( $method_keys['email'] ) ) {
				++$breakdown['email_only'];
			} elseif ( isset( $method_keys['username'] ) ) {
				++$breakdown['username_only'];
			}
		}

		return $breakdown;
	}

	/**
	 * Build the identity role projection report.
	 *
	 * @param WP_User[] $users Users.
	 * @param array     $user_analysis User analysis keyed by ID.
	 * @param string[]  $identity_roles Identity roles.
	 * @return array
	 */
	private static function build_identity_role_projection_report( array $users, array $user_analysis, array $identity_roles ) {
		$roles = array();

		foreach ( $identity_roles as $role ) {
			$roles[ $role ] = array();
		}

		foreach ( $users as $user ) {
			if ( ! ( $user instanceof WP_User ) ) {
				continue;
			}

			foreach ( (array) $user->roles as $role ) {
				if ( ! in_array( $role, $identity_roles, true ) ) {
					continue;
				}

				$analysis         = isset( $user_analysis[ (int) $user->ID ] ) ? $user_analysis[ (int) $user->ID ] : null;
				$roles[ $role ][] = array(
					'user'          => $user,
					'direct_member' => is_array( $analysis ) ? $analysis['direct_member'] : null,
					'weak_matches'  => is_array( $analysis ) ? $analysis['weak_matches'] : array(),
					'linkage_type'  => is_array( $analysis ) ? $analysis['linkage_type'] : 'none',
				);
			}
		}

		return $roles;
	}

	/**
	 * Build the unknown-role report.
	 *
	 * @param WP_User[] $users Users.
	 * @param string[]  $identity_roles Identity roles.
	 * @return array
	 */
	private static function build_unknown_role_report( array $users, array $identity_roles ) {
		$roles = array();

		foreach ( $users as $user ) {
			if ( ! ( $user instanceof WP_User ) ) {
				continue;
			}

			foreach ( (array) $user->roles as $role ) {
				if ( self::is_known_role( $role, $identity_roles ) ) {
					continue;
				}

				if ( ! isset( $roles[ $role ] ) ) {
					$roles[ $role ] = array();
				}

				$roles[ $role ][] = $user;
			}
		}

		ksort( $roles );

		return $roles;
	}

	/**
	 * Build the member status distribution.
	 *
	 * @param array $members Member rows.
	 * @return array
	 */
	private static function build_status_distribution( array $members ) {
		$statuses = array();

		foreach ( $members as $member ) {
			$status = isset( $member->status ) ? trim( (string) $member->status ) : '';
			if ( '' === $status ) {
				$status = '(empty)';
			}

			if ( ! isset( $statuses[ $status ] ) ) {
				$statuses[ $status ] = 0;
			}

			++$statuses[ $status ];
		}

		ksort( $statuses, SORT_NATURAL | SORT_FLAG_CASE );

		$rows = array();
		foreach ( $statuses as $status => $count ) {
			$rows[] = array(
				'status' => $status,
				'count'  => $count,
			);
		}

		return $rows;
	}

	/**
	 * Build diagnostic drift warnings.
	 *
	 * @param WP_User[] $users Users.
	 * @param array     $members Members.
	 * @param array     $user_analysis User analysis by user ID.
	 * @param array     $member_gaps Member gaps.
	 * @param string[]  $identity_roles Identity roles.
	 * @return array
	 */
	private static function build_drift_report( array $users, array $members, array $user_analysis, array $member_gaps, array $identity_roles ) {
		$role_without_member = array();
		$member_without_role = array();
		$linked_missing_user = array();
		$weak_only           = array();

		foreach ( $users as $user ) {
			if ( ! ( $user instanceof WP_User ) ) {
				continue;
			}

			$analysis          = isset( $user_analysis[ (int) $user->ID ] ) ? $user_analysis[ (int) $user->ID ] : null;
			$roles             = (array) $user->roles;
			$has_identity_role = false;
			foreach ( $roles as $role ) {
				if ( in_array( $role, $identity_roles, true ) ) {
					$has_identity_role = true;
					break;
				}
			}

			$linkage_type = is_array( $analysis ) ? $analysis['linkage_type'] : 'none';
			$has_member   = ( 'direct' === $linkage_type || 'weak' === $linkage_type );

			if ( $has_identity_role && ! $has_member ) {
				$role_without_member[] = array(
					'user'  => $user,
					'roles' => $roles,
				);
			}

			if ( $has_member && ! $has_identity_role ) {
				$member_without_role[] = array(
					'user'         => $user,
					'analysis'     => $analysis,
					'member_label' => self::format_primary_member_label( $analysis ),
				);
			}

			if ( 'weak' === $linkage_type ) {
				$weak_only[] = array(
					'user'         => $user,
					'weak_matches' => is_array( $analysis ) ? $analysis['weak_matches'] : array(),
				);
			}
		}

		foreach ( $member_gaps as $gap ) {
			$member = isset( $gap['member'] ) ? $gap['member'] : null;
			if ( ! is_object( $member ) ) {
				continue;
			}

			$user_id = isset( $member->user_id ) ? (int) $member->user_id : 0;
			if ( $user_id > 0 ) {
				$linked_missing_user[] = $gap;
			}
		}

		return array(
			'summary'             => array(
				array(
					'label' => __( 'Users with identity role but no member record', 'tpw-core' ),
					'count' => count( $role_without_member ),
				),
				array(
					'label' => __( 'Users with member record but no identity role', 'tpw-core' ),
					'count' => count( $member_without_role ),
				),
				array(
					'label' => __( 'Member records linked to non-existent users', 'tpw-core' ),
					'count' => count( $linked_missing_user ),
				),
				array(
					'label' => __( 'Users resolving only through weak linkage fallback', 'tpw-core' ),
					'count' => count( $weak_only ),
				),
			),
			'role_without_member' => $role_without_member,
			'member_without_role' => $member_without_role,
			'linked_missing_user' => $linked_missing_user,
			'weak_only'           => $weak_only,
		);
	}

	/**
	 * Find weak fallback matches for a user.
	 *
	 * @param WP_User $user User object.
	 * @param array   $member_indexes Member indexes.
	 * @return array
	 */
	private static function find_weak_matches_for_user( WP_User $user, array $member_indexes ) {
		$matches = array();

		$email_key = self::normalize_email_key( (string) $user->user_email );
		if ( '' !== $email_key && ! empty( $member_indexes['by_email'][ $email_key ] ) ) {
			foreach ( $member_indexes['by_email'][ $email_key ] as $member ) {
				$member_id = isset( $member->id ) ? (int) $member->id : 0;
				if ( ! isset( $matches[ $member_id ] ) ) {
					$matches[ $member_id ] = array(
						'member'  => $member,
						'methods' => array(),
					);
				}
				$matches[ $member_id ]['methods']['email'] = 'email';
			}
		}

		$username_key = self::normalize_username_key( (string) $user->user_login );
		if ( '' !== $username_key && ! empty( $member_indexes['by_username'][ $username_key ] ) ) {
			foreach ( $member_indexes['by_username'][ $username_key ] as $member ) {
				$member_id = isset( $member->id ) ? (int) $member->id : 0;
				if ( ! isset( $matches[ $member_id ] ) ) {
					$matches[ $member_id ] = array(
						'member'  => $member,
						'methods' => array(),
					);
				}
				$matches[ $member_id ]['methods']['username'] = 'username';
			}
		}

		foreach ( $matches as $member_id => $match ) {
			$matches[ $member_id ]['methods'] = array_values( $match['methods'] );
		}

		return array_values( $matches );
	}

	/**
	 * Find weak fallback matches for a member.
	 *
	 * @param object $member Member row.
	 * @param array  $user_indexes User indexes.
	 * @return array
	 */
	private static function find_weak_matches_for_member( $member, array $user_indexes ) {
		$matches = array();

		$email_key = self::normalize_email_key( isset( $member->email ) ? (string) $member->email : '' );
		if ( '' !== $email_key && ! empty( $user_indexes['by_email'][ $email_key ] ) ) {
			foreach ( $user_indexes['by_email'][ $email_key ] as $user ) {
				$matches[ (int) $user->ID ] = $user;
			}
		}

		$username_key = self::normalize_username_key( isset( $member->username ) ? (string) $member->username : '' );
		if ( '' !== $username_key && ! empty( $user_indexes['by_username'][ $username_key ] ) ) {
			foreach ( $user_indexes['by_username'][ $username_key ] as $user ) {
				$matches[ (int) $user->ID ] = $user;
			}
		}

		return array_values( $matches );
	}

	/**
	 * Get the identity roles currently audited.
	 *
	 * @return string[]
	 */
	private static function get_identity_roles() {
		$roles = apply_filters( 'tpw_identity_audit_identity_roles', self::IDENTITY_ROLES );
		if ( ! is_array( $roles ) ) {
			$roles = self::IDENTITY_ROLES;
		}

		$roles = array_values( array_unique( array_filter( array_map( 'sanitize_key', $roles ) ) ) );

		return $roles;
	}

	/**
	 * Check whether a role should be treated as known for unknown-role reporting.
	 *
	 * @param string   $role Role slug.
	 * @param string[] $identity_roles Identity roles.
	 * @return bool
	 */
	private static function is_known_role( $role, array $identity_roles ) {
		$role = sanitize_key( (string) $role );
		if ( '' === $role ) {
			return true;
		}

		$core_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		if ( in_array( $role, $core_roles, true ) ) {
			return true;
		}

		if ( in_array( $role, $identity_roles, true ) ) {
			return true;
		}

		$known_tpw_roles = apply_filters(
			'tpw_identity_audit_known_tpw_roles',
			array(
				'candidate',
				'tpw_secretary',
				'tpw_treasurer',
				'tpw_committee',
				'tpw_match_manager',
			)
		);

		$known_tpw_roles = is_array( $known_tpw_roles ) ? array_map( 'sanitize_key', $known_tpw_roles ) : array();
		if ( in_array( $role, $known_tpw_roles, true ) ) {
			return true;
		}

		return 0 === strpos( $role, 'tpw_' );
	}

	/**
	 * Format a user label for table output.
	 *
	 * @param WP_User $user User object.
	 * @return string
	 */
	private static function format_user_label( WP_User $user ) {
		$name  = trim( (string) $user->display_name );
		$login = (string) $user->user_login;
		$email = (string) $user->user_email;

		if ( '' === $name ) {
			$name = $login;
		}

		return sprintf( '%1$s (#%2$d) | %3$s | %4$s', $name, (int) $user->ID, $login, $email );
	}

	/**
	 * Format a member label for table output.
	 *
	 * @param object $member Member row.
	 * @return string
	 */
	private static function format_member_label( $member ) {
		$parts = array();
		$name  = trim(
			implode(
				' ',
				array_filter(
					array(
						isset( $member->first_name ) ? (string) $member->first_name : '',
						isset( $member->surname ) ? (string) $member->surname : '',
					)
				)
			)
		);

		if ( '' === $name ) {
			$name = __( '(Unnamed member)', 'tpw-core' );
		}

		$parts[] = sprintf( '%1$s (#%2$d)', $name, isset( $member->id ) ? (int) $member->id : 0 );

		if ( ! empty( $member->email ) ) {
			$parts[] = (string) $member->email;
		}

		if ( ! empty( $member->status ) ) {
			$parts[] = sprintf( 'status: %s', (string) $member->status );
		}

		return implode( ' | ', $parts );
	}

	/**
	 * Format the primary member label from a user analysis row.
	 *
	 * @param array|null $analysis User analysis row.
	 * @return string
	 */
	private static function format_primary_member_label( $analysis ) {
		if ( ! is_array( $analysis ) ) {
			return '';
		}

		if ( ! empty( $analysis['direct_member'] ) ) {
			return self::format_member_label( $analysis['direct_member'] );
		}

		if ( ! empty( $analysis['weak_matches'][0]['member'] ) ) {
			return self::format_member_label( $analysis['weak_matches'][0]['member'] );
		}

		return '';
	}

	/**
	 * Normalize an email key for case-insensitive matching.
	 *
	 * @param string $email Email value.
	 * @return string
	 */
	private static function normalize_email_key( $email ) {
		$email = strtolower( trim( (string) $email ) );
		return sanitize_email( $email );
	}

	/**
	 * Normalize a username key for case-insensitive matching.
	 *
	 * @param string $username Username value.
	 * @return string
	 */
	private static function normalize_username_key( $username ) {
		return strtolower( trim( sanitize_user( (string) $username, true ) ) );
	}

	/**
	 * Check whether the current user can view the report.
	 *
	 * @return bool
	 */
	private static function current_user_can_view() {
		if ( class_exists( 'TPW_Member_Access' ) && method_exists( 'TPW_Member_Access', 'can_manage_members_current' ) ) {
			return TPW_Member_Access::can_manage_members_current();
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Render the user-member linkage section.
	 *
	 * @param array $report Report payload.
	 * @return void
	 */
	private static function render_linkage_section( array $report ) {
		$linkage = $report['linkage'];

		echo '<hr />';
		echo '<h3>' . esc_html__( '1. User to Member Linkage Audit', 'tpw-core' ) . '</h3>';
		echo '<p>' . esc_html__( 'This report shows how current WordPress users relate to tpw_members rows using direct user_id linkage first, then the existing weak fallback paths where no direct stored relationship exists.', 'tpw-core' ) . '</p>';

		self::render_simple_table(
			array( __( 'Metric', 'tpw-core' ), __( 'Count', 'tpw-core' ) ),
			array(
				array( __( 'Users with direct member linkage', 'tpw-core' ), count( $linkage['direct'] ) ),
				array( __( 'Users matched only through weak fallback', 'tpw-core' ), count( $linkage['weak'] ) ),
				array( __( 'Users without any linkage', 'tpw-core' ), count( $linkage['unlinked_users'] ) ),
				array( __( 'Members without a corresponding user', 'tpw-core' ), count( $linkage['members_without_user'] ) ),
			)
		);

		self::render_user_linkage_table( __( 'Users with direct member linkage', 'tpw-core' ), $linkage['direct'], 'direct' );
		self::render_user_linkage_table( __( 'Users matched only through weak fallback', 'tpw-core' ), $linkage['weak'], 'weak' );
		self::render_user_linkage_table( __( 'Users without any member linkage', 'tpw-core' ), $linkage['unlinked_users'], 'none' );
		self::render_member_gap_table( __( 'Members without a corresponding user', 'tpw-core' ), $linkage['members_without_user'] );
	}

	/**
	 * Render the weak-linkage section.
	 *
	 * @param array $report Report payload.
	 * @return void
	 */
	private static function render_weak_linkage_section( array $report ) {
		$weak = $report['weak_linkage'];

		echo '<hr />';
		echo '<h3>' . esc_html__( '2. Weak Linkage Detection', 'tpw-core' ) . '</h3>';
		echo '<p>' . esc_html__( 'These users do not have a direct stored user_id to member relationship and appear to resolve only through the current compatibility fallbacks.', 'tpw-core' ) . '</p>';

		self::render_simple_table(
			array( __( 'Weak Match Type', 'tpw-core' ), __( 'Count', 'tpw-core' ) ),
			array(
				array( __( 'Email only', 'tpw-core' ), $weak['breakdown']['email_only'] ),
				array( __( 'Username only', 'tpw-core' ), $weak['breakdown']['username_only'] ),
				array( __( 'Email and username', 'tpw-core' ), $weak['breakdown']['email_username'] ),
				array( __( 'Ambiguous fallback matches', 'tpw-core' ), $weak['breakdown']['ambiguous_match'] ),
			)
		);

		self::render_user_linkage_table( __( 'Weak-linkage users', 'tpw-core' ), $weak['rows'], 'weak' );
	}

	/**
	 * Render the identity role projection section.
	 *
	 * @param array $report Report payload.
	 * @return void
	 */
	private static function render_identity_role_projection_section( array $report ) {
		$roles = $report['identity_roles'];

		echo '<hr />';
		echo '<h3>' . esc_html__( '3. Identity Role Projection Audit', 'tpw-core' ) . '</h3>';
		echo '<p>' . esc_html__( 'This section groups users by the current identity-style role slugs present in WordPress. The legacy tpw_member role is highlighted separately to support later cleanup planning.', 'tpw-core' ) . '</p>';

		$summary_rows = array();
		foreach ( $roles as $role => $users ) {
			$summary_rows[] = array( $role, count( $users ) );
		}
		self::render_simple_table( array( __( 'Role', 'tpw-core' ), __( 'Users', 'tpw-core' ) ), $summary_rows );

		if ( ! empty( $roles['tpw_member'] ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Legacy identity role detected: tpw_member is still assigned to one or more users.', 'tpw-core' ) . '</p></div>';
		}

		foreach ( $roles as $role => $users ) {
			self::render_identity_role_users_table( $role, $users );
		}
	}

	/**
	 * Render the unknown-role section.
	 *
	 * @param array $report Report payload.
	 * @return void
	 */
	private static function render_unknown_role_section( array $report ) {
		$roles = $report['unknown_roles'];

		echo '<hr />';
		echo '<h3>' . esc_html__( '4. Unknown Role Detection', 'tpw-core' ) . '</h3>';
		echo '<p>' . esc_html__( 'These assigned roles are not WordPress core roles and are not currently classified as known TPW roles by the audit. They may be legacy, site-local, or third-party roles and are reported without modification.', 'tpw-core' ) . '</p>';

		if ( empty( $roles ) ) {
			echo '<p>' . esc_html__( 'No unknown roles were found in current user assignments.', 'tpw-core' ) . '</p>';
			return;
		}

		$rows = array();
		foreach ( $roles as $role => $users ) {
			$user_labels = array();
			foreach ( $users as $user ) {
				$user_labels[] = self::format_user_label( $user );
			}

			$rows[] = array(
				$role,
				count( $users ),
				implode( "\n", $user_labels ),
			);
		}

		self::render_simple_table(
			array( __( 'Role', 'tpw-core' ), __( 'Assigned Users', 'tpw-core' ), __( 'User Details', 'tpw-core' ) ),
			$rows
		);
	}

	/**
	 * Render the status distribution section.
	 *
	 * @param array $report Report payload.
	 * @return void
	 */
	private static function render_status_distribution_section( array $report ) {
		$statuses = $report['statuses'];

		echo '<hr />';
		echo '<h3>' . esc_html__( '5. Member Status Distribution', 'tpw-core' ) . '</h3>';
		echo '<p>' . esc_html__( 'Current status counts from tpw_members.status. This is an audit snapshot only and does not enforce any status vocabulary.', 'tpw-core' ) . '</p>';

		$rows = array();
		foreach ( $statuses as $status_row ) {
			$rows[] = array( $status_row['status'], $status_row['count'] );
		}

		self::render_simple_table( array( __( 'Status', 'tpw-core' ), __( 'Count', 'tpw-core' ) ), $rows );
	}

	/**
	 * Render the drift section.
	 *
	 * @param array $report Report payload.
	 * @return void
	 */
	private static function render_drift_section( array $report ) {
		$drift = $report['drift'];

		echo '<hr />';
		echo '<h3>' . esc_html__( '6. Identity Drift Indicators', 'tpw-core' ) . '</h3>';
		echo '<p>' . esc_html__( 'These conditions are diagnostic warnings only. They help identify current-state drift before later migration and enforcement work begins.', 'tpw-core' ) . '</p>';

		$summary_rows = array();
		foreach ( $drift['summary'] as $row ) {
			$summary_rows[] = array( $row['label'], $row['count'] );
		}

		self::render_simple_table( array( __( 'Warning', 'tpw-core' ), __( 'Count', 'tpw-core' ) ), $summary_rows );

		self::render_drift_users_without_member_table( __( 'Users with identity role but no member record', 'tpw-core' ), $drift['role_without_member'] );
		self::render_drift_member_without_role_table( __( 'Users with member record but no identity role', 'tpw-core' ), $drift['member_without_role'] );
		self::render_member_gap_table( __( 'Member records linked to non-existent users', 'tpw-core' ), $drift['linked_missing_user'] );
		self::render_drift_weak_only_table( __( 'Users resolving only through weak linkage fallback', 'tpw-core' ), $drift['weak_only'] );
	}

	/**
	 * Render a basic HTML table.
	 *
	 * @param array $headers Column headers.
	 * @param array $rows Table rows.
	 * @return void
	 */
	private static function render_simple_table( array $headers, array $rows ) {
		echo '<table class="widefat striped" style="margin: 12px 0 20px;">';
		echo '<thead><tr>';
		foreach ( $headers as $header ) {
			echo '<th scope="col">' . esc_html( (string) $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="' . esc_attr( (string) count( $headers ) ) . '">' . esc_html__( 'No records found.', 'tpw-core' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				foreach ( $row as $cell ) {
					echo '<td style="white-space: pre-wrap;">' . esc_html( (string) $cell ) . '</td>';
				}
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render user-linkage detail tables.
	 *
	 * @param string $title Table title.
	 * @param array  $rows Row data.
	 * @param string $mode Rendering mode.
	 * @return void
	 */
	private static function render_user_linkage_table( $title, array $rows, $mode ) {
		echo '<h4>' . esc_html( $title ) . '</h4>';

		$table_rows = array();
		foreach ( $rows as $row ) {
			$user = $row['user'];
			if ( ! ( $user instanceof WP_User ) ) {
				continue;
			}

			$member_label = '';
			$linkage_note = '';

			if ( 'direct' === $mode && ! empty( $row['direct_member'] ) ) {
				$member_label = self::format_member_label( $row['direct_member'] );
				$linkage_note = __( 'Direct user_id linkage', 'tpw-core' );
			} elseif ( 'weak' === $mode ) {
				$match_labels = array();
				foreach ( $row['weak_matches'] as $match ) {
					$methods = implode( ', ', $match['methods'] );
					/* translators: %s: Comma-separated weak-linkage match methods such as email or username. */
					$match_labels[] = self::format_member_label( $match['member'] ) . ' | ' . sprintf( __( 'methods: %s', 'tpw-core' ), $methods );
				}
				$member_label = implode( "\n", $match_labels );
				$linkage_note = count( $row['weak_matches'] ) > 1
					? __( 'Weak fallback with multiple possible matches', 'tpw-core' )
					: __( 'Weak fallback only', 'tpw-core' );
			} else {
				$linkage_note = __( 'No linked member record found', 'tpw-core' );
			}

			$table_rows[] = array(
				self::format_user_label( $user ),
				$member_label,
				$linkage_note,
			);
		}

		self::render_simple_table(
			array( __( 'User', 'tpw-core' ), __( 'Matched Member', 'tpw-core' ), __( 'Notes', 'tpw-core' ) ),
			$table_rows
		);
	}

	/**
	 * Render member-gap tables.
	 *
	 * @param string $title Table title.
	 * @param array  $rows Gap rows.
	 * @return void
	 */
	private static function render_member_gap_table( $title, array $rows ) {
		echo '<h4>' . esc_html( $title ) . '</h4>';

		$table_rows = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['member'] ) || ! is_object( $row['member'] ) ) {
				continue;
			}

			$member  = $row['member'];
			$reason  = isset( $row['reason'] ) ? (string) $row['reason'] : '';
			$user_id = isset( $member->user_id ) ? (int) $member->user_id : 0;

			$table_rows[] = array(
				self::format_member_label( $member ),
				$user_id > 0 ? $user_id : __( '(none)', 'tpw-core' ),
				$reason,
			);
		}

		self::render_simple_table(
			array( __( 'Member', 'tpw-core' ), __( 'Linked user_id', 'tpw-core' ), __( 'Reason', 'tpw-core' ) ),
			$table_rows
		);
	}

	/**
	 * Render grouped identity role user tables.
	 *
	 * @param string $role Role slug.
	 * @param array  $users Role-assigned users.
	 * @return void
	 */
	private static function render_identity_role_users_table( $role, array $users ) {
		/* translators: %s: WordPress role slug being reported in the identity audit. */
		echo '<h4>' . esc_html( sprintf( __( 'Users with role: %s', 'tpw-core' ), $role ) ) . '</h4>';

		$rows = array();
		foreach ( $users as $row ) {
			$user = $row['user'];
			if ( ! ( $user instanceof WP_User ) ) {
				continue;
			}

			$member_label = '';
			if ( ! empty( $row['direct_member'] ) ) {
				$member_label = self::format_member_label( $row['direct_member'] );
			} elseif ( ! empty( $row['weak_matches'] ) ) {
				$labels = array();
				foreach ( $row['weak_matches'] as $match ) {
					$labels[] = self::format_member_label( $match['member'] );
				}
				$member_label = implode( "\n", $labels );
			}

			$rows[] = array(
				self::format_user_label( $user ),
				$member_label,
				(string) $row['linkage_type'],
			);
		}

		self::render_simple_table(
			array( __( 'User', 'tpw-core' ), __( 'Resolved Member', 'tpw-core' ), __( 'Linkage Type', 'tpw-core' ) ),
			$rows
		);
	}

	/**
	 * Render drift detail for users with identity roles but no member.
	 *
	 * @param string $title Table title.
	 * @param array  $rows Drift rows.
	 * @return void
	 */
	private static function render_drift_users_without_member_table( $title, array $rows ) {
		echo '<h4>' . esc_html( $title ) . '</h4>';

		$table_rows = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['user'] ) || ! ( $row['user'] instanceof WP_User ) ) {
				continue;
			}

			$table_rows[] = array(
				self::format_user_label( $row['user'] ),
				implode( ', ', array_map( 'sanitize_key', (array) $row['roles'] ) ),
			);
		}

		self::render_simple_table( array( __( 'User', 'tpw-core' ), __( 'Identity Roles', 'tpw-core' ) ), $table_rows );
	}

	/**
	 * Render drift detail for users with members but no identity role.
	 *
	 * @param string $title Table title.
	 * @param array  $rows Drift rows.
	 * @return void
	 */
	private static function render_drift_member_without_role_table( $title, array $rows ) {
		echo '<h4>' . esc_html( $title ) . '</h4>';

		$table_rows = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['user'] ) || ! ( $row['user'] instanceof WP_User ) ) {
				continue;
			}

			$analysis     = isset( $row['analysis'] ) && is_array( $row['analysis'] ) ? $row['analysis'] : null;
			$linkage_type = is_array( $analysis ) && isset( $analysis['linkage_type'] ) ? (string) $analysis['linkage_type'] : 'unknown';

			$table_rows[] = array(
				self::format_user_label( $row['user'] ),
				isset( $row['member_label'] ) ? (string) $row['member_label'] : '',
				$linkage_type,
			);
		}

		self::render_simple_table(
			array( __( 'User', 'tpw-core' ), __( 'Resolved Member', 'tpw-core' ), __( 'Linkage Type', 'tpw-core' ) ),
			$table_rows
		);
	}

	/**
	 * Render drift detail for weak-only users.
	 *
	 * @param string $title Table title.
	 * @param array  $rows Drift rows.
	 * @return void
	 */
	private static function render_drift_weak_only_table( $title, array $rows ) {
		echo '<h4>' . esc_html( $title ) . '</h4>';

		$table_rows = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['user'] ) || ! ( $row['user'] instanceof WP_User ) ) {
				continue;
			}

			$match_labels = array();
			foreach ( (array) $row['weak_matches'] as $match ) {
				$match_labels[] = self::format_member_label( $match['member'] ) . ' | ' . implode( ', ', $match['methods'] );
			}

			$table_rows[] = array(
				self::format_user_label( $row['user'] ),
				implode( "\n", $match_labels ),
			);
		}

		self::render_simple_table( array( __( 'User', 'tpw-core' ), __( 'Weak Matches', 'tpw-core' ) ), $table_rows );
	}
}

TPW_Identity_Audit_Admin::init();
