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
        self::register_sumup_settings();

        if ( 'core' === self::get_square_settings_registration_owner() ) {
            self::register_square_settings();
        }
    }

    public static function register_sumup_settings() {
        register_setting('tpw_payment_settings', 'tpw_sumup_client_id');
        register_setting('tpw_payment_settings', 'tpw_sumup_client_secret');
        register_setting('tpw_payment_settings', 'tpw_sumup_access_token');
    }

    public static function register_square_settings() {
        register_setting('tpw_payment_settings', 'tpw_square_app_id');
        register_setting('tpw_payment_settings', 'tpw_square_access_token');
        register_setting('tpw_payment_settings', 'tpw_square_location_id');
        register_setting('tpw_payment_settings', 'tpw_square_sandbox_mode');
        // Label field stored in tpw_payment_methods.name (Square)
        register_setting('tpw_payment_settings', 'tpw_label_square', [
            'sanitize_callback' => [__CLASS__, 'save_method_label_square']
        ]);
        // Square surcharge fields
        register_setting('tpw_payment_settings', 'tpw_surcharge_square_percent', [
            'sanitize_callback' => [__CLASS__, 'sanitize_surcharge_value']
        ]);
        register_setting('tpw_payment_settings', 'tpw_surcharge_square_fixed', [
            'sanitize_callback' => [__CLASS__, 'sanitize_surcharge_value']
        ]);
    }

    public static function get_square_settings_registration_owner(): string {
        $owner = apply_filters( 'tpw_core/square_settings_registration_owner', 'core' );

        return in_array( $owner, [ 'core', 'addon' ], true ) ? $owner : 'core';
    }

    public static function render_page() {
        include plugin_dir_path(__FILE__) . '/views/payment-settings-page.php';
    }

    public static function render_square_page() {
        $route_owner = function_exists( 'tpw_core_get_square_settings_route_owner' )
            ? tpw_core_get_square_settings_route_owner()
            : 'core';

        if ( 'addon' === $route_owner && has_action( 'tpw_core/square_settings_route' ) ) {
            do_action( 'tpw_core/square_settings_route', 'tpw-square-settings' );
            return;
        }

        include plugin_dir_path(__FILE__) . '/views/square-settings-page.php';
    }

    public static function sanitize_surcharge_value($val) {
        $v = floatval($val);
        if ($v < 0) { $v = 0; }
        return round($v, 2);
    }

    public static function save_method_label_square( $val ) {
        $label = sanitize_text_field( (string) $val );
        global $wpdb; $table = $wpdb->prefix . 'tpw_payment_methods';
        $wpdb->update( $table, [ 'name' => $label ], [ 'slug' => 'square' ] );
        return $label;
    }
}

TPW_Payments_Settings::init();
