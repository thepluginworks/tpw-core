<?php
/**
 * Fired during plugin activation
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPW_Core_Activator {

    /**
     * Code to run on plugin activation.
     */
    public static function activate() {
        // Trigger any setup tasks here (e.g., flushing rewrite rules)
        flush_rewrite_rules();
        // require_once TPW_CORE_PATH . 'modules/guests/class-tpw-guests-table.php';
        // TPW_Guests_Table::create_table();
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-manager.php';
        TPW_Menus_Manager::create_table();
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-event-menu-rel.php';
        TPW_Event_Menu_Rel::create_table();
        
        require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payment-db.php';
        TPW_Payment_DB::create_table();

        require_once TPW_CORE_PATH . 'modules/costs/class-tpw-costs-db.php';
        TPW_Costs_DB::create_table();

        require_once TPW_CORE_PATH . 'modules/members/class-tpw-members-db.php';
        TPW_Members_DB::create_table();

        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-course-choices-manager.php';

        // Set default currency settings if not already set
        $settings = get_option( 'flexievent_settings', [] );

        if ( empty( $settings['currency_symbol'] ) ) {
            $settings['currency_symbol'] = '£';
        }

        if ( empty( $settings['currency_code'] ) ) {
            $settings['currency_code'] = 'GBP';
        }

        update_option( 'flexievent_settings', $settings );
    }
}
