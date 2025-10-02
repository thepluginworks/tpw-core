

<div class="tpw-admin-ui"><div class="wrap">
    <h1>Square Payment Settings</h1>
    <form method="post" action="options.php">
        <?php
            settings_fields('tpw_payment_settings');
            do_settings_sections('tpw_payment_settings');
        ?>
        <table class="form-table">
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
        </table>
        <?php submit_button(); ?>
    </form>
</div></div>