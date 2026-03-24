<?php

class TPW_Core_Create_Menu {

    public static function init() {
        if ( apply_filters('tpw_enable_create_menu', false) ) {
            add_action('admin_menu', [__CLASS__, 'add_submenus'], 20);
        }
    }

    public static function add_submenus() {
        if (!class_exists('TPW_Menus_Admin')) {
            if ( apply_filters('tpw_show_dining_menu', false) ) {
                require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-admin.php';
                TPW_Menus_Admin::init();
            }
        }
        if (!class_exists('TPW_Payments_Admin')) {
            if ( apply_filters('tpw_show_payment_settings', false) ) {
                require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payments-admin.php';
                TPW_Payments_Admin::init();
            }
        }

        if (class_exists('TPW_Menus_Admin')) {
            add_submenu_page(
                'tpw-flexievent-dashboard',
                'Dining Menus',
                'Dining Menus',
                'manage_options',
                'tpw-core-dining-menus',
                [__CLASS__, 'render_dining_menu_page']
            );
        }

        if (class_exists('TPW_Payments_Admin')) {
            add_submenu_page(
                'tpw-flexievent-dashboard',
                'Payment Methods',
                'Payment Methods',
                'manage_options',
                'options-general.php?page=tpw-core-settings&tab=payment-methods'
            );
        }
    }

    public static function render_dining_menu_page() {
        if (class_exists('TPW_Menus_Admin')) {
            TPW_Menus_Admin::render_menu_page();
        } else {
            echo esc_html__( 'Dining menu admin class not found.', 'tpw-core' );
        }
    }
}
