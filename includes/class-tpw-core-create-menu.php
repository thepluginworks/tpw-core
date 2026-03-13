<?php

class TPW_Core_Create_Menu {

    public static function init() {
        if ( apply_filters('tpw_enable_create_menu', false) ) {
            error_log('TPW_Core_Create_Menu::init() called');
            add_action('admin_menu', [__CLASS__, 'add_submenus'], 20);
        }
    }

    public static function add_submenus() {
        error_log('TPW_Core_Create_Menu::add_submenus() running');
        
        if (!class_exists('TPW_Menus_Admin')) {
            if ( apply_filters('tpw_show_dining_menu', false) ) {
                require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-admin.php';
                TPW_Menus_Admin::init();
            } else {
                error_log('TPW_Menus_Admin class does not exist');
            }
        }
        if (!class_exists('TPW_Payments_Admin')) {
            if ( apply_filters('tpw_show_payment_settings', false) ) {
                require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payments-admin.php';
                TPW_Payments_Admin::init();
            } else {
                error_log('TPW_Payments_Admin class does not exist');
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

            add_submenu_page(
                'tpw-flexievent-dashboard',
                'Payment Methods',
                'Payment Methods',
                'manage_options',
                'tpw-core-payment-methods',
                [__CLASS__, 'redirect_payment_methods_page']
            );

            remove_submenu_page(
                'tpw-flexievent-dashboard',
                'tpw-core-payment-methods'
            );
        }
    }

    public static function redirect_payment_methods_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'tpw-core' ) );
        }

        if ( ! function_exists( 'tpw_core_get_payment_methods_settings_url' ) ) {
            wp_die( esc_html__( 'Payment Methods settings URL helper is unavailable.', 'tpw-core' ) );
        }

        wp_safe_redirect( tpw_core_get_payment_methods_settings_url() );
        exit;
    }

    public static function render_dining_menu_page() {
        if (class_exists('TPW_Menus_Admin')) {
            TPW_Menus_Admin::render_menu_page();
        } else {
            echo esc_html__( 'Dining menu admin class not found.', 'tpw-core' );
        }
    }
}
