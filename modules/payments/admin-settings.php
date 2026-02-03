<?php

// Add "Payment" tab to FlexiEvent settings page
add_filter('flexievent_settings_tabs', function($tabs) {
    $tabs['payments'] = 'Payments';
    return $tabs;
});

// Add content for the "Payments" tab
add_action('flexievent_settings_tab_content_payments', function($settings) {
    // Define the settings fields inside the callback
    $settings_fields = [
        'enable_payments' => 'Enable Payments (Yes/No)',
        'currency_symbol' => 'Currency Symbol (e.g. £)',
    ];
    ?>
    <table class="form-table">
        <tbody>
            <?php foreach ($settings_fields as $key => $label): ?>
                <?php if ($key === 'enable_payments') continue; ?>
                <tr>
                    <th scope="row">
                        <label for="flexievent_settings[<?php echo esc_attr($key); ?>]">
                            <?php echo esc_html($label); ?>
                        </label>
                    </th>
                    <td>
                        <?php if ($key === 'currency_symbol'): ?>
                            <?php $selected_symbol = get_option( 'flexievent_currency_symbol', '£' ); ?>
                            <select name="flexievent_settings[currency_symbol]" id="flexievent_settings[currency_symbol]">
                                <option value="£" <?php selected($selected_symbol, '£'); ?>>£ – British Pound (GBP)</option>
                                <option value="$" <?php selected($selected_symbol, '$'); ?>>$ – US Dollar (USD)</option>
                                <option value="€" <?php selected($selected_symbol, '€'); ?>>€ – Euro (EUR)</option>
                                <option disabled>──────────</option>
                                <option value="A$" <?php selected($selected_symbol, 'A$'); ?>>A$ – Australian Dollar (AUD)</option>
                                <option value="NZ$" <?php selected($selected_symbol, 'NZ$'); ?>>NZ$ – New Zealand Dollar (NZD)</option>
                                <option value="HK$" <?php selected($selected_symbol, 'HK$'); ?>>HK$ – Hong Kong Dollar (HKD)</option>
                                <option value="SGD" <?php selected($selected_symbol, 'SGD'); ?>>SGD – Singapore Dollar</option>
                                <option value="MX$" <?php selected($selected_symbol, 'MX$'); ?>>MX$ – Mexican Peso (MXN)</option>
                                <option value="TWD" <?php selected($selected_symbol, 'TWD'); ?>>TWD – New Taiwan Dollar</option>
                                <option value="SAR" <?php selected($selected_symbol, 'SAR'); ?>>SAR – Saudi Riyal</option>
                                <option value="EGP" <?php selected($selected_symbol, 'EGP'); ?>>EGP – Egyptian Pound</option>
                                <option value="؋" <?php selected($selected_symbol, '؋'); ?>>؋ – Afghan Afghani (AFN)</option>
                                <option value="R$" <?php selected($selected_symbol, 'R$'); ?>>R$ – Brazilian Real (BRL)</option>
                                <option value="C$" <?php selected($selected_symbol, 'C$'); ?>>C$ – Canadian Dollar (CAD)</option>
                                <option value="¥" <?php selected($selected_symbol, '¥'); ?>>¥ – Japanese Yen (JPY)</option>
                                <option value="CN¥" <?php selected($selected_symbol, 'CN¥'); ?>>CN¥ – Chinese Yuan (CNY)</option>
                                <option value="Kč" <?php selected($selected_symbol, 'Kč'); ?>>Kč – Czech Koruna (CZK)</option>
                                <option value="kr (DKK)" <?php selected($selected_symbol, 'kr (DKK)'); ?>>kr – Danish Krone (DKK)</option>
                                <option value="kr (NOK)" <?php selected($selected_symbol, 'kr (NOK)'); ?>>kr – Norwegian Krone (NOK)</option>
                                <option value="kr (SEK)" <?php selected($selected_symbol, 'kr (SEK)'); ?>>kr – Swedish Krona (SEK)</option>
                                <option value="₹" <?php selected($selected_symbol, '₹'); ?>>₹ – Indian Rupee (INR)</option>
                                <option value="Rp" <?php selected($selected_symbol, 'Rp'); ?>>Rp – Indonesian Rupiah (IDR)</option>
                                <option value="₪" <?php selected($selected_symbol, '₪'); ?>>₪ – Israeli Shekel (ILS)</option>
                                <option value="₩" <?php selected($selected_symbol, '₩'); ?>>₩ – South Korean Won (KRW)</option>
                                <option value="RM" <?php selected($selected_symbol, 'RM'); ?>>RM – Malaysian Ringgit (MYR)</option>
                                <option value="₦" <?php selected($selected_symbol, '₦'); ?>>₦ – Nigerian Naira (NGN)</option>
                                <option value="₱" <?php selected($selected_symbol, '₱'); ?>>₱ – Philippine Peso (PHP)</option>
                                <option value="zł" <?php selected($selected_symbol, 'zł'); ?>>zł – Polish Zloty (PLN)</option>
                                <option value="руб" <?php selected($selected_symbol, 'руб'); ?>>руб – Russian Ruble (RUB)</option>
                                <option value="R" <?php selected($selected_symbol, 'R'); ?>>R – South African Rand (ZAR)</option>
                                <option value="Fr" <?php selected($selected_symbol, 'Fr'); ?>>Fr – Swiss Franc (CHF)</option>
                                <option value="฿" <?php selected($selected_symbol, '฿'); ?>>฿ – Thai Baht (THB)</option>
                                <option value="₺" <?php selected($selected_symbol, '₺'); ?>>₺ – Turkish Lira (TRY)</option>
                                <option value="₴" <?php selected($selected_symbol, '₴'); ?>>₴ – Ukrainian Hryvnia (UAH)</option>
                                <option value="د.إ" <?php selected($selected_symbol, 'د.إ'); ?>>د.إ – UAE Dirham (AED)</option>
                            </select>
                        <?php else: ?>
                            <input
                                type="text"
                                name="flexievent_settings[<?php echo esc_attr($key); ?>]"
                                id="flexievent_settings[<?php echo esc_attr($key); ?>]"
                                value="<?php echo esc_attr($settings[$key] ?? ''); ?>"
                                class="regular-text"
                            />
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th scope="row">
                    <label for="flexievent_settings[currency_code]">Currency Code (ISO 4217)</label>
                </th>
                <td>
                    <input
                        type="text"
                        name="flexievent_settings[currency_code]"
                        id="flexievent_settings[currency_code]"
                        value="<?php echo esc_attr( get_option( 'flexievent_currency_code', 'GBP' ) ); ?>"
                        class="regular-text"
                    />
                </td>
            </tr>
        </tbody>
    </table>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const symbolSelect = document.getElementById('flexievent_settings[currency_symbol]');
        const codeInput = document.getElementById('flexievent_settings[currency_code]');

        const symbolToCode = {
            '£': 'GBP',
            '$': 'USD',
            '€': 'EUR',
            'A$': 'AUD',
            'NZ$': 'NZD',
            'HK$': 'HKD',
            'SGD': 'SGD',
            'MX$': 'MXN',
            'TWD': 'TWD',
            'SAR': 'SAR',
            'EGP': 'EGP',
            '؋': 'AFN',
            'R$': 'BRL',
            'C$': 'CAD',
            '¥': 'JPY',
            'CN¥': 'CNY',
            'Kč': 'CZK',
            'kr (DKK)': 'DKK',
            'kr (NOK)': 'NOK',
            'kr (SEK)': 'SEK',
            '₹': 'INR',
            'Rp': 'IDR',
            '₪': 'ILS',
            '₩': 'KRW',
            'RM': 'MYR',
            '₦': 'NGN',
            '₱': 'PHP',
            'zł': 'PLN',
            'руб': 'RUB',
            'R': 'ZAR',
            'Fr': 'CHF',
            '฿': 'THB',
            '₺': 'TRY',
            '₴': 'UAH',
            'د.إ': 'AED'
        };

        symbolSelect.addEventListener('change', function () {
            const selectedSymbol = symbolSelect.value;
            if (symbolToCode[selectedSymbol]) {
                codeInput.value = symbolToCode[selectedSymbol];
            }
        });
    });
    </script>
    <?php
});

// --- TPW Core Settings integration: Payment Methods tab content ---
add_action( 'tpw_core_settings_tab_content_payment-methods', function( $active_tab ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        echo '<p>' . esc_html__( 'You do not have permission to view this page.', 'tpw-core' ) . '</p>';
        return;
    }

    // Load the existing Payments admin screen renderer (used by the standalone page too).
    $admin_class_file = defined( 'TPW_CORE_PATH' )
        ? TPW_CORE_PATH . 'modules/payments/class-tpw-payments-admin.php'
        : plugin_dir_path( __FILE__ ) . 'class-tpw-payments-admin.php';

    if ( file_exists( $admin_class_file ) ) {
        require_once $admin_class_file;
    }

    if ( class_exists( 'TPW_Payments_Admin' ) && method_exists( 'TPW_Payments_Admin', 'render_manage_methods_content' ) ) {
        TPW_Payments_Admin::render_manage_methods_content();
        return;
    }

    echo '<p>' . esc_html__( 'Payment Methods UI is unavailable (missing admin renderer).', 'tpw-core' ) . '</p>';
}, 10, 1 );