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
        <?php
        if ( function_exists( 'tpw_admin_output_header' ) ) {
            tpw_admin_output_header(
                __( 'Manage Payment Methods', 'tpw-core' ),
                __( 'Enable, disable, and configure payment methods for your events. For Admins and Treasurers.', 'tpw-core' )
            );
            echo '<div class="tpw-admin-ui"><div class="wrap">';
        } elseif ( function_exists( 'flexievent_output_header' ) ) {
            flexievent_output_header(
                __( 'Manage Payment Methods', 'tpw-core' ),
                __( 'Enable, disable, and configure payment methods for your events. For Admins and Treasurers.', 'tpw-core' )
            );
            echo '<div class="tpw-admin-ui"><div class="wrap">';
        } else {
            echo '<div class="tpw-admin-ui"><div class="wrap"><h1>' . esc_html__( 'Manage Payment Methods', 'tpw-core' ) . '</h1>';
        }
        ?>
            <form method="post">
                <?php wp_nonce_field('tpw_update_payment_methods'); ?>

                <div class="tpw-payments-list">
                    <?php foreach ($methods as $method): ?>
                        <?php
                            if (in_array($method->slug, ['woocommerce', 'sumup'])) { continue; }
                            if ($method->slug === 'cheque-cash') { continue; }

                            $needs_setup = false; $status_chip = '<span class="tpw-status-chip configured">Configured</span>';
                            $summary = '';

                            if ($method->slug === 'bacs') {
                                $bacs_fields = ['tpw_bacs_account_name', 'tpw_bacs_account_number', 'tpw_bacs_sort_code'];
                                $missing = array_filter($bacs_fields, fn($opt) => !get_option($opt));
                                if (!empty($missing)) { $needs_setup = true; $status_chip = '<span class="tpw-status-chip needs-setup">Needs setup</span>'; }
                                $acc_name = get_option('tpw_bacs_account_name');
                                $acc_no = get_option('tpw_bacs_account_number');
                                $sort = get_option('tpw_bacs_sort_code');
                                $masked_no = $acc_no ? str_repeat('•', max(0, strlen($acc_no)-4)) . substr($acc_no, -4) : '';
                                $summary = ($acc_name || $acc_no || $sort) ? sprintf('<span class="tpw-pay-summary">%s%s%s%s%s</span>',
                                    $acc_name ? esc_html($acc_name) : '',
                                    ($acc_name && ($masked_no || $sort)) ? ' · ' : '',
                                    $masked_no ? 'Acct ' . esc_html($masked_no) : '',
                                    ($masked_no && $sort) ? ' · ' : '',
                                    $sort ? 'Sort ' . esc_html($sort) : ''
                                ) : '';
                            } elseif ($method->slug === 'cheque') {
                                $payable = get_option('tpw_cheque_payable_to');
                                if (!$payable) { $needs_setup = true; $status_chip = '<span class="tpw-status-chip needs-setup">Needs setup</span>'; }
                                $summary = $payable ? '<span class="tpw-pay-summary">Payable to: ' . esc_html($payable) . '</span>' : '';
                            } elseif ($method->slug === 'cash') {
                                $msg = trim((string) get_option('tpw_cash_message'));
                                if (!$msg) { $needs_setup = true; $status_chip = '<span class="tpw-status-chip needs-setup">Needs setup</span>'; }
                                if ($msg) { $snippet = wp_trim_words(wp_strip_all_tags($msg), 10, '…'); $summary = '<span class="tpw-pay-summary">' . esc_html($snippet) . '</span>'; }
                            } elseif ($method->slug === 'sumup') {
                                $access_token = get_option('tpw_sumup_access_token');
                                $status_chip = $access_token ? '<span class="tpw-status-chip configured">Connected</span>' : '<span class="tpw-status-chip disconnected">Disconnected</span>';
                            }

                            $disabled_attr = '';
                            if ($method->slug === 'woocommerce' && !class_exists('WooCommerce')) {
                                $disabled_attr = 'disabled';
                            }
                        ?>
                        <div class="tpw-pay-row">
                            <div class="tpw-pay-col tpw-pay-name">
                                <strong><?php echo esc_html($method->name); ?></strong>
                                <?php echo $summary; ?>
                            </div>
                            <div class="tpw-pay-col tpw-pay-status">
                                <label>
                                    <input type="checkbox" name="payment_methods[]" value="<?php echo esc_attr($method->slug); ?>" <?php checked($method->active, 1); ?> <?php echo $disabled_attr; ?> />
                                    <?php esc_html_e('Enable', 'tpw-core'); ?>
                                </label>
                                <?php echo $status_chip; ?>
                            </div>
                            <div class="tpw-pay-col tpw-pay-action">
                                <?php if ($method->slug === 'bacs'): ?>
                                    <?php if ($needs_setup): ?>
                                        <a href="admin.php?page=tpw-bacs-settings" class="button button-primary">Configure Bank Transfer</a>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=tpw-bacs-settings')); ?>" class="button">Edit</a>
                                    <?php endif; ?>
                                <?php elseif ($method->slug === 'cheque'): ?>
                                    <?php if ($needs_setup): ?>
                                        <a href="admin.php?page=tpw-cheque-settings" class="button button-primary">Configure Cheque</a>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=tpw-cheque-settings')); ?>" class="button">Edit</a>
                                    <?php endif; ?>
                                <?php elseif ($method->slug === 'cash'): ?>
                                    <?php if ($needs_setup): ?>
                                        <a href="admin.php?page=tpw-cash-settings" class="button button-primary">Configure Cash</a>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=tpw-cash-settings')); ?>" class="button">Edit</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=tpw-' . esc_attr($method->slug) . '-settings')); ?>" class="button">Edit</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php submit_button('Save Changes'); ?>
            </form>
        </div></div>
        <?php
    }
}

TPW_Payments_Admin::init();
