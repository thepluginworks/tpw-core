<?php

/**
 * Core bootstrap for TPW components.
 *
 * Loads core modules, wires actions, and exposes high‑level lifecycle methods
 * used by the plugin entry file. This class contains no UI and no side‑effects
 * beyond including files and firing lifecycle hooks.
 *
 * @since 1.0.1 Added class‑level docs and method annotations.
 */
class TPW_Core {

    /**
     * Initialize core modules and fire the loaded marker.
     *
     * @since 1.0.0
     * @return void
     */
    public static function init() {
        self::load_dependencies();

        if ( class_exists( 'TPW_Payments_Manager' ) && method_exists( 'TPW_Payments_Manager', 'reconcile_square_runtime_state' ) ) {
            add_action( 'init', [ 'TPW_Payments_Manager', 'reconcile_square_runtime_state' ], 20 );
        }

        do_action('tpw_core_loaded');
    }

    /**
     * Load required files and modules.
     *
     * Includes payment, menu and admin components. Avoid adding runtime logic
     * here—this function should only include files and register initializers.
     *
     * @since 1.0.0
     * @return void
     */
    private static function load_dependencies() {
        $base = plugin_dir_path(dirname(__FILE__));

        // Payments
        require_once $base . 'modules/payments/class-tpw-payments-manager.php';
        require_once $base . 'modules/payments/class-tpw-payment-logger.php';
        require_once $base . 'modules/payments/class-tpw-payment-logs-admin.php';

        // Guests
        //require_once $base . 'modules/guests/class-tpw-guests-table.php';

        // Menus
        require_once $base . 'modules/menus/class-tpw-menus-manager.php';
        require_once $base . 'modules/menus/class-tpw-menus-saver.php';
        require_once $base . 'modules/menus/class-tpw-event-menu-rel.php';

        // Admin init
        TPW_Payment_Logs_Admin::init();
    }

    /**
     * Activation tasks for the core plugin.
     *
     * Creates or upgrades required tables. Safe to call multiple times.
     *
     * @since 1.0.0
     * @return void
     */
    public static function activate() {
        TPW_Payment_Logger::create_table();
        //TPW_Guests_Table::create_table();
        TPW_Menus_Manager::create_table();
        TPW_Event_Menu_Rel::create_table();
    }

    /**
     * Optional deactivation cleanup.
     *
     * Currently a no‑op. Reserved for future deactivation behaviour.
     *
     * @since 1.0.0
     * @return void
     */
    public static function deactivate() {
        // Currently no deactivation logic required
    }
}
