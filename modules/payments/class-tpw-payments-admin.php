<?php

class TPW_Payments_Admin {
    public static function init() {
        error_log('TPW_Payments_Admin::init() called');
        // AJAX handler for updating sort order of payment methods
        add_action('wp_ajax_tpw_update_payment_sort', [ __CLASS__, 'ajax_update_sort' ]);
    }

    /**
     * AJAX: Update sort order for payment methods.
     * Expects POST 'order' as an array of slugs in desired order.
     */
    public static function ajax_update_sort() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }
        check_ajax_referer( 'tpw_update_payment_sort', 'nonce' );

        $order = isset($_POST['order']) ? (array) $_POST['order'] : [];
        if ( empty( $order ) ) {
            wp_send_json_error( [ 'message' => 'No order provided' ], 400 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tpw_payment_methods';
        // Ensure column exists; best-effort
        $has_sort = $wpdb->get_var( "SHOW COLUMNS FROM $table LIKE 'sort_order'" );
        if ( ! $has_sort ) {
            $wpdb->query( "ALTER TABLE $table ADD COLUMN sort_order INT(11) NOT NULL DEFAULT 0 AFTER slug" );
        }

        $i = 0;
        foreach ( $order as $slug ) {
            $slug = sanitize_key( (string) $slug );
            if ( $slug === '' ) { continue; }
            $wpdb->update( $table, [ 'sort_order' => $i ], [ 'slug' => $slug ] );
            $i++;
        }

        wp_send_json_success( [ 'message' => 'Order updated' ] );
    }

    /**
     * Render the Manage Payment Methods UI (form + list) without page wrappers.
     *
     * This is used by the standalone admin page and also by the TPW Core Settings tab.
     */
    public static function render_manage_methods_content() {
        if ( class_exists( 'TPW_Payments_Manager' ) && method_exists( 'TPW_Payments_Manager', 'reconcile_square_runtime_state' ) ) {
            TPW_Payments_Manager::reconcile_square_runtime_state();
        }

        $square_addon_active = function_exists( 'tpw_core_is_square_gateway_addon_active' )
            && tpw_core_is_square_gateway_addon_active();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('tpw_update_payment_methods')) {
            global $wpdb;
            $table = $wpdb->prefix . 'tpw_payment_methods';

            $selected = isset($_POST['payment_methods']) ? (array) $_POST['payment_methods'] : [];
            $selected = array_map( 'sanitize_key', $selected );

            if ( ! $square_addon_active ) {
                $selected = array_values( array_diff( $selected, [ 'square' ] ) );
            }

            $all = $wpdb->get_results("SELECT slug FROM $table");
            foreach ($all as $method) {
                $is_active = in_array($method->slug, $selected) ? 1 : 0;
                $wpdb->update($table, ['active' => $is_active], ['slug' => $method->slug]);
            }

            echo '<div class="updated"><p>Payment methods updated.</p></div>';

            if ( ! $square_addon_active ) {
                if ( class_exists( 'TPW_Payments_Manager' ) && method_exists( 'TPW_Payments_Manager', 'reconcile_square_runtime_state' ) ) {
                    TPW_Payments_Manager::reconcile_square_runtime_state();
                }

                echo '<div class="notice notice-warning"><p>'
                    . esc_html__( 'Square settings are retained, but Square cannot be enabled until the TPW Square Gateway add-on is active.', 'tpw-core' )
                    . '</p></div>';
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tpw_payment_methods';
        // Ensure sortable library is available
        if ( function_exists('wp_enqueue_script') ) {
            wp_enqueue_script('jquery-ui-sortable');
        }
        // Ensure the new sort_order column exists for older installs
        $has_sort = $wpdb->get_var( "SHOW COLUMNS FROM $table LIKE 'sort_order'" );
        if ( ! $has_sort ) {
            // Best-effort add; ignore errors if it already exists
            $wpdb->query( "ALTER TABLE $table ADD COLUMN sort_order INT(11) NOT NULL DEFAULT 0 AFTER slug" );
        }
        // Ensure new methods are present for existing installs (idempotent)
        $exists_card_day = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE slug = %s", 'card-on-the-day' ) );
        if ( $exists_card_day === 0 ) {
            $wpdb->insert( $table, [
                'name'       => 'Card on the day',
                'slug'       => 'card-on-the-day',
                'active'     => 0,
                'created_at' => current_time('mysql'),
            ] );
        }
        $methods = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC, name ASC");
        $sort_nonce = wp_create_nonce('tpw_update_payment_sort');

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
                $methods[] = (object) [
                    'slug' => 'card-on-the-day',
                    'name' => 'Card on the day',
                    'active' => get_option('tpw_card_on_the_day_enabled') ? 1 : 0
                ];
            }
        }

        if ( ! $square_addon_active ) {
            echo '<div class="notice notice-warning"><p>'
                . esc_html__( 'Square remains configured in Core, but it is unavailable on forms until the TPW Square Gateway add-on is active.', 'tpw-core' )
                . '</p></div>';
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
                            } elseif ($method->slug === 'card-on-the-day') {
                                $msg = trim((string) get_option('tpw_card_on_the_day_message'));
                                if (!$msg) { $needs_setup = true; $status_chip = '<span class="tpw-status-chip needs-setup">Needs setup</span>'; }
                                if ($msg) { $snippet = wp_trim_words(wp_strip_all_tags($msg), 10, '…'); $summary = '<span class="tpw-pay-summary">' . esc_html($snippet) . '</span>'; }
                            } elseif ($method->slug === 'sumup') {
                                $access_token = get_option('tpw_sumup_access_token');
                                $status_chip = $access_token ? '<span class="tpw-status-chip configured">Connected</span>' : '<span class="tpw-status-chip disconnected">Disconnected</span>';
                            } elseif ($method->slug === 'square' && ! $square_addon_active) {
                                $has_square_config = class_exists( 'TPW_Payments_Manager' ) && method_exists( 'TPW_Payments_Manager', 'square_has_stored_configuration' )
                                    ? TPW_Payments_Manager::square_has_stored_configuration()
                                    : false;
                                $remembered_square_active = strtolower( (string) get_option( 'tpw_square_requested_active', '0' ) );
                                $will_restore = in_array( $remembered_square_active, [ '1', 'yes', 'on', 'true', 'enabled' ], true );

                                $status_chip = $has_square_config
                                    ? '<span class="tpw-status-chip configured">Configured</span> <span class="tpw-status-chip disconnected">Add-on required</span>'
                                    : '<span class="tpw-status-chip disconnected">Add-on required</span>';

                                $summary_parts = [
                                    esc_html__( 'Unavailable on forms until the TPW Square Gateway add-on is active.', 'tpw-core' ),
                                ];

                                if ( $will_restore ) {
                                    $summary_parts[] = esc_html__( 'The previous enabled state will be restored when the add-on becomes active again.', 'tpw-core' );
                                }

                                $summary = '<span class="tpw-pay-summary">' . esc_html( implode( ' ', $summary_parts ) ) . '</span>';
                            }

                            $disabled_attr = '';
                            if ($method->slug === 'woocommerce' && !class_exists('WooCommerce')) {
                                $disabled_attr = 'disabled';
                            } elseif ($method->slug === 'square' && ! $square_addon_active) {
                                $disabled_attr = 'disabled';
                            }
                        ?>
                        <div class="tpw-pay-row" data-slug="<?php echo esc_attr($method->slug); ?>">
                            <div class="tpw-pay-col tpw-pay-drag" title="Drag to reorder" aria-label="Drag to reorder">
                                <span class="tpw-drag-handle" aria-hidden="true">⋮⋮</span>
                            </div>
                            <div class="tpw-pay-col tpw-pay-name">
                                <?php
                                    // Display fixed method names on this list only, regardless of admin-customised labels elsewhere.
                                    $fixed_names = [
                                        'square'           => 'Square',
                                        'bacs'             => 'Bank Transfer',
                                        'card-on-the-day'  => 'Card on the day',
                                        'cash'             => 'Cash',
                                        'cheque'           => 'Cheque',
                                    ];
                                    $slug = isset($method->slug) ? (string) $method->slug : '';
                                    $display_name = isset($fixed_names[$slug]) ? $fixed_names[$slug] : (string) ($method->name ?? '');
                                ?>
                                <strong><?php echo esc_html( $display_name ); ?></strong>
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
                                <?php elseif ($method->slug === 'card-on-the-day'): ?>
                                    <?php if ($needs_setup): ?>
                                        <a href="admin.php?page=tpw-card-on-the-day-settings" class="button button-primary">Configure Card on the day</a>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=tpw-card-on-the-day-settings')); ?>" class="button">Edit</a>
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
            <div id="tpw-sort-feedback" style="margin-top:8px; display:none;"></div>
        <style>
            .tpw-payments-list { counter-reset: rownum; }
            .tpw-pay-row { display:flex; align-items:center; gap:12px; border:1px solid #e2e8f0; padding:8px 12px; border-radius:6px; background:#fff; }
            .tpw-pay-row + .tpw-pay-row { margin-top:8px; }
            .tpw-pay-drag { width:24px; cursor:move; color:#64748b; display:flex; align-items:center; justify-content:center; }
            .tpw-drag-handle { font-size:18px; line-height:1; user-select:none; }
            .ui-sortable-helper { box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        </style>
        <script>
        (function($){
            $(function(){
                var $list = $('.tpw-payments-list');
                if (!$list.length || !$.fn.sortable) return;
                $list.sortable({
                    items: '.tpw-pay-row',
                    handle: '.tpw-drag-handle',
                    axis: 'y',
                    update: function(){
                        var order = $list.find('.tpw-pay-row').map(function(){ return $(this).data('slug'); }).get();
                        $('#tpw-sort-feedback').stop(true,true).text('Saving order…').css({display:'block', color:'#334155'});
                        $.post(ajaxurl, {
                            action: 'tpw_update_payment_sort',
                            nonce: '<?php echo esc_js($sort_nonce); ?>',
                            order: order
                        }).done(function(resp){
                            var ok = resp && resp.success;
                            $('#tpw-sort-feedback').text(ok ? 'Order saved.' : 'Save failed.').css('color', ok ? '#16a34a' : '#dc2626').delay(1500).fadeOut(400);
                        }).fail(function(){
                            $('#tpw-sort-feedback').text('Save failed.').css('color', '#dc2626').delay(2000).fadeOut(400);
                        });
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    public static function render_page() {
        error_log('TPW_Payments_Admin::render_page() called');

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

        self::render_manage_methods_content();

        echo '</div></div>';
    }
}

TPW_Payments_Admin::init();
