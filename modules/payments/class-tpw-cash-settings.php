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
    }

    public function render_cash_settings_page() {
        ?>
        <div class="wrap">
            <h1>Cash Payment Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('tpw_cash_settings_group');
                do_settings_sections('tpw_cash_settings_group');
                $message = get_option('tpw_cash_message', '');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="tpw_cash_message">Message to display</label></th>
                        <td>
                            <textarea name="tpw_cash_message" id="tpw_cash_message" rows="6" class="large-text"><?php echo esc_textarea($message); ?></textarea>
                            <p class="description">This message will be shown on the checkout, thank you page, and confirmation email.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

new TPW_Cash_Settings();