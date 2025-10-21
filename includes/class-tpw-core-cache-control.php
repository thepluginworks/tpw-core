<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TPW Core Cache Control
 * Ensures member/admin pages and secure endpoints are never edge-cached; allows public content to be cached safely.
 */
class TPW_Core_Cache_Control {
    /**
     * Bootstrap: hook early to send_headers via the global function wrapper.
     */
    public static function init() {
        // Use named function to satisfy external integrations and explicit requirement.
        add_action( 'send_headers', 'tpw_core_send_cache_headers', 0 );
    }

    /**
     * Main header sender invoked by the global wrapper.
     */
    public static function send_headers() {
        // Don't interfere with normal wp-admin screens (WordPress handles cache there),
        // but do handle admin-ajax and admin-post explicitly below.
        if ( is_admin() && ! self::is_admin_endpoint_request() ) {
            return;
        }

        // Always treat AJAX and admin-post requests as private/no-cache
        if ( self::is_admin_endpoint_request() || self::is_secure_request() ) {
            self::send_private_headers();
            return;
        }

        // Logged-in users or pages containing sensitive shortcodes should bypass cache
        if ( is_user_logged_in() || self::has_logged_in_cookies() || self::is_private_page() ) {
            self::send_private_headers();
            return;
        }

        // Default: allow public content to be cached (30 days)
        self::send_public_headers();
    }

    /**
     * Detect pages and shortcodes that must never be cached.
     */
    private static function is_private_page() {
        // Known system pages by slug
        if ( function_exists( 'is_page' ) && is_page( array( 'my-profile', 'member-login', 'tpw-control' ) ) ) {
            return true;
        }

        // Virtual/rewritten routes (e.g., /my-profile/) or system pages by query var
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
        if ( $uri !== '' ) {
            // Normalize path portion only
            $path = parse_url( $uri, PHP_URL_PATH );
            if ( is_string( $path ) ) {
                $path = rtrim( strtolower( $path ), '/' ) . '/';
                if ( $path === '/my-profile/' || $path === '/member-login/' || $path === '/tpw-control/' ) {
                    return true;
                }
            }
        }

        // Detect sensitive shortcodes in the current post content
        global $post;
        if ( $post instanceof WP_Post ) {
            $content = (string) $post->post_content;
            // Use string check first to avoid loading shortcode regex unnecessarily
            if (
                ( strpos( $content, '[tpw_member_profile' ) !== false && has_shortcode( $content, 'tpw_member_profile' ) ) ||
                ( strpos( $content, '[tpw-control' ) !== false && has_shortcode( $content, 'tpw-control' ) ) ||
                ( strpos( $content, '[tpw_manage_members' ) !== false && has_shortcode( $content, 'tpw_manage_members' ) ) ||
                ( strpos( $content, '[tpw_upload_page' ) !== false && has_shortcode( $content, 'tpw_upload_page' ) )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect secure endpoints that should never be cached regardless of auth state.
     */
    private static function is_secure_request() {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ( $uri === '' ) {
            return false;
        }
        // Secure file serving endpoint in TPW Control module
        if ( strpos( $uri, '/serve.php' ) !== false ) {
            return true;
        }
        return false;
    }

    /**
     * Admin AJAX and admin-post endpoints should always be private/no-cache.
     */
    private static function is_admin_endpoint_request() {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ( $uri === '' ) {
            return false;
        }
        if ( strpos( $uri, '/wp-admin/admin-ajax.php' ) !== false || strpos( $uri, '/wp-admin/admin-post.php' ) !== false ) {
            return true;
        }
        // Also detect by script filename as a fallback
        $script = isset( $_SERVER['SCRIPT_NAME'] ) ? (string) $_SERVER['SCRIPT_NAME'] : '';
        if ( $script !== '' && ( substr( $script, -14 ) === 'admin-ajax.php' || substr( $script, -14 ) === 'admin-post.php' ) ) {
            return true;
        }
        return false;
    }

    /**
     * Detect logged-in state purely from cookies as an extra safety net.
     */
    private static function has_logged_in_cookies() {
        if ( empty( $_COOKIE ) || ! is_array( $_COOKIE ) ) {
            return false;
        }
        foreach ( array_keys( $_COOKIE ) as $key ) {
            if (
                strpos( $key, 'wordpress_logged_in_' ) === 0 ||
                strpos( $key, 'wordpress_sec_' ) === 0 ||
                strpos( $key, 'wp-settings-' ) === 0 ||
                strpos( $key, 'wp-settings-time-' ) === 0
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Send strict private/no-cache headers.
     */
    private static function send_private_headers() {
        // Replace any existing Cache-Control header values
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
        header( 'Pragma: no-cache', true );
    }

    /**
     * Send permissive public cache headers for static content (30 days).
     */
    private static function send_public_headers() {
        header( 'Cache-Control: public, max-age=2592000', true );
    }
}

/**
 * Global wrapper as requested: runs on every request via the send_headers hook.
 */
function tpw_core_send_cache_headers() {
    TPW_Core_Cache_Control::send_headers();
}

// Initialize immediately so we're hooked as early as possible
TPW_Core_Cache_Control::init();
