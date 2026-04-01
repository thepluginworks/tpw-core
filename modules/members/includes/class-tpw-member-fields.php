<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class TPW_Member_Fields {

	public function __construct() {
		// add_shortcode( 'tpw_field_settings', [ $this, 'render_settings_page' ] );
		add_action( 'init', [ $this, 'handle_field_settings_submission' ] );
		// If ?action=field_settings is active, hook render_settings_page via the_content
		if ( isset($_GET['action']) && $_GET['action'] === 'field_settings' ) {
			add_action( 'wp', [ $this, 'render_settings_page_wrapper' ] );
		}
	}

	/**
	 * Helper: retrieve visible fields for a group from the new visibility table.
	 */
	public static function get_visible_fields_for_group( $group = 'member' ) {
		global $wpdb;
		$tbl = $wpdb->prefix . 'tpw_member_field_visibility';
		return (array) $wpdb->get_col( $wpdb->prepare("SELECT field_key FROM $tbl WHERE `group` = %s AND is_visible = 1", $group) );
	}

	/**
	 * Load and render the field settings admin page
	 */
	public function render_settings_page() {
		$this->maybe_insert_missing_core_fields();
		$core_fields  = $this->get_core_fields();
		$field_config = $this->get_field_settings();
		$custom_fields = $this->get_custom_fields();

		ob_start();
		$this->render_settings_template( $core_fields, $field_config, $custom_fields );
		return ob_get_clean();
	}

	/**
	 * Render the field settings template
	 */
	public function render_settings_template( $core_fields, $field_config, $custom_fields ) {
		include TPW_CORE_PATH . 'modules/members/settings/member-fields-settings.php';
	}

	/**
	 * Fetch all core fields from the database or define statically
	 */
	private function get_core_fields() {
		global $wpdb;
		$fields = [
			'first_name'            => [ 'label' => 'First Name', 'type' => 'varchar(100)' ],
			'surname'               => [ 'label' => 'Last Name', 'type' => 'varchar(100)' ],
			'initials'              => [ 'label' => 'Initials', 'type' => 'varchar(10)' ],
			'title'                 => [ 'label' => 'Title', 'type' => 'varchar(20)' ],
			'decoration'            => [ 'label' => 'Decoration', 'type' => 'varchar(50)' ],
			'email'                 => [ 'label' => 'Email', 'type' => 'varchar(255)' ],
			'mobile'                => [ 'label' => 'Mobile', 'type' => 'varchar(30)' ],
			'landline'              => [ 'label' => 'Landline', 'type' => 'varchar(30)' ],
			'address1'              => [ 'label' => 'Address Line 1', 'type' => 'varchar(255)' ],
			'address2'              => [ 'label' => 'Address Line 2', 'type' => 'varchar(255)' ],
			'town'                  => [ 'label' => 'Town/City', 'type' => 'varchar(100)' ],
			'county'                => [ 'label' => 'County', 'type' => 'varchar(100)' ],
			'postcode'              => [ 'label' => 'Postcode', 'type' => 'varchar(20)' ],
			'country'               => [ 'label' => 'Country', 'type' => 'varchar(100)' ],
			'dob'                   => [ 'label' => 'Date of Birth', 'type' => 'date' ],
			'date_joined'           => [ 'label' => 'Date Joined', 'type' => 'date' ],
			'status' => [
				'label'   => 'Status',
				'type'    => 'select',
				'options' => [
					'Active',
					'Inactive',
					'Deceased',
					'Honorary',
					'Resigned',
					'Suspended',
					'Pending',
					'Life Member',
				]
			],
			'is_committee'          => [ 'label' => 'Committee Member', 'type' => 'tinyint(1)' ],
			'is_match_manager'      => [ 'label' => 'Match Manager', 'type' => 'tinyint(1)' ],
			'is_admin'              => [ 'label' => 'Administrator', 'type' => 'tinyint(1)' ],
			'is_noticeboard_admin' => [ 'label' => 'Noticeboard Admin', 'type' => 'tinyint(1)' ],
			'is_gallery_admin'     => [ 'label' => 'Gallery Admin', 'type' => 'tinyint(1)' ],
			'is_manage_members'    => [ 'label' => 'Members Manager', 'type' => 'tinyint(1)' ],
			'is_volunteer'         => [ 'label' => 'Volunteer', 'type' => 'tinyint(1)' ],
			'username'              => [ 'label' => 'Username', 'type' => 'varchar(100)' ],
			'password_hash'         => [ 'label' => 'Password Hash', 'type' => 'varchar(255)' ],
		];

		if (
			method_exists( 'TPW_Member_Field_Loader', 'should_show_membership_entitlement' )
			&& TPW_Member_Field_Loader::should_show_membership_entitlement()
		) {
			$fields['membership_entitlement'] = [
				'label'   => 'Membership Entitlement',
				'type'    => 'select',
				'options' => class_exists( 'TPW_Member_Controller' ) && method_exists( 'TPW_Member_Controller', 'get_membership_entitlement_options' )
					? array_keys( array_filter( TPW_Member_Controller::get_membership_entitlement_options(), static function( $label, $value ) {
						return '' !== $value;
					}, ARRAY_FILTER_USE_BOTH ) )
					: [ 'full_dining', 'country' ],
			];
		}

		// If FlexiGolf is active and columns exist, append them as core fields
		if ( method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active() ) {
			$table = $wpdb->prefix . 'tpw_members';
			$cols  = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
			if ( in_array( 'whi', (array) $cols, true ) ) {
				$fields['whi'] = [ 'label' => 'WHI', 'type' => 'varchar(10)' ];
			}
			if ( in_array( 'whi_updated', (array) $cols, true ) ) {
				$fields['whi_updated'] = [ 'label' => 'WHI Updated', 'type' => 'date' ];
			}
			if ( in_array( 'cdh_id', (array) $cols, true ) ) {
				$fields['cdh_id'] = [ 'label' => 'CDH ID', 'type' => 'varchar(50)' ];
			}
		}
		// If inactive, do not add FG fields at all (ensures settings UI stays clean)

		return $fields;
	}

	/**
	 * Load field config from tpw_field_settings table
	 */
	private function get_field_settings() {
		global $wpdb;
		$table = $wpdb->prefix . 'tpw_field_settings';
		$results = [];
		$rows = $wpdb->get_results( "SELECT * FROM $table" );
		foreach ( $rows as $row ) {
			$results[ $row->field_key ] = $row;
		}

		// Ensure core fields are always enabled
		$always_enabled = [ 'username', 'first_name', 'surname', 'status' ];
		foreach ( $always_enabled as $key ) {
			if ( isset( $results[$key] ) ) {
				$results[$key]->is_enabled = 1;
			}
		}

		return $results;
	}

	/**
	 * Load any custom fields from tpw_member_meta and join with field_settings for labels,
	 * also add an in_use flag by counting entries for each meta_key in tpw_member_meta.
	 */
	private function get_custom_fields() {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'tpw_field_settings';
		$core_fields = array_keys( $this->get_core_fields() );
		$placeholders = implode( ',', array_fill( 0, count($core_fields), '%s' ) );

		$sql = "
			SELECT field_key as meta_key, custom_label, is_enabled, sort_order, field_type,
				(SELECT COUNT(*) FROM {$wpdb->prefix}tpw_member_meta WHERE meta_key = field_key) as usage_count
			FROM $settings_table
			WHERE field_key NOT IN ($placeholders)
		";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$core_fields ) );

		if (
			method_exists( 'TPW_Member_Field_Loader', 'should_show_membership_entitlement' )
			&& ! TPW_Member_Field_Loader::should_show_membership_entitlement()
		) {
			$results = array_values( array_filter( (array) $results, function( $row ) {
				return 'membership_entitlement' !== $row->field_key;
			} ) );
		}

		// Hide FlexiGolf-related fields from the settings UI when FlexiGolf is inactive
		// Also hide specific FG fields if their DB columns are missing
		$fg_keys = ['whi','whi_updated','cdh_id'];
		$fg_active = ( method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active() );
		if ( ! $fg_active ) {
			$results = array_values( array_filter( (array) $results, function( $row ) use ( $fg_keys ) {
				return ! in_array( $row->field_key, $fg_keys, true );
			} ) );
		} else {
			// If active, check columns exist before showing
			$cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $wpdb->prefix . 'tpw_members', 0 );
			$results = array_values( array_filter( (array) $results, function( $row ) use ( $fg_keys, $cols ) {
				if ( ! in_array( $row->field_key, $fg_keys, true ) ) return true;
				return in_array( $row->field_key, (array) $cols, true );
			} ) );
		}

		$custom_fields = [];
		foreach ( $results as $row ) {
			$custom_fields[] = [
				'key'        => $row->meta_key,
				'label'      => $row->custom_label ?: $row->meta_key,
				'in_use'     => intval($row->usage_count) > 0,
				'is_enabled' => isset($row->is_enabled) ? intval($row->is_enabled) : 1,
				'sort_order' => isset($row->sort_order) ? intval($row->sort_order) : 999,
				'type'       => isset($row->field_type) ? $row->field_type : 'text',
			];
		}

		return $custom_fields;
	}

	/**
	 * Handle POST requests to save settings
	 */
	public function handle_field_settings_submission() {
		// Early exit guard for irrelevant requests
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) return;
		if ( empty( $_POST['tpw_field_settings_nonce'] ) ) return;
		if ( ! isset($_POST['fields']) && ! isset($_POST['new_meta_label']) && ! isset($_POST['custom_fields']) ) return;
		if ( ! wp_verify_nonce( $_POST['tpw_field_settings_nonce'], 'tpw_save_field_settings' ) ) return;

		global $wpdb;
		$table = $wpdb->prefix . 'tpw_field_settings';

		// Save core field settings
		if ( isset($_POST['fields']) && is_array($_POST['fields']) ) {
			$core_fields = $this->get_core_fields();
			$username_label = isset( $core_fields['username']['label'] ) ? (string) $core_fields['username']['label'] : 'Username';
			$sections_map = get_option('tpw_member_field_sections', []);
			if (!is_array($sections_map)) { $sections_map = []; }
			foreach ( $_POST['fields'] as $key => $field ) {
				$key = sanitize_key($key);
				$field = is_array( $field ) ? wp_unslash( $field ) : array();
				// Prevent disabling of critical core fields
				$always_enabled = [ 'username', 'first_name', 'surname', 'status' ];
				if ( in_array( $key, $always_enabled, true ) ) {
					$is_enabled = 1;
				} else {
					$is_enabled = isset( $field['is_enabled'] ) ? 1 : 0;
				}
				if ( 'username' === $key ) {
					$custom_label = $username_label;
				} else {
					$custom_label = isset( $field['custom_label'] ) ? sanitize_text_field($field['custom_label']) : '';
				}
				$sort_order = isset( $field['sort_order'] ) ? intval($field['sort_order']) : 0;
				$basic_search = isset($field['basic_search']) ? 1 : 0;
				$depends_on = isset($field['depends_on']) ? sanitize_key($field['depends_on']) : '';
				if ( $depends_on === '' ) { $depends_on = null; }
				// Guard against self or circular (one-level only): if invalid, discard
				if ( $depends_on === $key ) { $depends_on = null; }
				if ( $depends_on ) {
					// Simple circular check: look up parent depends_on; if parent depends on this, reject
					$parent_dep = $wpdb->get_var( $wpdb->prepare( "SELECT depends_on FROM {$table} WHERE field_key = %s", $depends_on ) );
					if ( $parent_dep === $key ) { $depends_on = null; }
				}

				$existing_field = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE field_key = %s", $key ) );

				if ( $existing_field ) {
					$wpdb->update(
						$table,
						[
							'is_enabled'   => $is_enabled,
							'custom_label' => $custom_label,
							'sort_order'   => $sort_order,
							'basic_search' => $basic_search,
							'depends_on'   => $depends_on,
						],
						[ 'field_key' => $key ],
						[ '%d', '%s', '%d', '%d', '%s' ],
						[ '%s' ]
					);
				} else {
					$wpdb->insert(
						$table,
						[
							'field_key'    => $key,
							'is_enabled'   => $is_enabled,
							'custom_label' => $custom_label,
							'sort_order'   => $sort_order,
							'basic_search' => $basic_search,
							'depends_on'   => $depends_on,
						],
						[ '%s', '%d', '%s', '%d', '%d', '%s' ]
					);
				}

				// Persist optional section in a separate option mapping (no DB schema change)
				if ( isset($field['section']) ) {
					$sec = sanitize_text_field($field['section']);
					if ($sec === '') {
						unset($sections_map[$key]);
					} else {
						$sections_map[$key] = $sec;
					}
				}
			}

			$username_row_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE field_key = %s", 'username' ) );
			if ( $username_row_exists ) {
				$wpdb->update(
					$table,
					[
						'is_enabled'   => 1,
						'custom_label' => $username_label,
					],
					[ 'field_key' => 'username' ],
					[ '%d', '%s' ],
					[ '%s' ]
				);
			} else {
				$wpdb->insert(
					$table,
					[
						'field_key'    => 'username',
						'is_enabled'   => 1,
						'custom_label' => $username_label,
						'sort_order'   => 0,
					],
					[ '%s', '%d', '%s', '%d' ]
				);
			}
			update_option('tpw_member_field_sections', $sections_map );
		}

		// Persist Download selections as a simple array option (field keys)
		$download_fields = [];
		if ( isset($_POST['tpw_member_field_download']) ) {
			if ( is_array($_POST['tpw_member_field_download']) ) {
				$download_fields = array_values( array_unique( array_filter( array_map('sanitize_key', $_POST['tpw_member_field_download'] ) ) ) );
			} else {
				// Single value fallback
				$download_fields = [ sanitize_key( (string) $_POST['tpw_member_field_download'] ) ];
			}
		}
		update_option( 'tpw_member_field_download', $download_fields );

		// Persist conditional fields (multiple)
		if ( isset($_POST['tpw_conditional_fields']) && is_array($_POST['tpw_conditional_fields']) ) {
			$conds = array_values( array_unique( array_filter( array_map('sanitize_key', $_POST['tpw_conditional_fields']) ) ) );
			update_option( 'tpw_conditional_fields', $conds );
			// Optional: clean up legacy single option if present
			if ( get_option('tpw_conditional_field', null ) !== null ) {
				delete_option('tpw_conditional_field');
			}
		} elseif ( isset($_POST['tpw_conditional_fields']) ) {
			// If provided but empty array, save empty to clear
			update_option( 'tpw_conditional_fields', [] );
		}

		// Save new custom field label, if provided
		if ( ! empty($_POST['new_meta_label']) ) {
			$label = sanitize_text_field( wp_unslash( $_POST['new_meta_label'] ) );
			$field_type = isset( $_POST['new_meta_type'] ) ? sanitize_text_field( wp_unslash( $_POST['new_meta_type'] ) ) : 'text';
			$base_key = 'tpw_' . sanitize_key( strtolower( $label ) );
			$meta_key = $base_key;
			$suffix = 1;

			// Ensure the generated meta_key is unique
			while ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE field_key = %s", $meta_key ) ) > 0 ) {
				$meta_key = $base_key . '_' . $suffix;
				$suffix++;
			}

			$wpdb->insert(
				$table,
				[
					'field_key'     => $meta_key,
					'is_enabled'    => 1,
					'custom_label'  => $label,
					'field_type'    => $field_type,
					'sort_order'    => 999,
				],
				[
					'%s', '%d', '%s', '%s', '%d'
				]
			);

			// Default visibility for new custom field: Admin only
			try {
				$vis_table = $wpdb->prefix . 'tpw_member_field_visibility';
				// Avoid duplicate if somehow exists
				$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$vis_table} WHERE `group` = %s AND field_key = %s", 'admin', $meta_key ) );
				if ( $exists === 0 ) {
					$wpdb->insert( $vis_table, [ 'field_key' => $meta_key, 'group' => 'admin', 'is_visible' => 1 ], [ '%s','%s','%d' ] );
				}
			} catch ( \Throwable $e ) {
				// Suppress and continue; not critical to block field creation
			}
		}

		// Handle delete buttons (new method)
		if ( isset($_POST['delete_custom_field']) && is_array($_POST['delete_custom_field']) ) {
			foreach ( $_POST['delete_custom_field'] as $key ) {
				$key = sanitize_key($key);

				$in_use = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}tpw_member_meta WHERE meta_key = %s", $key
				) );

				if ( $in_use > 0 ) continue;

				$wpdb->delete(
					$table,
					[ 'field_key' => $key ],
					[ '%s' ]
				);

				// Also remove any visibility mappings for this field
				$vis_table = $wpdb->prefix . 'tpw_member_field_visibility';
				$wpdb->delete( $vis_table, [ 'field_key' => $key ], [ '%s' ] );
			}
		}

		// Edit or delete custom fields
		if ( isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ) {
			$sections_map = get_option('tpw_member_field_sections', []);
			if (!is_array($sections_map)) { $sections_map = []; }
			foreach ($_POST['custom_fields'] as $meta_key => $field) {
				$meta_key = sanitize_key($meta_key);
				$field    = is_array( $field ) ? wp_unslash( $field ) : array();

				$in_use = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}tpw_member_meta WHERE meta_key = %s", $meta_key
				) );

				if ( isset($field['delete']) && $field['delete'] === '1' ) {
					if ( $in_use > 0 ) {
						continue; // prevent deletion if in use
					}

					$wpdb->delete(
						$table,
						[ 'field_key' => $meta_key ],
						[ '%s' ]
					);

					// Also remove any visibility mappings for this field
					$vis_table = $wpdb->prefix . 'tpw_member_field_visibility';
					$wpdb->delete( $vis_table, [ 'field_key' => $meta_key ], [ '%s' ] );
				} else {
					// Handle possible rename: if a new key is provided and differs, update both settings and visibility tables
					if ( isset($field['key']) ) {
						$new_key = sanitize_key( $field['key'] );
						if ( $new_key && $new_key !== $meta_key ) {
							// Ensure target key does not already exist in field settings to avoid collision
							$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE field_key = %s", $new_key ) );
							if ( $exists === 0 ) {
								// Update settings table key
								$wpdb->update( $table, [ 'field_key' => $new_key ], [ 'field_key' => $meta_key ], [ '%s' ], [ '%s' ] );
								// Update member meta keys
								$wpdb->update( $wpdb->prefix . 'tpw_member_meta', [ 'meta_key' => $new_key ], [ 'meta_key' => $meta_key ], [ '%s' ], [ '%s' ] );
								// Update visibility mappings
								$wpdb->update( $wpdb->prefix . 'tpw_member_field_visibility', [ 'field_key' => $new_key ], [ 'field_key' => $meta_key ], [ '%s' ], [ '%s' ] );

								// Keep working variable aligned to the new key for subsequent updates below
								$meta_key = $new_key;
							}
						}
					}
					$new_label  = isset( $field['custom_label'] ) ? sanitize_text_field($field['custom_label']) : '';
					$sort_order = isset($field['sort_order']) ? intval($field['sort_order']) : 999;
					$is_enabled = isset($field['is_enabled']) ? 1 : 0;
					$basic_search = isset($field['basic_search']) ? 1 : 0;
					$depends_on = isset($field['depends_on']) ? sanitize_key($field['depends_on']) : '';
					if ( $depends_on === '' ) { $depends_on = null; }
					if ( $depends_on === $meta_key ) { $depends_on = null; }
					if ( $depends_on ) {
						$parent_dep = $wpdb->get_var( $wpdb->prepare( "SELECT depends_on FROM {$table} WHERE field_key = %s", $depends_on ) );
						if ( $parent_dep === $meta_key ) { $depends_on = null; }
					}

					$wpdb->update(
						$table,
						[
							'custom_label' => $new_label,
							'is_enabled'   => $is_enabled,
							'sort_order'   => $sort_order,
							'basic_search'  => $basic_search,
							'depends_on'    => $depends_on,
						],
						[ 'field_key' => $meta_key ],
						[ '%s', '%d', '%d', '%d', '%s' ],
						[ '%s' ]
					);

					// Persist optional section for this custom field
					if ( isset($field['section']) ) {
						$sec = sanitize_text_field($field['section']);
						if ($sec === '') {
							unset($sections_map[$meta_key]);
						} else {
							$sections_map[$meta_key] = $sec;
						}
					}
				}
			}
			update_option('tpw_member_field_sections', $sections_map );
		}

		// Redirect to avoid resubmission
		wp_safe_redirect( esc_url_raw( add_query_arg( 'updated', '1', $_SERVER['REQUEST_URI'] ) ) );
		exit;
	}

	/**
	 * Ensure all core fields exist in the tpw_field_settings table.
	 */
	private function maybe_insert_missing_core_fields() {
		global $wpdb;

		$table = $wpdb->prefix . 'tpw_field_settings';
		$existing_keys = $wpdb->get_col( "
			SELECT field_key FROM $table
			WHERE 1=1
		" );

		$existing_sorts = $wpdb->get_results( "SELECT field_key, sort_order FROM $table" );
		$sort_map = [];
		$max_sort = -1;
		foreach ( (array) $existing_sorts as $row ) {
			if ( isset( $row->field_key ) ) {
				$so = isset( $row->sort_order ) ? (int) $row->sort_order : 0;
				$sort_map[ $row->field_key ] = $so;
				if ( $so > $max_sort ) {
					$max_sort = $so;
				}
			}
		}
		$next_sort = $max_sort + 1;

		// Ensure DOB sits immediately after Title in sort order.
		if ( isset( $sort_map['title'] ) ) {
			$desired = (int) $sort_map['title'] + 1;
			if ( isset( $sort_map['dob'] ) ) {
				$dob_sort = (int) $sort_map['dob'];
				if ( $dob_sort !== $desired ) {
					if ( $dob_sort < $desired ) {
						$wpdb->query( $wpdb->prepare( "UPDATE $table SET sort_order = sort_order - 1 WHERE sort_order > %d AND sort_order <= %d", $dob_sort, $desired ) );
					} else {
						$wpdb->query( $wpdb->prepare( "UPDATE $table SET sort_order = sort_order + 1 WHERE sort_order >= %d AND sort_order < %d", $desired, $dob_sort ) );
					}
					$wpdb->update( $table, [ 'sort_order' => $desired ], [ 'field_key' => 'dob' ], [ '%d' ], [ '%s' ] );
					$sort_map['dob'] = $desired;
				}
			}
		}

		foreach ( $this->get_core_fields() as $field_key => $field_info ) {
			if ( ! in_array( $field_key, $existing_keys, true ) ) {
				$insert_sort = $next_sort++;
				if ( 'dob' === $field_key && isset( $sort_map['title'] ) ) {
					$insert_sort = (int) $sort_map['title'] + 1;
					$wpdb->query( $wpdb->prepare( "UPDATE $table SET sort_order = sort_order + 1 WHERE sort_order >= %d", $insert_sort ) );
				} elseif ( 'membership_entitlement' === $field_key && isset( $sort_map['status'] ) ) {
					$insert_sort = (int) $sort_map['status'] + 1;
					$wpdb->query( $wpdb->prepare( "UPDATE $table SET sort_order = sort_order + 1 WHERE sort_order >= %d", $insert_sort ) );
				} elseif ( 'is_gallery_admin' === $field_key && isset( $sort_map['is_noticeboard_admin'] ) ) {
					$insert_sort = (int) $sort_map['is_noticeboard_admin'] + 1;
					$wpdb->query( $wpdb->prepare( "UPDATE $table SET sort_order = sort_order + 1 WHERE sort_order >= %d", $insert_sort ) );
				} elseif ( 'is_manage_members' === $field_key && isset( $sort_map['is_gallery_admin'] ) ) {
					$insert_sort = (int) $sort_map['is_gallery_admin'] + 1;
					$wpdb->query( $wpdb->prepare( "UPDATE $table SET sort_order = sort_order + 1 WHERE sort_order >= %d", $insert_sort ) );
				} elseif ( 'is_manage_members' === $field_key && isset( $sort_map['is_noticeboard_admin'] ) ) {
					$insert_sort = (int) $sort_map['is_noticeboard_admin'] + 1;
					$wpdb->query( $wpdb->prepare( "UPDATE $table SET sort_order = sort_order + 1 WHERE sort_order >= %d", $insert_sort ) );
				} elseif ( 'is_volunteer' === $field_key && isset( $sort_map['is_manage_members'] ) ) {
					$insert_sort = (int) $sort_map['is_manage_members'] + 1;
					$wpdb->query( $wpdb->prepare( "UPDATE $table SET sort_order = sort_order + 1 WHERE sort_order >= %d", $insert_sort ) );
				} elseif ( 'is_volunteer' === $field_key && isset( $sort_map['is_gallery_admin'] ) ) {
					$insert_sort = (int) $sort_map['is_gallery_admin'] + 1;
					$wpdb->query( $wpdb->prepare( "UPDATE $table SET sort_order = sort_order + 1 WHERE sort_order >= %d", $insert_sort ) );
				} elseif ( 'is_volunteer' === $field_key && isset( $sort_map['is_noticeboard_admin'] ) ) {
					$insert_sort = (int) $sort_map['is_noticeboard_admin'] + 1;
					$wpdb->query( $wpdb->prepare( "UPDATE $table SET sort_order = sort_order + 1 WHERE sort_order >= %d", $insert_sort ) );
				}
				$is_enabled = ( 'is_volunteer' === $field_key ) ? 0 : 1;
				$wpdb->insert(
					$table,
					[
						'field_key'   => $field_key,
						'is_enabled'     => $is_enabled,
						'custom_label'       => $field_info['label'],
						'sort_order'  => $insert_sort,
						'field_type'  => isset($field_info['type']) ? $field_info['type'] : 'text',
					],
					[ '%s', '%d', '%s', '%d', '%s' ]
				);
				$sort_map[ $field_key ] = $insert_sort;
			}
		}
	}

	/**
	 * Hook render_settings_page to the_content for settings page display
	 */
	public function render_settings_page_wrapper() {
		add_filter( 'the_content', [ $this, 'render_settings_page' ] );
	}

}