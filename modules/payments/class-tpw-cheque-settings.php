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
    }

    public static function render_page() {
        ?>
        <div class="wrap">
            <h1>Cheque Payment Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('tpw_cheque_settings');
                do_settings_sections('tpw_cheque_settings');
                ?>
                <table class="form-table">
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
                </table>
                <?php submit_button('Save Cheque Settings'); ?>
            </form>
        </div>
        <?php
    }
}

TPW_Cheque_Settings::init();
