<?php

class TPW_Card_On_The_Day_Settings {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings_page() {
        add_submenu_page(
            'tpw_core',
            __('Card on the day Settings', 'tpw-core'),
            __('Card on the day Settings', 'tpw-core'),
            'manage_options',
            'tpw-card-on-the-day-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        // Instructional message (same as cash style)
        register_setting('tpw_card_on_the_day_settings_group', 'tpw_card_on_the_day_message');
        // Label field stored in tpw_payment_methods.name
        register_setting('tpw_card_on_the_day_settings_group', 'tpw_label_card_on_the_day', [
            'sanitize_callback' => [$this, 'save_method_label_card_on_the_day']
        ]);
        // Surcharge fields (percent and fixed)
        register_setting('tpw_card_on_the_day_settings_group', 'tpw_surcharge_card-on-the-day_percent', [
            'sanitize_callback' => [$this, 'sanitize_surcharge_value']
        ]);
        register_setting('tpw_card_on_the_day_settings_group', 'tpw_surcharge_card-on-the-day_fixed', [
            'sanitize_callback' => [$this, 'sanitize_surcharge_value']
        ]);
    }

    public function sanitize_surcharge_value($val) {
        $v = floatval($val);
        if ($v < 0) { $v = 0; }
        return round($v, 2);
    }

    public function render_settings_page() {
        ?>
        <?php if ( function_exists( 'tpw_core_render_settings_header' ) ) { tpw_core_render_settings_header( __( 'Card on the day Settings', 'tpw-core' ) ); } ?>
        <div class="tpw-admin-ui"><div class="wrap">
            <p><a href="<?php echo esc_url( admin_url('admin.php?page=tpw-core-payment-methods') ); ?>" class="button"><?php esc_html_e('Back to Payment Methods', 'tpw-core'); ?></a></p>
            <?php if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Surcharge settings updated successfully.', 'tpw-core'); ?></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('tpw_card_on_the_day_settings_group');
                do_settings_sections('tpw_card_on_the_day_settings_group');
                $message = get_option('tpw_card_on_the_day_message', '');
                // Current label from DB
                global $wpdb; $table = $wpdb->prefix . 'tpw_payment_methods';
                $current_label = $wpdb->get_var( $wpdb->prepare("SELECT name FROM $table WHERE slug = %s", 'card-on-the-day') );
                if ( ! is_string($current_label) || $current_label === '' ) { $current_label = 'Card on the day'; }
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
                        <th scope="row"><label for="tpw_label_card_on_the_day"><?php esc_html_e('Label', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="text" name="tpw_label_card_on_the_day" id="tpw_label_card_on_the_day" value="<?php echo esc_attr( $current_label ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Shown on checkout.', 'tpw-core'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="tpw_card_on_the_day_message"><?php esc_html_e('Message to display', 'tpw-core'); ?></label></th>
                        <td>
                            <textarea name="tpw_card_on_the_day_message" id="tpw_card_on_the_day_message" rows="6" class="large-text"><?php echo esc_textarea($message); ?></textarea>
                            <p class="description"><?php esc_html_e('This message will be shown on the checkout, thank you page, and confirmation email.', 'tpw-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_surcharge_card-on-the-day_percent"><?php esc_html_e('Surcharge (%)', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="number" name="tpw_surcharge_card-on-the-day_percent" id="tpw_surcharge_card-on-the-day_percent" step="0.01" min="0" value="<?php echo esc_attr( get_option('tpw_surcharge_card-on-the-day_percent', 0) ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_surcharge_card-on-the-day_fixed"><?php printf( esc_html__( 'Fixed (%s)', 'tpw-core' ), esc_html( $currency_symbol ) ); ?></label></th>
                        <td>
                            <input type="number" name="tpw_surcharge_card-on-the-day_fixed" id="tpw_surcharge_card-on-the-day_fixed" step="0.01" min="0" value="<?php echo esc_attr( get_option('tpw_surcharge_card-on-the-day_fixed', 0) ); ?>" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div></div>
        <?php
    }

    public function save_method_label_card_on_the_day( $val ) {
        $label = sanitize_text_field( (string) $val );
        global $wpdb; $table = $wpdb->prefix . 'tpw_payment_methods';
        $wpdb->update( $table, [ 'name' => $label ], [ 'slug' => 'card-on-the-day' ] );
        return $label;
    }
}

new TPW_Card_On_The_Day_Settings();
?>