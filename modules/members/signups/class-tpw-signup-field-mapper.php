<?php
/**
 * Map normalized signup payload fields into WP user, member, and member meta data.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Field_Mapper {
	/**
	 * Map a request payload against the normalized public schema.
	 *
	 * @param array $request_payload Decoded attempt request payload.
	 * @param array $schema         Normalized public signup schema.
	 * @return array<string, array<string, mixed>>|WP_Error
	 */
	public function map_request_payload( $request_payload, $schema ) {
		$request_payload = is_array( $request_payload ) ? $request_payload : array();
		$schema          = is_array( $schema ) ? $schema : array();

		$schema_fields = $this->collect_schema_fields(
			isset( $schema['nodes'] ) && is_array( $schema['nodes'] ) ? $schema['nodes'] : array()
		);
		if ( empty( $schema_fields ) ) {
			return new WP_Error( 'tpw_signup_schema_empty', 'The signup schema does not expose any mappable fields.' );
		}

		$field_values = array();
		if ( isset( $request_payload['fields'] ) && is_array( $request_payload['fields'] ) ) {
			$field_values = $request_payload['fields'];
		}

		$member_data      = array();
		$member_meta_data = array();

		foreach ( $schema_fields as $field_key => $field ) {
			if ( ! array_key_exists( $field_key, $field_values ) ) {
				continue;
			}

			$mapped_value = $this->normalize_field_value( $field, $field_values[ $field_key ] );

			if ( ! empty( $field['is_core'] ) ) {
				$member_data[ $field_key ] = $mapped_value;
			} else {
				$member_meta_data[ $field_key ] = $mapped_value;
			}
		}

		$email      = isset( $member_data['email'] ) ? sanitize_email( $member_data['email'] ) : '';
		$first_name = isset( $member_data['first_name'] ) ? sanitize_text_field( $member_data['first_name'] ) : '';
		$last_name  = '';

		if ( isset( $member_data['surname'] ) ) {
			$last_name = sanitize_text_field( $member_data['surname'] );
		} elseif ( isset( $field_values['last_name'] ) ) {
			$last_name = sanitize_text_field( $field_values['last_name'] );
		}

		$wp_user_data = array(
			'user_email'   => $email,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => $this->build_display_name( $first_name, $last_name, $email ),
			'user_login'   => '',
		);

		return array(
			'wp_user_data'       => $wp_user_data,
			'member_data'        => $member_data,
			'member_meta_data'   => $member_meta_data,
			'allowed_field_keys' => array_keys( $schema_fields ),
		);
	}

	/**
	 * Flatten schema nodes down to keyed field definitions.
	 *
	 * @param array $nodes Schema nodes.
	 * @return array<string, array<string, mixed>>
	 */
	private function collect_schema_fields( $nodes ) {
		$fields = array();

		foreach ( $nodes as $node ) {
			$node_type = isset( $node['node_type'] ) ? (string) $node['node_type'] : 'field';

			if ( in_array( $node_type, array( 'section', 'group', 'repeater' ), true ) ) {
				$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
				$fields   = array_merge( $fields, $this->collect_schema_fields( $children ) );
				continue;
			}

			$key = isset( $node['key'] ) ? sanitize_key( $node['key'] ) : '';
			if ( '' === $key ) {
				continue;
			}

			$fields[ $key ] = $node;
		}

		return $fields;
	}

	/**
	 * Normalize a payload field value according to schema type.
	 *
	 * @param array $field Field schema.
	 * @param mixed $value Raw field value.
	 * @return mixed
	 */
	private function normalize_field_value( $field, $value ) {
		$type = isset( $field['type'] ) ? (string) $field['type'] : 'text';

		if ( 'checkbox' === $type ) {
			return $this->normalize_checkbox_value( $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		$value = (string) $value;

		if ( 'email' === $type ) {
			return sanitize_email( $value );
		}

		if ( 'textarea' === $type ) {
			return sanitize_textarea_field( $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Normalize checkbox-style values into integers for member persistence.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private function normalize_checkbox_value( $value ) {
		if ( true === $value || 1 === $value || '1' === $value ) {
			return 1;
		}

		if ( is_string( $value ) ) {
			$normalized = strtolower( trim( $value ) );
			if ( in_array( $normalized, array( 'true', 'yes', 'on' ), true ) ) {
				return 1;
			}
		}

		return 0;
	}

	/**
	 * Build a conservative display name for the WP user.
	 *
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 * @param string $email      Email address.
	 * @return string
	 */
	private function build_display_name( $first_name, $last_name, $email ) {
		$display_name = trim( $first_name . ' ' . $last_name );

		if ( '' !== $display_name ) {
			return $display_name;
		}

		if ( '' !== $first_name ) {
			return $first_name;
		}

		if ( '' !== $last_name ) {
			return $last_name;
		}

		return $email;
	}

}
