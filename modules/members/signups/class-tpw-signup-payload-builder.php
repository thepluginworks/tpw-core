<?php
/**
 * Branch 3 payload preparation for Join sign-up attempts.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Payload_Builder {
	/**
	 * Build request, retry-safe, and fingerprint payloads.
	 *
	 * @param array $schema Public signup schema.
	 * @param array $normalized_values Validated values.
	 * @param array $context Request context.
	 * @return array<string, mixed>
	 */
	public function build( $schema, $normalized_values, $context = array() ) {
		$fields = $this->collect_field_payloads(
			isset( $schema['nodes'] ) && is_array( $schema['nodes'] ) ? $schema['nodes'] : array(),
			is_array( $normalized_values ) ? $normalized_values : array()
		);

		$fingerprint_input = array(
			'form_key' => isset( $schema['form_key'] ) ? sanitize_key( $schema['form_key'] ) : 'members_join',
			'fields'   => $fields,
		);

		$request_payload = $fingerprint_input;
		$retry_payload   = array(
			'form_key'         => $fingerprint_input['form_key'],
			'fields'           => $fields,
			'submitted_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			'source'           => array(
				'page_id'   => isset( $context['page_id'] ) ? absint( $context['page_id'] ) : 0,
				'page_url'  => isset( $context['page_url'] ) ? esc_url_raw( $context['page_url'] ) : '',
				'user_agent'=> isset( $context['user_agent'] ) ? sanitize_text_field( $context['user_agent'] ) : '',
			),
		);

		$email      = isset( $normalized_values['email'] ) ? sanitize_email( $normalized_values['email'] ) : '';
		$first_name = isset( $normalized_values['first_name'] ) ? sanitize_text_field( $normalized_values['first_name'] ) : '';
		$last_name  = '';

		if ( isset( $normalized_values['surname'] ) ) {
			$last_name = sanitize_text_field( $normalized_values['surname'] );
		} elseif ( isset( $normalized_values['last_name'] ) ) {
			$last_name = sanitize_text_field( $normalized_values['last_name'] );
		}

		return array(
			'fingerprint_input' => $fingerprint_input,
			'request_payload'   => $request_payload,
			'retry_payload'     => $retry_payload,
			'attempt_data'      => array(
				'flow_key'        => 'members_join',
				'plugin_key'      => 'tpw-core',
				'email'           => $email,
				'first_name'      => $first_name,
				'last_name'       => $last_name,
				'request_payload' => $request_payload,
				'retry_payload'   => $retry_payload,
			),
		);
	}

	/**
	 * Collect stable field payloads from the normalized schema.
	 *
	 * @param array $nodes Schema nodes.
	 * @param array $normalized_values Validated values.
	 * @return array<string, mixed>
	 */
	private function collect_field_payloads( $nodes, $normalized_values ) {
		$payload = array();

		foreach ( $nodes as $node ) {
			$node_type = isset( $node['node_type'] ) ? (string) $node['node_type'] : 'field';

			if ( in_array( $node_type, array( 'section', 'group', 'repeater' ), true ) ) {
				$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
				$payload  = array_merge( $payload, $this->collect_field_payloads( $children, $normalized_values ) );
				continue;
			}

			$key = isset( $node['key'] ) ? sanitize_key( $node['key'] ) : '';
			if ( '' === $key ) {
				continue;
			}

			$value = array_key_exists( $key, $normalized_values ) ? $normalized_values[ $key ] : '';
			$payload[ $key ] = $this->normalize_payload_value( $node, $value );
		}

		ksort( $payload );

		return $payload;
	}

	/**
	 * Normalize a payload value for persistence.
	 *
	 * @param array $field Field schema.
	 * @param mixed $value Normalized value.
	 * @return mixed
	 */
	private function normalize_payload_value( $field, $value ) {
		$type = isset( $field['type'] ) ? (string) $field['type'] : 'text';

		if ( 'checkbox' === $type ) {
			return true === $value;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		return sanitize_textarea_field( (string) $value );
	}
}