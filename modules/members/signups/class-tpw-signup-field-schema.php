<?php
/**
 * Normalized sign-up field schema reader and settings saver.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Field_Schema {
	/**
	 * Get global Members sign-up settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_members_signup_settings() {
		$settings = get_option( 'tpw_members_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return array(
			'enable_signups' => ! empty( $settings['enable_signups'] ) ? '1' : '0',
			'signup_page_id' => isset( $settings['signup_page_id'] ) ? absint( $settings['signup_page_id'] ) : 0,
		);
	}

	/**
	 * Save Members sign-up settings and field metadata from request data.
	 *
	 * @param array $request Raw request array.
	 * @return void
	 */
	public static function save_members_signup_settings_from_request( $request ) {
		$request = is_array( $request ) ? $request : array();
		$settings = get_option( 'tpw_members_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$posted_settings = array();
		if ( isset( $request['tpw_members_settings'] ) && is_array( $request['tpw_members_settings'] ) ) {
			$posted_settings = wp_unslash( $request['tpw_members_settings'] );
		}

		$settings['enable_signups'] = isset( $posted_settings['enable_signups'] ) ? '1' : '0';
		$settings['signup_page_id'] = isset( $posted_settings['signup_page_id'] ) ? absint( $posted_settings['signup_page_id'] ) : 0;

		update_option( 'tpw_members_settings', $settings );

		$posted_fields = array();
		if ( isset( $request['signup_fields'] ) && is_array( $request['signup_fields'] ) ) {
			$posted_fields = wp_unslash( $request['signup_fields'] );
		}

		self::save_field_signup_metadata( $posted_fields );
	}

	/**
	 * Get a normalized view of all configured member fields for sign-up settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_signup_field_settings_rows() {
		$rows          = self::get_field_rows();
		$core_keys     = self::get_core_field_keys();
		$section_map   = TPW_Signup_Sections::get_sections();
		$schema_fields = array();

		foreach ( $rows as $row ) {
			$field_key        = isset( $row->field_key ) ? sanitize_key( $row->field_key ) : '';
			$raw_signup_safe  = ! empty( $row->signup_safe );
			$raw_signup_order = isset( $row->signup_order ) ? absint( $row->signup_order ) : absint( $row->sort_order );

			if ( '' === $field_key ) {
				continue;
			}

			$signup_safe_editable = ! self::is_signup_safe_locked_field( $field_key );
			$effective_signup_safe = $signup_safe_editable ? $raw_signup_safe : false;
			$signup_enabled = $effective_signup_safe && ! empty( $row->signup_enabled );
			$signup_required = $signup_enabled && ! empty( $row->signup_required );
			$signup_section = isset( $row->signup_section ) ? sanitize_key( $row->signup_section ) : '';

			if ( ! isset( $section_map[ $signup_section ] ) ) {
				$signup_section = '';
			}

			$schema_fields[] = array(
				'key'                  => $field_key,
				'label'                => self::get_field_label( $row ),
				'type'                 => isset( $row->field_type ) ? (string) $row->field_type : 'text',
				'is_core'              => in_array( $field_key, $core_keys, true ),
				'signup_safe'          => $effective_signup_safe,
				'signup_safe_editable' => $signup_safe_editable,
				'signup_enabled'       => $signup_enabled,
				'signup_required'      => $signup_required,
				'signup_section'       => $signup_section,
				'signup_section_label' => '' !== $signup_section ? $section_map[ $signup_section ] : '',
				'signup_order'         => $raw_signup_order,
				'sort_order'           => isset( $row->sort_order ) ? absint( $row->sort_order ) : 999,
			);
		}

		usort(
			$schema_fields,
			static function( $left, $right ) {
				if ( $left['sort_order'] === $right['sort_order'] ) {
					return strcmp( $left['label'], $right['label'] );
				}

				return $left['sort_order'] <=> $right['sort_order'];
			}
		);

		return $schema_fields;
	}

	/**
	 * Get the normalized sign-up-capable field set used by later branches.
	 *
	 * @param array $args Query flags.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_signup_capable_fields( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'enabled_only' => true,
				'safe_only'    => true,
			)
		);

		$sections = TPW_Signup_Sections::get_sections();
		$fields   = array_filter(
			self::get_signup_field_settings_rows(),
			static function( $field ) use ( $args ) {
				if ( ! empty( $args['safe_only'] ) && empty( $field['signup_safe'] ) ) {
					return false;
				}

				if ( ! empty( $args['enabled_only'] ) && empty( $field['signup_enabled'] ) ) {
					return false;
				}

				return true;
			}
		);

		$section_order = array_keys( $sections );

		usort(
			$fields,
			static function( $left, $right ) use ( $section_order ) {
				$left_section_index  = array_search( $left['signup_section'], $section_order, true );
				$right_section_index = array_search( $right['signup_section'], $section_order, true );

				if ( false === $left_section_index ) {
					$left_section_index = PHP_INT_MAX;
				}

				if ( false === $right_section_index ) {
					$right_section_index = PHP_INT_MAX;
				}

				if ( $left_section_index === $right_section_index ) {
					if ( $left['signup_order'] === $right['signup_order'] ) {
						return strcmp( $left['label'], $right['label'] );
					}

					return $left['signup_order'] <=> $right['signup_order'];
				}

				return $left_section_index <=> $right_section_index;
			}
		);

		return array_values( $fields );
	}

	/**
	 * Save sign-up metadata back onto the existing member field settings rows.
	 *
	 * @param array $posted_fields Posted sign-up field rows.
	 * @return void
	 */
	public static function save_field_signup_metadata( $posted_fields ) {
		global $wpdb;

		$table         = $wpdb->prefix . 'tpw_field_settings';
		$existing_keys = $wpdb->get_col( "SELECT field_key FROM {$table}" );

		if ( ! is_array( $posted_fields ) ) {
			$posted_fields = array();
		}

		foreach ( $posted_fields as $field_key => $field_config ) {
			$field_key = sanitize_key( $field_key );

			if ( '' === $field_key || ! in_array( $field_key, $existing_keys, true ) ) {
				continue;
			}

			$field_config = is_array( $field_config ) ? $field_config : array();
			$signup_safe_locked = self::is_signup_safe_locked_field( $field_key );
			$signup_safe = $signup_safe_locked ? 0 : ( isset( $field_config['signup_safe'] ) ? 1 : 0 );
			$signup_enabled = ( 1 === $signup_safe && isset( $field_config['signup_enabled'] ) ) ? 1 : 0;
			$signup_required = ( 1 === $signup_enabled && isset( $field_config['signup_required'] ) ) ? 1 : 0;
			$signup_section = ( 1 === $signup_safe ) ? self::sanitize_section_key( isset( $field_config['signup_section'] ) ? $field_config['signup_section'] : '' ) : '';
			$signup_order = isset( $field_config['signup_order'] ) ? absint( $field_config['signup_order'] ) : self::get_default_signup_order( $field_key );

			$wpdb->update(
				$table,
				array(
					'signup_safe'     => $signup_safe,
					'signup_enabled'  => $signup_enabled,
					'signup_required' => $signup_required,
					'signup_section'  => $signup_section,
					'signup_order'    => $signup_order,
				),
				array( 'field_key' => $field_key ),
				array( '%d', '%d', '%d', '%s', '%d' ),
				array( '%s' )
			);
		}
	}

	/**
	 * Get raw field settings rows.
	 *
	 * @return array<int, object>
	 */
	private static function get_field_rows() {
		global $wpdb;

		$table = $wpdb->prefix . 'tpw_field_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Normalized sign-up schema reads from the Members field settings table.
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, field_key ASC" );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Resolve core field keys from the existing Members field system.
	 *
	 * @return string[]
	 */
	private static function get_core_field_keys() {
		self::ensure_member_field_loader();

		if ( ! class_exists( 'TPW_Member_Field_Loader' ) ) {
			return array();
		}

		return array_keys( TPW_Member_Field_Loader::get_core_fields() );
	}

	/**
	 * Ensure the member field loader class is available.
	 *
	 * @return void
	 */
	private static function ensure_member_field_loader() {
		if ( class_exists( 'TPW_Member_Field_Loader' ) ) {
			return;
		}

		$loader_file = TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-field-loader.php';
		if ( file_exists( $loader_file ) ) {
			require_once $loader_file;
		}
	}

	/**
	 * Get a user-facing label for a field row.
	 *
	 * @param object $row Field row.
	 * @return string
	 */
	private static function get_field_label( $row ) {
		if ( ! empty( $row->custom_label ) ) {
			return (string) $row->custom_label;
		}

		if ( ! empty( $row->field_key ) ) {
			return ucwords( str_replace( '_', ' ', (string) $row->field_key ) );
		}

		return '';
	}

	/**
	 * Check whether sign-up safety is locked off for a field.
	 *
	 * @param string $field_key Field key.
	 * @return bool
	 */
	private static function is_signup_safe_locked_field( $field_key ) {
		$field_key = sanitize_key( $field_key );

		return in_array(
			$field_key,
			array(
				'username',
				'password_hash',
				'status',
				'date_joined',
				'is_committee',
				'is_match_manager',
				'is_admin',
				'is_noticeboard_admin',
				'is_gallery_admin',
				'is_manage_members',
				'is_volunteer',
				'whi',
				'whi_updated',
				'cdh_id',
			),
			true
		);
	}

	/**
	 * Sanitize a section key against the fixed core registry.
	 *
	 * @param string $section_key Section key.
	 * @return string
	 */
	private static function sanitize_section_key( $section_key ) {
		$section_key = sanitize_key( (string) $section_key );

		return TPW_Signup_Sections::is_valid_section( $section_key ) ? $section_key : '';
	}

	/**
	 * Get a sensible default sign-up order for a field.
	 *
	 * @param string $field_key Field key.
	 * @return int
	 */
	private static function get_default_signup_order( $field_key ) {
		foreach ( self::get_field_rows() as $row ) {
			if ( isset( $row->field_key ) && sanitize_key( $row->field_key ) === $field_key ) {
				return isset( $row->sort_order ) ? absint( $row->sort_order ) : 999;
			}
		}

		return 999;
	}
}