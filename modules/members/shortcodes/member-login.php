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
        // Ensure pages rendering the login form are not cached (prevents stale/invalid nonces)
        add_action('send_headers', [__CLASS__, 'maybe_disable_cache']);
        add_action('template_redirect', [__CLASS__, 'maybe_handle_post']);

    // Rewrite password reset links in emails to point to front-end /member-login/
    add_filter('retrieve_password_message', [__CLASS__, 'filter_reset_email_message'], 10, 4);

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
            // Signal common cache plugins to bypass caching this page as early as possible
            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }
            $base = TPW_CORE_URL . 'modules/members/shortcodes/';
            wp_enqueue_style('tpw-member-login', $base . 'css/member-login.css', [], '1.0');
            wp_enqueue_script('tpw-member-login', $base . 'js/member-login.js', ['jquery'], '1.0', true);
        }
    }

    /**
     * Send no-cache headers on pages that include the member login shortcode to avoid
     * caches serving a stale page with expired nonces, especially when a redirect_to
     * query parameter is present.
     */
    public static function maybe_disable_cache() {
        if (!is_singular()) return;
        global $post; if (!$post) return;
        if (has_shortcode($post->post_content, 'tpw_member_login')) {
            // Signal common cache plugins to bypass caching this page
            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }
            if (!headers_sent()) {
                // Core helper to send appropriate no-cache headers
                nocache_headers();
            }
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
        } elseif ($action === 'do_reset') {
            self::handle_do_reset_post();
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
        // Priority 1: honour redirect_to param from request when present
        $requested = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';
        if ( is_string( $requested ) ) {
            $requested = trim( (string) wp_unslash( $requested ) );
        } else {
            $requested = '';
        }

        if ( $requested !== '' ) {
            // decode in case it was added with rawurlencode(), then sanitize
            $target = esc_url_raw( rawurldecode( $requested ) );
            if ( $target !== '' ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[TPW DEBUG] Member login redirect using redirect_to param: ' . $target );
                }
                wp_safe_redirect( $target );
                exit;
            }
            // otherwise fall through to fallback handling
        }

        /**
         * Filter the redirect URL after a successful member login.
         * Falls back to the configured "Redirect After Login" option handled by core.
         *
         * @param string  $redirect_url The default redirect URL.
         * @param WP_User $user         The authenticated user object.
         */
        $redirect_url = apply_filters( 'tpw_member_login_redirect', home_url(), $signon );
        $target = esc_url_raw( $redirect_url );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[TPW DEBUG] Member login redirect using fallback destination: ' . $target );
        }
        wp_safe_redirect( $target );
        exit;
    }

    private static function handle_reset_post() {
        $nonce_ok = false;
        // Primary: our plugin-specific nonce
        if ( isset($_POST['tpw_member_reset_nonce']) && wp_verify_nonce( $_POST['tpw_member_reset_nonce'], 'tpw_member_reset' ) ) {
            $nonce_ok = true;
        }
        // Fallback: WordPress core 'lostpassword' nonce used on wp-login.php
        if ( ! $nonce_ok && isset($_POST['_wpnonce']) && wp_verify_nonce( $_POST['_wpnonce'], 'lostpassword' ) ) {
            $nonce_ok = true;
        }
        if ( ! $nonce_ok ) {
            self::store_message('reset_error', __('Security check failed. Please refresh the page and try again.', 'tpw-core'));
            self::redirect_back_preserving_redirect();
        }

        $identifier = sanitize_text_field($_POST['identifier'] ?? '');
        if ($identifier === '') {
            self::store_message('reset_error', __('Please enter your email or username.', 'tpw-core'));
            self::redirect_back_preserving_redirect();
        }

        // Map email to login if needed
        if (is_email($identifier)) {
            $user = get_user_by('email', $identifier);
        } else {
            $user = get_user_by('login', $identifier);
        }
        if (!$user) {
            self::store_message('reset_error', __('We could not find that account.', 'tpw-core'));
            self::redirect_back_preserving_redirect();
        }

        // Trigger WordPress password reset flow
        $result = retrieve_password($user->user_login);
        if ($result === true) {
            self::store_message('reset_success', __('If that account exists, a reset link has been sent.', 'tpw-core'));
        } else {
            $message = is_wp_error($result) ? $result->get_error_message() : __('Password reset failed. Please try again later.', 'tpw-core');
            self::store_message('reset_error', $message);
        }
        self::redirect_back_preserving_redirect();
    }

    private static function handle_do_reset_post() {
        // Nonce
        $nonce_ok = isset($_POST['tpw_member_do_reset_nonce']) && wp_verify_nonce($_POST['tpw_member_do_reset_nonce'], 'tpw_member_do_reset');
        if ( ! $nonce_ok ) {
            self::store_message('reset_error', __('Security check failed. Please refresh the page and try again.', 'tpw-core'));
            $k = sanitize_text_field($_POST['key'] ?? '');
            $l = sanitize_text_field($_POST['login'] ?? '');
            self::redirect_back_to_rp($k, $l);
        }

        $key   = sanitize_text_field($_POST['key'] ?? '');
        $login = sanitize_text_field($_POST['login'] ?? '');
        $pass1 = (string) ($_POST['pass1'] ?? '');
        $pass2 = (string) ($_POST['pass2'] ?? '');

        if ($key === '' || $login === '') {
            self::store_message('reset_error', __('Invalid reset link. Please request a new password reset.', 'tpw-core'));
            self::redirect_back_preserving_redirect();
        }
        if ($pass1 === '' || $pass2 === '') {
            self::store_message('reset_error', __('Please enter your new password twice.', 'tpw-core'));
            self::redirect_back_to_rp($key, $login);
        }
        if ($pass1 !== $pass2) {
            self::store_message('reset_error', __('Passwords do not match.', 'tpw-core'));
            self::redirect_back_to_rp($key, $login);
        }

        // Validate the reset key
        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            self::store_message('reset_error', $user->get_error_message());
            self::redirect_back_preserving_redirect();
        }

        // Set the new password
        reset_password($user, $pass1);
        self::store_message('reset_success', __('Your password has been reset. You can now log in.', 'tpw-core'));

        // After successful reset: send the user to the front-end login page, preserving redirect_to.
        $member_login_url = '';
        if ( class_exists('TPW_Core_System_Pages') && method_exists('TPW_Core_System_Pages', 'get_permalink') ) {
            $ml = TPW_Core_System_Pages::get_permalink( 'member-login' );
            if ( is_string($ml) && $ml !== '' ) $member_login_url = $ml;
        }
        if ( $member_login_url === '' ) {
            $member_login_url = site_url( '/member-login/' );
        }

        $requested = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';
        if ( is_string( $requested ) ) {
            $requested = trim( (string) wp_unslash( $requested ) );
        } else {
            $requested = '';
        }
        if ( $requested !== '' ) {
            $member_login_url = add_query_arg( 'redirect_to', $requested, $member_login_url );
        }
        wp_safe_redirect( esc_url_raw( $member_login_url ) );
        exit;
    }

    /**
     * Adjust the password reset email so links point to the front-end /member-login/ page,
     * preserving the action=rp, key, login, and any redirect_to provided at request-time.
     */
    public static function filter_reset_email_message( $message, $key, $user_login, $user_data ) {
        // Determine the front-end member login URL
        $member_login_url = '';
        if ( class_exists('TPW_Core_System_Pages') && method_exists('TPW_Core_System_Pages', 'get_permalink') ) {
            $ml = TPW_Core_System_Pages::get_permalink( 'member-login' );
            if ( is_string($ml) && $ml !== '' ) $member_login_url = $ml;
        }
        if ( $member_login_url === '' ) {
            $member_login_url = site_url( '/member-login/' );
        }

        // Preserve redirect_to if provided during the request that triggered this email
        $redirect_to = isset($_REQUEST['redirect_to']) ? (string) wp_unslash( $_REQUEST['redirect_to'] ) : '';
        $redirect_to = is_string($redirect_to) ? trim($redirect_to) : '';

        // Build our replacement link
        $new_url = add_query_arg( [
            'action' => 'rp',
            'key'    => (string) $key,
            'login'  => (string) $user_login,
        ], $member_login_url );
        if ( $redirect_to !== '' ) {
            $new_url = add_query_arg( 'redirect_to', $redirect_to, $new_url );
        }

        // Try to detect and replace the original wp-login.php reset URL if present
        $replaced = false;
        if ( is_string( $message ) && $message !== '' ) {
            if ( preg_match_all( '#https?://[^\s\"\']+/wp-login\.php\?[^\s\"\']*#i', $message, $m ) ) {
                foreach ( $m[0] as $found ) {
                    // Only replace links that include action=rp
                    if ( false !== stripos( $found, 'action=rp' ) ) {
                        // Parse to ensure we preserve key/login if needed (fallback)
                        $parts = wp_parse_url( $found );
                        if ( isset($parts['query']) ) {
                            parse_str( $parts['query'], $qs );
                            if ( empty($qs['key']) ) { /* keep $key */ }
                            if ( empty($qs['login']) ) { /* keep $user_login */ }
                        }
                        $message = str_replace( $found, $new_url, $message );
                        $replaced = true;
                        break;
                    }
                }
            }
        }

        if ( ! $replaced ) {
            // If we didn't find the default link, append our link clearly so users get the correct destination
            $message .= "\n\n" . sprintf( __( 'Reset your password: %s', 'tpw-core' ), $new_url );
        }
        return $message;
    }

    /**
     * Redirect back to the current page using PRG pattern, preserving redirect_to param if available.
     */
    private static function redirect_back_preserving_redirect() {
        $target = function_exists('get_permalink') ? get_permalink() : home_url('/');
        $requested = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';
        if ( is_string( $requested ) ) {
            $requested = trim( (string) wp_unslash( $requested ) );
        } else {
            $requested = '';
        }
        if ( $requested !== '' ) {
            $target = add_query_arg( 'redirect_to', $requested, $target );
        }
        wp_safe_redirect( $target );
        exit;
    }

    /**
     * Redirect back to the rp (set new password) state, preserving key/login and redirect_to.
     */
    private static function redirect_back_to_rp( $key, $login ) {
        $target = function_exists('get_permalink') ? get_permalink() : home_url('/');
        $args = [ 'action' => 'rp' ];
        if ( is_string($key) && $key !== '' )  { $args['key'] = $key; }
        if ( is_string($login) && $login !== '' ) { $args['login'] = $login; }
        $requested = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';
        if ( is_string( $requested ) ) {
            $requested = trim( (string) wp_unslash( $requested ) );
        } else {
            $requested = '';
        }
        if ( $requested !== '' ) {
            $args['redirect_to'] = $requested;
        }
        $target = add_query_arg( $args, $target );
        wp_safe_redirect( $target );
        exit;
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
            // Canonical action URL for forms (avoid posting to a query-string URL like ?redirect_to=...)
            $action_url = function_exists('get_permalink') ? get_permalink() : home_url('/');
            $data = [
                'messages' => $messages,
                'redirect' => $redirect,
                'action_url' => $action_url,
            ];
            include $template;
        } else {
            echo esc_html__('Login form template missing.', 'tpw-core');
        }
        return ob_get_clean();
    }
}

TPW_Member_Login_Shortcode::init();
