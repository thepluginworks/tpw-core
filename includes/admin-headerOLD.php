<?php
/**
 * Admin Header
 *
 * @package TPW Core
 */

defined('ABSPATH') || exit;

function tpw_admin_output_headerS( $title, $notice_message = '' ) {
    echo '<div class="wrap tpw-fe-header here">';
    echo '  <div class="tpw-fe-header-title">';
    echo '    <h1>' . esc_html( $title ) . '</h1>';
    if ( ! empty( $notice_message ) ) {
        echo '    <div class="tpw-fe-notice"><p>' . esc_html( $notice_message ) . '</p></div>';
    }
    echo '  </div>';
    echo '  <div class="tpw-fe-logo">';
    echo '<img src="' . esc_url( plugin_dir_url( dirname( __FILE__ ) ) . 'assets/images/thepluginworks-logo-300.png' ) . '" alt="The Plugin Works Logo" />';
    echo '  </div>';
    echo '</div>';
}