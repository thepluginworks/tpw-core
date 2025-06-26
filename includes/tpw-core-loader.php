<?php
/**
 * Loads TPW Core classes and modules.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Core includes
require_once TPW_CORE_PATH . 'includes/class-tpw-core-activator.php';
require_once TPW_CORE_PATH . 'includes/class-tpw-core-deactivator.php';
require_once TPW_CORE_PATH . 'includes/class-tpw-core.php';
require_once TPW_CORE_PATH . 'includes/tpw-core-functions.php';
require_once TPW_CORE_PATH . 'includes/class-tpw-core-create-menu.php';
require_once TPW_CORE_PATH . 'modules/costs/class-tpw-costs-save.php';
require_once TPW_CORE_PATH . 'modules/costs/class-tpw-costs.php';

// Module includes
//require_once TPW_CORE_PATH . 'modules/guests/class-tpw-guests-cpt.php';
//require_once TPW_CORE_PATH . 'modules/guests/class-tpw-guests-meta.php';
//require_once TPW_CORE_PATH . 'modules/guests/class-tpw-guests-admin.php';
//require_once TPW_CORE_PATH . 'modules/guests/class-tpw-guests-table.php';

//require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-cpt.php';
//require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-meta.php';
require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-manager.php';
require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-saver.php';
require_once TPW_CORE_PATH . 'modules/menus/class-tpw-event-menu-rel.php';

//require_once TPW_CORE_PATH . 'modules/choices/class-tpw-choices-handler.php';
//require_once TPW_CORE_PATH . 'modules/choices/class-tpw-choices-utils.php';

// API
//require_once TPW_CORE_PATH . 'modules/api/class-tpw-api-init.php';
//require_once TPW_CORE_PATH . 'modules/api/endpoints/class-tpw-api-guests.php';
//require_once TPW_CORE_PATH . 'modules/api/endpoints/class-tpw-api-menus.php';
//require_once TPW_CORE_PATH . 'modules/api/endpoints/class-tpw-api-choices.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payment-logger.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payment-logs-admin.php';
TPW_Payment_Logs_Admin::init();
require_once TPW_CORE_PATH . 'modules/payments/gateways/class-tpw-sumup-gateway.php';
require_once TPW_CORE_PATH . 'modules/payments/gateways/sumup-oauth-callback.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payments-settings.php';
TPW_Payments_Settings::init();
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-bacs-settings.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-cheque-settings.php';

add_action('init', 'tpw_core_load_optional_modules', 20);

function tpw_core_load_optional_modules() {
    if ( apply_filters('tpw_show_dining_menu', false) ) {
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menu-courses-manager.php';
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-admin.php';
        TPW_Menus_Admin::init();

        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-course-choices-manager.php';
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-course-choices-admin.php';
        TPW_Course_Choices_Admin::init();

        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-course-choice-form-admin.php';
        TPW_Course_Choice_Form_Admin::init();
    }

    if ( apply_filters('tpw_show_payment_settings', false) ) {
        require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payments-admin.php';
        TPW_Payments_Admin::init();
    }

    if ( apply_filters('tpw_enable_create_menu', false) ) {
        TPW_Core_Create_Menu::init();
    }
}