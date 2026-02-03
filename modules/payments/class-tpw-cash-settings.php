<?php

class TPW_Cash_Settings {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_cash_settings_page']);
        add_action('admin_init', [$this, 'register_cash_settings']);
    }

    public function register_cash_settings_page() {
        add_submenu_page(
            'tpw_core',
            'Cash Payment Settings',
            'Cash Payment Settings',
            'manage_options',
            'tpw-cash-settings',
            [$this, 'render_cash_settings_page']
        );
    }

    public function register_cash_settings() {
        register_setting('tpw_cash_settings_group', 'tpw_cash_message');
        // Label field stored in tpw_payment_methods.name
        register_setting('tpw_cash_settings_group', 'tpw_label_cash', [
            'sanitize_callback' => [$this, 'save_method_label_cash']
        ]);
        // Surcharge fields
        register_setting('tpw_cash_settings_group', 'tpw_surcharge_cash_percent', [
            'sanitize_callback' => [$this, 'sanitize_surcharge_value']
        ]);
        register_setting('tpw_cash_settings_group', 'tpw_surcharge_cash_fixed', [
            'sanitize_callback' => [$this, 'sanitize_surcharge_value']
        ]);
    }

    public function sanitize_surcharge_value($val) {
        $v = floatval($val);
        if ($v < 0) { $v = 0; }
        return round($v, 2);
    }

    public function render_cash_settings_page() {
        ?>
        <?php if ( function_exists( 'tpw_core_render_settings_header' ) ) { tpw_core_render_settings_header( 'Cash Payment Settings' ); } ?>
        <div class="tpw-admin-ui"><div class="wrap">
            <p><a href="<?php echo esc_url( tpw_core_get_payment_methods_settings_url() ); ?>" class="button">Back to Payment Methods</a></p>
            <?php if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) : ?>
                <div class="notice notice-success is-dismissible"><p>Surcharge settings updated successfully.</p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('tpw_cash_settings_group');
                do_settings_sections('tpw_cash_settings_group');
                $message = get_option('tpw_cash_message', '');
                // Current label from DB
                global $wpdb; $table = $wpdb->prefix . 'tpw_payment_methods';
                $current_label = $wpdb->get_var( $wpdb->prepare("SELECT name FROM $table WHERE slug = %s", 'cash') );
                if ( ! is_string($current_label) || $current_label === '' ) { $current_label = 'Cash'; }
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
                        <th scope="row"><label for="tpw_label_cash">Label</label></th>
                        <td>
                            <input type="text" name="tpw_label_cash" id="tpw_label_cash" value="<?php echo esc_attr( $current_label ); ?>" class="regular-text" />
                            <p class="description">Shown on checkout.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="tpw_cash_message">Message to display</label></th>
                        <td>
                            <textarea name="tpw_cash_message" id="tpw_cash_message" rows="6" class="large-text"><?php echo esc_textarea($message); ?></textarea>
                            <p class="description">This message will be shown on the checkout, thank you page, and confirmation email.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_surcharge_cash_percent">Surcharge (%)</label></th>
                        <td>
                            <input type="number" name="tpw_surcharge_cash_percent" id="tpw_surcharge_cash_percent" step="0.01" min="0" value="<?php echo esc_attr( get_option('tpw_surcharge_cash_percent', 0) ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_surcharge_cash_fixed"><?php printf( esc_html__( 'Fixed (%s)', 'tpw-core' ), esc_html( $currency_symbol ) ); ?></label></th>
                        <td>
                            <input type="number" name="tpw_surcharge_cash_fixed" id="tpw_surcharge_cash_fixed" step="0.01" min="0" value="<?php echo esc_attr( get_option('tpw_surcharge_cash_fixed', 0) ); ?>" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div></div>
        <?php
    }

    public function save_method_label_cash( $val ) {
        $label = sanitize_text_field( (string) $val );
        global $wpdb; $table = $wpdb->prefix . 'tpw_payment_methods';
        $wpdb->update( $table, [ 'name' => $label ], [ 'slug' => 'cash' ] );
        return $label;
    }
}

new TPW_Cash_Settings();