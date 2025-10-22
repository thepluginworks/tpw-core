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
        add_submenu_page(
            'tpw_core',
            'Square Settings',
            'Square Settings',
            'manage_options',
            'tpw-square-settings',
            [__CLASS__, 'render_square_page']
        );
    }

    public static function register_settings() {
        register_setting('tpw_payment_settings', 'tpw_sumup_client_id');
        register_setting('tpw_payment_settings', 'tpw_sumup_client_secret');
        register_setting('tpw_payment_settings', 'tpw_sumup_access_token');
        register_setting('tpw_payment_settings', 'tpw_square_app_id');
        register_setting('tpw_payment_settings', 'tpw_square_access_token');
        register_setting('tpw_payment_settings', 'tpw_square_location_id');
        register_setting('tpw_payment_settings', 'tpw_square_sandbox_mode');
        // Square surcharge fields
        register_setting('tpw_payment_settings', 'tpw_surcharge_square_percent', [
            'sanitize_callback' => [__CLASS__, 'sanitize_surcharge_value']
        ]);
        register_setting('tpw_payment_settings', 'tpw_surcharge_square_fixed', [
            'sanitize_callback' => [__CLASS__, 'sanitize_surcharge_value']
        ]);
    }

    public static function render_page() {
        include plugin_dir_path(__FILE__) . '/views/payment-settings-page.php';
    }

    public static function render_square_page() {
        include plugin_dir_path(__FILE__) . '/views/square-settings-page.php';
    }

    public static function sanitize_surcharge_value($val) {
        $v = floatval($val);
        if ($v < 0) { $v = 0; }
        return round($v, 2);
    }
}

TPW_Payments_Settings::init();
