<?php
/**
 * Schema-driven Join form validation.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Form_Validator {
	/**
	 * Validate submitted form data against the normalized schema.
	 *
	 * @param array $schema Public signup schema.
	 * @param array $submitted Submitted values.
	 * @return array<string, array>
	 */
	public function validate( $schema, $submitted ) {
		$result = array(
			'values'            => array(),
			'normalized_values' => array(),
			'errors'            => array(),
		);

		$submitted = is_array( $submitted ) ? $submitted : array();
		$nodes     = isset( $schema['nodes'] ) && is_array( $schema['nodes'] ) ? $schema['nodes'] : array();

		$this->validate_nodes( $nodes, $submitted, $result );

		return $result;
	}

	/**
	 * Recursively validate schema nodes.
	 *
	 * @param array $nodes Schema nodes.
	 * @param array $submitted Submitted values.
	 * @param array $result Validation result.
	 * @return void
	 */
	private function validate_nodes( $nodes, $submitted, &$result ) {
		foreach ( $nodes as $node ) {
			$node_type = isset( $node['node_type'] ) ? (string) $node['node_type'] : 'field';

			switch ( $node_type ) {
				case 'section':
				case 'group':
				case 'repeater':
					$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
					$this->validate_nodes( $children, $submitted, $result );
					break;

				case 'field':
				default:
					$this->validate_field( $node, $submitted, $result );
			}
		}
	}

	/**
	 * Validate one field node.
	 *
	 * @param array $field Field schema.
	 * @param array $submitted Submitted values.
	 * @param array $result Validation result.
	 * @return void
	 */
	private function validate_field( $field, $submitted, &$result ) {
		$key = isset( $field['key'] ) ? sanitize_key( $field['key'] ) : '';
		if ( '' === $key ) {
			return;
		}

		$type      = isset( $field['type'] ) ? (string) $field['type'] : 'text';
		$required  = ! empty( $field['signup_required'] );
		$options   = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
		$raw_value = array_key_exists( $key, $submitted ) ? $submitted[ $key ] : null;

		$sticky_value = $this->sanitize_sticky_value( $type, $raw_value );
		$typed_value  = $this->normalize_typed_value( $type, $sticky_value );

		$result['values'][ $key ] = $sticky_value;
		$result['normalized_values'][ $key ] = $typed_value;

		if ( $required && $this->is_empty_value( $type, $sticky_value, $typed_value ) ) {
			$result['errors'][ $key ] = __( 'This field is required.', 'tpw-core' );
			return;
		}

		if ( $this->is_empty_value( $type, $sticky_value, $typed_value ) ) {
			return;
		}

		if ( 'email' === $type && ! is_email( (string) $typed_value ) ) {
			$result['errors'][ $key ] = __( 'Enter a valid email address.', 'tpw-core' );
			return;
		}

		if ( 'date' === $type && ! $this->is_valid_date_value( $sticky_value ) ) {
			$result['errors'][ $key ] = __( 'Enter a valid date.', 'tpw-core' );
			return;
		}

		if ( 'datetime-local' === $type && ! $this->is_valid_datetime_value( $sticky_value ) ) {
			$result['errors'][ $key ] = __( 'Enter a valid date and time.', 'tpw-core' );
			return;
		}

		if ( 'number' === $type && ! is_numeric( (string) $sticky_value ) ) {
			$result['errors'][ $key ] = __( 'Enter a valid number.', 'tpw-core' );
			return;
		}

		if ( 'select' === $type && ! empty( $options ) && ! in_array( $sticky_value, $options, true ) ) {
			$result['errors'][ $key ] = __( 'Choose a valid option.', 'tpw-core' );
		}
	}

	/**
	 * Sanitize a sticky display value.
	 *
	 * @param string $type Field type.
	 * @param mixed  $value Submitted value.
	 * @return string
	 */
	private function sanitize_sticky_value( $type, $value ) {
		if ( 'checkbox' === $type ) {
			return empty( $value ) ? '' : '1';
		}

		if ( is_array( $value ) ) {
			return '';
		}

		$value = null === $value ? '' : wp_unslash( (string) $value );

		if ( 'textarea' === $type ) {
			return sanitize_textarea_field( $value );
		}

		if ( 'email' === $type ) {
			return sanitize_email( $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Normalize a typed payload value.
	 *
	 * @param string $type Field type.
	 * @param string $value Sticky display value.
	 * @return mixed
	 */
	private function normalize_typed_value( $type, $value ) {
		switch ( $type ) {
			case 'checkbox':
				return '1' === $value;

			case 'number':
				if ( '' === $value ) {
					return '';
				}

				return false !== strpos( $value, '.' ) ? (float) $value : (int) $value;

			default:
				return $value;
		}
	}

	/**
	 * Determine whether a field value is empty.
	 *
	 * @param string $type Field type.
	 * @param string $sticky_value Sticky value.
	 * @param mixed  $typed_value Typed value.
	 * @return bool
	 */
	private function is_empty_value( $type, $sticky_value, $typed_value ) {
		if ( 'checkbox' === $type ) {
			return true !== $typed_value;
		}

		return '' === trim( (string) $sticky_value );
	}

	/**
	 * Validate a YYYY-MM-DD date string.
	 *
	 * @param string $value Date value.
	 * @return bool
	 */
	private function is_valid_date_value( $value ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $value, $matches ) ) {
			return false;
		}

		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] );
	}

	/**
	 * Validate a HTML datetime-local value.
	 *
	 * @param string $value Datetime value.
	 * @return bool
	 */
	private function is_valid_datetime_value( $value ) {
		return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) $value );
	}
}