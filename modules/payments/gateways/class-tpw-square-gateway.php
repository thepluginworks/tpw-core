<?php

if ( ! class_exists( 'TPW_Square_Gateway', false ) ) {
	$tpw_square_gateway_legacy_owner = function_exists( 'tpw_core_get_square_gateway_legacy_owner' )
		? tpw_core_get_square_gateway_legacy_owner()
		: 'core';

	if ( 'core' === $tpw_square_gateway_legacy_owner ) {
		class TPW_Square_Gateway {

			public static function is_enabled() {
				return false;
			}

			public static function label() {
				return 'Pay by Card (via Square)';
			}

			public static function process_payment( array $args ) {
				error_log( '[TPW CORE] Retired Square compatibility shim invoked while the Square add-on is unavailable.' );

				return new WP_Error(
					'square_addon_required',
					'Square payments require the TPW Square Gateway add-on to be installed and active.',
					array(
						'status'            => 503,
						'require_new_nonce' => false,
						'detail'            => 'Core no longer provides standalone Square execution.',
					)
				);
			}
		}
	}
}
