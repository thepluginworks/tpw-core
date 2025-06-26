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
    }

    public static function render_page() {
        ?>
        <div class="wrap">
            <h1>Bank Transfer Payment Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('tpw_bacs_settings');
                do_settings_sections('tpw_bacs_settings');
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
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
}

TPW_BACS_Settings::init();
