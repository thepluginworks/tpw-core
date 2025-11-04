

<div class="tpw-admin-ui"><div class="wrap">
    <h1>Square Payment Settings</h1>
    <?php if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) : ?>
        <div class="notice notice-success is-dismissible"><p>Surcharge settings updated successfully.</p></div>
    <?php endif; ?>
    <form method="post" action="options.php">
        <?php
            settings_fields('tpw_payment_settings');
            do_settings_sections('tpw_payment_settings');
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
                <th scope="row"><label for="tpw_label_square">Label</label></th>
                <td>
                    <?php
                    global $wpdb; $table = $wpdb->prefix . 'tpw_payment_methods';
                    $current_label = $wpdb->get_var( $wpdb->prepare("SELECT name FROM $table WHERE slug = %s", 'square') );
                    if ( ! is_string($current_label) || $current_label === '' ) { $current_label = 'Pay by Card (via Square)'; }
                    ?>
                    <input type="text" name="tpw_label_square" id="tpw_label_square" value="<?php echo esc_attr( $current_label ); ?>" class="regular-text" />
                    <p class="description">Shown on checkout.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tpw_square_app_id">Application ID</label></th>
                <td><input type="text" name="tpw_square_app_id" id="tpw_square_app_id" value="<?php echo esc_attr(get_option('tpw_square_app_id')); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="tpw_square_access_token">Access Token</label></th>
                <td><input type="password" name="tpw_square_access_token" id="tpw_square_access_token" value="<?php echo esc_attr(get_option('tpw_square_access_token')); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="tpw_square_location_id">Location ID</label></th>
                <td><input type="text" name="tpw_square_location_id" id="tpw_square_location_id" value="<?php echo esc_attr(get_option('tpw_square_location_id')); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="tpw_square_sandbox_mode">Sandbox Mode</label></th>
                <td>
                    <input type="checkbox" name="tpw_square_sandbox_mode" id="tpw_square_sandbox_mode" value="1" <?php checked(1, get_option('tpw_square_sandbox_mode'), true); ?> />
                    <label for="tpw_square_sandbox_mode">Use Square Sandbox for testing</label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tpw_surcharge_square_percent">Surcharge (%)</label></th>
                <td>
                    <input type="number" name="tpw_surcharge_square_percent" id="tpw_surcharge_square_percent" step="0.01" min="0" value="<?php echo esc_attr( get_option('tpw_surcharge_square_percent', 0) ); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tpw_surcharge_square_fixed"><?php printf( esc_html__( 'Fixed (%s)', 'tpw-core' ), esc_html( $currency_symbol ) ); ?></label></th>
                <td>
                    <input type="number" name="tpw_surcharge_square_fixed" id="tpw_surcharge_square_fixed" step="0.01" min="0" value="<?php echo esc_attr( get_option('tpw_surcharge_square_fixed', 0) ); ?>" />
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div></div>