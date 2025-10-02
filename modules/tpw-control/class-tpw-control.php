<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TPW Control - front-end admin hub
 *
 * Responsibilities:
 * - Registers [tpw-control] shortcode
 * - Owns the sections registry (filterable) so other plugins can hook in
 * - Bootstraps router + shared UI
 * - Enqueues front-end assets
 */
class TPW_Control {
    const SHORTCODE = 'tpw-control';
    const ACTION_QUERY_VAR = 'action'; // used as /tpw-control/?action=

    public static function init() {
        add_shortcode( self::SHORTCODE, [ __CLASS__, 'render_shortcode' ] );

        // Allow external plugins to register sections early
        do_action( 'tpw_control/register_sections' );

        // Enqueue assets when shortcode is present
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue_assets' ] );

        // Ensure Upload Pages tables exist early (once per request)
        add_action( 'init', function(){
            if ( class_exists( 'TPW_Control_Upload_Pages' ) ) {
                TPW_Control_Upload_Pages::ensure_tables();
            } else {
                // Lazy-load the class if not loaded yet
                $path = __DIR__ . '/class-tpw-control-upload-pages.php';
                if ( file_exists( $path ) ) {
                    require_once $path;
                    if ( class_exists( 'TPW_Control_Upload_Pages' ) ) {
                        TPW_Control_Upload_Pages::ensure_tables();
                    }
                }
            }
        }, 9 );

        // Register custom query vars used by TPW Control
        add_filter( 'query_vars', function( $vars ){
            $vars[] = self::ACTION_QUERY_VAR; // action
            $vars[] = 'sub';
            $vars[] = 'upload_page_id';
            $vars[] = 'pg'; // pagination for lists
            return $vars;
        } );

        // Process POST actions early so redirects work (e.g., Create Page -> redirect to Edit)
        add_action( 'template_redirect', function(){
            // Only handle our POST when on a TPW Control page (shortcode present) or explicitly targeting our section
            if ( empty($_POST['tpw_control_upload_pages_action']) ) return;
            $on_control_page = false;
            if ( is_singular() ) {
                global $post;
                if ( $post && has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
                    $on_control_page = true;
                }
            }
            $action = get_query_var( self::ACTION_QUERY_VAR );
            if ( $action === 'upload-pages' ) $on_control_page = true;
            if ( ! $on_control_page ) return;
            // Ensure UI class is available for permission checks
            if ( ! class_exists( 'TPW_Control_UI' ) ) {
                $ui = __DIR__ . '/class-tpw-control-ui.php';
                if ( file_exists( $ui ) ) require_once $ui;
            }
            // Lazy load Upload Pages class if needed
            if ( ! class_exists( 'TPW_Control_Upload_Pages' ) ) {
                $path = __DIR__ . '/class-tpw-control-upload-pages.php';
                if ( file_exists( $path ) ) require_once $path;
            }
            if ( class_exists( 'TPW_Control_Upload_Pages' ) ) {
                // Avoid double-processing by short-circuiting after redirect in handler
                TPW_Control_Upload_Pages::handle_post();
            }
        }, 0 );
    }

    /**
     * Global manage gate for TPW Control.
     * Default: admins only. Extendable via 'tpw_control_can_manage' filter.
     */
    public static function can_manage() {
        // Default to admin-only, allow plugins/themes to relax or tighten
        $allowed = class_exists('TPW_Control_UI') ? TPW_Control_UI::is_admin() : current_user_can('manage_options');
        return (bool) apply_filters( 'tpw_control_can_manage', $allowed );
    }

    public static function maybe_enqueue_assets() {
        // Best-effort detection: enqueue on pages containing the shortcode or on /tpw-control/ virtual pages
        if ( is_singular() ) {
            global $post;
            if ( $post && has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
                self::enqueue_assets();
                return;
            }
        }
        // Also allow forcing via filter (e.g. for virtual routes)
        if ( apply_filters( 'tpw_control/force_enqueue', false ) ) {
            self::enqueue_assets();
        }
    }

    protected static function enqueue_assets() {
        $base = plugin_dir_url( dirname( __FILE__, 2 ) . '/..' );
        // Safer base using TPW_CORE_URL
        if ( defined('TPW_CORE_URL') ) {
            $base = trailingslashit( TPW_CORE_URL ) . 'modules/tpw-control/';
        }

        // Ensure WP media modal is available (required for Add Media button in editors)
        if ( function_exists( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }

        // Ensure core TPW UI and Buttons CSS are available for consistent styling
        if ( defined('TPW_CORE_URL') && defined('TPW_CORE_PATH') ) {
            $btn_file = TPW_CORE_PATH . 'assets/css/tpw-buttons.css';
            $btn_url  = TPW_CORE_URL . 'assets/css/tpw-buttons.css';
            $ui_file  = TPW_CORE_PATH . 'assets/css/tpw-ui.css';
            $ui_url   = TPW_CORE_URL . 'assets/css/tpw-ui.css';
            $btn_ver  = file_exists( $btn_file ) ? filemtime( $btn_file ) : '1.0.0';
            $ui_ver   = file_exists( $ui_file ) ? filemtime( $ui_file ) : '1.0.0';
            wp_enqueue_style( 'tpw-buttons', $btn_url, [], $btn_ver );
            wp_enqueue_style( 'tpw-ui', $ui_url, [], $ui_ver );
        }

        // Module-specific assets
        wp_enqueue_style( 'tpw-control', $base . 'assets/css/tpw-control.css', [], '1.0.0' );
        // jQuery UI Sortable for drag-and-drop file sorting
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script( 'tpw-control', $base . 'assets/js/tpw-control.js', [ 'jquery','jquery-ui-sortable' ], '1.0.0', true );
        wp_localize_script( 'tpw-control', 'TPW_CONTROL', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'tpw_control' ),
        ] );
    }

    /**
     * Sections registry. Array of keyed sections, each item shape:
     * - key: string unique key
     * - label: string menu label
     * - capability: callable|bool|string Caps check or false for visible-to-members, true for anyone logged in
     * - callback: callable Render function for content area
     * - position: int Sort order in sidebar
     * - icon: optional string icon css class or url
     */
    public static function get_sections() {
        $sections = [
            'dashboard' => [
                'key'        => 'dashboard',
                'label'      => __( 'Dashboard', 'tpw-core' ),
                // Default visibility: members (per allowed statuses)
                'visibility' => [ 'logged_in' => true ],
                // Back-compat marker
                'capability' => '__tpw_control_is_member__',
                'callback'   => [ 'TPW_Control_Router', 'render_dashboard' ],
                'position'   => 0,
                'icon'       => 'dashicons-admin-home',
            ],
            'upload-pages' => [
                'key'        => 'upload-pages',
                'label'      => __( 'Upload Pages', 'tpw-core' ),
                'visibility' => [ 'logged_in' => true, 'flags_any' => ['is_committee', 'is_admin'] ],
                'capability' => '__tpw_control_is_committee_or_admin__',
                'callback'   => [ 'TPW_Control_Upload_Pages', 'render' ],
                'position'   => 10,
                'icon'       => 'dashicons-media-document',
            ],
            'menu-manager' => [
                'key'        => 'menu-manager',
                'label'      => __( 'Menu Manager', 'tpw-core' ),
                'visibility' => [ 'logged_in' => true, 'flags_any' => ['is_admin'] ],
                'capability' => '__tpw_control_is_admin__',
                'callback'   => [ 'TPW_Control_Router', 'render_menu_manager' ],
                'position'   => 20,
                'icon'       => 'dashicons-menu',
            ],
        ];
        /**
         * Filters to allow external plugins to add or modify sections.
         * Prefer using 'tpw_control_register_sections'. The legacy 'tpw_control/sections'
         * filter remains for backward compatibility.
         * Each added section should follow the documented shape above.
         */
        $sections = apply_filters( 'tpw_control_register_sections', $sections );
        $sections = apply_filters( 'tpw_control/sections', $sections );

        // Normalize and sort
        $sections = array_filter( $sections, function( $s ) {
            return is_array( $s ) && ! empty( $s['key'] ) && ! empty( $s['label'] ) && ! empty( $s['callback'] );
        } );
        uasort( $sections, function( $a, $b ){
            $pa = isset($a['position']) ? (int)$a['position'] : 9999;
            $pb = isset($b['position']) ? (int)$b['position'] : 9999;
            return $pa <=> $pb;
        } );
        return $sections;
    }

    public static function render_shortcode( $atts = [] ) {
        require_once __DIR__ . '/class-tpw-control-ui.php';
        require_once __DIR__ . '/class-tpw-control-router.php';
        require_once __DIR__ . '/class-tpw-control-upload-pages.php';

        ob_start();
        TPW_Control_Router::render_layout();
        return ob_get_clean();
    }
}
