<?php

class TPW_Core {

    /**
     * Initialize the core plugin functionality.
     */
    public static function init() {
        self::load_dependencies();
        do_action('tpw_core_loaded');
    }

    /**
     * Load required files and modules.
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
     * Activation logic for the core plugin.
     */
    public static function activate() {
        TPW_Payment_Logger::create_table();
        //TPW_Guests_Table::create_table();
        TPW_Menus_Manager::create_table();
        TPW_Event_Menu_Rel::create_table();
    }

    /**
     * Optional deactivation cleanup.
     */
    public static function deactivate() {
        // Currently no deactivation logic required
    }
}
