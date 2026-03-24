<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'tpw_members_render_settings_tabs' ) ) {
	/**
	 * Render the unified Members settings tabs row.
	 *
	 * @param string|null $active Optional active key; if omitted, derives from URL.
	 */
	function tpw_members_render_settings_tabs( $active = null ) {
		$base = get_permalink();
		$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'settings';
		$tab    = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
		if ( $active === null ) {
			if ( $action === 'field_settings' ) $active = 'field_settings';
			elseif ( $action === 'member-field-visibility' ) $active = 'visibility';
			elseif ( $action === 'settings' ) $active = $tab; else $active = 'general';
		}
		$mk = function($label, $key, $args){
			$url = esc_url( add_query_arg( $args, $GLOBALS['base_url_for_tpw_tabs'] ?? get_permalink() ) );
			$cls = 'tpw-tab' . ( $key === $GLOBALS['active_tab_for_tpw_tabs'] ? ' active' : '' );
			return '<a href="'.$url.'" class="'.$cls.'">'.esc_html($label).'</a>';
		};

		// Build URLs
		$base_url = esc_url( add_query_arg([], $base) );
		$GLOBALS['base_url_for_tpw_tabs'] = $base_url;
		$GLOBALS['active_tab_for_tpw_tabs'] = $active;

	echo '<h3 class="tpw-tabs">';
	echo $mk( __( 'General', 'tpw-core' ), 'general', [ 'action' => 'settings', 'tab' => 'general' ] );
	echo $mk( __( 'Sign-Ups', 'tpw-core' ), 'signups', [ 'action' => 'settings', 'tab' => 'signups' ] );
	echo $mk( __( 'Field Settings', 'tpw-core' ), 'field_settings', [ 'action' => 'field_settings' ] );
	echo $mk( __( 'Directory Field Visibility', 'tpw-core' ), 'visibility', [ 'action' => 'member-field-visibility' ] );
	echo $mk( __( 'Member Profile', 'tpw-core' ), 'profile', [ 'action' => 'settings', 'tab' => 'profile' ] );
	echo $mk( __( 'Address Lookup', 'tpw-core' ), 'postcodes', [ 'action' => 'settings', 'tab' => 'postcodes' ] );
	echo $mk( __( 'Help', 'tpw-core' ), 'help', [ 'action' => 'settings', 'tab' => 'help' ] );
	echo '</h3>';
	}
}
