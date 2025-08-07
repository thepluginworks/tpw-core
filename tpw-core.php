<?php
/**
 * Plugin Name: TPW Core
 * Description: Core plugin for ThePluginWorks RSVP and Event Management System.
 * Author: ThePluginWorks Ltd
 * Version: 1.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Freemius SDK integration
require_once dirname( __FILE__ ) . '/freemius/freemius-init.php';
tpw_core_fs(); // Explicitly initialize the Freemius SDK

// Define paths
define( 'TPW_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TPW_CORE_URL', plugin_dir_url( __FILE__ ) );

// Autoload includes
require_once TPW_CORE_PATH . 'includes/tpw-core-loader.php';
require_once TPW_CORE_PATH . 'includes/emails.php';

// Activation hook
register_activation_hook( __FILE__, 'tpw_core_activate' );
function tpw_core_activate() {
    if ( class_exists( 'TPW_Core_Activator' ) ) {
        TPW_Core_Activator::activate();
    }
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'tpw_core_deactivate' );
function tpw_core_deactivate() {
    if ( class_exists( 'TPW_Core_Deactivator' ) ) {
        TPW_Core_Deactivator::deactivate();
    }
}

// Init core loader
add_action( 'plugins_loaded', [ 'TPW_Core', 'init' ] );

// Marker function to confirm TPW Core is active
if ( ! function_exists( 'tpw_core_loaded_marker' ) ) {
    function tpw_core_loaded_marker() {
        return true;
    }
}

// Declare HPOS compatibility with WooCommerce
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );