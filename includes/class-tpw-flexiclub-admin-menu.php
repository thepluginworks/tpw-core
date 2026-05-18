<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_FlexiClub_Admin_Menu {
	const TOP_LEVEL_SLUG      = 'tpw-flexiclub-dashboard';
	const PAGE_MEMBERS        = 'tpw-flexiclub-manage-members';
	const PAGE_GALLERY        = 'tpw-flexiclub-gallery-admin';
	const PAGE_UPLOADS        = 'tpw-flexiclub-upload-pages';
	const PAGE_MENU_MANAGER   = 'tpw-flexiclub-menu-manager';
	const PAGE_LOGS           = 'tpw-flexiclub-logs';
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
		add_action( 'admin_init', [ __CLASS__, 'handle_bridge_actions' ] );
		add_filter( 'tpw_core_menu_map', [ __CLASS__, 'filter_menu_map' ] );
	}

	public static function register_menu() {
		$visible_items = self::get_visible_items();
		if ( empty( $visible_items ) ) {
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

		add_submenu_page(
			self::TOP_LEVEL_SLUG,
			__( 'Dashboard', 'tpw-core' ),
			__( 'Dashboard', 'tpw-core' ),
			'read',
			self::TOP_LEVEL_SLUG,
			[ __CLASS__, 'render_dashboard' ]
		);

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
				self::SETTINGS_ROUTE
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
		$items = self::get_dashboard_items();

		self::render_page_start(
			__( 'FlexiClub Dashboard', 'tpw-core' ),
			__( 'Open the current FlexiClub admin screens and front-end management tools from one place.', 'tpw-core' )
		);

		echo '<div class="tpw-card">';
		echo '<p>' . esc_html__( 'This dashboard is a navigation hub. Existing routes and implementations remain in place; front-end-only tools open via bridge pages.', 'tpw-core' ) . '</p>';
		echo '</div>';

		echo '<div class="tpw-card">';
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Area', 'tpw-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'tpw-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Open', 'tpw-core' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $items as $item ) {
			echo '<tr>';
			echo '<td><strong>' . esc_html( $item['label'] ) . '</strong><br />' . esc_html( $item['description'] ) . '</td>';
			echo '<td>' . esc_html( $item['type'] ) . '</td>';
			echo '<td><a class="button button-secondary" href="' . esc_url( $item['url'] ) . '">' . esc_html__( 'Open', 'tpw-core' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>';

		self::render_page_end();
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

	public static function filter_menu_map( $map ) {
		$map = is_array( $map ) ? $map : [];

		$map[] = [
			'query'        => [ 'page' => 'tpw-core-settings', 'tab' => 'payment-methods' ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::PAYMENTS_ROUTE,
		];

		$map[] = [
			'query'        => [ 'page' => 'tpw-core-settings', 'tab' => 'system-pages' ],
			'parent_slug'  => self::TOP_LEVEL_SLUG,
			'submenu_slug' => self::SYSTEM_PAGES_ROUTE,
		];

		$map[] = [
			'query'        => [ 'page' => 'tpw-core-settings', 'tab' => 'email-logs' ],
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
			'submenu_slug' => self::SETTINGS_ROUTE,
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
				'url'         => admin_url( 'admin.php?page=' . self::PAGE_MEMBERS ),
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
				'url'         => admin_url( 'admin.php?page=' . self::PAGE_GALLERY ),
			];
		}

		if ( in_array( self::PAGE_UPLOADS, $visible, true ) ) {
			$items[] = [
				'label'       => __( 'Upload Pages / Archive', 'tpw-core' ),
				'description' => __( 'Bridge to the existing archive and upload-pages feature on its current front-end compatibility route.', 'tpw-core' ),
				'type'        => __( 'Bridge', 'tpw-core' ),
				'url'         => admin_url( 'admin.php?page=' . self::PAGE_UPLOADS ),
			];
		}

		if ( in_array( self::PAGE_MENU_MANAGER, $visible, true ) ) {
			$items[] = [
				'label'       => __( 'Menu Permissions', 'tpw-core' ),
				'description' => __( 'Bridge to the front-end feature that controls WordPress menu visibility and permissions across the FlexiClub ecosystem.', 'tpw-core' ),
				'type'        => __( 'Bridge', 'tpw-core' ),
				'url'         => admin_url( 'admin.php?page=' . self::PAGE_MENU_MANAGER ),
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
				'url'         => admin_url( self::SETTINGS_ROUTE ),
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
			'repair_supported' => $status['repair_supported'],
			'repair_slug'      => $status['repair_slug'],
			'message'          => $status['message'],
		];
	}

	protected static function build_tpw_control_status( $config, $with_section ) {
		$status = self::locate_shortcode_page( 'tpw-control', 'tpw-control' );
		$open   = '';
		if ( $status['page_url'] !== '' ) {
			$open = $status['page_url'];
			if ( $with_section ) {
				$open = add_query_arg( 'action', (string) $config['section'], $open );
			}
		}

		$section_text = '';
		if ( $with_section ) {
			$section_text = self::tpw_control_section_is_registered( (string) $config['section'] )
				? __( 'Registered on the existing compatibility route.', 'tpw-core' )
				: __( 'This feature section is not currently registered on the existing compatibility route.', 'tpw-core' );
		}

		$message = '';
		if ( ! $status['page_exists'] ) {
			$message = __( 'No compatible front-end page is currently configured. Page creation or repair is not yet supported from this bridge.', 'tpw-core' );
		} elseif ( ! $status['shortcode_present'] ) {
			$message = __( 'A compatible front-end page exists, but the expected shortcode is missing from its content. Page creation or repair is not yet supported from this bridge.', 'tpw-core' );
		}

		return [
			'page_text'        => $status['page_text'],
			'shortcode'        => (string) $config['shortcode'],
			'shortcode_text'   => $status['shortcode_text'],
			'route_text'       => isset( $config['route_label'] ) ? (string) $config['route_label'] : '',
			'section_text'     => $section_text,
			'open_url'         => $open,
			'edit_url'         => $status['edit_url'],
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
			'open_url'         => $status['page_url'],
			'edit_url'         => $status['edit_url'],
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