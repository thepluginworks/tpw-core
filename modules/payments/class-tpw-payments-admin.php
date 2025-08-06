<?php

class TPW_Payments_Admin {
    public static function init() {
        error_log('TPW_Payments_Admin::init() called');
    }

    public static function render_page() {
        error_log('TPW_Payments_Admin::render_page() called');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('tpw_update_payment_methods')) {
            global $wpdb;
            $table = $wpdb->prefix . 'tpw_payment_methods';

            $selected = isset($_POST['payment_methods']) ? (array) $_POST['payment_methods'] : [];

            $all = $wpdb->get_results("SELECT slug FROM $table");
            foreach ($all as $method) {
                $is_active = in_array($method->slug, $selected) ? 1 : 0;
                $wpdb->update($table, ['active' => $is_active], ['slug' => $method->slug]);
            }

            echo '<div class="updated"><p>Payment methods updated.</p></div>';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tpw_payment_methods';
        $methods = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");

        foreach ($methods as $index => $method) {
            if ($method->slug === 'cheque-cash') {
                // Remove the combined method
                unset($methods[$index]);

                // Add separate methods
                $methods[] = (object) [
                    'slug' => 'cheque',
                    'name' => 'Cheque',
                    'active' => get_option('tpw_cheque_enabled') ? 1 : 0
                ];
                $methods[] = (object) [
                    'slug' => 'cash',
                    'name' => 'Cash',
                    'active' => get_option('tpw_cash_enabled') ? 1 : 0
                ];
            }
        }

        ?>
        <div class="wrap">
            <h1>Manage Payment Methods</h1>
            <form method="post">
                <?php wp_nonce_field('tpw_update_payment_methods'); ?>
                <table class="form-table">
                    <tbody>
                        <?php foreach ($methods as $method): ?>
                            <?php
                                if (in_array($method->slug, ['woocommerce', 'sumup'])) {
                                    continue; // Temporarily hide these payment methods
                                }

                                if ($method->slug === 'cheque-cash') {
                                    continue; // This combined slug is no longer used
                                }
                            ?>
                            <?php
                                $row_class = '';
                                if ($method->slug === 'sumup' && !get_option('tpw_sumup_access_token')) {
                                    $row_class = 'style="background-color: #ffe6e6;"';
                                } elseif ($method->slug === 'bacs') {
                                    $bacs_fields = ['tpw_bacs_account_name', 'tpw_bacs_account_number', 'tpw_bacs_sort_code'];
                                    $missing = array_filter($bacs_fields, fn($opt) => !get_option($opt));
                                    if (!empty($missing)) {
                                        $row_class = 'style="background-color: #ffe6e6;"';
                                    }
                                } elseif ($method->slug === 'cheque' && !get_option('tpw_cheque_payable_to')) {
                                    $row_class = 'style="background-color: #ffe6e6;"';
                                }

                                $disabled_attr = '';
                                $note = '';
                                if ($method->slug === 'woocommerce' && !class_exists('WooCommerce')) {
                                    $disabled_attr = 'disabled';
                                    $note = '<p style="color: gray; margin-top: 4px;">Requires WooCommerce to be installed and activated.</p>';
                                }
                            ?>
                            <tr <?php echo $row_class; ?>>
                                <th scope="row"><?php echo esc_html($method->name); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="payment_methods[]" value="<?php echo esc_attr($method->slug); ?>" <?php checked($method->active, 1); ?> <?php echo $disabled_attr; ?> />
                                        Enable
                                    </label>
                                    <?php echo $note; ?>
                                    <?php if ($method->slug === 'sumup'): ?>
                                        <br/>
                                        <?php
                                        $client_id = get_option('tpw_sumup_client_id');
                                        if ($client_id) {
                                            $redirect_uri = urlencode(admin_url('admin-post.php?action=tpw_sumup_callback'));
                                            $scope = 'payments checkout user.profile';
                                            $auth_url = "https://api.sumup.com/authorize?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}&scope={$scope}";
                                        } else {
                                            $auth_url = '';
                                        }
                                        ?>
                                        <?php
                                        $access_token = get_option('tpw_sumup_access_token');
                                        if (!$client_id) {
                                            echo '<p style="color: red; margin-top: 4px;">⚠️ Client ID not set. Please edit settings first.</p>';
                                        } else {
                                            $status = $access_token ? '✅ Connected to SumUp' : '⚠️ Disconnected from SumUp';
                                            $status_color = $access_token ? 'green' : 'red';
                                            echo '<p style="color: ' . esc_attr($status_color) . '; margin-top: 4px;">' . esc_html($status) . '</p>';
                                        }
                                        ?>
                                    <?php elseif ($method->slug === 'bacs'): ?>
                                        
                                        <?php
                                        $bacs_fields = ['tpw_bacs_account_name', 'tpw_bacs_account_number', 'tpw_bacs_sort_code'];
                                        $missing = array_filter($bacs_fields, fn($opt) => !get_option($opt));
                                        if (!empty($missing)) :
                                        ?>
                                            <p style="color: red; margin-top: 4px;">⚠️ One or more BACS fields are missing</p>
                                        <?php endif; ?>
                                    <?php elseif ($method->slug === 'cheque'): ?>
                                        <br/>
                                        <a href="admin.php?page=tpw-cheque-settings" class="button">Configure Cheque</a>
                                        <?php if (!get_option('tpw_cheque_payable_to')): ?>
                                            <p style="color: red; margin-top: 4px;">⚠️ Cheque payable name is missing</p>
                                        <?php endif; ?>
                                    <?php elseif ($method->slug === 'cash'): ?>
                                        <br/>
                                        <a href="admin.php?page=tpw-cash-settings" class="button">Configure Cash</a>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=tpw-' . esc_attr($method->slug) . '-settings')); ?>" class="button">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button('Save Changes'); ?>
            </form>
        </div>
        <?php
    }
}

TPW_Payments_Admin::init();
