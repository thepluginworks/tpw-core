<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Only render for logged-in members (defensive; upstream already enforces)
if ( ! is_user_logged_in() ) {
    return;
}

// Enqueue Core UI styles (scoped admin UI + buttons)
if ( defined( 'TPW_CORE_URL' ) && defined( 'TPW_CORE_PATH' ) ) {
    $admin_ui_css = TPW_CORE_PATH . 'assets/css/tpw-admin-ui.css';
    $buttons_css  = TPW_CORE_PATH . 'assets/css/tpw-buttons.css';
    wp_enqueue_style( 'tpw-admin-ui', TPW_CORE_URL . 'assets/css/tpw-admin-ui.css', [], file_exists($admin_ui_css) ? filemtime($admin_ui_css) : null );
    wp_enqueue_style( 'tpw-buttons',  TPW_CORE_URL . 'assets/css/tpw-buttons.css',  [], file_exists($buttons_css)  ? filemtime($buttons_css)  : null );
}

// Wrapper must follow Payments UI doc conventions
$ui_style = function_exists('tpw_core_build_ui_theme_style_attr') ? tpw_core_build_ui_theme_style_attr() : '';
echo '<div class="tpw-admin-ui tpw-admin-wrapper" style="' . esc_attr( $ui_style ) . '">';

// Expect variables from the caller: $sources (array), $active_type (string), $active_source (array|null)
$sources = isset($sources) && is_array($sources) ? $sources : [];
$active_type = isset($active_type) ? (string) $active_type : '';
$active_source = isset($active_source) ? $active_source : null;

if ( empty( $sources ) ) {
    echo '<div class="tpw-card">';
    echo '  <h2>' . esc_html__( 'My Payments', 'tpw-core' ) . '</h2>';
    echo '  <p>' . esc_html__( 'No payment modules are active.', 'tpw-core' ) . '</p>';
    echo '</div>';
    echo '</div>';
    return;
}

$single = ( count( $sources ) === 1 );

echo '<div class="tpw-layout">';
if ( ! $single ) {
    // Sidebar
    include __DIR__ . '/partials/sidebar.php';
}

// Content area
echo '<main class="tpw-content" role="region" aria-label="Payments content">';
echo '  <div class="tpw-card" style="margin-bottom:12px;">';
echo '    <h2>' . esc_html__( 'My Payments', 'tpw-core' ) . '</h2>';
echo '  </div>';

if ( $active_type && is_array( $active_source ) ) {
    $cb = $active_source['callback'] ?? null;
    if ( $cb && is_callable( $cb ) ) {
        // Allow callbacks to echo their own layout; keep within content area per spec
        call_user_func( $cb );
    } else {
        echo '<div class="tpw-card"><p>' . esc_html__( 'The selected payments view is not available.', 'tpw-core' ) . '</p></div>';
    }
} else {
    // Fallback: choose the first available source
    $first = reset( $sources );
    if ( $first && isset( $first['callback'] ) && is_callable( $first['callback'] ) ) {
        call_user_func( $first['callback'] );
    }
}

echo '</main>';
echo '</div>'; // .tpw-layout

echo '</div>'; // .tpw-admin-ui .tpw-admin-wrapper
