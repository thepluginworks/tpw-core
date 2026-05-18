<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_FlexiClub_Admin_Menu {
	const TOP_LEVEL_SLUG      = 'tpw-flexiclub-dashboard';
	const DASHBOARD_SETUP_META = 'tpw_flexiclub_dashboard_setup_dismissed';
	const PAGE_MEMBERS        = 'tpw-flexiclub-manage-members';
	const PAGE_GALLERY        = 'tpw-flexiclub-gallery-admin';
	const PAGE_UPLOADS        = 'tpw-flexiclub-upload-pages';
	const PAGE_MENU_MANAGER   = 'tpw-flexiclub-menu-manager';
	const PAGE_LOGS           = 'tpw-flexiclub-logs';
	const PAGE_SETTINGS       = 'tpw-flexiclub-settings';
	const SETTINGS_ROUTE      = 'options-general.php?page=tpw-core-settings';
	const SYSTEM_PAGES_ROUTE  = 'options-general.php?page=tpw-core-settings&tab=system-pages';
	const PAYMENTS_ROUTE      = 'options-general.php?page=tpw-core-settings&tab=payment-methods';
	const EMAIL_LOGS_ROUTE    = 'options-general.php?page=tpw-core-settings&tab=email-logs';
	const PAYMENT_LOGS_ROUTE  = 'tools.php?page=tpw-payment-logs';
	const NOTICEBOARD_ROUTE   = 'edit.php?post_type=tpw_notice';

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 12 );
		add_action( 'admin_init', [ __CLASS__, 'handle_dashboard_actions' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_bridge_actions' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_dashboard_assets' ] );
		add_filter( 'tpw_core_menu_map', [ __CLASS__, 'filter_menu_map' ] );
	}

	public static function register_menu() {
		$visible_items      = self::get_visible_items();
		$dashboard_visible  = self::current_user_can_view_dashboard();

		if ( empty( $visible_items ) && ! $dashboard_visible ) {
			return;
		}

		add_menu_page(
			__( 'FlexiClub', 'tpw-core' ),
			__( 'FlexiClub', 'tpw-core' ),
			'read',
			self::TOP_LEVEL_SLUG,
			[ __CLASS__, 'render_dashboard' ],
			'dashicons-groups',
			58.2
		);

		if ( $dashboard_visible ) {
			add_submenu_page(
				self::TOP_LEVEL_SLUG,
				__( 'Dashboard', 'tpw-core' ),
				__( 'Dashboard', 'tpw-core' ),
				'manage_options',
				self::TOP_LEVEL_SLUG,
				[ __CLASS__, 'render_dashboard' ]
			);
		}

		if ( in_array( self::PAGE_MEMBERS, $visible_items, true ) ) {
			add_submenu_page(
				self::TOP_LEVEL_SLUG,
				__( 'Manage Members', 'tpw-core' ),
				__( 'Manage Members', 'tpw-core' ),
				'read',
				self::PAGE_MEMBERS,
				[ __CLASS__, 'render_bridge_page' ]
			);
		}

		if ( in_array( self::NOTICEBOARD_ROUTE, $visible_items, true ) ) {
			add_submenu_page(
				self::TOP_LEVEL_SLUG,
				__( 'Noticeboard', 'tpw-core' ),
				__( 'Noticeboard', 'tpw-core' ),
				'edit_posts',
				self::NOTICEBOARD_ROUTE
			);
		}

		if ( in_array( self::PAGE_GALLERY, $visible_items, true ) ) {
			add_submenu_page(
				self::TOP_LEVEL_SLUG,
				__( 'Gallery Admin', 'tpw-core' ),
				__( 'Gallery Admin', 'tpw-core' ),
				'read',
				self::PAGE_GALLERY,
				[ __CLASS__, 'render_bridge_page' ]
			);
		}

		if ( in_array( self::PAGE_UPLOADS, $visible_items, true ) ) {
			add_submenu_page(
				self::TOP_LEVEL_SLUG,
				__( 'Upload Pages / Archive', 'tpw-core' ),
				__( 'Upload Pages / Archive', 'tpw-core' ),
				'read',
				self::PAGE_UPLOADS,
				[ __CLASS__, 'render_bridge_page' ]
			);
		}

		if ( in_array( self::PAGE_MENU_MANAGER, $visible_items, true ) ) {
			add_submenu_page(
				self::TOP_LEVEL_SLUG,
				__( 'Menu Permissions', 'tpw-core' ),
				__( 'Menu Permissions', 'tpw-core' ),
				'read',
				self::PAGE_MENU_MANAGER,
				[ __CLASS__, 'render_bridge_page' ]
			);
		}

		if ( in_array( self::SYSTEM_PAGES_ROUTE, $visible_items, true ) ) {
			add_submenu_page(
				self::TOP_LEVEL_SLUG,
				__( 'System Pages', 'tpw-core' ),
				__( 'System Pages', 'tpw-core' ),
				'manage_options',
				self::SYSTEM_PAGES_ROUTE
			);
		}

		if ( in_array( self::PAYMENTS_ROUTE, $visible_items, true ) ) {
			add_submenu_page(
				self::TOP_LEVEL_SLUG,
				__( 'Payments', 'tpw-core' ),
				__( 'Payments', 'tpw-core' ),
				'manage_options',
				self::PAYMENTS_ROUTE
			);
		}

		if ( in_array( self::SETTINGS_ROUTE, $visible_items, true ) ) {
			add_submenu_page(
				self::TOP_LEVEL_SLUG,
				__( 'Settings', 'tpw-core' ),
				__( 'Settings', 'tpw-core' ),
				'manage_options',
				self::PAGE_SETTINGS,
				'tpw_core_render_settings_page'
			);
		}

		if ( in_array( self::PAGE_LOGS, $visible_items, true ) ) {
			add_submenu_page(
				self::TOP_LEVEL_SLUG,
				__( 'Logs', 'tpw-core' ),
				__( 'Logs', 'tpw-core' ),
				'manage_options',
				self::PAGE_LOGS,
				[ __CLASS__, 'render_logs_page' ]
			);
		}
	}

	public static function render_dashboard() {
		if ( ! self::current_user_can_view_dashboard() ) {
			self::redirect_dashboard_request();
			return;
		}

		$dashboard = self::get_dashboard_view_model();
		$template  = defined( 'TPW_CORE_PATH' ) ? TPW_CORE_PATH . 'templates/admin/flexiclub-dashboard.php' : '';

		echo '<div class="tpw-admin-ui tpw-flexiclub-dashboard" style="' . esc_attr( function_exists( 'tpw_core_build_ui_theme_style_attr' ) ? tpw_core_build_ui_theme_style_attr() : '' ) . '">';
		echo '<div class="wrap">';

		if ( $template && file_exists( $template ) ) {
			include $template;
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'FlexiClub Dashboard template is missing.', 'tpw-core' ) . '</p></div>';
		}

		echo '</div>';
		echo '</div>';
	}

	public static function render_bridge_page() {
		$config = self::get_current_bridge_config();
		if ( empty( $config ) ) {
			wp_die( esc_html__( 'Unknown FlexiClub bridge page.', 'tpw-core' ) );
		}

		if ( ! self::current_user_can_bridge( $config ) ) {
			self::render_page_start(
				$config['title'],
				esc_html__( 'You do not have permission to access this FlexiClub bridge page.', 'tpw-core' )
			);
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Access denied.', 'tpw-core' ) . '</p></div>';
			self::render_page_end();
			return;
		}

		$status = self::build_bridge_status( $config );

		if ( ! self::bridge_diagnostics_requested() && empty( $status['diagnostics_required'] ) && ! empty( $status['open_url'] ) ) {
			wp_safe_redirect( $status['open_url'] );
			exit;
		}

		self::render_page_start( $config['title'], $config['description'] );
		self::render_bridge_notice();

		echo '<div class="tpw-card">';
		echo '<table class="widefat striped">';
		echo '<tbody>';
		echo '<tr><th>' . esc_html__( 'Screen type', 'tpw-core' ) . '</th><td>' . esc_html__( 'Bridge / launcher', 'tpw-core' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Front-end page', 'tpw-core' ) . '</th><td>' . esc_html( $status['page_text'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Expected shortcode', 'tpw-core' ) . '</th><td><code>' . esc_html( $status['shortcode'] ) . '</code></td></tr>';
		echo '<tr><th>' . esc_html__( 'Shortcode status', 'tpw-core' ) . '</th><td>' . esc_html( $status['shortcode_text'] ) . '</td></tr>';
		if ( isset( $status['route_text'] ) && $status['route_text'] !== '' ) {
			echo '<tr><th>' . esc_html__( 'Target route', 'tpw-core' ) . '</th><td>' . esc_html( $status['route_text'] ) . '</td></tr>';
		}
		if ( isset( $status['section_text'] ) && $status['section_text'] !== '' ) {
			echo '<tr><th>' . esc_html__( 'Section status', 'tpw-core' ) . '</th><td>' . esc_html( $status['section_text'] ) . '</td></tr>';
		}
		echo '</tbody>';
		echo '</table>';
		echo '</div>';

		echo '<p>';
		if ( $status['open_url'] !== '' ) {
			echo '<a class="button button-primary" href="' . esc_url( $status['open_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $config['open_label'] ) . '</a> ';
		}
		if ( $status['edit_url'] !== '' ) {
			echo '<a class="button button-secondary" href="' . esc_url( $status['edit_url'] ) . '">' . esc_html__( 'Edit Page', 'tpw-core' ) . '</a> ';
		}
		if ( $status['repair_supported'] ) {
			echo '<form method="post" style="display:inline-block; margin-left:8px;">';
			wp_nonce_field( 'tpw_flexiclub_repair_page', 'tpw_flexiclub_repair_nonce' );
			echo '<input type="hidden" name="tpw_flexiclub_bridge_action" value="repair_page" />';
			echo '<input type="hidden" name="tpw_flexiclub_repair_slug" value="' . esc_attr( $status['repair_slug'] ) . '" />';
			echo '<input type="hidden" name="tpw_flexiclub_return_page" value="' . esc_attr( $config['page_slug'] ) . '" />';
			submit_button( __( 'Create / Repair Page', 'tpw-core' ), 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '</p>';

		if ( $status['message'] !== '' ) {
			echo '<div class="notice notice-info"><p>' . esc_html( $status['message'] ) . '</p></div>';
		}

		self::render_page_end();
	}

	public static function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'tpw-core' ) );
		}

		self::render_page_start(
			__( 'FlexiClub Logs', 'tpw-core' ),
			__( 'Open the existing log screens without duplicating their implementations.', 'tpw-core' )
		);

		echo '<div class="tpw-card">';
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Log screen', 'tpw-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Current route', 'tpw-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Open', 'tpw-core' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		echo '<tr>';
		echo '<td><strong>' . esc_html__( 'Email Logs', 'tpw-core' ) . '</strong><br />' . esc_html__( 'Existing FlexiClub settings tab for outbound email diagnostics.', 'tpw-core' ) . '</td>';
		echo '<td>' . esc_html( 'options-general.php?page=tpw-core-settings&tab=email-logs' ) . '</td>';
		echo '<td><a class="button button-secondary" href="' . esc_url( admin_url( self::EMAIL_LOGS_ROUTE ) ) . '">' . esc_html__( 'Open', 'tpw-core' ) . '</a></td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td><strong>' . esc_html__( 'Payment Logs', 'tpw-core' ) . '</strong><br />' . esc_html__( 'Existing Tools screen for payment log inspection.', 'tpw-core' ) . '</td>';
		echo '<td>' . esc_html( 'tools.php?page=tpw-payment-logs' ) . '</td>';
		echo '<td><a class="button button-secondary" href="' . esc_url( admin_url( self::PAYMENT_LOGS_ROUTE ) ) . '">' . esc_html__( 'Open', 'tpw-core' ) . '</a></td>';
		echo '</tr>';
		echo '</tbody>';
		echo '</table>';
		echo '</div>';

		self::render_page_end();
	}

	public static function handle_bridge_actions() {
		if ( ! isset( $_POST['tpw_flexiclub_bridge_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['tpw_flexiclub_bridge_action'] ) );
		if ( 'repair_page' !== $action ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'tpw-core' ), 403 );
		}

		$nonce = isset( $_POST['tpw_flexiclub_repair_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tpw_flexiclub_repair_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'tpw_flexiclub_repair_page' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'tpw-core' ), 400 );
		}

		$system_slug = isset( $_POST['tpw_flexiclub_repair_slug'] ) ? sanitize_key( wp_unslash( $_POST['tpw_flexiclub_repair_slug'] ) ) : '';
		$return_page = isset( $_POST['tpw_flexiclub_return_page'] ) ? sanitize_key( wp_unslash( $_POST['tpw_flexiclub_return_page'] ) ) : self::PAGE_GALLERY;

		$args = [ 'page' => $return_page ];
		if ( '' === $system_slug || ! class_exists( 'TPW_Core_System_Pages' ) ) {
			$args['tpw_flexiclub_notice'] = 'repair_failed';
			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
			exit;
		}

		$page_id = (int) TPW_Core_System_Pages::ensure_page( $system_slug );
		$args['tpw_flexiclub_notice'] = $page_id > 0 ? 'repair_success' : 'repair_failed';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_dashboard_actions() {
		if ( ! isset( $_GET['page'] ) || self::TOP_LEVEL_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		if ( ! isset( $_GET['tpw_flexiclub_dashboard_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['tpw_flexiclub_dashboard_action'] ) );
		if ( 'dismiss_setup_banner' !== $action ) {
			return;
		}

		if ( ! self::current_user_can_view_dashboard() ) {
			wp_die( esc_html__( 'Access denied.', 'tpw-core' ), 403 );
		}

		check_admin_referer( 'tpw_flexiclub_dismiss_setup_banner' );
		update_user_meta( get_current_user_id(), self::DASHBOARD_SETUP_META, '1' );

		wp_safe_redirect( self::get_dashboard_base_url() );
		exit;
	}

	public static function filter_menu_map( $map ) {
		$map = is_array( $map ) ? $map : [];

		$map[] = [
			'query'        => [ 'page' => 'tpw-core-settings', 'tab' => 'payment-methods' ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::PAYMENTS_ROUTE,
		];

		$map[] = [
			'query'        => [ 'page' => self::PAGE_SETTINGS, 'tab' => 'payment-methods' ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::PAYMENTS_ROUTE,
		];

		$map[] = [
			'query'        => [ 'page' => 'tpw-core-settings', 'tab' => 'system-pages' ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::SYSTEM_PAGES_ROUTE,
		];

		$map[] = [
			'query'        => [ 'page' => self::PAGE_SETTINGS, 'tab' => 'system-pages' ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::SYSTEM_PAGES_ROUTE,
		];

		$map[] = [
			'query'        => [ 'page' => 'tpw-core-settings', 'tab' => 'email-logs' ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::PAGE_LOGS,
		];

		$map[] = [
			'query'        => [ 'page' => self::PAGE_SETTINGS, 'tab' => 'email-logs' ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::PAGE_LOGS,
		];

		$map[] = [
			'pages'        => [ 'tpw-payment-logs' ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::PAGE_LOGS,
		];

		$map[] = [
			'query'        => [ 'page' => 'tpw-core-settings' ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::PAGE_SETTINGS,
		];

		$map[] = [
			'query'        => [ 'page' => self::PAGE_SETTINGS ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::PAGE_SETTINGS,
		];

		$map[] = [
			'post_types'   => [ 'tpw_notice' ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::NOTICEBOARD_ROUTE,
		];

		return $map;
	}

	protected static function get_visible_items() {
		$items = [];

		if ( self::current_user_can_manage_members() ) {
			$items[] = self::PAGE_MEMBERS;
		}

		if ( current_user_can( 'edit_posts' ) ) {
			$items[] = self::NOTICEBOARD_ROUTE;
		}

		if ( self::current_user_can_gallery_manage() ) {
			$items[] = self::PAGE_GALLERY;
		}

		if ( self::current_user_can_tpw_control_section( 'upload-pages' ) ) {
			$items[] = self::PAGE_UPLOADS;
		}

		if ( self::current_user_can_tpw_control_section( 'menu-manager' ) ) {
			$items[] = self::PAGE_MENU_MANAGER;
		}

		if ( current_user_can( 'manage_options' ) ) {
			$items[] = self::SYSTEM_PAGES_ROUTE;
			$items[] = self::SETTINGS_ROUTE;
			$items[] = self::PAGE_LOGS;

			if ( function_exists( 'tpw_core_payments_required' ) && tpw_core_payments_required() ) {
				$items[] = self::PAYMENTS_ROUTE;
			}
		}

		return array_values( array_unique( $items ) );
	}

	protected static function get_dashboard_items() {
		$items   = [];
		$visible = self::get_visible_items();

		if ( in_array( self::PAGE_MEMBERS, $visible, true ) ) {
			$items[] = [
				'label'       => __( 'Manage Members', 'tpw-core' ),
				'description' => __( 'Bridge to the existing front-end Manage Members screen.', 'tpw-core' ),
				'type'        => __( 'Bridge', 'tpw-core' ),
				'url'         => self::get_menu_item_url( self::PAGE_MEMBERS ),
			];
		}

		if ( in_array( self::NOTICEBOARD_ROUTE, $visible, true ) ) {
			$items[] = [
				'label'       => __( 'Noticeboard', 'tpw-core' ),
				'description' => __( 'Existing wp-admin Noticeboard CPT screen.', 'tpw-core' ),
				'type'        => __( 'WP Admin', 'tpw-core' ),
				'url'         => admin_url( self::NOTICEBOARD_ROUTE ),
			];
		}

		if ( in_array( self::PAGE_GALLERY, $visible, true ) ) {
			$items[] = [
				'label'       => __( 'Gallery Admin', 'tpw-core' ),
				'description' => __( 'Bridge to the existing front-end Gallery Admin page.', 'tpw-core' ),
				'type'        => __( 'Bridge', 'tpw-core' ),
				'url'         => self::get_menu_item_url( self::PAGE_GALLERY ),
			];
		}

		if ( in_array( self::PAGE_UPLOADS, $visible, true ) ) {
			$items[] = [
				'label'       => __( 'Upload Pages / Archive', 'tpw-core' ),
				'description' => __( 'Bridge to the existing archive and upload-pages feature on its current front-end compatibility route.', 'tpw-core' ),
				'type'        => __( 'Bridge', 'tpw-core' ),
				'url'         => self::get_menu_item_url( self::PAGE_UPLOADS ),
			];
		}

		if ( in_array( self::PAGE_MENU_MANAGER, $visible, true ) ) {
			$items[] = [
				'label'       => __( 'Menu Permissions', 'tpw-core' ),
				'description' => __( 'Bridge to the front-end feature that controls WordPress menu visibility and permissions across the FlexiClub ecosystem.', 'tpw-core' ),
				'type'        => __( 'Bridge', 'tpw-core' ),
				'url'         => self::get_menu_item_url( self::PAGE_MENU_MANAGER ),
			];
		}

		if ( in_array( self::SYSTEM_PAGES_ROUTE, $visible, true ) ) {
			$items[] = [
				'label'       => __( 'System Pages', 'tpw-core' ),
				'description' => __( 'Existing System Pages tab inside FlexiClub Settings.', 'tpw-core' ),
				'type'        => __( 'WP Admin', 'tpw-core' ),
				'url'         => admin_url( self::SYSTEM_PAGES_ROUTE ),
			];
		}

		if ( in_array( self::PAYMENTS_ROUTE, $visible, true ) ) {
			$items[] = [
				'label'       => __( 'Payments', 'tpw-core' ),
				'description' => __( 'Existing Payment Methods settings tab.', 'tpw-core' ),
				'type'        => __( 'WP Admin', 'tpw-core' ),
				'url'         => admin_url( self::PAYMENTS_ROUTE ),
			];
		}

		if ( in_array( self::SETTINGS_ROUTE, $visible, true ) ) {
			$items[] = [
				'label'       => __( 'Settings', 'tpw-core' ),
				'description' => __( 'Existing FlexiClub settings screen.', 'tpw-core' ),
				'type'        => __( 'WP Admin', 'tpw-core' ),
				'url'         => self::get_settings_admin_url(),
			];
		}

		if ( in_array( self::PAGE_LOGS, $visible, true ) ) {
			$items[] = [
				'label'       => __( 'Logs', 'tpw-core' ),
				'description' => __( 'Launcher for the existing email and payment log screens.', 'tpw-core' ),
				'type'        => __( 'WP Admin', 'tpw-core' ),
				'url'         => admin_url( 'admin.php?page=' . self::PAGE_LOGS ),
			];
		}

		return $items;
	}

	protected static function get_bridge_configs() {
		return [
			self::PAGE_MEMBERS => [
				'page_slug'      => self::PAGE_MEMBERS,
				'title'          => __( 'Manage Members', 'tpw-core' ),
				'description'    => __( 'Launch the existing front-end Manage Members interface. No duplicate wp-admin member CRUD is created here.', 'tpw-core' ),
				'open_label'     => __( 'Open Manage Members', 'tpw-core' ),
				'detector'       => 'members',
				'shortcode'      => '[tpw_manage_members]',
				'capability'     => [ __CLASS__, 'current_user_can_manage_members' ],
			],
			self::PAGE_GALLERY => [
				'page_slug'      => self::PAGE_GALLERY,
				'title'          => __( 'Gallery Admin', 'tpw-core' ),
				'description'    => __( 'Launch the existing front-end Gallery Admin system page. The wp-admin bridge only reports status and opens the current page.', 'tpw-core' ),
				'open_label'     => __( 'Open Gallery Admin', 'tpw-core' ),
				'detector'       => 'gallery',
				'shortcode'      => '[tpw_gallery_admin]',
				'system_slug'    => 'gallery-admin',
				'capability'     => [ __CLASS__, 'current_user_can_gallery_manage' ],
			],
			self::PAGE_UPLOADS => [
				'page_slug'      => self::PAGE_UPLOADS,
				'title'          => __( 'Upload Pages / Archive', 'tpw-core' ),
				'description'    => __( 'This bridge describes the archive and upload-pages feature, checks the current front-end compatibility page, and opens the existing front-end implementation without duplicating it in wp-admin.', 'tpw-core' ),
				'open_label'     => __( 'Open Upload Pages / Archive', 'tpw-core' ),
				'detector'       => 'tpw-control-section',
				'shortcode'      => '[tpw-control]',
				'section'        => 'upload-pages',
				'route_label'    => '/tpw-control/?action=upload-pages',
				'capability'     => function() {
					return self::current_user_can_tpw_control_section( 'upload-pages' );
				},
			],
			self::PAGE_MENU_MANAGER => [
				'page_slug'      => self::PAGE_MENU_MANAGER,
				'title'          => __( 'Menu Permissions', 'tpw-core' ),
				'description'    => __( 'This bridge describes the menu-permissions feature that controls WordPress menu visibility and access across the FlexiClub ecosystem, then opens the existing front-end implementation without duplicating it in wp-admin.', 'tpw-core' ),
				'open_label'     => __( 'Open Menu Permissions', 'tpw-core' ),
				'detector'       => 'tpw-control-section',
				'shortcode'      => '[tpw-control]',
				'section'        => 'menu-manager',
				'route_label'    => '/tpw-control/?action=menu-manager',
				'capability'     => function() {
					return self::current_user_can_tpw_control_section( 'menu-manager' );
				},
			],
		];
	}

	protected static function get_current_bridge_config() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$configs = self::get_bridge_configs();

		return isset( $configs[ $page ] ) ? $configs[ $page ] : [];
	}

	protected static function current_user_can_bridge( $config ) {
		if ( empty( $config['capability'] ) ) {
			return current_user_can( 'read' );
		}

		if ( is_callable( $config['capability'] ) ) {
			return (bool) call_user_func( $config['capability'] );
		}

		return current_user_can( (string) $config['capability'] );
	}

	protected static function build_bridge_status( $config ) {
		$type = isset( $config['detector'] ) ? (string) $config['detector'] : '';

		switch ( $type ) {
			case 'gallery':
				return self::build_gallery_status( $config );
			case 'tpw-control-section':
				return self::build_tpw_control_status( $config, true );
			case 'members':
			default:
				return self::build_shortcode_page_status( 'manage-members', 'tpw_manage_members', $config );
		}
	}

	protected static function build_gallery_status( $config ) {
		$status = self::locate_system_page( (string) $config['system_slug'], 'tpw_gallery_admin' );

		return [
			'page_text'        => $status['page_text'],
			'shortcode'        => (string) $config['shortcode'],
			'shortcode_text'   => $status['shortcode_text'],
			'route_text'       => $status['route_text'],
			'section_text'     => '',
			'open_url'         => $status['open_url'],
			'edit_url'         => $status['edit_url'],
			'diagnostics_required' => empty( $status['page_exists'] ) || empty( $status['shortcode_present'] ) || '' === $status['open_url'],
			'repair_supported' => $status['repair_supported'],
			'repair_slug'      => $status['repair_slug'],
			'message'          => $status['message'],
		];
	}

	protected static function build_tpw_control_status( $config, $with_section ) {
		$status = self::locate_shortcode_page( 'tpw-control', 'tpw-control' );
		$section_registered = true;
		$can_open           = ! empty( $status['page_exists'] ) && ! empty( $status['shortcode_present'] ) && '' !== $status['page_url'];
		$open               = '';

		if ( $with_section ) {
			$section_registered = self::tpw_control_section_is_registered( (string) $config['section'] );
			$can_open           = $can_open && $section_registered;
		}

		if ( $can_open ) {
			$open = $status['page_url'];
			if ( $with_section ) {
				$open = add_query_arg( 'action', (string) $config['section'], $open );
			}
		}

		$section_text = '';
		if ( $with_section ) {
			$section_text = $section_registered
				? __( 'Registered on the existing compatibility route.', 'tpw-core' )
				: __( 'This feature section is not currently registered on the existing compatibility route.', 'tpw-core' );
		}

		$message = '';
		if ( ! $status['page_exists'] ) {
			$message = __( 'No compatible front-end page is currently configured. Page creation or repair is not yet supported from this bridge.', 'tpw-core' );
		} elseif ( ! $status['shortcode_present'] ) {
			$message = __( 'A compatible front-end page exists, but the expected shortcode is missing from its content. Page creation or repair is not yet supported from this bridge.', 'tpw-core' );
		} elseif ( $with_section && ! $section_registered ) {
			$message = __( 'The front-end page exists, but the requested tool is not currently registered on that compatibility route.', 'tpw-core' );
		}

		return [
			'page_text'        => $status['page_text'],
			'shortcode'        => (string) $config['shortcode'],
			'shortcode_text'   => $status['shortcode_text'],
			'route_text'       => isset( $config['route_label'] ) ? (string) $config['route_label'] : '',
			'section_text'     => $section_text,
			'open_url'         => $open,
			'edit_url'         => $status['edit_url'],
			'diagnostics_required' => ! $can_open,
			'repair_supported' => false,
			'repair_slug'      => '',
			'message'          => $message,
		];
	}

	protected static function build_shortcode_page_status( $fallback_slug, $shortcode_tag, $config ) {
		$status  = self::locate_shortcode_page( $shortcode_tag, $fallback_slug );
		$message = '';

		if ( ! $status['page_exists'] ) {
			$message = __( 'No published front-end page is currently configured for this feature.', 'tpw-core' );
		} elseif ( ! $status['shortcode_present'] ) {
			$message = __( 'A published page was found, but the expected shortcode is missing from its content.', 'tpw-core' );
		}

		return [
			'page_text'        => $status['page_text'],
			'shortcode'        => (string) $config['shortcode'],
			'shortcode_text'   => $status['shortcode_text'],
			'route_text'       => '/' . ltrim( (string) $fallback_slug, '/' ) . '/',
			'section_text'     => '',
			'open_url'         => ! empty( $status['shortcode_present'] ) ? $status['page_url'] : '',
			'edit_url'         => $status['edit_url'],
			'diagnostics_required' => empty( $status['page_exists'] ) || empty( $status['shortcode_present'] ) || '' === $status['page_url'],
			'repair_supported' => false,
			'repair_slug'      => '',
			'message'          => $message,
		];
	}

	protected static function locate_shortcode_page( $shortcode_tag, $fallback_slug = '' ) {
		$page = self::find_page_with_shortcode_tag( $shortcode_tag );
		if ( ! $page && $fallback_slug !== '' ) {
			$page = get_page_by_path( sanitize_title( $fallback_slug ), OBJECT, 'page' );
		}

		$page_exists      = ( $page instanceof WP_Post ) && 'page' === $page->post_type && 'publish' === $page->post_status;
		$shortcode_markup = '[' . $shortcode_tag . ']';
		$shortcode_found  = false;
		$page_url         = '';
		$edit_url         = '';

		if ( $page_exists ) {
			$content         = (string) $page->post_content;
			$shortcode_found = self::page_has_shortcode_tag( $content, $shortcode_tag );
			$page_url        = (string) get_permalink( $page );
			$edit_url        = (string) get_edit_post_link( $page->ID, '' );
		}

		$page_text = $page_exists
			? sprintf(
				/* translators: 1: page title, 2: page ID */
				__( 'Found: %1$s (#%2$d)', 'tpw-core' ),
				(string) $page->post_title,
				(int) $page->ID
			)
			: __( 'Not found.', 'tpw-core' );

		return [
			'page_exists'     => $page_exists,
			'page_url'        => $page_url,
			'page_text'       => $page_text,
			'edit_url'        => $edit_url,
			'shortcode_text'  => $shortcode_found ? __( 'Present on the published page.', 'tpw-core' ) : __( 'Missing from the published page.', 'tpw-core' ),
			'shortcode'       => $shortcode_markup,
			'shortcode_present' => $shortcode_found,
		];
	}

	protected static function locate_system_page( $system_slug, $shortcode_tag ) {
		$page_id = class_exists( 'TPW_Core_System_Pages' ) ? (int) TPW_Core_System_Pages::get_page_id( $system_slug ) : 0;
		$page    = $page_id > 0 ? get_post( $page_id ) : null;
		$exists  = ( $page instanceof WP_Post ) && 'page' === $page->post_type && 'publish' === $page->post_status;
		$has_sc  = false;
		$page_url = '';
		$edit_url = '';
		$message  = '';

		if ( $exists ) {
			$has_sc   = self::page_has_shortcode_tag( (string) $page->post_content, $shortcode_tag );
			$page_url = (string) get_permalink( $page );
			$edit_url = (string) get_edit_post_link( $page->ID, '' );
			if ( ! $has_sc ) {
				$message = __( 'Automatic repair is unavailable here because the current system-pages tooling does not overwrite existing page content.', 'tpw-core' );
			}
		} elseif ( class_exists( 'TPW_Core_System_Pages' ) ) {
			$page_url = (string) TPW_Core_System_Pages::get_permalink( $system_slug );
		}

		return [
			'page_text'        => $exists
				? sprintf(
					/* translators: 1: page title, 2: page ID */
					__( 'Found: %1$s (#%2$d)', 'tpw-core' ),
					(string) $page->post_title,
					(int) $page->ID
				)
				: __( 'Not found.', 'tpw-core' ),
			'shortcode_text'   => $has_sc ? __( 'Present on the published page.', 'tpw-core' ) : __( 'Missing from the published page.', 'tpw-core' ),
			'route_text'       => '/' . ltrim( (string) $system_slug, '/' ) . '/',
			'open_url'         => $has_sc ? $page_url : '',
			'edit_url'         => $edit_url,
			'repair_supported' => ! $exists,
			'repair_slug'      => ! $exists ? (string) $system_slug : '',
			'message'          => $message,
		];
	}

	protected static function find_page_with_shortcode_tag( $shortcode_tag ) {
		$tag = trim( (string) $shortcode_tag );
		if ( '' === $tag ) {
			return null;
		}

		$query = new WP_Query(
			[
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				's'              => '[' . $tag,
			]
		);

		if ( ! $query->have_posts() ) {
			return null;
		}

		foreach ( $query->posts as $post_id ) {
			$content = (string) get_post_field( 'post_content', (int) $post_id );
			if ( self::page_has_shortcode_tag( $content, $tag ) ) {
				return get_post( (int) $post_id );
			}
		}

		return null;
	}

	protected static function page_has_shortcode_tag( $content, $shortcode_tag ) {
		$content = (string) $content;
		$tag     = trim( (string) $shortcode_tag );

		if ( '' === $content || '' === $tag ) {
			return false;
		}

		if ( function_exists( 'has_shortcode' ) ) {
			return has_shortcode( $content, $tag );
		}

		return false !== strpos( $content, '[' . $tag );
	}

	public static function current_user_can_manage_members() {
		if ( class_exists( 'TPW_Member_Access', false ) && method_exists( 'TPW_Member_Access', 'can_manage_members_current' ) ) {
			return TPW_Member_Access::can_manage_members_current();
		}

		return current_user_can( 'manage_options' );
	}

	protected static function bridge_diagnostics_requested() {
		return isset( $_GET['tpw_flexiclub_diagnostics'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['tpw_flexiclub_diagnostics'] ) );
	}

	protected static function is_flexievent_active() {
		return post_type_exists( 'tpw_event' ) || class_exists( 'TPW_FlexiEvent', false ) || defined( 'TPW_FLEXIEVENT_VERSION' );
	}

	public static function current_user_can_gallery_manage() {
		if ( function_exists( 'tpw_gallery_user_can_manage' ) ) {
			return tpw_gallery_user_can_manage();
		}

		if ( function_exists( 'tpw_core_user_can' ) ) {
			return tpw_core_user_can( 'tpw_gallery_manage_all' );
		}

		return current_user_can( 'manage_options' );
	}

	public static function current_user_can_tpw_control_hub() {
		if ( class_exists( 'TPW_Control', false ) && method_exists( 'TPW_Control', 'can_manage' ) && TPW_Control::can_manage() ) {
			return true;
		}

		return self::current_user_can_tpw_control_section( 'upload-pages' ) || self::current_user_can_tpw_control_section( 'menu-manager' );
	}

	public static function current_user_can_tpw_control_section( $section_key ) {
		if ( ! class_exists( 'TPW_Control', false ) ) {
			return false;
		}

		self::ensure_tpw_control_ui();
		if ( ! class_exists( 'TPW_Control_UI', false ) ) {
			return false;
		}

		$sections = TPW_Control::get_sections();
		if ( empty( $sections[ $section_key ] ) || ! is_array( $sections[ $section_key ] ) ) {
			return false;
		}

		return TPW_Control_UI::section_is_visible( $sections[ $section_key ] );
	}

	protected static function tpw_control_section_is_registered( $section_key ) {
		if ( ! class_exists( 'TPW_Control', false ) ) {
			return false;
		}

		$sections = TPW_Control::get_sections();
		return isset( $sections[ $section_key ] ) && is_array( $sections[ $section_key ] );
	}

	protected static function ensure_tpw_control_ui() {
		if ( class_exists( 'TPW_Control_UI', false ) ) {
			return;
		}

		$path = defined( 'TPW_CORE_PATH' ) ? TPW_CORE_PATH . 'modules/tpw-control/class-tpw-control-ui.php' : '';
		if ( $path && file_exists( $path ) ) {
			require_once $path;
		}
	}

	protected static function current_user_can_view_dashboard() {
		return current_user_can( 'manage_options' );
	}

	public static function enqueue_dashboard_assets( $hook_suffix = '' ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::TOP_LEVEL_SLUG !== $page ) {
			return;
		}

		if ( function_exists( 'tpw_core_enqueue_shared_ui_assets' ) ) {
			tpw_core_enqueue_shared_ui_assets(
				[
					'ui'       => true,
					'admin_ui' => true,
					'buttons'  => true,
				]
			);
		}

		if ( ! defined( 'TPW_CORE_PATH' ) || ! defined( 'TPW_CORE_URL' ) ) {
			return;
		}

		$css_file = TPW_CORE_PATH . 'assets/css/flexiclub-dashboard.css';
		$css_url  = TPW_CORE_URL . 'assets/css/flexiclub-dashboard.css';

		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'tpw-flexiclub-dashboard',
				$css_url,
				[ 'tpw-admin-ui', 'tpw-buttons' ],
				filemtime( $css_file )
			);
		}
	}

	protected static function redirect_dashboard_request() {
		$target = self::get_dashboard_redirect_url();

		if ( '' !== $target ) {
			wp_safe_redirect( $target );
			exit;
		}

		wp_die( esc_html__( 'You do not have permission to access this page.', 'tpw-core' ) );
	}

	protected static function get_dashboard_redirect_url() {
		foreach ( self::get_visible_items() as $item ) {
			$url = self::get_menu_item_url( $item );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return '';
	}

	protected static function get_menu_item_url( $item_slug ) {
		switch ( $item_slug ) {
			case self::PAGE_MEMBERS:
				return self::get_members_management_url();
			case self::PAGE_GALLERY:
				return self::get_gallery_launch_url();
			case self::PAGE_UPLOADS:
				return self::get_tpw_control_launch_url( 'upload-pages', self::PAGE_UPLOADS );
			case self::PAGE_MENU_MANAGER:
				return self::get_tpw_control_launch_url( 'menu-manager', self::PAGE_MENU_MANAGER );
			case self::PAGE_LOGS:
				return admin_url( 'admin.php?page=' . $item_slug );
			case self::NOTICEBOARD_ROUTE:
			case self::SYSTEM_PAGES_ROUTE:
			case self::PAYMENTS_ROUTE:
			case self::EMAIL_LOGS_ROUTE:
			case self::PAYMENT_LOGS_ROUTE:
				return admin_url( $item_slug );
			case self::SETTINGS_ROUTE:
				return self::get_settings_admin_url();
			default:
				return '';
		}
	}

	protected static function get_dashboard_view_model() {
		$current_user      = wp_get_current_user();
		$members_summary   = self::get_members_summary();
		$notices_summary   = self::get_notices_summary();
		$events_summary    = self::get_events_summary();
		$system_summary    = self::get_system_pages_summary();
		$gallery_summary   = self::get_gallery_summary();
		$uploads_summary   = self::get_upload_pages_summary();
		$menu_summary      = self::get_menu_permissions_summary();
		$payments_summary  = self::get_payments_summary();
		$settings_summary  = self::get_settings_summary();
		$logs_summary      = self::get_logs_summary();
		$checklist_items   = self::get_dashboard_checklist_items(
			$members_summary,
			$notices_summary,
			$system_summary,
			$menu_summary,
			$settings_summary,
			$payments_summary
		);
		$completed_steps   = count(
			array_filter(
				$checklist_items,
				static function( $item ) {
					return ! empty( $item['done'] );
				}
			)
		);
		$checklist_total   = count( $checklist_items );
		$checklist_complete = $checklist_total > 0 && $completed_steps >= $checklist_total;
		$checklist_requested = self::dashboard_checklist_requested();
		$show_checklist     = ! $checklist_complete || $checklist_requested;
		$banner_dismissed   = self::is_dashboard_setup_banner_dismissed();
		$primary_item       = self::get_dashboard_primary_checklist_item( $checklist_items, $checklist_complete );

		return [
			'logo_url'        => self::get_dashboard_logo_url(),
			'icon_url'        => self::get_dashboard_icon_url(),
			'version'         => defined( 'TPW_CORE_VERSION' ) ? (string) TPW_CORE_VERSION : '',
			'welcome_name'    => $current_user instanceof WP_User ? (string) $current_user->display_name : __( 'Admin', 'tpw-core' ),
			'summary_cards'   => [
				[
					'title'         => __( 'Total Members', 'tpw-core' ),
					'value'         => self::format_metric_value( $members_summary['count'] ),
					'description'   => $members_summary['metric_text'],
					'action_label'  => __( 'View members', 'tpw-core' ),
					'action_url'    => self::get_members_management_url(),
					'icon'          => 'dashicons-groups',
				],
				[
					'title'         => __( 'Active Notices', 'tpw-core' ),
					'value'         => self::format_metric_value( $notices_summary['count'] ),
					'description'   => $notices_summary['metric_text'],
					'action_label'  => __( 'View notices', 'tpw-core' ),
					'action_url'    => admin_url( self::NOTICEBOARD_ROUTE ),
					'icon'          => 'dashicons-megaphone',
				],
				[
					'title'         => __( 'Upcoming Events', 'tpw-core' ),
					'value'         => self::format_metric_value( $events_summary['count'], false ),
					'description'   => $events_summary['metric_text'],
					'action_label'  => $events_summary['action_label'],
					'action_url'    => $events_summary['action_url'],
					'icon'          => 'dashicons-calendar-alt',
				],
			],
			'overview_cards'  => [
				[
					'title'         => __( 'Members', 'tpw-core' ),
					'metric'        => self::format_metric_value( $members_summary['count'] ),
					'tone'          => 'members',
					'status_label'  => $members_summary['status_label'],
					'status_tone'   => $members_summary['status_tone'],
					'description'   => $members_summary['card_text'],
					'action_label'  => __( 'Manage members', 'tpw-core' ),
					'action_url'    => self::get_members_management_url(),
					'icon'          => 'dashicons-groups',
					'disabled'      => false,
				],
				[
					'title'         => __( 'Noticeboard', 'tpw-core' ),
					'metric'        => self::format_metric_value( $notices_summary['count'] ),
					'tone'          => 'noticeboard',
					'status_label'  => $notices_summary['status_label'],
					'status_tone'   => $notices_summary['status_tone'],
					'description'   => $notices_summary['card_text'],
					'action_label'  => __( 'Open noticeboard', 'tpw-core' ),
					'action_url'    => admin_url( self::NOTICEBOARD_ROUTE ),
					'icon'          => 'dashicons-megaphone',
					'disabled'      => false,
				],
				[
					'title'         => __( 'Gallery Admin', 'tpw-core' ),
					'metric'        => $gallery_summary['metric_value'],
					'tone'          => 'gallery',
					'status_label'  => $gallery_summary['status_label'],
					'status_tone'   => $gallery_summary['status_tone'],
					'description'   => $gallery_summary['card_text'],
					'action_label'  => __( 'Open gallery admin', 'tpw-core' ),
					'action_url'    => self::get_menu_item_url( self::PAGE_GALLERY ),
					'icon'          => 'dashicons-format-gallery',
					'disabled'      => false,
				],
				[
					'title'         => __( 'Upload Pages / Archive', 'tpw-core' ),
					'metric'        => $uploads_summary['metric_value'],
					'tone'          => 'uploads',
					'status_label'  => $uploads_summary['status_label'],
					'status_tone'   => $uploads_summary['status_tone'],
					'description'   => $uploads_summary['card_text'],
					'action_label'  => __( 'Open archive tools', 'tpw-core' ),
					'action_url'    => self::get_menu_item_url( self::PAGE_UPLOADS ),
					'icon'          => 'dashicons-cloud-upload',
					'disabled'      => false,
				],
				[
					'title'         => __( 'Menu Permissions', 'tpw-core' ),
					'metric'        => $menu_summary['metric_value'],
					'tone'          => 'permissions',
					'status_label'  => $menu_summary['status_label'],
					'status_tone'   => $menu_summary['status_tone'],
					'description'   => $menu_summary['card_text'],
					'action_label'  => __( 'Review permissions', 'tpw-core' ),
					'action_url'    => self::get_menu_item_url( self::PAGE_MENU_MANAGER ),
					'icon'          => 'dashicons-lock',
					'disabled'      => false,
				],
				[
					'title'         => __( 'System Pages', 'tpw-core' ),
					'metric'        => $system_summary['metric_value'],
					'tone'          => 'system-pages',
					'status_label'  => $system_summary['status_label'],
					'status_tone'   => $system_summary['status_tone'],
					'description'   => $system_summary['card_text'],
					'action_label'  => __( 'Open system pages', 'tpw-core' ),
					'action_url'    => admin_url( self::SYSTEM_PAGES_ROUTE ),
					'icon'          => 'dashicons-admin-page',
					'disabled'      => false,
				],
				[
					'title'         => __( 'Payments', 'tpw-core' ),
					'metric'        => $payments_summary['metric_value'],
					'tone'          => 'payments',
					'status_label'  => $payments_summary['status_label'],
					'status_tone'   => $payments_summary['status_tone'],
					'description'   => $payments_summary['card_text'],
					'action_label'  => __( 'Configure payments', 'tpw-core' ),
					'action_url'    => $payments_summary['action_url'],
					'icon'          => 'dashicons-money-alt',
					'disabled'      => empty( $payments_summary['action_url'] ),
					'show_action'   => ! empty( $payments_summary['action_url'] ),
				],
				[
					'title'         => __( 'Settings', 'tpw-core' ),
					'metric'        => $settings_summary['metric_value'],
					'tone'          => 'settings',
					'status_label'  => $settings_summary['status_label'],
					'status_tone'   => $settings_summary['status_tone'],
					'description'   => $settings_summary['card_text'],
					'action_label'  => __( 'Open settings', 'tpw-core' ),
					'action_url'    => self::get_settings_admin_url(),
					'icon'          => 'dashicons-admin-generic',
					'disabled'      => false,
				],
				[
					'title'         => __( 'Logs', 'tpw-core' ),
					'metric'        => $logs_summary['metric_value'],
					'tone'          => 'logs',
					'status_label'  => $logs_summary['status_label'],
					'status_tone'   => $logs_summary['status_tone'],
					'description'   => $logs_summary['card_text'],
					'action_label'  => __( 'View logs', 'tpw-core' ),
					'action_url'    => self::get_menu_item_url( self::PAGE_LOGS ),
					'icon'          => 'dashicons-chart-line',
					'disabled'      => false,
				],
			],
			'quick_actions'   => self::get_dashboard_quick_actions( $payments_summary ),
			'extend_cards'    => self::get_dashboard_extend_cards(),
			'checklist_items' => $checklist_items,
			'checklist_done'  => $completed_steps,
			'checklist_total' => $checklist_total,
			'checklist_progress' => $checklist_total > 0 ? ( $completed_steps / $checklist_total ) * 100 : 0,
			'checklist_complete' => $checklist_complete,
			'checklist_requested' => $checklist_requested,
			'show_checklist' => $show_checklist,
			'show_setup_banner' => $checklist_complete && ! $show_checklist && ! $banner_dismissed,
			'checklist_url'  => self::get_dashboard_checklist_url(),
			'collapse_checklist_url' => self::get_dashboard_base_url(),
			'dismiss_setup_url' => self::get_dashboard_dismiss_setup_url(),
			'checklist_primary_action' => $primary_item,
			'activity_items'  => self::get_dashboard_activity_items(),
			'system_items'    => self::get_dashboard_system_items(
				$members_summary,
				$system_summary,
				$payments_summary,
				$logs_summary
			),
		];
	}

	protected static function get_dashboard_quick_actions( $payments_summary ) {
		$actions = [
			[
				'label'    => __( 'Setup Checklist', 'tpw-core' ),
				'url'      => self::get_dashboard_checklist_url(),
				'disabled' => false,
			],
			[
				'label'    => __( 'Add New Member', 'tpw-core' ),
				'url'      => self::get_members_management_url( 'add' ),
				'disabled' => false,
			],
			[
				'label'    => __( 'Add New Notice', 'tpw-core' ),
				'url'      => admin_url( 'post-new.php?post_type=tpw_notice' ),
				'disabled' => false,
			],
			[
				'label'    => __( 'Add Gallery Images', 'tpw-core' ),
				'url'      => self::get_gallery_launch_url(),
				'disabled' => false,
			],
			[
				'label'    => __( 'Create or Check System Pages', 'tpw-core' ),
				'url'      => admin_url( self::SYSTEM_PAGES_ROUTE ),
				'disabled' => false,
			],
			[
				'label'    => __( 'Review Menu Permissions', 'tpw-core' ),
				'url'      => self::get_tpw_control_launch_url( 'menu-manager', self::PAGE_MENU_MANAGER ),
				'disabled' => false,
			],
		];

		if ( ! empty( $payments_summary['payments_required'] ) && ! empty( $payments_summary['action_url'] ) ) {
			$actions[] = [
				'label'    => __( 'Configure Payments', 'tpw-core' ),
				'url'      => $payments_summary['action_url'],
				'disabled' => false,
			];
		}

		$actions[] = [
			'label'    => __( 'View Logs', 'tpw-core' ),
			'url'      => self::get_menu_item_url( self::PAGE_LOGS ),
			'disabled' => false,
		];

		return $actions;
	}

	protected static function get_dashboard_primary_checklist_item( $items, $complete ) {
		$items = is_array( $items ) ? $items : [];

		foreach ( $items as $item ) {
			if ( empty( $item['done'] ) ) {
				return [
					'label' => __( 'Continue setup', 'tpw-core' ),
					'url'   => isset( $item['url'] ) ? $item['url'] : self::get_dashboard_checklist_url(),
				];
			}
		}

		return [
			'label' => $complete ? __( 'Review checklist', 'tpw-core' ) : __( 'Open checklist', 'tpw-core' ),
			'url'   => self::get_dashboard_checklist_url(),
		];
	}

	protected static function get_dashboard_base_url() {
		return add_query_arg(
			[
				'page' => self::TOP_LEVEL_SLUG,
			],
			admin_url( 'admin.php' )
		);
	}

	protected static function get_dashboard_checklist_url() {
		return add_query_arg(
			[
				'page'                        => self::TOP_LEVEL_SLUG,
				'tpw_flexiclub_show_checklist' => '1',
			],
			admin_url( 'admin.php' )
		) . '#tpw-flexiclub-checklist';
	}

	protected static function get_dashboard_dismiss_setup_url() {
		return wp_nonce_url(
			add_query_arg(
				[
					'page'                           => self::TOP_LEVEL_SLUG,
					'tpw_flexiclub_dashboard_action' => 'dismiss_setup_banner',
				],
				admin_url( 'admin.php' )
			),
			'tpw_flexiclub_dismiss_setup_banner'
		);
	}

	protected static function dashboard_checklist_requested() {
		return isset( $_GET['tpw_flexiclub_show_checklist'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['tpw_flexiclub_show_checklist'] ) );
	}

	protected static function is_dashboard_setup_banner_dismissed() {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return false;
		}

		return '1' === (string) get_user_meta( $user_id, self::DASHBOARD_SETUP_META, true );
	}

	protected static function get_dashboard_extend_cards() {
		$definitions = [
			[
				'name'             => __( 'FlexiEvent', 'tpw-core' ),
				'description'      => __( 'Events, scheduling, and club activities.', 'tpw-core' ),
				'icon_url'         => self::get_plugin_icon_url( 'flexievent-icon.svg' ),
				'plugin_names'     => [ 'FlexiEvent', 'TPW FlexiEvent' ],
				'text_domains'     => [ 'flexievent', 'tpw-flexievent' ],
				'basenames'        => [ 'flexievent/flexievent.php', 'tpw-flexievent/flexievent.php', 'tpw-flexievent/tpw-flexievent.php' ],
				'active_classes'   => [ 'TPW_FlexiEvent' ],
				'active_constants' => [ 'TPW_FLEXIEVENT_VERSION' ],
				'active_post_types'=> [ 'tpw_event' ],
				'product_url'      => 'https://thepluginworks.com/FlexiEvent',
				'active_url'       => admin_url( 'edit.php?post_type=tpw_event' ),
				'active_label'     => __( 'Manage events', 'tpw-core' ),
			],
			[
				'name'         => __( 'FlexiSubscriptions', 'tpw-core' ),
				'description'  => __( 'Membership subscriptions and renewals.', 'tpw-core' ),
				'icon_url'     => self::get_plugin_icon_url( 'flexisubscriptions-icon.svg' ),
				'plugin_names' => [ 'FlexiSubscriptions', 'TPW FlexiSubscriptions' ],
				'text_domains' => [ 'flexisubscriptions', 'tpw-flexisubscriptions' ],
				'basenames'    => [ 'flexisubscriptions/flexisubscriptions.php', 'tpw-flexisubscriptions/flexisubscriptions.php', 'tpw-flexisubscriptions/tpw-flexisubscriptions.php' ],
				'product_url'  => 'https://thepluginworks.com/FlexiSubscriptions',
				'active_url'   => admin_url( 'admin.php?page=csp_dashboard_home' ),
				'active_label' => __( 'Manage subscriptions', 'tpw-core' ),
			],
			[
				'name'         => __( 'FlexiTicket', 'tpw-core' ),
				'description'  => __( 'Ticketing and event sales for members.', 'tpw-core' ),
				'icon_url'     => self::get_plugin_icon_url( 'flexiticket-icon.svg' ),
				'plugin_names' => [ 'FlexiTicket', 'TPW FlexiTicket' ],
				'text_domains' => [ 'flexiticket', 'tpw-flexiticket' ],
				'basenames'    => [ 'flexiticket/flexiticket.php', 'tpw-flexiticket/flexiticket.php', 'tpw-flexiticket/tpw-flexiticket.php' ],
				'product_url'  => 'https://thepluginworks.com/FlexiTicket',
				'active_url'   => admin_url( 'edit.php?post_type=tpw_event' ),
				'active_label' => __( 'Manage events', 'tpw-core' ),
			],
			[
				'name'         => __( 'FlexiLedger', 'tpw-core' ),
				'description'  => __( 'Financial tracking and reconciliation tools.', 'tpw-core' ),
				'icon_url'     => self::get_plugin_icon_url( 'flexiledger-icon.svg' ),
				'plugin_names' => [ 'FlexiLedger', 'TPW FlexiLedger' ],
				'text_domains' => [ 'flexiledger', 'tpw-flexiledger' ],
				'basenames'    => [ 'flexiledger/flexiledger.php', 'tpw-flexiledger/flexiledger.php', 'tpw-flexiledger/tpw-flexiledger.php' ],
				'product_url'  => 'https://thepluginworks.com/FlexiLedger',
			],
			[
				'name'             => __( 'FlexiGolf', 'tpw-core' ),
				'description'      => __( 'Fixtures, results, and match administration.', 'tpw-core' ),
				'icon_url'         => self::get_plugin_icon_url( 'flexigolf-icon.svg' ),
				'plugin_names'     => [ 'FlexiGolf', 'TPW FlexiGolf' ],
				'text_domains'     => [ 'flexigolf', 'tpw-flexigolf' ],
				'basenames'        => [ 'flexigolf/flexigolf.php', 'flexigolf/flexigolf-main.php', 'tpw-flexigolf/tpw-flexigolf.php', 'tpw-flexigolf/tpw-flexigolf-main.php' ],
				'active_classes'   => [ 'FlexiGolf' ],
				'active_constants' => [ 'FLEXIGOLF_VERSION' ],
				'product_url'      => 'https://thepluginworks.com/FlexiGolf',
			],
			[
				'name'         => __( 'FlexiPolicy', 'tpw-core' ),
				'description'  => __( 'Club documents, policy delivery, and acknowledgements.', 'tpw-core' ),
				'icon_url'     => self::get_plugin_icon_url( 'flexipolicy-icon.svg' ),
				'plugin_names' => [ 'FlexiPolicy', 'TPW FlexiPolicy' ],
				'text_domains' => [ 'flexipolicy', 'tpw-flexipolicy' ],
				'basenames'    => [ 'flexipolicy/flexipolicy.php', 'tpw-flexipolicy/flexipolicy.php', 'tpw-flexipolicy/tpw-flexipolicy.php' ],
				'product_url'  => 'https://thepluginworks.com/FlexiPolicy',
			],
			[
				'name'         => __( 'FlexiRota', 'tpw-core' ),
				'description'  => __( 'Volunteer and duty rota planning.', 'tpw-core' ),
				'icon_url'     => self::get_plugin_icon_url( 'flexirota-icon.svg' ),
				'plugin_names' => [ 'FlexiRota', 'TPW FlexiRota' ],
				'text_domains' => [ 'flexirota', 'tpw-flexirota' ],
				'basenames'    => [ 'flexirota/flexirota.php', 'tpw-flexirota/flexirota.php', 'tpw-flexirota/tpw-flexirota.php' ],
				'product_url'  => 'https://thepluginworks.com/FlexiRota',
			],
			[
				'name'         => __( 'Lodge RSVP', 'tpw-core' ),
				'description'  => __( 'Responses, attendance, and payment-ready RSVPs.', 'tpw-core' ),
				'icon_url'     => self::get_plugin_icon_url( 'flexilodgersvp-icon.svg' ),
				'plugin_names' => [ 'Lodge RSVP', 'TPW RSVP Lodge Meetings', 'RSVP Lodge Meetings' ],
				'text_domains' => [ 'lodge-rsvp', 'tpw-lodge-rsvp', 'tpw-rsvp-lodge-meetings' ],
				'basenames'    => [ 'lodge-rsvp/lodge-rsvp.php', 'tpw-lodge-rsvp/tpw-lodge-rsvp.php', 'tpw-rsvp-lodge-meetings/tpw-rsvp-lodge-meetings.php' ],
				'product_url'  => 'https://thepluginworks.com/lodge-rsvp-plugin-for-wordpress/',
				'active_url'   => admin_url( 'edit.php?post_type=tpw_event' ),
				'active_label' => __( 'Manage events', 'tpw-core' ),
			],
		];

		$cards = [];
		foreach ( $definitions as $definition ) {
			$cards[] = self::build_dashboard_extend_card( $definition );
		}

		return $cards;
	}

	protected static function build_dashboard_extend_card( $definition, $plugin_state = null ) {
		$definition   = is_array( $definition ) ? $definition : [];
		$plugin_state = is_array( $plugin_state ) ? $plugin_state : self::resolve_dashboard_plugin_state( $definition );

		$card = [
			'name'         => isset( $definition['name'] ) ? $definition['name'] : '',
			'description'  => isset( $definition['description'] ) ? $definition['description'] : '',
			'icon_url'     => isset( $definition['icon_url'] ) ? $definition['icon_url'] : '',
			'status_label' => __( 'Available', 'tpw-core' ),
			'status_tone'  => 'neutral',
			'action_label' => '',
			'action_url'   => '',
		];

		if ( ! empty( $plugin_state['active'] ) ) {
			$card['status_label'] = __( 'Active', 'tpw-core' );
			$card['status_tone']  = 'success';

			if ( ! empty( $definition['active_url'] ) ) {
				$card['action_label'] = ! empty( $definition['active_label'] ) ? $definition['active_label'] : __( 'Open plugin', 'tpw-core' );
				$card['action_url']   = $definition['active_url'];
			}

			return $card;
		}

		if ( ! empty( $plugin_state['installed'] ) ) {
			$card['status_label'] = __( 'Installed', 'tpw-core' );
			$card['status_tone']  = 'info';

			if ( ! empty( $plugin_state['activation_url'] ) ) {
				$card['action_label'] = ! empty( $plugin_state['can_activate'] ) ? __( 'Activate plugin', 'tpw-core' ) : __( 'View plugins', 'tpw-core' );
				$card['action_url']   = $plugin_state['activation_url'];
			} else {
				$card['action_label'] = __( 'View plugins', 'tpw-core' );
				$card['action_url']   = admin_url( 'plugins.php' );
			}

			return $card;
		}

		if ( ! empty( $definition['product_url'] ) ) {
			$card['action_label'] = __( 'Learn more', 'tpw-core' );
			$card['action_url']   = $definition['product_url'];
		}

		return $card;
	}

	protected static function resolve_dashboard_plugin_state( $definition ) {
		self::ensure_plugin_api_loaded();

		$plugin_file = self::find_dashboard_plugin_basename( $definition );
		$is_active   = self::dashboard_plugin_matches_active_marker( $definition );

		if ( ! $is_active && '' !== $plugin_file && function_exists( 'is_plugin_active' ) ) {
			$is_active = is_plugin_active( $plugin_file );
		}

		$installed      = $is_active || '' !== $plugin_file;
		$can_activate   = $installed && ! $is_active && '' !== $plugin_file && current_user_can( 'activate_plugins' );
		$activation_url = '';

		if ( $installed && ! $is_active ) {
			if ( $can_activate ) {
				$activation_url = wp_nonce_url(
					add_query_arg(
						[
							'action' => 'activate',
							'plugin' => $plugin_file,
						],
						admin_url( 'plugins.php' )
					),
					'activate-plugin_' . $plugin_file
				);
			} else {
				$activation_url = admin_url( 'plugins.php' );
			}
		}

		return [
			'active'         => $is_active,
			'installed'      => $installed,
			'plugin_file'    => $plugin_file,
			'can_activate'   => $can_activate,
			'activation_url' => $activation_url,
		];
	}

	protected static function ensure_plugin_api_loaded() {
		if ( function_exists( 'get_plugins' ) && function_exists( 'is_plugin_active' ) ) {
			return;
		}

		$plugin_api = trailingslashit( ABSPATH ) . 'wp-admin/includes/plugin.php';
		if ( file_exists( $plugin_api ) ) {
			require_once $plugin_api;
		}
	}

	protected static function find_dashboard_plugin_basename( $definition ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			return '';
		}

		$plugins = get_plugins();
		if ( empty( $plugins ) || ! is_array( $plugins ) ) {
			return '';
		}

		$candidate_basenames = isset( $definition['basenames'] ) && is_array( $definition['basenames'] ) ? $definition['basenames'] : [];
		foreach ( $candidate_basenames as $basename ) {
			if ( isset( $plugins[ $basename ] ) ) {
				return (string) $basename;
			}
		}

		$expected_names = isset( $definition['plugin_names'] ) && is_array( $definition['plugin_names'] ) ? $definition['plugin_names'] : [];
		$text_domains   = isset( $definition['text_domains'] ) && is_array( $definition['text_domains'] ) ? $definition['text_domains'] : [];

		$expected_names = array_map( [ __CLASS__, 'normalize_dashboard_plugin_match_value' ], $expected_names );
		$text_domains   = array_map( [ __CLASS__, 'normalize_dashboard_plugin_match_value' ], $text_domains );

		foreach ( $plugins as $basename => $headers ) {
			$name        = self::normalize_dashboard_plugin_match_value( isset( $headers['Name'] ) ? $headers['Name'] : '' );
			$text_domain = self::normalize_dashboard_plugin_match_value( isset( $headers['TextDomain'] ) ? $headers['TextDomain'] : '' );

			if ( '' !== $name && in_array( $name, $expected_names, true ) ) {
				return (string) $basename;
			}

			if ( '' !== $text_domain && in_array( $text_domain, $text_domains, true ) ) {
				return (string) $basename;
			}
		}

		return '';
	}

	protected static function dashboard_plugin_matches_active_marker( $definition ) {
		$post_types = isset( $definition['active_post_types'] ) && is_array( $definition['active_post_types'] ) ? $definition['active_post_types'] : [];
		foreach ( $post_types as $post_type ) {
			if ( post_type_exists( $post_type ) ) {
				return true;
			}
		}

		$classes = isset( $definition['active_classes'] ) && is_array( $definition['active_classes'] ) ? $definition['active_classes'] : [];
		foreach ( $classes as $class_name ) {
			if ( class_exists( $class_name, false ) ) {
				return true;
			}
		}

		$constants = isset( $definition['active_constants'] ) && is_array( $definition['active_constants'] ) ? $definition['active_constants'] : [];
		foreach ( $constants as $constant_name ) {
			if ( defined( $constant_name ) ) {
				return true;
			}
		}

		return false;
	}

	protected static function normalize_dashboard_plugin_match_value( $value ) {
		$value = strtolower( trim( (string) $value ) );

		return preg_replace( '/[^a-z0-9]+/', '', $value );
	}

	protected static function get_dashboard_checklist_items( $members_summary, $notices_summary, $system_summary, $menu_summary, $settings_summary, $payments_summary ) {
		return [
			[
				'label'       => __( 'Create or confirm your system pages', 'tpw-core' ),
				'description' => __( 'Make sure the required member and control pages are linked and published.', 'tpw-core' ),
				'done'        => ! empty( $system_summary['required_complete'] ),
				'url'         => admin_url( self::SYSTEM_PAGES_ROUTE ),
			],
			[
				'label'       => __( 'Add your first members', 'tpw-core' ),
				'description' => __( 'Start building the club member register and linked accounts.', 'tpw-core' ),
				'done'        => ! empty( $members_summary['count'] ),
				'url'         => self::get_members_management_url( 'add' ),
			],
			[
				'label'       => __( 'Configure menu permissions', 'tpw-core' ),
				'description' => __( 'Control which audiences can see and access club navigation items.', 'tpw-core' ),
				'done'        => ! empty( $menu_summary['configured'] ),
				'url'         => self::get_tpw_control_launch_url( 'menu-manager', self::PAGE_MENU_MANAGER ),
			],
			[
				'label'       => __( 'Configure settings', 'tpw-core' ),
				'description' => __( 'Review branding, login, and shared FlexiClub platform settings.', 'tpw-core' ),
				'done'        => ! empty( $settings_summary['configured'] ),
				'url'         => self::get_settings_admin_url(),
			],
			[
				'label'       => __( 'Configure payments', 'tpw-core' ),
				'description' => __( 'Enable and set up the payment methods your club wants to offer.', 'tpw-core' ),
				'done'        => ! empty( $payments_summary['configured'] ) || ! empty( $payments_summary['optional'] ),
				'url'         => ! empty( $payments_summary['action_url'] ) ? $payments_summary['action_url'] : self::get_settings_admin_url(),
				'optional'    => ! empty( $payments_summary['optional'] ),
			],
			[
				'label'       => __( 'Publish your first notice', 'tpw-core' ),
				'description' => __( 'Share updates, reminders, and announcements from the Noticeboard.', 'tpw-core' ),
				'done'        => ! empty( $notices_summary['count'] ),
				'url'         => admin_url( 'post-new.php?post_type=tpw_notice' ),
			],
		];
	}

	protected static function get_dashboard_activity_items() {
		$items = [];

		foreach ( self::get_recent_member_activity() as $entry ) {
			$items[] = $entry;
		}

		foreach ( self::get_recent_notice_activity() as $entry ) {
			$items[] = $entry;
		}

		foreach ( self::get_recent_email_log_activity() as $entry ) {
			$items[] = $entry;
		}

		foreach ( self::get_recent_payment_log_activity() as $entry ) {
			$items[] = $entry;
		}

		usort(
			$items,
			static function( $left, $right ) {
				return (int) $right['timestamp'] <=> (int) $left['timestamp'];
			}
		);

		$items = array_slice( $items, 0, 6 );

		if ( empty( $items ) ) {
			$items[] = [
				'title'     => __( 'Activity will appear here as your club starts using FlexiClub.', 'tpw-core' ),
				'meta'      => __( 'System activity', 'tpw-core' ),
				'time'      => __( 'Just now', 'tpw-core' ),
				'timestamp' => time(),
			];
		}

		return $items;
	}

	protected static function get_dashboard_system_items( $members_summary, $system_summary, $payments_summary, $logs_summary ) {
		$items = [
			[
				'label' => __( 'Members module', 'tpw-core' ),
				'value' => $members_summary['status_label'],
				'tone'  => $members_summary['status_tone'],
			],
			[
				'label' => __( 'Required pages', 'tpw-core' ),
				'value' => $system_summary['status_label'],
				'tone'  => $system_summary['status_tone'],
			],
			[
				'label' => __( 'Recent logs', 'tpw-core' ),
				'value' => $logs_summary['status_label'],
				'tone'  => $logs_summary['status_tone'],
			],
		];

		if ( ! empty( $payments_summary['payments_required'] ) ) {
			array_splice(
				$items,
				2,
				0,
				[
					[
						'label' => __( 'Payment methods', 'tpw-core' ),
						'value' => $payments_summary['status_label'],
						'tone'  => $payments_summary['status_tone'],
					],
				]
			);
		}

		return $items;
	}

	protected static function get_members_summary() {
		global $wpdb;

		$count = null;
		if ( function_exists( 'tpw_core_members_table_exists' ) && tpw_core_members_table_exists() ) {
			$table = $wpdb->prefix . 'tpw_members';
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		return [
			'count'       => $count,
			'status_label'=> null === $count ? __( 'Missing', 'tpw-core' ) : ( $count > 0 ? __( 'Active', 'tpw-core' ) : __( 'Ready', 'tpw-core' ) ),
			'status_tone' => null === $count ? 'error' : ( $count > 0 ? 'success' : 'neutral' ),
			'metric_text' => null === $count
				? __( 'Members are not set up on this site yet.', 'tpw-core' )
				: sprintf(
					/* translators: %s: total members */
					_n( '%s member recorded', '%s members recorded', $count, 'tpw-core' ),
					number_format_i18n( $count )
				),
			'card_text'   => null === $count
				? __( 'The member register is not available yet.', 'tpw-core' )
				: __( 'Member records are ready to manage across the club workspace.', 'tpw-core' ),
		];
	}

	protected static function get_notices_summary() {
		$count = null;
		if ( post_type_exists( 'tpw_notice' ) ) {
			$counts = wp_count_posts( 'tpw_notice' );
			$count  = $counts ? (int) $counts->publish : 0;
		}

		return [
			'count'       => $count,
			'status_label'=> null === $count ? __( 'Missing', 'tpw-core' ) : ( $count > 0 ? __( 'Active', 'tpw-core' ) : __( 'Ready', 'tpw-core' ) ),
			'status_tone' => null === $count ? 'error' : ( $count > 0 ? 'success' : 'neutral' ),
			'metric_text' => null === $count
				? __( 'Noticeboard data is currently unavailable.', 'tpw-core' )
				: sprintf(
					/* translators: %s: published notices */
					_n( '%s published notice', '%s published notices', $count, 'tpw-core' ),
					number_format_i18n( $count )
				),
			'card_text'   => null === $count
				? __( 'Open the Noticeboard when the content type is available.', 'tpw-core' )
				: ( $count > 0
					? __( 'Share updates, reminders, and club announcements from one place.', 'tpw-core' )
					: __( 'The Noticeboard is ready for your next club update.', 'tpw-core' ) ),
		];
	}

	protected static function get_events_summary() {
		global $wpdb;

		$table_name   = $wpdb->prefix . 'tpw_events';
		$is_active    = self::is_flexievent_active();
		$table_exists = self::table_exists( $table_name );
		$event_count  = __( 'Not installed', 'tpw-core' );
		$metric_text  = __( 'Install FlexiEvent to manage club events.', 'tpw-core' );
		$action_label = __( 'Add FlexiEvent', 'tpw-core' );
		$action_url   = '#tpw-flexiclub-extend';

		if ( $is_active ) {
			$event_count  = __( 'FlexiEvent active', 'tpw-core' );
			$metric_text  = __( 'FlexiEvent is active.', 'tpw-core' );
			$action_label = __( 'View events', 'tpw-core' );
			$action_url   = admin_url( 'edit.php?post_type=tpw_event' );
		}

		if ( $is_active && $table_exists ) {
			$today = current_time( 'Y-m-d' );
			$query = $wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$table_name} e
				 INNER JOIN {$wpdb->posts} p ON p.ID = e.post_id
				 WHERE e.event_begin_date >= %s
				 AND p.post_type = %s
				 AND p.post_status = %s",
				$today,
				'tpw_event',
				'publish'
			);
			$raw_count = $wpdb->get_var( $query );

			if ( null !== $raw_count ) {
				$event_count = (int) $raw_count;
				$metric_text = $event_count > 0
					? sprintf(
						/* translators: %s: number of upcoming events */
						__( '%s upcoming events scheduled.', 'tpw-core' ),
						number_format_i18n( $event_count )
					)
					: __( 'No upcoming events at this time.', 'tpw-core' );
			} else {
				$event_count = __( 'FlexiEvent active', 'tpw-core' );
				$metric_text = __( 'FlexiEvent is active, but the upcoming event count is not available right now.', 'tpw-core' );
			}
		}

		return [
			'count'        => $event_count,
			'metric_text'  => $metric_text,
			'action_label' => $action_label,
			'action_url'   => $action_url,
		];
	}

	protected static function get_system_pages_summary() {
		if ( ! class_exists( 'TPW_Core_System_Pages' ) || ! method_exists( 'TPW_Core_System_Pages', 'get_all' ) ) {
			return [
				'configured_count' => null,
				'registered_total' => 0,
				'required_complete'=> false,
				'status_label'     => __( 'Missing', 'tpw-core' ),
				'status_tone'      => 'error',
				'metric_text'      => __( 'Review the current system page assignments for FlexiClub and add-on features.', 'tpw-core' ),
				'metric_value'     => __( 'Missing', 'tpw-core' ),
				'card_text'        => __( 'The full registered system-page set could not be resolved safely on this request.', 'tpw-core' ),
			];
		}

		$rows             = TPW_Core_System_Pages::get_all();
		$registered_total = 0;
		$configured_count = 0;

		foreach ( (array) $rows as $row ) {
			if ( ! is_object( $row ) || ! isset( $row->slug ) ) {
				continue;
			}

			$registered_total++;
			$page_id   = isset( $row->wp_page_id ) ? (int) $row->wp_page_id : 0;
			$published = $page_id > 0 && 'publish' === get_post_status( $page_id );

			if ( $published ) {
				$configured_count++;
			}
		}

		if ( $registered_total < 1 ) {
			return [
				'configured_count' => null,
				'registered_total' => 0,
				'required_complete'=> false,
				'status_label'     => __( 'Missing', 'tpw-core' ),
				'status_tone'      => 'error',
				'metric_text'      => __( 'Review the current system page assignments for FlexiClub and add-on features.', 'tpw-core' ),
				'metric_value'     => __( 'Missing', 'tpw-core' ),
				'card_text'        => __( 'No registered system pages were found in the resolved ecosystem registry.', 'tpw-core' ),
			];
		}

		$complete      = $registered_total === $configured_count;
		$missing_count = $registered_total - $configured_count;
		$status_label  = $complete ? __( 'Complete', 'tpw-core' ) : __( 'Needs review', 'tpw-core' );
		$status_tone   = $complete ? 'success' : 'warning';
		$metric_value = sprintf(
			/* translators: 1: configured pages, 2: registered pages */
			__( '%1$s / %2$s', 'tpw-core' ),
			number_format_i18n( $configured_count ),
			number_format_i18n( $registered_total )
		);

		return [
			'configured_count' => $configured_count,
			'registered_total' => $registered_total,
			'required_complete'=> $complete,
			'status_label'     => $status_label,
			'status_tone'      => $status_tone,
			'metric_text'      => sprintf(
				/* translators: 1: configured pages, 2: registered pages */
				__( '%1$s of %2$s registered system pages are linked.', 'tpw-core' ),
				number_format_i18n( $configured_count ),
				number_format_i18n( $registered_total )
			),
			'metric_value'     => $metric_value,
			'card_text'        => $complete
				? __( 'All required system pages are published and ready to use.', 'tpw-core' )
				: sprintf(
					/* translators: 1: configured pages, 2: missing pages */
					_n( '%1$s required page is ready and %2$s still needs review.', '%1$s required pages are ready and %2$s still need review.', $missing_count, 'tpw-core' ),
					number_format_i18n( $configured_count ),
					number_format_i18n( $missing_count )
				),
		];
	}

	protected static function get_gallery_summary() {
		global $wpdb;

		$status      = self::get_safe_system_page_status( 'gallery-admin', 'tpw_gallery_admin' );
		$galleries   = 0;
		$image_count = 0;

		$galleries_table = $wpdb->prefix . 'tpw_galleries';
		$images_table    = $wpdb->prefix . 'tpw_gallery_images';
		if ( self::table_exists( $galleries_table ) ) {
			$galleries = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$galleries_table}" );
		}
		if ( self::table_exists( $images_table ) ) {
			$image_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$images_table}" );
		}

		$ready = '' !== $status['open_url'];

		$has_content = $galleries > 0 || $image_count > 0;

		return [
			'status_label' => $ready ? ( $has_content ? __( 'Active', 'tpw-core' ) : __( 'Ready', 'tpw-core' ) ) : __( 'Needs review', 'tpw-core' ),
			'status_tone'  => $ready ? ( $has_content ? 'success' : 'neutral' ) : 'warning',
			'metric_value' => self::format_metric_value( $image_count ),
			'card_text'    => $ready
				? __( 'Manage gallery collections and image libraries for club content.', 'tpw-core' )
				: __( 'The Gallery Admin page needs to be checked before launch.', 'tpw-core' ),
		];
	}

	protected static function get_upload_pages_summary() {
		global $wpdb;

		$pages_table = $wpdb->prefix . 'tpw_upload_pages';
		$files_table = $wpdb->prefix . 'tpw_upload_pages_files';
		$page_count  = self::table_exists( $pages_table ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pages_table}" ) : 0;
		$file_count  = self::table_exists( $files_table ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$files_table}" ) : 0;
		$registered  = self::tpw_control_section_is_registered( 'upload-pages' );
		$page_status = self::locate_shortcode_page( 'tpw-control', 'tpw-control' );
		$ready       = $registered && ! empty( $page_status['page_exists'] ) && ! empty( $page_status['shortcode_present'] );
		$has_usage   = $page_count > 0 || $file_count > 0;

		return [
			'status_label' => $ready ? ( $has_usage ? __( 'Active', 'tpw-core' ) : __( 'Ready', 'tpw-core' ) ) : __( 'Needs review', 'tpw-core' ),
			'status_tone'  => $ready ? ( $has_usage ? 'success' : 'neutral' ) : 'warning',
			'metric_value' => self::format_metric_value( $page_count ),
			'card_text'    => ! $ready
				? __( 'The archive tools need review before members can rely on them.', 'tpw-core' )
				: ( $has_usage
					? sprintf(
						/* translators: 1: archive pages count, 2: archived file count */
						__( '%1$s archive pages and %2$s files are currently in use.', 'tpw-core' ),
						number_format_i18n( $page_count ),
						number_format_i18n( $file_count )
					)
					: __( 'Archive tools are available whenever you need to add upload and archive pages.', 'tpw-core' ) ),
		];
	}

	protected static function get_menu_permissions_summary() {
		global $wpdb;

		$menus_count          = function_exists( 'wp_get_nav_menus' ) ? count( wp_get_nav_menus() ) : 0;
		$configured_count     = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_tpw_visibility_json'
			)
		);
		$raw_rule_rows         = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_tpw_visibility_json'
			)
		);
		$invalid_rule_count    = 0;
		$valid_rule_item_count = 0;

		foreach ( $raw_rule_rows as $raw_rule ) {
			$parsed_rule = is_string( $raw_rule ) ? json_decode( $raw_rule, true ) : ( is_array( $raw_rule ) ? $raw_rule : null );

			if ( ! is_array( $parsed_rule ) ) {
				$invalid_rule_count++;
				continue;
			}

			$has_any_rule = false;
			foreach ( $parsed_rule as $rule_value ) {
				if ( is_array( $rule_value ) && ! empty( array_filter( $rule_value ) ) ) {
					$has_any_rule = true;
					break;
				}

				if ( ! is_array( $rule_value ) && '' !== (string) $rule_value && null !== $rule_value ) {
					$has_any_rule = true;
					break;
				}
			}

			if ( $has_any_rule ) {
				$valid_rule_item_count++;
			}
		}

		$configured = $valid_rule_item_count > 0;
		$status_label = $invalid_rule_count > 0
			? __( 'Needs review', 'tpw-core' )
			: ( $configured ? __( 'In use', 'tpw-core' ) : __( 'Ready', 'tpw-core' ) );
		$status_tone  = $invalid_rule_count > 0
			? 'warning'
			: ( $configured ? 'success' : 'neutral' );
		$card_text    = $invalid_rule_count > 0
			? __( 'Some menu permission rules could not be read and should be reviewed.', 'tpw-core' )
			: ( $configured
				? sprintf(
					/* translators: %s: count of protected menu items */
					_n( '%s menu item uses permission rules.', '%s menu items use permission rules.', $valid_rule_item_count, 'tpw-core' ),
					number_format_i18n( $valid_rule_item_count )
				)
				: __( 'Menu permissions are available when you need to restrict navigation.', 'tpw-core' ) );

		return [
			'configured'   => $configured,
			'invalid_rules' => $invalid_rule_count,
			'status_label' => $status_label,
			'status_tone'  => $status_tone,
			'metric_value' => self::format_metric_value( $menus_count ),
			'card_text'    => $card_text,
		];
	}

	protected static function get_payments_summary() {
		global $wpdb;

		$payments_required = function_exists( 'tpw_core_payments_required' ) && tpw_core_payments_required();
		$table             = $wpdb->prefix . 'tpw_payment_methods';
		$active_count      = 0;
		$configured_count  = 0;

		if ( self::table_exists( $table ) ) {
			$active_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE active = 1" );
			$configured_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		if ( ! $payments_required ) {
			return [
				'configured'   => false,
				'optional'     => true,
				'payments_required' => false,
				'status_label' => __( 'Inactive', 'tpw-core' ),
				'status_tone'  => 'warning',
				'metric_value' => __( 'Optional', 'tpw-core' ),
				'card_text'    => __( 'No payment-enabled modules are currently active.', 'tpw-core' ),
				'action_url'   => '',
			];
		}

		$status_label = $active_count > 0 ? __( 'Active', 'tpw-core' ) : __( 'Needs review', 'tpw-core' );
		$status_tone  = $active_count > 0 ? 'success' : 'warning';

		return [
			'configured'   => $active_count > 0,
			'optional'     => false,
			'payments_required' => true,
			'status_label' => $status_label,
			'status_tone'  => $status_tone,
			'metric_value' => self::format_metric_value( $active_count ),
			'card_text'    => $configured_count > 0
				? __( 'Club payment methods are configured for member payments and checkout.', 'tpw-core' )
				: __( 'No payment methods have been configured yet.', 'tpw-core' ),
			'action_url'   => admin_url( self::PAYMENTS_ROUTE ),
		];
	}

	protected static function get_settings_summary() {
		$theme_settings    = get_option( 'tpw_ui_theme_settings', [] );
		$default_login     = (int) get_option( 'tpw_core_default_login_page', 0 );
		$redirect_page     = (int) get_option( 'tpw_login_redirect_page_id', 0 );
		$configured        = ( is_array( $theme_settings ) && ! empty( array_filter( $theme_settings ) ) ) || $default_login > 0 || $redirect_page > 0;

		return [
			'configured'   => $configured,
			'status_label' => __( 'Ready', 'tpw-core' ),
			'status_tone'  => 'neutral',
			'metric_value' => __( 'Available', 'tpw-core' ),
			'card_text'    => $configured
				? __( 'Core branding, login, and platform settings are ready to review or refine.', 'tpw-core' )
				: __( 'Review the main FlexiClub settings to tailor the platform for your club.', 'tpw-core' ),
		];
	}

	protected static function get_settings_admin_url( $tab = '' ) {
		$args = [ 'page' => self::PAGE_SETTINGS ];

		if ( '' !== $tab ) {
			$args['tab'] = sanitize_key( $tab );
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	protected static function get_logs_summary() {
		global $wpdb;

		$email_table   = class_exists( 'TPW_Email_Logs' ) ? TPW_Email_Logs::table_name() : $wpdb->prefix . 'tpw_email_logs';
		$payment_table = $wpdb->prefix . 'tpw_payment_logs';
		$email_total   = self::table_exists( $email_table ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$email_table}" ) : 0;
		$payment_total = self::table_exists( $payment_table ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$payment_table}" ) : 0;
		$email_failed  = self::table_exists( $email_table ) ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$email_table} WHERE status = %s", 'failed' ) ) : 0;
		$payment_failed = self::table_exists( $payment_table ) ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$payment_table} WHERE status = %s", 'failed' ) ) : 0;
		$issue_count    = $email_failed + $payment_failed;

		return [
			'status_label' => $issue_count > 0 ? __( 'Needs review', 'tpw-core' ) : __( 'Healthy', 'tpw-core' ),
			'status_tone'  => $issue_count > 0 ? 'warning' : 'success',
			'metric_value' => self::format_metric_value( $email_total + $payment_total ),
			'card_text'    => $issue_count > 0
				? __( 'Recent operational logs need review for email or payment issues.', 'tpw-core' )
				: __( 'Email and payment logs are available for operational review.', 'tpw-core' ),
		];
	}

	protected static function get_members_management_url( $action = 'list' ) {
		$status = self::locate_shortcode_page( 'tpw_manage_members', 'manage-members' );
		if ( ! empty( $status['page_url'] ) && ! empty( $status['shortcode_present'] ) ) {
			return add_query_arg( 'action', sanitize_key( $action ), $status['page_url'] );
		}

		return admin_url( 'admin.php?page=' . self::PAGE_MEMBERS );
	}

	protected static function get_gallery_launch_url() {
		$status = self::get_safe_system_page_status( 'gallery-admin', 'tpw_gallery_admin' );
		if ( ! empty( $status['open_url'] ) ) {
			return $status['open_url'];
		}

		return admin_url( 'admin.php?page=' . self::PAGE_GALLERY );
	}

	protected static function get_safe_system_page_status( $system_slug, $shortcode_tag ) {
		if ( ! class_exists( 'TPW_Core_System_Pages' ) ) {
			return [
				'open_url' => '',
			];
		}

		$page_id = 0;
		foreach ( (array) TPW_Core_System_Pages::get_all() as $row ) {
			if ( isset( $row->slug ) && sanitize_key( $row->slug ) === sanitize_key( $system_slug ) ) {
				$page_id = isset( $row->wp_page_id ) ? (int) $row->wp_page_id : 0;
				break;
			}
		}

		$page = $page_id > 0 ? get_post( $page_id ) : null;
		if ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return [
				'open_url' => '',
			];
		}

		$has_shortcode = self::page_has_shortcode_tag( (string) $page->post_content, $shortcode_tag );

		return [
			'open_url' => $has_shortcode ? (string) get_permalink( $page ) : '',
		];
	}

	protected static function get_tpw_control_launch_url( $section, $fallback_page_slug ) {
		$status = self::build_tpw_control_status(
			[
				'section'    => $section,
				'shortcode'  => '[tpw-control]',
				'route_label'=> '',
			],
			true
		);

		if ( ! empty( $status['open_url'] ) ) {
			return $status['open_url'];
		}

		return admin_url( 'admin.php?page=' . $fallback_page_slug );
	}

	protected static function get_recent_member_activity() {
		global $wpdb;

		$items = [];
		if ( ! function_exists( 'tpw_core_members_table_exists' ) || ! tpw_core_members_table_exists() ) {
			return $items;
		}

		$table = $wpdb->prefix . 'tpw_members';
		$rows  = $wpdb->get_results( "SELECT first_name, surname, updated_at FROM {$table} WHERE updated_at IS NOT NULL ORDER BY updated_at DESC LIMIT 2" );

		foreach ( (array) $rows as $row ) {
			$timestamp = strtotime( (string) $row->updated_at );
			if ( ! $timestamp ) {
				continue;
			}

			$name = trim( (string) $row->first_name . ' ' . (string) $row->surname );
			$items[] = [
				'title'     => sprintf(
					/* translators: %s: member name */
					__( '%s profile updated', 'tpw-core' ),
					$name !== '' ? $name : __( 'Member', 'tpw-core' )
				),
				'meta'      => __( 'Members', 'tpw-core' ),
				'time'      => self::format_relative_time( $timestamp ),
				'timestamp' => $timestamp,
			];
		}

		return $items;
	}

	protected static function get_recent_notice_activity() {
		$items = [];
		if ( ! post_type_exists( 'tpw_notice' ) ) {
			return $items;
		}

		$notices = get_posts(
			[
				'post_type'      => 'tpw_notice',
				'post_status'    => 'publish',
				'posts_per_page' => 2,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		foreach ( $notices as $notice ) {
			$timestamp = get_post_time( 'U', true, $notice );
			if ( ! $timestamp ) {
				continue;
			}

			$items[] = [
				'title'     => sprintf(
					/* translators: %s: notice title */
					__( 'Notice published: %s', 'tpw-core' ),
					$notice->post_title
				),
				'meta'      => __( 'Noticeboard', 'tpw-core' ),
				'time'      => self::format_relative_time( $timestamp ),
				'timestamp' => $timestamp,
			];
		}

		return $items;
	}

	protected static function get_recent_email_log_activity() {
		global $wpdb;

		$items = [];
		$table = class_exists( 'TPW_Email_Logs' ) ? TPW_Email_Logs::table_name() : $wpdb->prefix . 'tpw_email_logs';
		if ( ! self::table_exists( $table ) ) {
			return $items;
		}

		$rows = $wpdb->get_results( "SELECT recipient, status, timestamp FROM {$table} ORDER BY timestamp DESC, id DESC LIMIT 2" );
		foreach ( (array) $rows as $row ) {
			$timestamp = strtotime( (string) $row->timestamp );
			if ( ! $timestamp ) {
				continue;
			}

			$items[] = [
				'title'     => 'failed' === (string) $row->status
					? __( 'Email delivery failed', 'tpw-core' )
					: __( 'Email sent successfully', 'tpw-core' ),
				'meta'      => (string) $row->recipient !== '' ? sprintf( __( 'Email to %s', 'tpw-core' ), (string) $row->recipient ) : __( 'Email logs', 'tpw-core' ),
				'time'      => self::format_relative_time( $timestamp ),
				'timestamp' => $timestamp,
			];
		}

		return $items;
	}

	protected static function get_recent_payment_log_activity() {
		global $wpdb;

		$items = [];
		$table = $wpdb->prefix . 'tpw_payment_logs';
		if ( ! self::table_exists( $table ) ) {
			return $items;
		}

		$rows = $wpdb->get_results( "SELECT reference, status, created_at FROM {$table} ORDER BY created_at DESC LIMIT 2" );
		foreach ( (array) $rows as $row ) {
			$timestamp = strtotime( (string) $row->created_at );
			if ( ! $timestamp ) {
				continue;
			}

			$items[] = [
				'title'     => 'failed' === (string) $row->status
					? __( 'Payment log requires review', 'tpw-core' )
					: __( 'Payment activity recorded', 'tpw-core' ),
				'meta'      => (string) $row->reference !== '' ? sprintf( __( 'Reference %s', 'tpw-core' ), (string) $row->reference ) : __( 'Payment logs', 'tpw-core' ),
				'time'      => self::format_relative_time( $timestamp ),
				'timestamp' => $timestamp,
			];
		}

		return $items;
	}

	protected static function get_dashboard_logo_url() {
		return self::get_plugin_icon_url( 'flexiclub-logo-horizontal.svg' );
	}

	protected static function get_dashboard_icon_url() {
		$icon = self::get_plugin_icon_url( 'flexiclub-logo-icon.svg' );
		if ( '' !== $icon ) {
			return $icon;
		}

		return self::get_plugin_icon_url( 'flexiclub-icon.svg' );
	}

	protected static function get_plugin_icon_url( $filename ) {
		if ( ! defined( 'TPW_CORE_PATH' ) || ! defined( 'TPW_CORE_URL' ) ) {
			return '';
		}

		$path = TPW_CORE_PATH . 'assets/images/' . ltrim( (string) $filename, '/' );
		if ( ! file_exists( $path ) ) {
			return '';
		}

		return TPW_CORE_URL . 'assets/images/' . ltrim( (string) $filename, '/' );
	}

	protected static function table_exists( $table_name ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	protected static function format_metric_value( $value, $allow_placeholder = true ) {
		if ( null === $value ) {
			return $allow_placeholder ? '—' : __( 'Not available', 'tpw-core' );
		}

		if ( is_numeric( $value ) ) {
			return number_format_i18n( (int) $value );
		}

		return (string) $value;
	}

	protected static function format_relative_time( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return __( 'Recently', 'tpw-core' );
		}

		return sprintf(
			/* translators: %s: relative time string */
			__( '%s ago', 'tpw-core' ),
			human_time_diff( $timestamp, current_time( 'timestamp' ) )
		);
	}

	protected static function render_page_start( $title, $subtitle = '' ) {
		if ( function_exists( 'tpw_core_output_header' ) ) {
			tpw_core_output_header( $title, $subtitle );
		}

		echo '<div class="tpw-admin-ui" style="' . esc_attr( function_exists( 'tpw_core_build_ui_theme_style_attr' ) ? tpw_core_build_ui_theme_style_attr() : '' ) . '">';
		echo '<div class="wrap">';
	}

	protected static function render_page_end() {
		echo '</div>';
		echo '</div>';
	}

	protected static function render_bridge_notice() {
		$notice = isset( $_GET['tpw_flexiclub_notice'] ) ? sanitize_key( wp_unslash( $_GET['tpw_flexiclub_notice'] ) ) : '';
		if ( '' === $notice ) {
			return;
		}

		if ( 'repair_success' === $notice ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'The front-end page was created or repaired.', 'tpw-core' ) . '</p></div>';
			return;
		}

		if ( 'repair_failed' === $notice ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The front-end page could not be created or repaired.', 'tpw-core' ) . '</p></div>';
		}
	}
}
