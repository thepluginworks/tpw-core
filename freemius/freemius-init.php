<?php
if ( ! function_exists( 'tpw_core_fs' ) ) {
    // Create a helper function for easy SDK access.
    function tpw_core_fs() {
        global $tpw_core_fs;

        if ( ! isset( $tpw_core_fs ) ) {
            // Activate multisite network integration.
            if ( ! defined( 'WP_FS__PRODUCT_19783_MULTISITE' ) ) {
                define( 'WP_FS__PRODUCT_19783_MULTISITE', true );
            }

            // Include Freemius SDK core.
            require_once dirname( __FILE__ ) . '/start.php';

            $tpw_core_fs = fs_dynamic_init( array(
                'id'                  => '19783',
                'slug'                => 'tpw-core',
                'type'                => 'plugin',
                'public_key'          => 'pk_230160ce6b3f69b3aac273acd5aaf',
                'is_premium'          => false,
                'has_addons'          => false,
                'has_paid_plans'      => false,
                'menu'                => array(
                    'first-path'     => 'plugins.php',
                    'account'        => false,
                    'contact'        => false,
                    'support'        => false,
                ),
            ) );
        }

        return $tpw_core_fs;
    }
}
