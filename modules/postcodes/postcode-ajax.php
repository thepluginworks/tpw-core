<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX endpoint: tpw_lookup_postcode
 *
 * Validates nonce and proxies requests to TPW_Postcode_Helper::lookup_postcode(),
 * returning a normalized JSON payload. Available to both logged-in and public
 * visitors (nonce required).
 *
 * @since 1.0.0
 */
add_action( 'wp_ajax_tpw_lookup_postcode', 'tpw_core_ajax_lookup_postcode' );
add_action( 'wp_ajax_nopriv_tpw_lookup_postcode', 'tpw_core_ajax_lookup_postcode' );

/**
 * Handle postcode lookup request.
 *
 * @since 1.0.0
 * @return void JSON response
 */
function tpw_core_ajax_lookup_postcode() {
	$nonce = '';
	if ( isset( $_POST['_ajax_nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) );
	} elseif ( isset( $_POST['nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
	}

	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'tpw_lookup_postcode' ) ) {
		wp_send_json(
			array(
				'success' => false,
				'message' => __( 'Invalid request.', 'tpw-core' ),
			),
			403
		);
	}

	$postcode      = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '';
	$country       = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : tpw_core_get_default_country();
	$mode          = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'basic';
	$street_prefix = isset( $_POST['street_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['street_prefix'] ) ) : '';

	if ( '' === $postcode ) {
		wp_send_json(
			array(
				'success' => false,
				'message' => __( 'Postcode is required.', 'tpw-core' ),
			),
			400
		);
	}

	if ( ! class_exists( 'TPW_Postcode_Helper' ) ) {
		wp_send_json(
			array(
				'success' => false,
				'message' => __( 'Postcode helper unavailable.', 'tpw-core' ),
			),
			500
		);
	}

	if ( ! TPW_Postcode_Helper::is_lookup_enabled() ) {
		wp_send_json(
			array(
				'success' => false,
				'message' => __( 'Address lookup is not available for the selected provider.', 'tpw-core' ),
			),
			400
		);
	}

	$res = TPW_Postcode_Helper::lookup_postcode( $postcode, $country, $mode, $street_prefix );
	if ( $res && empty( $res['error'] ) ) {
		wp_send_json(
			array(
				'success' => true,
				'data'    => $res,
			)
		);
	}

	$msg = is_array( $res ) && ! empty( $res['message'] ) ? $res['message'] : __( 'Postcode not found.', 'tpw-core' );
	wp_send_json(
		array(
			'success' => false,
			'message' => $msg,
		),
		404
	);
}
