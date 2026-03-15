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

		if ( class_exists( 'TPW_Join_Page' ) ) {
			TPW_Join_Page::reconcile_settings( $settings );
		}

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
		$section_order = array_keys( $section_map );
		$schema_fields = array();

		foreach ( $rows as $row ) {
			$field_key = isset( $row->field_key ) ? sanitize_key( $row->field_key ) : '';

			if ( '' === $field_key || ! self::is_public_signup_field( $field_key ) ) {
				continue;
			}

			$defaults          = self::get_default_signup_metadata( $field_key, $row );
			$signup_enabled    = self::resolve_signup_enabled( $row, $defaults );
			$signup_required   = $signup_enabled && self::resolve_signup_required( $row, $defaults );
			$signup_section    = self::resolve_signup_section( $row, $defaults );
			$signup_order      = self::resolve_signup_order( $row, $defaults );
			$explicit_order    = self::has_explicit_signup_order( $row );
			$known_default     = self::has_known_default_signup_order( $field_key );

			if ( ! isset( $section_map[ $signup_section ] ) ) {
				$signup_section = $defaults['signup_section'];
			}

			$schema_fields[] = array(
				'key'                  => $field_key,
				'label'                => self::get_field_label( $row ),
				'type'                 => self::normalize_public_field_type( $row ),
				'node_type'            => 'field',
				'is_core'              => in_array( $field_key, $core_keys, true ),
				'signup_enabled'       => $signup_enabled,
				'signup_required'      => $signup_required,
				'signup_section'       => $signup_section,
				'signup_section_label' => '' !== $signup_section ? $section_map[ $signup_section ] : '',
				'signup_order'         => $signup_order,
				'signup_order_explicit'=> $explicit_order,
				'known_default_order'  => $known_default,
				'sort_order'           => isset( $row->sort_order ) ? absint( $row->sort_order ) : 999,
				'options'              => self::extract_field_options( $row ),
			);
		}

		usort(
			$schema_fields,
			static function( $left, $right ) use ( $section_order ) {
				$left_section_index  = array_search( $left['signup_section'], $section_order, true );
				$right_section_index = array_search( $right['signup_section'], $section_order, true );

				if ( false === $left_section_index ) {
					$left_section_index = PHP_INT_MAX;
				}

				if ( false === $right_section_index ) {
					$right_section_index = PHP_INT_MAX;
				}

				if ( $left_section_index !== $right_section_index ) {
					return $left_section_index <=> $right_section_index;
				}

				if ( $left['signup_order_explicit'] || $right['signup_order_explicit'] ) {
					if ( $left['signup_order'] !== $right['signup_order'] ) {
						return $left['signup_order'] <=> $right['signup_order'];
					}

					if ( $left['signup_order_explicit'] !== $right['signup_order_explicit'] ) {
						return $left['signup_order_explicit'] ? -1 : 1;
					}
				}

				if ( $left['known_default_order'] !== $right['known_default_order'] ) {
					return $left['known_default_order'] ? -1 : 1;
				}

				if ( $left['known_default_order'] && $right['known_default_order'] && $left['signup_order'] !== $right['signup_order'] ) {
					return $left['signup_order'] <=> $right['signup_order'];
				}

				if ( $left['sort_order'] !== $right['sort_order'] ) {
					return $left['sort_order'] <=> $right['sort_order'];
				}

				return strcmp( $left['label'], $right['label'] );
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
			)
		);

		$sections = TPW_Signup_Sections::get_sections();
		$fields   = array_filter(
			self::get_signup_field_settings_rows(),
			static function( $field ) use ( $args ) {
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
	 * Get the normalized public sign-up form schema for Branch 3.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_public_signup_schema() {
		$sections       = TPW_Signup_Sections::get_sections();
		$enabled_fields = self::get_signup_capable_fields(
			array(
				'enabled_only' => true,
			)
		);
		$nodes          = array();

		foreach ( $sections as $section_key => $section_label ) {
			$section_fields = array_values(
				array_filter(
					$enabled_fields,
					static function( $field ) use ( $section_key ) {
						return isset( $field['signup_section'] ) && $section_key === $field['signup_section'];
					}
				)
			);

			if ( empty( $section_fields ) ) {
				continue;
			}

			$nodes[] = array(
				'node_type' => 'section',
				'key'       => $section_key,
				'label'     => $section_label,
				'children'  => array_values(
					array_map(
						static function( $field ) {
							$field['node_type'] = 'field';

							return $field;
						},
						$section_fields
					)
				),
			);
		}

		return array(
			'form_key' => 'members_join',
			'title'    => __( 'Join', 'tpw-core' ),
			'nodes'    => $nodes,
			'meta'     => array(
				'node_types' => array( 'field', 'section', 'group', 'repeater' ),
			),
		);
	}

	/**
	 * Check whether public sign-ups are enabled.
	 *
	 * @return bool
	 */
	public static function signups_enabled() {
		$settings = self::get_members_signup_settings();

		return '1' === $settings['enable_signups'];
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
		$field_rows     = self::get_field_rows();
		$field_map      = array();

		foreach ( $field_rows as $row ) {
			if ( empty( $row->field_key ) ) {
				continue;
			}

			$field_map[ sanitize_key( $row->field_key ) ] = $row;
		}

		if ( ! is_array( $posted_fields ) ) {
			$posted_fields = array();
		}

		foreach ( $field_map as $field_key => $row ) {
			$field_config = isset( $posted_fields[ $field_key ] ) && is_array( $posted_fields[ $field_key ] ) ? $posted_fields[ $field_key ] : array();

			if ( '' === $field_key || ! in_array( $field_key, $existing_keys, true ) || ! self::is_public_signup_field( $field_key ) ) {
				continue;
			}

			$defaults        = self::get_default_signup_metadata( $field_key, $row );
			$signup_enabled  = isset( $field_config['signup_enabled'] ) ? 1 : 0;
			$signup_required = ( 1 === $signup_enabled && isset( $field_config['signup_required'] ) ) ? 1 : 0;
			$signup_section  = self::sanitize_section_key( isset( $field_config['signup_section'] ) ? $field_config['signup_section'] : $defaults['signup_section'] );
			$signup_order    = isset( $field_config['signup_order'] ) ? absint( $field_config['signup_order'] ) : $defaults['signup_order'];

			if ( '' === $signup_section ) {
				$signup_section = $defaults['signup_section'];
			}

			if ( $signup_order < 1 ) {
				$signup_order = $defaults['signup_order'];
			}

			$wpdb->update(
				$table,
				array(
					'signup_enabled'  => $signup_enabled,
					'signup_required' => $signup_required,
					'signup_section'  => $signup_section,
					'signup_order'    => $signup_order,
				),
				array( 'field_key' => $field_key ),
				array( '%d', '%d', '%s', '%d' ),
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
	 * Normalize a public field type from the field settings row.
	 *
	 * @param object $row Field row.
	 * @return string
	 */
	private static function normalize_public_field_type( $row ) {
		$field_key = isset( $row->field_key ) ? sanitize_key( $row->field_key ) : '';
		$type      = isset( $row->field_type ) ? strtolower( trim( (string) $row->field_type ) ) : 'text';

		$core_default_types = array(
			'dob'                 => 'date',
			'date_joined'         => 'date',
			'email'               => 'email',
			'mobile'              => 'tel',
			'landline'            => 'tel',
			'status'              => 'select',
			'is_committee'        => 'checkbox',
			'is_match_manager'    => 'checkbox',
			'is_admin'            => 'checkbox',
			'is_noticeboard_admin'=> 'checkbox',
			'is_gallery_admin'    => 'checkbox',
			'is_manage_members'   => 'checkbox',
			'is_volunteer'        => 'checkbox',
		);

		$type_map = array(
			'varchar'    => 'text',
			'char'       => 'text',
			'text'       => 'text',
			'longtext'   => 'textarea',
			'mediumtext' => 'textarea',
			'tinytext'   => 'text',
			'int'        => 'number',
			'integer'    => 'number',
			'bigint'     => 'number',
			'decimal'    => 'number',
			'float'      => 'number',
			'double'     => 'number',
			'date'       => 'date',
			'datetime'   => 'datetime-local',
			'checkbox'   => 'checkbox',
			'select'     => 'select',
			'email'      => 'email',
			'tel'        => 'tel',
			'telephone'  => 'tel',
			'textarea'   => 'textarea',
		);

		if ( isset( $core_default_types[ $field_key ] ) ) {
			return $core_default_types[ $field_key ];
		}

		foreach ( $type_map as $prefix => $normalized_type ) {
			if ( 0 === strpos( $type, $prefix ) ) {
				return $normalized_type;
			}
		}

		return 'text';
	}

	/**
	 * Extract select options for a field row.
	 *
	 * @param object $row Field row.
	 * @return array<int, string>
	 */
	private static function extract_field_options( $row ) {
		if ( 'select' !== self::normalize_public_field_type( $row ) ) {
			return array();
		}

		if ( empty( $row->field_options ) || ! is_string( $row->field_options ) ) {
			return array();
		}

		$options = preg_split( '/\r\n|\r|\n/', $row->field_options );
		$options = is_array( $options ) ? $options : array();
		$options = array_map( 'trim', $options );
		$options = array_filter(
			$options,
			static function( $option ) {
				return '' !== $option;
			}
		);

		return array_values( array_unique( $options ) );
	}

	/**
	 * Check whether a field is eligible for public sign-up configuration.
	 *
	 * @param string $field_key Field key.
	 * @return bool
	 */
	private static function is_public_signup_field( $field_key ) {
		$field_key = sanitize_key( $field_key );
		$blocked   = array(
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
		);

		$allowed = ! in_array( $field_key, $blocked, true );

		return (bool) apply_filters( 'tpw_members/is_public_signup_field', $allowed, $field_key );
	}

	/**
	 * Resolve whether a field should be enabled.
	 *
	 * @param object $row Field row.
	 * @param array  $defaults Default metadata.
	 * @return bool
	 */
	private static function resolve_signup_enabled( $row, $defaults ) {
		if ( self::has_explicit_signup_configuration( $row, $defaults ) ) {
			return ! empty( $row->signup_enabled );
		}

		return ! empty( $defaults['signup_enabled'] );
	}

	/**
	 * Resolve whether a field should be required.
	 *
	 * @param object $row Field row.
	 * @param array  $defaults Default metadata.
	 * @return bool
	 */
	private static function resolve_signup_required( $row, $defaults ) {
		if ( self::has_explicit_signup_configuration( $row, $defaults ) ) {
			return ! empty( $row->signup_required );
		}

		return ! empty( $defaults['signup_required'] );
	}

	/**
	 * Resolve the effective sign-up section.
	 *
	 * @param object $row Field row.
	 * @param array  $defaults Default metadata.
	 * @return string
	 */
	private static function resolve_signup_section( $row, $defaults ) {
		$signup_section = isset( $row->signup_section ) ? sanitize_key( $row->signup_section ) : '';

		if ( self::has_explicit_signup_section( $row ) && '' !== $signup_section ) {
			return $signup_section;
		}

		return $defaults['signup_section'];
	}

	/**
	 * Resolve the effective sign-up order.
	 *
	 * @param object $row Field row.
	 * @param array  $defaults Default metadata.
	 * @return int
	 */
	private static function resolve_signup_order( $row, $defaults ) {
		if ( self::has_explicit_signup_order( $row ) ) {
			return absint( $row->signup_order );
		}

		if ( ! empty( $defaults['signup_order'] ) ) {
			return absint( $defaults['signup_order'] );
		}

		return self::get_default_signup_order( isset( $row->field_key ) ? sanitize_key( $row->field_key ) : '' );
	}

	/**
	 * Check whether a row already has explicit signup configuration.
	 *
	 * @param object $row Field row.
	 * @return bool
	 */
	private static function has_explicit_signup_configuration( $row, $defaults ) {
		$signup_section = isset( $row->signup_section ) ? sanitize_key( $row->signup_section ) : '';

		if ( ! empty( $row->signup_enabled ) || ! empty( $row->signup_required ) ) {
			return true;
		}

		if ( '' !== $signup_section && $signup_section !== $defaults['signup_section'] ) {
			return true;
		}

		return self::has_explicit_signup_order( $row );
	}

	/**
	 * Check whether a row has an explicit section assignment.
	 *
	 * @param object $row Field row.
	 * @return bool
	 */
	private static function has_explicit_signup_section( $row ) {
		$signup_section = isset( $row->signup_section ) ? sanitize_key( $row->signup_section ) : '';

		return '' !== $signup_section;
	}

	/**
	 * Check whether a row has an explicit admin-configured signup order.
	 *
	 * @param object $row Field row.
	 * @return bool
	 */
	private static function has_explicit_signup_order( $row ) {
		$signup_order = isset( $row->signup_order ) ? absint( $row->signup_order ) : 0;
		$sort_order   = isset( $row->sort_order ) ? absint( $row->sort_order ) : 0;

		if ( $signup_order < 1 ) {
			return false;
		}

		if ( $sort_order > 0 && $signup_order === $sort_order ) {
			return false;
		}

		return true;
	}

	/**
	 * Get default signup metadata for a public field.
	 *
	 * @param string $field_key Field key.
	 * @param object $row Field row.
	 * @return array<string, mixed>
	 */
	private static function get_default_signup_metadata( $field_key, $row ) {
		$defaults = array(
			'first_name' => array(
				'signup_enabled'  => true,
				'signup_required' => true,
				'signup_section'  => 'personal_details',
				'signup_order'    => 10,
			),
			'surname' => array(
				'signup_enabled'  => true,
				'signup_required' => true,
				'signup_section'  => 'personal_details',
				'signup_order'    => 20,
			),
			'email' => array(
				'signup_enabled'  => true,
				'signup_required' => true,
				'signup_section'  => 'account_details',
				'signup_order'    => 10,
			),
			'mobile' => array(
				'signup_enabled'  => true,
				'signup_required' => false,
				'signup_section'  => 'personal_details',
				'signup_order'    => 60,
			),
			'title' => array(
				'signup_enabled'  => false,
				'signup_required' => false,
				'signup_section'  => 'personal_details',
				'signup_order'    => 30,
			),
			'initials' => array(
				'signup_enabled'  => false,
				'signup_required' => false,
				'signup_section'  => 'personal_details',
				'signup_order'    => 40,
			),
			'dob' => array(
				'signup_enabled'  => false,
				'signup_required' => false,
				'signup_section'  => 'personal_details',
				'signup_order'    => 50,
			),
			'address1' => array(
				'signup_enabled'  => true,
				'signup_required' => false,
				'signup_section'  => 'address',
				'signup_order'    => 10,
			),
			'address2' => array(
				'signup_enabled'  => true,
				'signup_required' => false,
				'signup_section'  => 'address',
				'signup_order'    => 20,
			),
			'town' => array(
				'signup_enabled'  => true,
				'signup_required' => false,
				'signup_section'  => 'address',
				'signup_order'    => 30,
			),
			'county' => array(
				'signup_enabled'  => true,
				'signup_required' => false,
				'signup_section'  => 'address',
				'signup_order'    => 40,
			),
			'postcode' => array(
				'signup_enabled'  => true,
				'signup_required' => false,
				'signup_section'  => 'address',
				'signup_order'    => 50,
			),
			'country' => array(
				'signup_enabled'  => true,
				'signup_required' => false,
				'signup_section'  => 'address',
				'signup_order'    => 60,
			),
		);

		if ( isset( $defaults[ $field_key ] ) ) {
			return $defaults[ $field_key ];
		}

		return array(
			'signup_enabled'  => false,
			'signup_required' => false,
			'signup_section'  => self::get_recommended_signup_section( $field_key ),
			'signup_order'    => self::get_default_signup_order( $field_key ),
		);
	}

	/**
	 * Get a recommended section for eligible fields.
	 *
	 * @param string $field_key Field key.
	 * @return string
	 */
	private static function get_recommended_signup_section( $field_key ) {
		$address_fields = array( 'address1', 'address2', 'town', 'county', 'postcode', 'country' );
		$account_fields = array( 'email' );
		$emergency_fields = array( 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship' );

		if ( in_array( $field_key, $address_fields, true ) ) {
			return 'address';
		}

		if ( in_array( $field_key, $account_fields, true ) ) {
			return 'account_details';
		}

		if ( in_array( $field_key, $emergency_fields, true ) ) {
			return 'emergency_contact';
		}

		return 'personal_details';
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
		$preferred_order = self::get_known_default_signup_order_map();

		if ( isset( $preferred_order[ $field_key ] ) ) {
			return $preferred_order[ $field_key ];
		}

		foreach ( self::get_field_rows() as $row ) {
			if ( isset( $row->field_key ) && sanitize_key( $row->field_key ) === $field_key ) {
				$sort_order = isset( $row->sort_order ) ? absint( $row->sort_order ) : 0;

				if ( $sort_order > 0 ) {
					return $sort_order;
				}

				break;
			}
		}

		return 100;
	}

	/**
	 * Check whether a field has a fixed known default signup order.
	 *
	 * @param string $field_key Field key.
	 * @return bool
	 */
	private static function has_known_default_signup_order( $field_key ) {
		$preferred_order = self::get_known_default_signup_order_map();

		return isset( $preferred_order[ $field_key ] );
	}

	/**
	 * Get the fixed known default signup order map.
	 *
	 * @return array<string, int>
	 */
	private static function get_known_default_signup_order_map() {
		return array(
			'email'    => 10,
			'first_name' => 10,
			'surname'  => 20,
			'title'    => 30,
			'initials' => 40,
			'dob'      => 50,
			'mobile'   => 60,
			'landline' => 70,
			'address1' => 10,
			'address2' => 20,
			'town'     => 30,
			'county'   => 40,
			'postcode' => 50,
			'country'  => 60,
		);
	}
}