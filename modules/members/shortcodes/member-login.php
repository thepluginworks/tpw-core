<?php
if (!defined('ABSPATH')) { exit; }

class TPW_Member_Login_Shortcode {
    public static function init() {
        // Conditionally register only if members module active
        if (!self::is_members_active()) {
            return;
        }
        add_shortcode('tpw_member_login', [__CLASS__, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue_assets']);
        add_action('template_redirect', [__CLASS__, 'maybe_handle_post']);

        // Filters for customization
        add_filter('tpw_member_login_messages', [__CLASS__, 'identity'], 10, 1);
    }

    private static function is_members_active() {
        if (defined('TPW_MEMBERS_ACTIVE') && TPW_MEMBERS_ACTIVE) return true;
        if (function_exists('tpw_members_module_enabled') && true === tpw_members_module_enabled()) return true;
        global $wpdb; $table = $wpdb->prefix . 'tpw_members';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        return (bool) $exists;
    }

    public static function identity($messages) { return $messages; }

    public static function maybe_enqueue_assets() {
        if (!is_singular()) return;
        global $post; if (!$post) return;
        if (has_shortcode($post->post_content, 'tpw_member_login')) {
            $base = TPW_CORE_URL . 'modules/members/shortcodes/';
            wp_enqueue_style('tpw-member-login', $base . 'css/member-login.css', [], '1.0');
            wp_enqueue_script('tpw-member-login', $base . 'js/member-login.js', ['jquery'], '1.0', true);
        }
    }

    public static function maybe_handle_post() {
        if ('POST' !== $_SERVER['REQUEST_METHOD']) return;
        if (!isset($_POST['tpw_member_login_action'])) return;

        $action = sanitize_text_field($_POST['tpw_member_login_action']);
        if ($action === 'login') {
            self::handle_login_post();
        } elseif ($action === 'reset') {
            self::handle_reset_post();
        }
    }

    private static function too_many_attempts_key() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        return 'tpw_login_attempts_' . md5($ip);
    }

    private static function check_rate_limit() {
        $key = self::too_many_attempts_key();
        $bundle = get_transient($key);
        $now = time();
        $window = 10 * 60; // 10 minutes
        $limit = 10; // attempts per window
        if (!is_array($bundle)) {
            $bundle = ['count' => 0, 'start' => $now];
        }
        if (($now - $bundle['start']) > $window) {
            $bundle = ['count' => 0, 'start' => $now];
        }
        if ($bundle['count'] >= $limit) {
            return new WP_Error('rate_limited', __('Too many attempts. Please try again later.', 'tpw-core'));
        }
        return $bundle;
    }

    private static function bump_rate_limit($bundle) {
        $key = self::too_many_attempts_key();
        if (!is_array($bundle)) return;
        $bundle['count'] += 1;
        set_transient($key, $bundle, 15 * MINUTE_IN_SECONDS);
    }

    private static function reset_rate_limit() {
        delete_transient(self::too_many_attempts_key());
    }

    private static function handle_login_post() {
        if (!isset($_POST['tpw_member_login_nonce']) || !wp_verify_nonce($_POST['tpw_member_login_nonce'], 'tpw_member_login')) {
            wp_die(__('Security check failed.', 'tpw-core'));
        }
        $bundle = self::check_rate_limit();
        if (is_wp_error($bundle)) {
            self::store_message('error', $bundle->get_error_message());
            return;
        }

        $login = sanitize_text_field($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']) ? true : false;

        if ($login === '' || $password === '') {
            self::bump_rate_limit($bundle);
            self::store_message('error', __('Please enter your email/username and password.', 'tpw-core'));
            return;
        }

        // Allow email or username
        if (is_email($login)) {
            $user = get_user_by('email', $login);
            if ($user) {
                $login = $user->user_login;
            }
        }

        $creds = [
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => $remember,
        ];
        $signon = wp_signon($creds, is_ssl());
        if (is_wp_error($signon)) {
            self::bump_rate_limit($bundle);
            $code = $signon->get_error_code();
            $msg = __('Login failed. Please check your details and try again.', 'tpw-core');
            if ($code === 'invalid_username' || $code === 'incorrect_password') {
                $msg = __('Invalid email/username or password.', 'tpw-core');
            }
            self::store_message('error', $msg);
            return;
        }

        // Success
        self::reset_rate_limit();
        /**
         * Filter the redirect URL after a successful member login.
         *
         * @param string  $redirect_url The default redirect URL.
         * @param WP_User $user         The authenticated user object.
         */
        $redirect_url = apply_filters('tpw_member_login_redirect', home_url(), $signon);
        wp_safe_redirect($redirect_url);
        exit;
    }

    private static function handle_reset_post() {
        if (!isset($_POST['tpw_member_reset_nonce']) || !wp_verify_nonce($_POST['tpw_member_reset_nonce'], 'tpw_member_reset')) {
            wp_die(__('Security check failed.', 'tpw-core'));
        }

        $identifier = sanitize_text_field($_POST['identifier'] ?? '');
        if ($identifier === '') {
            self::store_message('reset_error', __('Please enter your email or username.', 'tpw-core'));
            return;
        }

        // Map email to login if needed
        if (is_email($identifier)) {
            $user = get_user_by('email', $identifier);
        } else {
            $user = get_user_by('login', $identifier);
        }
        if (!$user) {
            self::store_message('reset_error', __('We could not find that account.', 'tpw-core'));
            return;
        }

        // Trigger WordPress password reset flow
        $result = retrieve_password($user->user_login);
        if ($result === true) {
            self::store_message('reset_success', __('If that account exists, a reset link has been sent.', 'tpw-core'));
        } else {
            $message = is_wp_error($result) ? $result->get_error_message() : __('Password reset failed. Please try again later.', 'tpw-core');
            self::store_message('reset_error', $message);
        }
    }

    private static function store_message($type, $text) {
        if (!session_id()) { if (!headers_sent()) { @session_start(); } }
        $_SESSION['tpw_member_login_messages'][$type] = wp_kses_post($text);
    }

    public static function render_shortcode($atts, $content = null) {
        // Pass messages from session (then clear)
        $messages = [];
        if (!session_id()) { if (!headers_sent()) { @session_start(); } }
        if (isset($_SESSION['tpw_member_login_messages'])) {
            $messages = $_SESSION['tpw_member_login_messages'];
            unset($_SESSION['tpw_member_login_messages']);
        }
        $messages = apply_filters('tpw_member_login_messages', $messages);

        ob_start();
        $template = TPW_CORE_PATH . 'modules/members/shortcodes/templates/member-login-form.php';
        if (file_exists($template)) {
            $redirect = apply_filters('tpw_member_login_redirect', home_url(), wp_get_current_user());
            $data = [
                'messages' => $messages,
                'redirect' => $redirect,
            ];
            include $template;
        } else {
            echo esc_html__('Login form template missing.', 'tpw-core');
        }
        return ob_get_clean();
    }
}

TPW_Member_Login_Shortcode::init();
