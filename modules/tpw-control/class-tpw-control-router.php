<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class TPW_Control_Router {
    public static function get_current_action() {
        $action = isset($_GET[ TPW_Control::ACTION_QUERY_VAR ]) ? sanitize_key( $_GET[ TPW_Control::ACTION_QUERY_VAR ] ) : '';
        if ( '' === $action ) return 'dashboard';
        return $action;
    }

    public static function current_section() {
        $sections = TPW_Control::get_sections();
        $key = self::get_current_action();
        return isset( $sections[ $key ] ) ? $sections[ $key ] : ( $sections['dashboard'] ?? null );
    }

    public static function render_layout() {
        // Global manage gate: restrict hub to admins by default (filterable)
        if ( ! TPW_Control::can_manage() ) {
            self::render_not_allowed( __( 'You do not have permission to access TPW Control.', 'tpw-core' ) );
            return;
        }
        $section = self::current_section();
        // Permission gate
        if ( ! TPW_Control_UI::section_is_visible( $section ) ) {
            self::render_not_allowed( __( 'You do not have permission to view this section.', 'tpw-core' ) );
            return;
        }
        $template = __DIR__ . '/templates/layout.php';
        if ( file_exists( $template ) ) {
            // Expose to template:
            $sections = TPW_Control::get_sections();
            $current  = $section['key'];
            include $template;
        } else {
            echo '<div class="tpw-control">';
            self::render_content_only();
            echo '</div>';
        }
    }

    public static function render_content_only() {
        $section = self::current_section();
        if ( is_callable( $section['callback'] ) ) {
            call_user_func( $section['callback'] );
            return;
        }
        // Allow external sections to render via action: tpw_control_render_section_{slug}
        $slug = isset( $section['key'] ) ? sanitize_key( $section['key'] ) : '';
        if ( $slug ) {
            $hook = "tpw_control_render_section_{$slug}";
            /**
             * Action to render a dynamic/externally-provided section.
             * Handlers should echo output directly.
             */
            do_action( $hook, $section );
            return;
        }
        self::render_dashboard();
    }

    public static function render_dashboard() {
        $file = __DIR__ . '/templates/dashboard.php';
        if ( file_exists( $file ) ) include $file;
        else echo '<h2>' . esc_html__( 'TPW Control', 'tpw-core' ) . '</h2>';
    }

    public static function render_menu_manager() {
        $file = __DIR__ . '/templates/sections/menu-manager.php';
        if ( file_exists( $file ) ) include $file;
        else echo '<p>' . esc_html__( 'Menu Manager coming soon…', 'tpw-core' ) . '</p>';
    }

    protected static function render_not_allowed( $message = '' ) {
        echo '<div class="tpw-control tpw-control--denied">';
        $msg = $message !== '' ? $message : __( 'You do not have permission to view this page.', 'tpw-core' );
        echo '<p>' . esc_html( $msg ) . '</p>';
        echo '</div>';
    }
}
