<?php

class TPW_Cheque_Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_menu() {
        add_submenu_page(
            'tpw_core',
            'Cheque Settings',
            'Cheque Settings',
            'manage_options',
            'tpw-cheque-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings() {
        register_setting('tpw_cheque_settings', 'tpw_cheque_payable_to');
        register_setting('tpw_cheque_settings', 'tpw_cheque_address1');
        register_setting('tpw_cheque_settings', 'tpw_cheque_address2');
        register_setting('tpw_cheque_settings', 'tpw_cheque_address3');
        register_setting('tpw_cheque_settings', 'tpw_cheque_town');
        register_setting('tpw_cheque_settings', 'tpw_cheque_county');
        register_setting('tpw_cheque_settings', 'tpw_cheque_postcode');
        register_setting('tpw_cheque_settings', 'tpw_cheque_post_name');
        // Label field stored in tpw_payment_methods.name
        register_setting('tpw_cheque_settings', 'tpw_label_cheque', [
            'sanitize_callback' => [__CLASS__, 'save_method_label_cheque']
        ]);
        // Surcharge fields
        register_setting('tpw_cheque_settings', 'tpw_surcharge_cheque_percent', [
            'sanitize_callback' => [__CLASS__, 'sanitize_surcharge_value']
        ]);
        register_setting('tpw_cheque_settings', 'tpw_surcharge_cheque_fixed', [
            'sanitize_callback' => [__CLASS__, 'sanitize_surcharge_value']
        ]);
    }

    public static function sanitize_surcharge_value($val) {
        $v = floatval($val);
        if ($v < 0) { $v = 0; }
        return round($v, 2);
    }

    public static function render_page() {
        ?>
    <div class="tpw-admin-ui"><div class="wrap">
            <h1>Cheque Payment Settings</h1>
            <?php if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) : ?>
                <div class="notice notice-success is-dismissible"><p>Surcharge settings updated successfully.</p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('tpw_cheque_settings');
                do_settings_sections('tpw_cheque_settings');
                // Current label from DB
                global $wpdb; $table = $wpdb->prefix . 'tpw_payment_methods';
                $current_label = $wpdb->get_var( $wpdb->prepare("SELECT name FROM $table WHERE slug = %s", 'cheque') );
                if ( ! is_string($current_label) || $current_label === '' ) { $current_label = 'Cheque'; }
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
                        <th scope="row"><label for="tpw_label_cheque">Label</label></th>
                        <td>
                            <input type="text" name="tpw_label_cheque" id="tpw_label_cheque" value="<?php echo esc_attr( $current_label ); ?>" class="regular-text" />
                            <p class="description">Shown on checkout.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_cheque_payable_to">Make Cheque Payable To</label></th>
                        <td><input type="text" name="tpw_cheque_payable_to" id="tpw_cheque_payable_to" value="<?php echo esc_attr(get_option('tpw_cheque_payable_to')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr><th colspan="2"><strong>If sending by post:</strong></th></tr>
                    <tr>
                        <th scope="row"><label for="tpw_cheque_post_name">Recipient Name</label></th>
                        <td><input type="text" name="tpw_cheque_post_name" id="tpw_cheque_post_name" value="<?php echo esc_attr(get_option('tpw_cheque_post_name')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_cheque_address1">Address Line 1</label></th>
                        <td><input type="text" name="tpw_cheque_address1" id="tpw_cheque_address1" value="<?php echo esc_attr(get_option('tpw_cheque_address1')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_cheque_address2">Address Line 2</label></th>
                        <td><input type="text" name="tpw_cheque_address2" id="tpw_cheque_address2" value="<?php echo esc_attr(get_option('tpw_cheque_address2')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_cheque_address3">Address Line 3</label></th>
                        <td><input type="text" name="tpw_cheque_address3" id="tpw_cheque_address3" value="<?php echo esc_attr(get_option('tpw_cheque_address3')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_cheque_town">Town/City</label></th>
                        <td><input type="text" name="tpw_cheque_town" id="tpw_cheque_town" value="<?php echo esc_attr(get_option('tpw_cheque_town')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_cheque_county">County</label></th>
                        <td><input type="text" name="tpw_cheque_county" id="tpw_cheque_county" value="<?php echo esc_attr(get_option('tpw_cheque_county')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_cheque_postcode">Postcode</label></th>
                        <td><input type="text" name="tpw_cheque_postcode" id="tpw_cheque_postcode" value="<?php echo esc_attr(get_option('tpw_cheque_postcode')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_surcharge_cheque_percent">Surcharge (%)</label></th>
                        <td>
                            <input type="number" name="tpw_surcharge_cheque_percent" id="tpw_surcharge_cheque_percent" step="0.01" min="0" value="<?php echo esc_attr( get_option('tpw_surcharge_cheque_percent', 0) ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_surcharge_cheque_fixed"><?php printf( esc_html__( 'Fixed (%s)', 'tpw-core' ), esc_html( $currency_symbol ) ); ?></label></th>
                        <td>
                            <input type="number" name="tpw_surcharge_cheque_fixed" id="tpw_surcharge_cheque_fixed" step="0.01" min="0" value="<?php echo esc_attr( get_option('tpw_surcharge_cheque_fixed', 0) ); ?>" />
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Cheque Settings'); ?>
            </form>
        </div></div>
        <?php
    }

    public static function save_method_label_cheque( $val ) {
        $label = sanitize_text_field( (string) $val );
        global $wpdb; $table = $wpdb->prefix . 'tpw_payment_methods';
        $wpdb->update( $table, [ 'name' => $label ], [ 'slug' => 'cheque' ] );
        return $label;
    }
}

TPW_Cheque_Settings::init();
