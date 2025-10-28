<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

error_log('payment-settings-page.php loaded');

/**
 * SumUp settings screen renderer.
 *
 * Renders the admin page UI and contextual Help tab for the SumUp gateway.
 *
 * @since 1.0.1
 */
class TPW_SumUp_Settings_Page {
    /**
     * Output the SumUp settings page.
     *
     * @since 1.0.1
     * @return void
     */
    public static function render_page() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tpw_sumup_nonce']) && wp_verify_nonce($_POST['tpw_sumup_nonce'], 'tpw_sumup_save_settings')) {
            $client_id = sanitize_text_field($_POST['tpw_sumup_client_id'] ?? '');
            $client_secret = sanitize_text_field($_POST['tpw_sumup_client_secret'] ?? '');

            update_option('tpw_sumup_client_id', $client_id);
            update_option('tpw_sumup_client_secret', $client_secret);

            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
        }

        error_log('[TPW_SUMUP] Reconnect GET param: ' . print_r($_GET, true));
        if (isset($_GET['reconnect']) && $_GET['reconnect'] === '1') {
            delete_option('tpw_sumup_access_token');
            error_log('[TPW_SUMUP] tpw_sumup_access_token deleted');
            delete_option('tpw_sumup_refresh_token');
            error_log('[TPW_SUMUP] tpw_sumup_refresh_token deleted');
        }

        error_log('TPW_SumUp_Settings_Page::render_page() was called');
    echo '<div class="tpw-admin-ui"><div class="wrap">';
        echo '<h1>Payment Gateway Settings</h1>';
        echo '<p><button type="button" class="button button-secondary" id="tpw-show-secure-setup">View Secure Setup Guide</button></p>';

        // Success message
        if (isset($_GET['sumup_connected']) && $_GET['sumup_connected'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>SumUp access token has been saved successfully.</p></div>';
        }

        echo '<form method="post">';
        wp_nonce_field('tpw_sumup_save_settings', 'tpw_sumup_nonce');

        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="tpw_sumup_client_id">Client ID</label></th>';
        $client_id_value = get_option('tpw_sumup_client_id');
        if (!$client_id_value && defined('TPW_SUMUP_CLIENT_ID')) {
            $client_id_value = TPW_SUMUP_CLIENT_ID;
        }
        echo '<td><input name="tpw_sumup_client_id" type="text" id="tpw_sumup_client_id" value="' . esc_attr($client_id_value) . '" class="regular-text"></td></tr>';
        echo '<tr><th scope="row"><label for="tpw_sumup_client_secret">Client Secret</label></th>';
        $client_secret_value = get_option('tpw_sumup_client_secret');
        if (!$client_secret_value && defined('TPW_SUMUP_CLIENT_SECRET')) {
            $client_secret_value = TPW_SUMUP_CLIENT_SECRET;
        }
        echo '<td><input name="tpw_sumup_client_secret" type="password" id="tpw_sumup_client_secret" value="' . esc_attr($client_secret_value) . '" class="regular-text"></td></tr>';
        echo '<p class="description">These credentials are stored securely in the database unless defined in wp-config.php, in which case they are read-only.</p>';
        echo '</tbody></table>';

        submit_button('Save Settings');
        echo '</form>';

        // Modal for Secure Setup Guide
        echo '
  <div id="tpw-secure-setup-modal" style="display:none; position:fixed; top:10%; left:50%; transform:translateX(-50%); background:#fff; padding:20px; max-width:600px; box-shadow:0 0 20px rgba(0,0,0,0.5); z-index:10000;">
    <h2>Secure Setup Guide</h2>
    <p><strong>Storing Credentials Securely</strong></p>
    <p>If you are comfortable editing files, it is recommended to define your SumUp credentials in <code>wp-config.php</code> instead of saving them in the database.</p>
    <pre><code>define(\'TPW_SUMUP_CLIENT_ID\', \'your-client-id\');
define(\'TPW_SUMUP_CLIENT_SECRET\', \'your-client-secret\');</code></pre>
    <p>Once defined, these fields become read-only in the plugin settings, helping prevent accidental changes or exposure.</p>
    <p><strong>Why?</strong> wp-config.php is not accessible from the web and is considered more secure for storing sensitive credentials.</p>
    <p>Make sure your <code>wp-config.php</code> file is not world-readable and is protected by your web server configuration.</p>
    <p><button type="button" class="button" id="tpw-close-secure-setup">Close</button></p>
  </div>
  ';

        $access_token = get_option('tpw_sumup_access_token');
        $client_id = get_option('tpw_sumup_client_id');
        $connected = !empty($access_token);

        echo '<h2>SumUp</h2>';
        if ($connected) {
            echo '<p><strong>✅ SumUp is connected.</strong></p>';
            echo '<p>Access token is stored securely.</p>';
            if (!empty($client_id)) {
                $scope = urlencode('payments transactions.history user.app-settings user.profile');
                $auth_redirect = 'https://api.sumup.com/authorize?response_type=code'
                    . '&client_id=' . urlencode($client_id)
                    . '&redirect_uri=' . urlencode(admin_url('admin-post.php?action=tpw_sumup_callback'))
                    . '&scope=' . $scope
                    . '&reconnect=1';
                echo '<p><a href="' . esc_url($auth_redirect) . '" class="button">Reconnect to SumUp</a></p>';
            }
        } else {
            echo '<p><strong>⚠️ SumUp is not connected.</strong></p>';
            if (!empty($client_id)) {
                $scope = urlencode('payments transactions.history user.app-settings user.profile');
                $auth_redirect = 'https://api.sumup.com/authorize?response_type=code'
                    . '&client_id=' . urlencode($client_id)
                    . '&redirect_uri=' . urlencode(admin_url('admin-post.php?action=tpw_sumup_callback'))
                    . '&scope=' . $scope;
                error_log('Updated SumUp OAuth URL: ' . $auth_redirect);
                echo '<p><a href="' . esc_url($auth_redirect) . '" class="button button-primary">Connect to SumUp</a></p>';
            } else {
                echo '<p>Please configure your SumUp Client ID first in the plugin settings.</p>';
            }
        }

        echo '</div></div>';
    }
}


/**
 * Contextual Help: Secure Setup guidance for SumUp credentials.
 *
 * @since 1.0.1
 */
add_action('load-toplevel_page_tpw_payment_settings', function() {
    $screen = get_current_screen();
    $screen->add_help_tab([
        'id'      => 'tpw_sumup_secure_setup',
        'title'   => 'Secure Setup Guide',
        'content' =>
            '<p><strong>Storing Credentials Securely</strong></p>' .
            '<p>If you are comfortable editing files, it is recommended to define your SumUp credentials in <code>wp-config.php</code> instead of saving them in the database.</p>' .
            '<pre><code>define(\'TPW_SUMUP_CLIENT_ID\', \'your-client-id\');
define(\'TPW_SUMUP_CLIENT_SECRET\', \'your-client-secret\');</code></pre>' .
            '<p>Once defined, these fields become read-only in the plugin settings, helping prevent accidental changes or exposure.</p>' .
            '<p><strong>Why?</strong> wp-config.php is not accessible from the web and is considered more secure for storing sensitive credentials.</p>' .
            '<p>Make sure your <code>wp-config.php</code> file is not world-readable and is protected by your web server configuration.</p>',
    ]);
});

TPW_SumUp_Settings_Page::render_page();

// Inline JavaScript to toggle the modal
echo '
<script>
  document.getElementById("tpw-show-secure-setup").addEventListener("click", function() {
    document.getElementById("tpw-secure-setup-modal").style.display = "block";
  });
  document.getElementById("tpw-close-secure-setup").addEventListener("click", function() {
    document.getElementById("tpw-secure-setup-modal").style.display = "none";
  });
</script>
';
