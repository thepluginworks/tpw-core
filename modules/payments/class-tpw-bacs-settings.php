<?php

class TPW_BACS_Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_menu() {
        add_submenu_page(
            'tpw_core',
            'BACS Settings',
            'BACS Settings',
            'manage_options',
            'tpw-bacs-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings() {
        register_setting('tpw_bacs_settings', 'tpw_bacs_account_name');
        register_setting('tpw_bacs_settings', 'tpw_bacs_account_number');
        register_setting('tpw_bacs_settings', 'tpw_bacs_sort_code');
        // Surcharge fields
        register_setting('tpw_bacs_settings', 'tpw_surcharge_bacs_percent', [
            'sanitize_callback' => [__CLASS__, 'sanitize_surcharge_value']
        ]);
        register_setting('tpw_bacs_settings', 'tpw_surcharge_bacs_fixed', [
            'sanitize_callback' => [__CLASS__, 'sanitize_surcharge_value']
        ]);
    }

    public static function sanitize_surcharge_value($val) {
        $v = floatval($val);
        if ($v < 0) { $v = 0; }
        // limit to 2 decimal places
        return round($v, 2);
    }

    public static function render_page() {
        ?>
    <div class="tpw-admin-ui"><div class="wrap">
            <h1>Bank Transfer Payment Settings</h1>
            <?php if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) : ?>
                <div class="notice notice-success is-dismissible"><p>Surcharge settings updated successfully.</p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('tpw_bacs_settings');
                do_settings_sections('tpw_bacs_settings');
                // Determine currency symbol from Core settings or fallback
                $currency_symbol = '£';
                if ( function_exists('tpw_core_get_currency_symbol') ) {
                    $currency_symbol = tpw_core_get_currency_symbol();
                } else {
                    $flex = get_option('flexievent_settings', []);
                    if ( is_array($flex) && ! empty($flex['currency_symbol']) ) {
                        $currency_symbol = $flex['currency_symbol'];
                    } else {
                        $currency_symbol = get_option('tpw_currency_symbol', '£');
                    }
                }
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tpw_bacs_account_name">Account Name</label></th>
                        <td><input type="text" name="tpw_bacs_account_name" id="tpw_bacs_account_name" value="<?php echo esc_attr(get_option('tpw_bacs_account_name')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_bacs_account_number">Account Number</label></th>
                        <td><input type="text" name="tpw_bacs_account_number" id="tpw_bacs_account_number" value="<?php echo esc_attr(get_option('tpw_bacs_account_number')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_bacs_sort_code">Sort Code</label></th>
                        <td><input type="text" name="tpw_bacs_sort_code" id="tpw_bacs_sort_code" value="<?php echo esc_attr(get_option('tpw_bacs_sort_code')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_surcharge_bacs_percent">Surcharge (%)</label></th>
                        <td>
                            <input type="number" name="tpw_surcharge_bacs_percent" id="tpw_surcharge_bacs_percent" step="0.01" min="0" value="<?php echo esc_attr( get_option('tpw_surcharge_bacs_percent', 0) ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_surcharge_bacs_fixed"><?php printf( esc_html__( 'Fixed (%s)', 'tpw-core' ), esc_html( $currency_symbol ) ); ?></label></th>
                        <td>
                            <input type="number" name="tpw_surcharge_bacs_fixed" id="tpw_surcharge_bacs_fixed" step="0.01" min="0" value="<?php echo esc_attr( get_option('tpw_surcharge_bacs_fixed', 0) ); ?>" />
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div></div>
        <?php
    }
}

TPW_BACS_Settings::init();
