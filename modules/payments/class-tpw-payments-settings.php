<?php

class TPW_Payments_Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_menu() {
        add_submenu_page(
            'tpw_core',
            'SumUp Settings',
            'SumUp Settings',
            'manage_options',
            'tpw-sumup-settings',
            [__CLASS__, 'render_page']
        );
        add_submenu_page(
            'tpw_core',
            'Test SumUp API',
            'Test SumUp',
            'manage_options',
            'tpw-sumup-test',
            function () {
                include plugin_dir_path(__FILE__) . '/views/sumup-test-page.php';
            }
        );
    }

    public static function register_settings() {
        register_setting('tpw_payment_settings', 'tpw_sumup_client_id');
        register_setting('tpw_payment_settings', 'tpw_sumup_client_secret');
        register_setting('tpw_payment_settings', 'tpw_sumup_access_token');
    }

    public static function render_page() {
        include plugin_dir_path(__FILE__) . '/views/payment-settings-page.php';
    }
}

TPW_Payments_Settings::init();
