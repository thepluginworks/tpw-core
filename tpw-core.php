<?php
/**
 * Plugin Name: TPW Core
 * Plugin URI: https://thepluginworks.com/
 * Description: Core plugin for ThePluginWorks RSVP and Event Management System.
 * Author: ThePluginWorks
 * Author URI: https://thepluginworks.com/
 * Version: 1.3.0
 * Text Domain: tpw-core
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin version constant
if ( ! defined( 'TPW_CORE_VERSION' ) ) {
    define( 'TPW_CORE_VERSION', '1.3.0' );
}

// Freemius SDK integration
require_once dirname( __FILE__ ) . '/freemius/freemius-init.php';
tpw_core_fs(); // Explicitly initialize the Freemius SDK

// Define paths
define( 'TPW_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TPW_CORE_URL', plugin_dir_url( __FILE__ ) );

// Load plugin textdomain for translations
add_action( 'init', function() {
    load_plugin_textdomain( 'tpw-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Load System Pages scaffold
require_once TPW_CORE_PATH . 'includes/class-tpw-core-system-pages.php';

// Autoload includes
require_once TPW_CORE_PATH . 'includes/tpw-core-loader.php';
require_once TPW_CORE_PATH . 'includes/emails.php';
// Cache-control: load early and hook send_headers at priority 0
add_action( 'plugins_loaded', function() {
    require_once TPW_CORE_PATH . 'includes/class-tpw-core-cache-control.php';
}, 0 );

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

// Handle early member delete requests before output starts
add_action( 'template_redirect', [ 'TPW_Member_Form_Handler', 'handle_delete_request' ] );

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

// Define the current members DB version
define( 'TPW_MEMBERS_DB_VERSION', '0.3.5' );

// Run DB upgrades if needed
add_action( 'admin_init', 'tpw_maybe_upgrade_members_db' );
function tpw_maybe_upgrade_members_db() {
    $stored_version = get_option( 'tpw_members_db_version' );

    if ( $stored_version !== TPW_MEMBERS_DB_VERSION ) {
        if ( class_exists( 'TPW_Members_DB' ) ) {
            TPW_Members_DB::create_table(); // This should be dbDelta-aware
        }
        update_option( 'tpw_members_db_version', TPW_MEMBERS_DB_VERSION );
    }
}

// Provide a default login redirect handler in Core reading tpw_login_redirect_page_id
/**
 * Filter: tpw_member_login_redirect
 *
 * Adjust the URL members are sent to after a successful login. Core reads the
 * configured page option and falls back to site home when unsafe or missing.
 *
 * @since 1.0.0
 */
add_filter( 'tpw_member_login_redirect', function( $url, $user ) {
    $page_id = (int) get_option( 'tpw_login_redirect_page_id', 0 );
    if ( $page_id > 0 && get_post_status( $page_id ) === 'publish' ) {
        // Avoid directing to the login page itself
        $is_login_page = false;
        if ( class_exists( 'TPW_Core_System_Pages' ) ) {
            $login_id = (int) \TPW_Core_System_Pages::get_page_id( 'member-login' );
            if ( $login_id > 0 && $login_id === $page_id ) {
                $is_login_page = true;
            }
        }
        if ( ! $is_login_page ) {
            $content = get_post_field( 'post_content', $page_id );
            if ( is_string( $content ) && false !== strpos( $content, '[tpw_member_login' ) ) {
                $is_login_page = true;
            }
        }
        if ( $is_login_page ) {
            return home_url();
        }

        $target = get_permalink( $page_id );
        if ( $target ) {
            // Allow redirect host just in case site domain differs
            $host = parse_url( $target, PHP_URL_HOST );
            if ( $host ) {
                add_filter( 'allowed_redirect_hosts', function( $hosts ) use ( $host ) {
                    $hosts[] = $host;
                    return array_values( array_unique( array_filter( $hosts ) ) );
                } );
            }
            return $target;
        }
    }
    // Fallback: site home
    return home_url();
}, 50, 2 );

// Core login URL resolver: plugins can override via filter priority; core provides sane defaults.
/**
 * Filter: tpw_core/login_url
 *
 * Resolve the front‑end login URL used when Core requires authentication for
 * protected pages. Implementations may return a custom login page URL and can
 * include a redirect_to parameter.
 *
 * Contract: apply_filters( 'tpw_core/login_url', string $url, string $redirect_to ): string
 *
 * @since 1.0.0
 */
add_filter( 'tpw_core/login_url', function( $url, $redirect_to = '' ) {
    // Honour existing plugin/site overrides
    if ( is_string( $url ) && $url !== '' ) {
        return $url;
    }
    // 1) Check the configured Default Login Page first
    $page_id = (int) get_option( 'tpw_core_default_login_page', 0 );
    if ( $page_id > 0 ) {
        $p = get_post( $page_id );
        if ( $p && 'page' === $p->post_type && 'publish' === $p->post_status ) {
            $permalink = get_permalink( $p );
            if ( $permalink ) {
                if ( $redirect_to !== '' ) {
                    $permalink = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $permalink );
                }
                return $permalink;
            }
        }
    }

    // 2) Fall back to the registered System Page 'member-login' (front-end form) if available
    $login_url = '';
    if ( class_exists( 'TPW_Core_System_Pages' ) ) {
        $login_url = TPW_Core_System_Pages::get_permalink( 'member-login' );
        if ( $login_url ) {
            if ( $redirect_to !== '' ) {
                $login_url = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $login_url );
            }
            return $login_url;
        }
    }

    // 3) Legacy/site fallback: a conventional front-end path, else WP login
    $legacy = site_url( '/member-login/' );
    if ( is_string( $legacy ) && $legacy !== '' ) {
        if ( $redirect_to !== '' ) {
            $legacy = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $legacy );
        }
        return $legacy;
    }

    // 4) Final fallback: wp-login.php
    return wp_login_url( $redirect_to );
}, 10, 2 );