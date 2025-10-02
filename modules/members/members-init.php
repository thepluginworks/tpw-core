<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Only enable logout redirect when the Members module is active.
$tpw_members_active = false;
if ( defined( 'TPW_MEMBERS_ACTIVE' ) && TPW_MEMBERS_ACTIVE ) {
    $tpw_members_active = true;
} elseif ( function_exists( 'tpw_members_module_enabled' ) && true === tpw_members_module_enabled() ) {
    $tpw_members_active = true;
} else {
    // Fallback: detect by table presence
    global $wpdb;
    $table = $wpdb->prefix . 'tpw_members';
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( ! empty( $exists ) ) {
        $tpw_members_active = true;
    }
}

if ( $tpw_members_active ) {
    // Enqueue shared TPW button styles for all public-facing Members screens
    add_action( 'wp_enqueue_scripts', function() {
        global $post;
        $has_members_ui = false;
        if ( $post && isset( $post->post_content ) ) {
            $content = (string) $post->post_content;
            // Core Members shortcodes and virtual page content
            if ( has_shortcode( $content, 'tpw_manage_members' ) || has_shortcode( $content, 'tpw_member_profile' ) || has_shortcode( $content, 'tpw_member_login' ) ) {
                $has_members_ui = true;
            }
        }
        // Also cover the virtual My Profile route injected by Core
        if ( get_query_var( 'tpw_my_profile' ) ) {
            $has_members_ui = true;
        }
        if ( ! $has_members_ui ) {
            return;
        }
        // Enqueue the shared buttons CSS from TPW Core
        $css_file = TPW_CORE_PATH . 'assets/css/tpw-buttons.css';
        wp_enqueue_style(
            'tpw-buttons',
            TPW_CORE_URL . 'assets/css/tpw-buttons.css',
            [],
            file_exists( $css_file ) ? filemtime( $css_file ) : null
        );
    }, 100 );

    add_action( 'wp_logout', function() {
        wp_safe_redirect( home_url() );
        exit;
    } );

    // Hide WP admin bar for regular members (non-admins)
    add_filter( 'show_admin_bar', function( $show ) {
        if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return $show;
    }, 1000 );

    // Shortcode: [tpw_logout_link] -> outputs a logout link redirecting to homepage
    add_shortcode( 'tpw_logout_link', function( $atts = [], $content = null ) {
        $url = wp_logout_url( home_url() );
        $label = $content ? wp_kses_post( $content ) : esc_html__( 'Logout', 'tpw-core' );
        return '<a class="tpw-logout-link" href="' . esc_url( $url ) . '">' . $label . '</a>';
    } );

    // For block themes using the Navigation block, append a logout item dynamically
    add_filter( 'render_block', function( $block_content, $block ) {
        if ( empty( $block['blockName'] ) || 'core/navigation' !== $block['blockName'] ) {
            return $block_content;
        }
        if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
            return $block_content;
        }
        $logout_url = esc_url( wp_logout_url( home_url() ) );
        $li = '<li class="wp-block-navigation-item menu-item-tpw-logout"><a href="' . $logout_url . '">' . esc_html__( 'Logout', 'tpw-core' ) . '</a></li>';
        // Insert before the first closing </ul> in the navigation container
        $updated = preg_replace( '/(<\/ul>)/i', $li . '$1', $block_content, 1 );
        return $updated ? $updated : $block_content;
    }, 10, 2 );
}
