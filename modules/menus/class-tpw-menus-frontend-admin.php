<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Menus_Frontend_Admin {
	const PAGE_SLUG = 'dining-menus';
	const SAVE_ACTION = 'tpw_fe_save_dining_menu';
	const DELETE_ACTION = 'tpw_fe_delete_dining_menu';
	const SAVE_CHOICE_ACTION = 'tpw_fe_save_course_choice';
	const DELETE_CHOICE_ACTION = 'tpw_fe_delete_course_choice';
	const RENAME_COURSE_ACTION = 'tpw_fe_rename_menu_course';

	public static function init() {
		add_filter( 'flexievent_fe_register_pages', [ __CLASS__, 'register_page' ] );
		add_action( 'admin_post_' . self::SAVE_ACTION, [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_post_' . self::DELETE_ACTION, [ __CLASS__, 'handle_delete' ] );
		add_action( 'admin_post_' . self::SAVE_CHOICE_ACTION, [ __CLASS__, 'handle_save_choice' ] );
		add_action( 'admin_post_' . self::DELETE_CHOICE_ACTION, [ __CLASS__, 'handle_delete_choice' ] );
		add_action( 'admin_post_' . self::RENAME_COURSE_ACTION, [ __CLASS__, 'handle_rename_course' ] );
	}

	public static function register_page( $pages ) {
		if ( ! self::is_enabled() ) {
			return $pages;
		}

		$pages = is_array( $pages ) ? $pages : [];
		$pages[ self::PAGE_SLUG ] = [
			'label'           => __( 'Dining Menus', 'tpw-core' ),
			'capability'      => 'manage_options',
			'default_view'    => 'list',
			'allowed_views'   => [ 'list', 'add', 'edit', 'course-choices', 'course-choice' ],
			'show_in_nav'     => true,
			'nav_order'       => 45,
			'nav_key'         => 'dining-menus',
			'variant'         => 'secondary',
			'render_callback' => [ __CLASS__, 'render_page' ],
			'notice_messages' => [
				'created'             => __( 'Dining menu created.', 'tpw-core' ),
				'updated'             => __( 'Dining menu updated.', 'tpw-core' ),
				'deleted'             => __( 'Dining menu deleted.', 'tpw-core' ),
				'choice_created'      => __( 'Course choice created.', 'tpw-core' ),
				'choice_updated'      => __( 'Course choice updated.', 'tpw-core' ),
				'choice_deleted'      => __( 'Course choice deleted.', 'tpw-core' ),
				'course_name_updated' => __( 'Course name updated.', 'tpw-core' ),
			],
			'error_messages'  => [
				'missing_menu_name'    => __( 'Menu name is required.', 'tpw-core' ),
				'missing_choice_label' => __( 'Dish name is required.', 'tpw-core' ),
				'invalid_menu'         => __( 'The selected dining menu is invalid.', 'tpw-core' ),
				'invalid_choice'       => __( 'The selected course choice is invalid.', 'tpw-core' ),
				'invalid_course'       => __( 'The selected course is invalid.', 'tpw-core' ),
				'invalid_nonce'        => __( 'Security check failed. Please try again.', 'tpw-core' ),
				'no_access'            => __( 'You do not have permission to manage dining menus.', 'tpw-core' ),
				'save_failed'          => __( 'The dining menu could not be saved.', 'tpw-core' ),
				'delete_failed'        => __( 'The dining menu could not be deleted.', 'tpw-core' ),
				'save_choice_failed'   => __( 'The course choice could not be saved.', 'tpw-core' ),
				'delete_choice_failed' => __( 'The course choice could not be deleted.', 'tpw-core' ),
				'rename_course_failed' => __( 'The course name could not be updated.', 'tpw-core' ),
			],
		];

		return $pages;
	}

	public static function render_page( $context ) {
		if ( ! self::is_enabled() ) {
			echo '<div class="flexievent-card"><p>' . esc_html__( 'Dining Menus is not currently available.', 'tpw-core' ) . '</p></div>';
			return;
		}

		$context       = is_array( $context ) ? $context : [];
		$view          = isset( $context['view'] ) ? sanitize_key( (string) $context['view'] ) : 'list';
		$base_url      = isset( $context['base_url'] ) ? (string) $context['base_url'] : '';
		$menu_id       = isset( $_GET['menu_id'] ) ? absint( wp_unslash( $_GET['menu_id'] ) ) : 0;
		$course_number = isset( $_GET['course_number'] ) ? absint( wp_unslash( $_GET['course_number'] ) ) : 0;
		$choice_id     = isset( $_GET['choice_id'] ) ? absint( wp_unslash( $_GET['choice_id'] ) ) : 0;

		if ( 'add' === $view ) {
			self::render_form_view( $base_url, 0 );
			return;
		}

		if ( 'edit' === $view ) {
			self::render_form_view( $base_url, $menu_id );
			return;
		}

		if ( 'course-choices' === $view ) {
			self::render_course_choices_view( $base_url, $menu_id );
			return;
		}

		if ( 'course-choice' === $view ) {
			self::render_course_choice_form_view( $base_url, $menu_id, $course_number, $choice_id );
			return;
		}

		self::render_list_view( $base_url );
	}

	public static function handle_save() {
		$base_url = self::resolve_base_url_from_post();
		$menu_id  = isset( $_POST['menu_id'] ) ? absint( wp_unslash( $_POST['menu_id'] ) ) : 0;

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'error' => 'no_access' ] ) );
			exit;
		}

		$nonce = isset( $_POST['tpw_fe_dining_menu_nonce'] ) ? (string) wp_unslash( $_POST['tpw_fe_dining_menu_nonce'] ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::SAVE_ACTION . '_' . $menu_id ) ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'view' => $menu_id > 0 ? 'edit' : 'add', 'menu_id' => $menu_id, 'error' => 'invalid_nonce' ] ) );
			exit;
		}

		$name              = isset( $_POST['menu_name'] ) ? sanitize_text_field( wp_unslash( $_POST['menu_name'] ) ) : '';
		$description       = isset( $_POST['menu_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['menu_description'] ) ) : '';
		$number_of_courses = isset( $_POST['number_of_courses'] ) ? max( 1, min( 30, (int) wp_unslash( $_POST['number_of_courses'] ) ) ) : 3;
		$price             = isset( $_POST['price'] ) ? (float) wp_unslash( $_POST['price'] ) : 0.0;

		if ( '' === $name ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'view' => $menu_id > 0 ? 'edit' : 'add', 'menu_id' => $menu_id, 'error' => 'missing_menu_name' ] ) );
			exit;
		}

		if ( $menu_id > 0 ) {
			$menu = TPW_Menus_Manager::get_menu_by_id( $menu_id );
			if ( ! $menu ) {
				wp_safe_redirect( self::page_url( $base_url, [ 'error' => 'invalid_menu' ] ) );
				exit;
			}

			$result = TPW_Menus_Manager::update_menu( $menu_id, $name, $description, $number_of_courses, $price );
			if ( false === $result ) {
				wp_safe_redirect( self::page_url( $base_url, [ 'view' => 'edit', 'menu_id' => $menu_id, 'error' => 'save_failed' ] ) );
				exit;
			}

			wp_safe_redirect( self::page_url( $base_url, [ 'view' => 'edit', 'menu_id' => $menu_id, 'notice' => 'updated' ] ) );
			exit;
		}

		$inserted_id = TPW_Menus_Saver::save_menu( $name, $description, $number_of_courses, $price );
		if ( ! $inserted_id ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'view' => 'add', 'error' => 'save_failed' ] ) );
			exit;
		}

		wp_safe_redirect( self::page_url( $base_url, [ 'view' => 'course-choices', 'menu_id' => (int) $inserted_id, 'notice' => 'created' ] ) );
		exit;
	}

	public static function handle_delete() {
		$base_url = self::resolve_base_url_from_post();
		$menu_id  = isset( $_POST['menu_id'] ) ? absint( wp_unslash( $_POST['menu_id'] ) ) : 0;

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'error' => 'no_access' ] ) );
			exit;
		}

		$nonce = isset( $_POST['tpw_fe_delete_dining_menu_nonce'] ) ? (string) wp_unslash( $_POST['tpw_fe_delete_dining_menu_nonce'] ) : '';
		if ( $menu_id <= 0 || '' === $nonce || ! wp_verify_nonce( $nonce, self::DELETE_ACTION . '_' . $menu_id ) ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'error' => 'invalid_nonce' ] ) );
			exit;
		}

		$menu = TPW_Menus_Manager::get_menu_by_id( $menu_id );
		if ( ! $menu ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'error' => 'invalid_menu' ] ) );
			exit;
		}

		$result = TPW_Menus_Manager::delete_menu( $menu_id );
		if ( false === $result ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'error' => 'delete_failed' ] ) );
			exit;
		}

		wp_safe_redirect( self::page_url( $base_url, [ 'notice' => 'deleted' ] ) );
		exit;
	}

	public static function handle_save_choice() {
		$base_url      = self::resolve_base_url_from_post();
		$menu_id       = isset( $_POST['menu_id'] ) ? absint( wp_unslash( $_POST['menu_id'] ) ) : 0;
		$course_number = isset( $_POST['course_number'] ) ? absint( wp_unslash( $_POST['course_number'] ) ) : 0;
		$choice_id     = isset( $_POST['choice_id'] ) ? absint( wp_unslash( $_POST['choice_id'] ) ) : 0;

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'error' => 'no_access' ] ) );
			exit;
		}

		$nonce = isset( $_POST['tpw_fe_course_choice_nonce'] ) ? (string) wp_unslash( $_POST['tpw_fe_course_choice_nonce'] ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::SAVE_CHOICE_ACTION . '_' . $choice_id . '_' . $menu_id . '_' . $course_number ) ) {
			wp_safe_redirect( self::course_choice_url( $base_url, $menu_id, $course_number, $choice_id, [ 'error' => 'invalid_nonce' ] ) );
			exit;
		}

		$menu = self::get_valid_menu( $menu_id );
		if ( ! $menu ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'error' => 'invalid_menu' ] ) );
			exit;
		}

		if ( ! self::is_valid_course_number( $menu, $course_number ) ) {
			wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'error' => 'invalid_course' ] ) );
			exit;
		}

		$label       = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$extra_cost  = isset( $_POST['extra_cost'] ) ? (float) wp_unslash( $_POST['extra_cost'] ) : 0.0;

		if ( '' === $label ) {
			wp_safe_redirect( self::course_choice_url( $base_url, $menu_id, $course_number, $choice_id, [ 'error' => 'missing_choice_label' ] ) );
			exit;
		}

		if ( $choice_id > 0 ) {
			$choice = TPW_Course_Choices_Manager::get_choice_by_id( $choice_id );
			if ( ! $choice || (int) $choice->menu_id !== $menu_id || (int) $choice->course_number !== $course_number ) {
				wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'error' => 'invalid_choice' ] ) );
				exit;
			}

			$result = TPW_Course_Choices_Manager::update_choice( $choice_id, $label, $description, $extra_cost );
			if ( false === $result ) {
				wp_safe_redirect( self::course_choice_url( $base_url, $menu_id, $course_number, $choice_id, [ 'error' => 'save_choice_failed' ] ) );
				exit;
			}

			wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'notice' => 'choice_updated' ] ) );
			exit;
		}

		$inserted_id = TPW_Course_Choices_Manager::insert_choice( $menu_id, $course_number, $label, $description, $extra_cost );
		if ( ! $inserted_id ) {
			wp_safe_redirect( self::course_choice_url( $base_url, $menu_id, $course_number, 0, [ 'error' => 'save_choice_failed' ] ) );
			exit;
		}

		wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'notice' => 'choice_created' ] ) );
		exit;
	}

	public static function handle_delete_choice() {
		$base_url  = self::resolve_base_url_from_post();
		$menu_id   = isset( $_POST['menu_id'] ) ? absint( wp_unslash( $_POST['menu_id'] ) ) : 0;
		$choice_id = isset( $_POST['choice_id'] ) ? absint( wp_unslash( $_POST['choice_id'] ) ) : 0;

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'error' => 'no_access' ] ) );
			exit;
		}

		$nonce = isset( $_POST['tpw_fe_delete_course_choice_nonce'] ) ? (string) wp_unslash( $_POST['tpw_fe_delete_course_choice_nonce'] ) : '';
		if ( $menu_id <= 0 || $choice_id <= 0 || '' === $nonce || ! wp_verify_nonce( $nonce, self::DELETE_CHOICE_ACTION . '_' . $choice_id ) ) {
			wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'error' => 'invalid_nonce' ] ) );
			exit;
		}

		$choice = TPW_Course_Choices_Manager::get_choice_by_id( $choice_id );
		if ( ! $choice || (int) $choice->menu_id !== $menu_id ) {
			wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'error' => 'invalid_choice' ] ) );
			exit;
		}

		$result = TPW_Course_Choices_Manager::delete_choice( $choice_id );
		if ( false === $result ) {
			wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'error' => 'delete_choice_failed' ] ) );
			exit;
		}

		wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'notice' => 'choice_deleted' ] ) );
		exit;
	}

	public static function handle_rename_course() {
		$base_url      = self::resolve_base_url_from_post();
		$menu_id       = isset( $_POST['menu_id'] ) ? absint( wp_unslash( $_POST['menu_id'] ) ) : 0;
		$course_number = isset( $_POST['course_number'] ) ? absint( wp_unslash( $_POST['course_number'] ) ) : 0;

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'error' => 'no_access' ] ) );
			exit;
		}

		$nonce = isset( $_POST['tpw_fe_rename_course_nonce'] ) ? (string) wp_unslash( $_POST['tpw_fe_rename_course_nonce'] ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::RENAME_COURSE_ACTION . '_' . $menu_id . '_' . $course_number ) ) {
			wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'error' => 'invalid_nonce' ] ) );
			exit;
		}

		$menu = self::get_valid_menu( $menu_id );
		if ( ! $menu ) {
			wp_safe_redirect( self::page_url( $base_url, [ 'error' => 'invalid_menu' ] ) );
			exit;
		}

		if ( ! self::is_valid_course_number( $menu, $course_number ) ) {
			wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'error' => 'invalid_course' ] ) );
			exit;
		}

		$course_name = isset( $_POST['course_name'] ) ? sanitize_text_field( wp_unslash( $_POST['course_name'] ) ) : '';
		$result      = TPW_Menu_Courses_Manager::set_course_name( $menu_id, $course_number, $course_name );

		if ( false === $result ) {
			wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'error' => 'rename_course_failed' ] ) );
			exit;
		}

		wp_safe_redirect( self::course_choices_url( $base_url, $menu_id, [ 'notice' => 'course_name_updated' ] ) );
		exit;
	}

	private static function is_enabled() {
		return class_exists( 'TPW_Menus_Manager' )
			&& class_exists( 'TPW_Menus_Saver' )
			&& class_exists( 'TPW_Course_Choices_Manager' )
			&& class_exists( 'TPW_Menu_Courses_Manager' )
			&& apply_filters( 'tpw_show_dining_menu', false );
	}

	private static function render_list_view( $base_url ) {
		$all_menus      = TPW_Menus_Manager::get_all_menus();
		$total_items    = count( $all_menus );
		$page_size      = 10;
		$current_page   = isset( $_GET['fe_page'] ) ? max( 1, absint( wp_unslash( $_GET['fe_page'] ) ) ) : 1;
		$total_pages    = max( 1, (int) ceil( $total_items / $page_size ) );
		$current_page   = min( $current_page, $total_pages );
		$offset         = max( 0, ( $current_page - 1 ) * $page_size );
		$menus          = array_slice( $all_menus, $offset, $page_size );
		$pagination_url = self::page_url( $base_url, [ 'fe_page' => 999999999 ] );
		$pagination_links = $total_pages > 1
			? paginate_links(
				[
					'base'      => str_replace( '999999999', '%#%', $pagination_url ),
					'format'    => '',
					'current'   => $current_page,
					'total'     => $total_pages,
					'type'      => 'array',
					'prev_text' => __( 'Previous', 'tpw-core' ),
					'next_text' => __( 'Next', 'tpw-core' ),
				]
			)
			: [];

		echo '<div class="flexievent-toolbar">';
		echo '<div>';
		echo '<h3>' . esc_html__( 'Dining Menus', 'tpw-core' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Manage dining menus and the course choices used by dining-enabled events from the FlexiEvent front-end admin.', 'tpw-core' ) . '</p>';
		echo '</div>';
		echo '<a class="tpw-btn tpw-btn-primary" href="' . esc_url( self::page_url( $base_url, [ 'view' => 'add' ] ) ) . '">' . esc_html__( 'Add Menu', 'tpw-core' ) . '</a>';
		echo '</div>';

		echo '<div class="flexievent-card flexievent-list-card">';
		if ( ! empty( $menus ) ) {
			echo '<div class="flexievent-list-summary">';
			echo '<div>' . esc_html__( 'Each menu now links directly into course management so you can maintain dishes without leaving the FE admin.', 'tpw-core' ) . '</div>';
			echo '<div class="flexievent-list-summary-count">' . sprintf( esc_html__( '%d menus', 'tpw-core' ), $total_items ) . '</div>';
			echo '</div>';
			echo '<div class="flexievent-table-wrap">';
			echo '<table class="flexievent-table">';
			echo '<thead><tr><th>' . esc_html__( 'Menu', 'tpw-core' ) . '</th><th>' . esc_html__( 'Description', 'tpw-core' ) . '</th><th>' . esc_html__( 'Courses', 'tpw-core' ) . '</th><th>' . esc_html__( 'Price', 'tpw-core' ) . '</th><th>' . esc_html__( 'Actions', 'tpw-core' ) . '</th></tr></thead>';
			echo '<tbody>';
			foreach ( $menus as $menu ) {
				$edit_url    = self::page_url(
					$base_url,
					[
						'view'    => 'edit',
						'menu_id' => (int) $menu->id,
					]
				);
				$courses_url = self::course_choices_url( $base_url, (int) $menu->id );

				echo '<tr>';
				echo '<td data-label="' . esc_attr__( 'Menu', 'tpw-core' ) . '"><strong>' . esc_html( self::normalise_text( $menu->name ) ) . '</strong></td>';
				echo '<td data-label="' . esc_attr__( 'Description', 'tpw-core' ) . '">' . esc_html( self::normalise_text( $menu->description ) ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Courses', 'tpw-core' ) . '">' . esc_html( (string) $menu->number_of_courses ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Price', 'tpw-core' ) . '">£' . esc_html( number_format( (float) $menu->price, 2 ) ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Actions', 'tpw-core' ) . '">';
				echo '<div class="flexievent-inline-actions">';
				echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $courses_url ) . '">' . esc_html__( 'Courses', 'tpw-core' ) . '</a>';
				echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'tpw-core' ) . '</a>';
				echo '<form method="post" action="' . esc_url( self::admin_post_url() ) . '" onsubmit="return window.confirm(' . esc_attr( wp_json_encode( __( 'Delete this dining menu?', 'tpw-core' ) ) ) . ');" style="display:inline-block;margin:0;">';
				echo '<input type="hidden" name="action" value="' . esc_attr( self::DELETE_ACTION ) . '" />';
				echo '<input type="hidden" name="base_url" value="' . esc_attr( $base_url ) . '" />';
				echo '<input type="hidden" name="menu_id" value="' . esc_attr( (int) $menu->id ) . '" />';
				wp_nonce_field( self::DELETE_ACTION . '_' . (int) $menu->id, 'tpw_fe_delete_dining_menu_nonce' );
				echo '<button type="submit" class="tpw-btn tpw-btn-secondary flexievent-danger-button">' . esc_html__( 'Delete', 'tpw-core' ) . '</button>';
				echo '</form>';
				echo '</div>';
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody>';
			echo '</table>';
			echo '</div>';

			if ( ! empty( $pagination_links ) && $total_pages > 1 ) {
				$start_item = $total_items > 0 ? $offset + 1 : 0;
				$end_item   = min( $total_items, $offset + count( $menus ) );

				echo '<div class="flexievent-pagination" aria-label="' . esc_attr__( 'Dining menus pagination', 'tpw-core' ) . '">';
				echo '<div class="flexievent-pagination-summary">';
				echo esc_html(
					sprintf(
						__( 'Showing %1$d-%2$d of %3$d', 'tpw-core' ),
						$start_item,
						$end_item,
						$total_items
					)
				);
				echo '</div>';
				echo '<nav class="flexievent-pagination-links">';
				foreach ( $pagination_links as $pagination_link ) {
					echo wp_kses_post( $pagination_link );
				}
				echo '</nav>';
				echo '</div>';
			}
		} else {
			echo '<div class="flexievent-empty-state">';
			echo '<p>' . esc_html__( 'No dining menus have been created yet.', 'tpw-core' ) . '</p>';
			echo '<p><a class="tpw-btn tpw-btn-primary" href="' . esc_url( self::page_url( $base_url, [ 'view' => 'add' ] ) ) . '">' . esc_html__( 'Create your first menu', 'tpw-core' ) . '</a></p>';
			echo '</div>';
		}
		echo '</div>';
	}

	private static function render_form_view( $base_url, $menu_id ) {
		$menu       = null;
		$is_edit    = $menu_id > 0;
		$page_url   = self::page_url( $base_url );
		$form_url   = self::admin_post_url();
		$page_title = $is_edit ? __( 'Edit Dining Menu', 'tpw-core' ) : __( 'Add Dining Menu', 'tpw-core' );
		$page_text  = $is_edit
			? __( 'Update the menu details used by your dining-enabled events.', 'tpw-core' )
			: __( 'Create a dining menu with the core details needed for event setup.', 'tpw-core' );

		if ( $is_edit ) {
			$menu = TPW_Menus_Manager::get_menu_by_id( $menu_id );
			if ( ! $menu ) {
				self::render_message_card(
					__( 'Dining menu not found.', 'tpw-core' ),
					__( 'The selected menu could not be loaded.', 'tpw-core' ),
					$page_url,
					__( 'Back to Menus', 'tpw-core' )
				);
				return;
			}
		}

		$name              = $menu ? (string) $menu->name : '';
		$description       = $menu ? (string) $menu->description : '';
		$number_of_courses = $menu ? (int) $menu->number_of_courses : 3;
		$price             = $menu ? number_format( (float) $menu->price, 2, '.', '' ) : '0.00';

		echo '<div class="flexievent-toolbar">';
		echo '<div>';
		echo '<h3>' . esc_html( $page_title ) . '</h3>';
		echo '<p class="description">' . esc_html( $page_text ) . '</p>';
		echo '</div>';
		echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $page_url ) . '">' . esc_html__( 'Back to Menus', 'tpw-core' ) . '</a>';
		echo '</div>';

		echo '<div class="flexievent-card">';
		echo '<form method="post" action="' . esc_url( $form_url ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '" />';
		echo '<input type="hidden" name="base_url" value="' . esc_attr( $base_url ) . '" />';
		echo '<input type="hidden" name="menu_id" value="' . esc_attr( (int) $menu_id ) . '" />';
		wp_nonce_field( self::SAVE_ACTION . '_' . (int) $menu_id, 'tpw_fe_dining_menu_nonce' );

		echo '<div class="flexievent-section">';
		echo '<div class="flexievent-section-header">';
		echo '<h4 class="flexievent-section-title">' . esc_html__( 'Menu Details', 'tpw-core' ) . '</h4>';
		echo '<p class="flexievent-section-text">' . esc_html__( 'These fields stay aligned with the existing backend menu records.', 'tpw-core' ) . '</p>';
		echo '</div>';
		echo '<div class="flexievent-grid">';
		echo '<div class="flexievent-field full-width">';
		echo '<label for="tpw-fe-menu-name">' . esc_html__( 'Menu Name', 'tpw-core' ) . '</label>';
		echo '<input type="text" id="tpw-fe-menu-name" name="menu_name" value="' . esc_attr( $name ) . '" required />';
		echo '</div>';
		echo '<div class="flexievent-field full-width">';
		echo '<label for="tpw-fe-menu-description">' . esc_html__( 'Description', 'tpw-core' ) . '</label>';
		echo '<textarea id="tpw-fe-menu-description" name="menu_description">' . esc_textarea( $description ) . '</textarea>';
		echo '</div>';
		echo '<div class="flexievent-field">';
		echo '<label for="tpw-fe-number-of-courses">' . esc_html__( 'Number of Courses', 'tpw-core' ) . '</label>';
		echo '<input type="number" id="tpw-fe-number-of-courses" name="number_of_courses" min="1" max="30" step="1" value="' . esc_attr( (string) $number_of_courses ) . '" required />';
		echo '</div>';
		echo '<div class="flexievent-field">';
		echo '<label for="tpw-fe-menu-price">' . esc_html__( 'Price', 'tpw-core' ) . '</label>';
		echo '<input type="number" id="tpw-fe-menu-price" name="price" min="0" step="0.01" value="' . esc_attr( $price ) . '" required />';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="flexievent-actions">';
		echo '<div class="flexievent-primary-actions">';
		echo '<button type="submit" class="tpw-btn tpw-btn-primary">' . esc_html( $is_edit ? __( 'Save Changes', 'tpw-core' ) : __( 'Create Menu', 'tpw-core' ) ) . '</button>';
		if ( $is_edit ) {
			echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( self::course_choices_url( $base_url, (int) $menu_id ) ) . '">' . esc_html__( 'Courses', 'tpw-core' ) . '</a>';
		}
		echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $page_url ) . '">' . esc_html__( 'Cancel', 'tpw-core' ) . '</a>';
		echo '</div>';
		echo '</div>';

		echo '</form>';
		echo '</div>';
	}

	private static function render_course_choices_view( $base_url, $menu_id ) {
		$page_url = self::page_url( $base_url );
		$menu     = self::get_valid_menu( $menu_id );

		if ( ! $menu ) {
			self::render_message_card(
				__( 'Dining menu not found.', 'tpw-core' ),
				__( 'Select a valid dining menu to manage its courses and dishes.', 'tpw-core' ),
				$page_url,
				__( 'Back to Menus', 'tpw-core' )
			);
			return;
		}

		echo '<div class="flexievent-toolbar">';
		echo '<div>';
		echo '<h3>' . esc_html__( 'Course Options', 'tpw-core' ) . '</h3>';
		echo '<p class="description">' . esc_html( sprintf( __( 'Manage course names and dish choices for %s.', 'tpw-core' ), self::normalise_text( $menu->name ) ) ) . '</p>';
		echo '</div>';
		echo '<div class="flexievent-primary-actions">';
		echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( self::page_url( $base_url, [ 'view' => 'edit', 'menu_id' => (int) $menu->id ] ) ) . '">' . esc_html__( 'Edit Menu', 'tpw-core' ) . '</a>';
		echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $page_url ) . '">' . esc_html__( 'Back to Menus', 'tpw-core' ) . '</a>';
		echo '</div>';
		echo '</div>';

		echo '<div class="flexievent-card">';
		echo '<div class="flexievent-section">';
		echo '<div class="flexievent-section-header">';
		echo '<h4 class="flexievent-section-title">' . esc_html( self::normalise_text( $menu->name ) ) . '</h4>';
		echo '<p class="flexievent-section-text">' . esc_html( sprintf( __( '%1$d courses configured. Menu price: %2$s.', 'tpw-core' ), (int) $menu->number_of_courses, '£' . number_format( (float) $menu->price, 2 ) ) ) . '</p>';
		echo '</div>';
		echo '<div class="flexievent-grid">';
		echo '<div class="flexievent-field">';
		echo '<label>' . esc_html__( 'Description', 'tpw-core' ) . '</label>';
		echo '<p>' . esc_html( self::normalise_text( $menu->description ) ) . '</p>';
		echo '</div>';
		echo '<div class="flexievent-field">';
		echo '<label>' . esc_html__( 'Number of Courses', 'tpw-core' ) . '</label>';
		echo '<p>' . esc_html( (string) $menu->number_of_courses ) . '</p>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		for ( $course_number = 1; $course_number <= (int) $menu->number_of_courses; $course_number++ ) {
			$choices      = TPW_Course_Choices_Manager::get_choices_for_course( (int) $menu->id, $course_number );
			$course_title = self::get_course_title( (int) $menu->id, $course_number );

			echo '<div class="flexievent-card">';
			echo '<div class="flexievent-section">';
			echo '<div class="flexievent-section-header">';
			echo '<h4 class="flexievent-section-title">' . esc_html( $course_title ) . '</h4>';
			echo '<p class="flexievent-section-text">' . esc_html( sprintf( _n( '%d dish available.', '%d dishes available.', count( $choices ), 'tpw-core' ), count( $choices ) ) ) . '</p>';
			echo '</div>';

			echo '<form method="post" action="' . esc_url( self::admin_post_url() ) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::RENAME_COURSE_ACTION ) . '" />';
			echo '<input type="hidden" name="base_url" value="' . esc_attr( $base_url ) . '" />';
			echo '<input type="hidden" name="menu_id" value="' . esc_attr( (int) $menu->id ) . '" />';
			echo '<input type="hidden" name="course_number" value="' . esc_attr( (int) $course_number ) . '" />';
			wp_nonce_field( self::RENAME_COURSE_ACTION . '_' . (int) $menu->id . '_' . (int) $course_number, 'tpw_fe_rename_course_nonce' );
			echo '<div class="flexievent-grid">';
			echo '<div class="flexievent-field full-width">';
			echo '<label for="tpw-fe-course-name-' . esc_attr( (string) $course_number ) . '">' . esc_html__( 'Course Name', 'tpw-core' ) . '</label>';
			echo '<input type="text" id="tpw-fe-course-name-' . esc_attr( (string) $course_number ) . '" name="course_name" value="' . esc_attr( $course_title ) . '" />';
			echo '</div>';
			echo '</div>';
			echo '<div class="flexievent-actions">';
			echo '<div class="flexievent-primary-actions">';
			echo '<button type="submit" class="tpw-btn tpw-btn-secondary">' . esc_html__( 'Update Course Name', 'tpw-core' ) . '</button>';
			echo '<a class="tpw-btn tpw-btn-primary" href="' . esc_url( self::course_choice_url( $base_url, (int) $menu->id, $course_number ) ) . '">' . esc_html__( 'Add Dish', 'tpw-core' ) . '</a>';
			echo '</div>';
			echo '</div>';
			echo '</form>';

			if ( ! empty( $choices ) ) {
				echo '<div class="flexievent-table-wrap">';
				echo '<table class="flexievent-table">';
				echo '<thead><tr><th>' . esc_html__( 'Dish', 'tpw-core' ) . '</th><th>' . esc_html__( 'Description', 'tpw-core' ) . '</th><th>' . esc_html__( 'Extra Cost', 'tpw-core' ) . '</th><th>' . esc_html__( 'Actions', 'tpw-core' ) . '</th></tr></thead>';
				echo '<tbody>';
				foreach ( $choices as $choice ) {
					$edit_url = self::course_choice_url( $base_url, (int) $menu->id, $course_number, (int) $choice->id );
					echo '<tr>';
					echo '<td data-label="' . esc_attr__( 'Dish', 'tpw-core' ) . '"><strong>' . esc_html( self::normalise_text( $choice->label ) ) . '</strong></td>';
					echo '<td data-label="' . esc_attr__( 'Description', 'tpw-core' ) . '">' . esc_html( self::normalise_text( $choice->description ) ) . '</td>';
					echo '<td data-label="' . esc_attr__( 'Extra Cost', 'tpw-core' ) . '">' . esc_html( self::format_money( (float) $choice->extra_cost ) ) . '</td>';
					echo '<td data-label="' . esc_attr__( 'Actions', 'tpw-core' ) . '">';
					echo '<div class="flexievent-actions">';
					echo '<div class="flexievent-primary-actions">';
					echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'tpw-core' ) . '</a>';
					echo '</div>';
					echo '<div class="flexievent-danger-actions">';
					echo '<form method="post" action="' . esc_url( self::admin_post_url() ) . '" onsubmit="return window.confirm(' . esc_attr( wp_json_encode( __( 'Delete this course choice?', 'tpw-core' ) ) ) . ');">';
					echo '<input type="hidden" name="action" value="' . esc_attr( self::DELETE_CHOICE_ACTION ) . '" />';
					echo '<input type="hidden" name="base_url" value="' . esc_attr( $base_url ) . '" />';
					echo '<input type="hidden" name="menu_id" value="' . esc_attr( (int) $menu->id ) . '" />';
					echo '<input type="hidden" name="choice_id" value="' . esc_attr( (int) $choice->id ) . '" />';
					wp_nonce_field( self::DELETE_CHOICE_ACTION . '_' . (int) $choice->id, 'tpw_fe_delete_course_choice_nonce' );
					echo '<button type="submit" class="tpw-btn tpw-btn-secondary flexievent-danger-button">' . esc_html__( 'Delete', 'tpw-core' ) . '</button>';
					echo '</form>';
					echo '</div>';
					echo '</div>';
					echo '</td>';
					echo '</tr>';
				}
				echo '</tbody>';
				echo '</table>';
				echo '</div>';
			} else {
				echo '<div class="flexievent-empty-state">';
				echo '<p>' . esc_html__( 'No dishes have been added for this course yet.', 'tpw-core' ) . '</p>';
				echo '<p><a class="tpw-btn tpw-btn-primary" href="' . esc_url( self::course_choice_url( $base_url, (int) $menu->id, $course_number ) ) . '">' . esc_html__( 'Add the first dish', 'tpw-core' ) . '</a></p>';
				echo '</div>';
			}

			echo '</div>';
			echo '</div>';
		}
	}

	private static function render_course_choice_form_view( $base_url, $menu_id, $course_number, $choice_id ) {
		$page_url = self::course_choices_url( $base_url, $menu_id );
		$menu     = self::get_valid_menu( $menu_id );

		if ( ! $menu ) {
			self::render_message_card(
				__( 'Dining menu not found.', 'tpw-core' ),
				__( 'Select a valid dining menu to manage course choices.', 'tpw-core' ),
				self::page_url( $base_url ),
				__( 'Back to Menus', 'tpw-core' )
			);
			return;
		}

		if ( ! self::is_valid_course_number( $menu, $course_number ) ) {
			self::render_message_card(
				__( 'Course not found.', 'tpw-core' ),
				__( 'The selected course is not available on this menu.', 'tpw-core' ),
				$page_url,
				__( 'Back to Course Options', 'tpw-core' )
			);
			return;
		}

		$choice   = null;
		$is_edit  = $choice_id > 0;
		$form_url = self::admin_post_url();
		$title    = $is_edit ? __( 'Edit Course Choice', 'tpw-core' ) : __( 'Add Course Choice', 'tpw-core' );
		$course   = self::get_course_title( (int) $menu->id, $course_number );
		$subtitle = $is_edit
			? sprintf( __( 'Update a dish option for %1$s on %2$s.', 'tpw-core' ), $course, self::normalise_text( $menu->name ) )
			: sprintf( __( 'Add a new dish option for %1$s on %2$s.', 'tpw-core' ), $course, self::normalise_text( $menu->name ) );

		if ( $is_edit ) {
			$choice = TPW_Course_Choices_Manager::get_choice_by_id( $choice_id );
			if ( ! $choice || (int) $choice->menu_id !== (int) $menu->id || (int) $choice->course_number !== $course_number ) {
				self::render_message_card(
					__( 'Course choice not found.', 'tpw-core' ),
					__( 'The selected dish could not be loaded for this course.', 'tpw-core' ),
					$page_url,
					__( 'Back to Course Options', 'tpw-core' )
				);
				return;
			}
		}

		$label       = $choice ? self::normalise_text( $choice->label ) : '';
		$description = $choice ? self::normalise_text( $choice->description ) : '';
		$extra_cost  = $choice && isset( $choice->extra_cost ) ? number_format( (float) $choice->extra_cost, 2, '.', '' ) : '0.00';

		echo '<div class="flexievent-toolbar">';
		echo '<div>';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		echo '<p class="description">' . esc_html( $subtitle ) . '</p>';
		echo '</div>';
		echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $page_url ) . '">' . esc_html__( 'Back to Course Options', 'tpw-core' ) . '</a>';
		echo '</div>';

		echo '<div class="flexievent-card">';
		echo '<form method="post" action="' . esc_url( $form_url ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_CHOICE_ACTION ) . '" />';
		echo '<input type="hidden" name="base_url" value="' . esc_attr( $base_url ) . '" />';
		echo '<input type="hidden" name="menu_id" value="' . esc_attr( (int) $menu->id ) . '" />';
		echo '<input type="hidden" name="course_number" value="' . esc_attr( (int) $course_number ) . '" />';
		echo '<input type="hidden" name="choice_id" value="' . esc_attr( (int) $choice_id ) . '" />';
		wp_nonce_field( self::SAVE_CHOICE_ACTION . '_' . (int) $choice_id . '_' . (int) $menu->id . '_' . (int) $course_number, 'tpw_fe_course_choice_nonce' );

		echo '<div class="flexievent-section">';
		echo '<div class="flexievent-section-header">';
		echo '<h4 class="flexievent-section-title">' . esc_html__( 'Dish Details', 'tpw-core' ) . '</h4>';
		echo '<p class="flexievent-section-text">' . esc_html( sprintf( __( 'This choice belongs to %1$s on %2$s.', 'tpw-core' ), $course, self::normalise_text( $menu->name ) ) ) . '</p>';
		echo '</div>';
		echo '<div class="flexievent-grid">';
		echo '<div class="flexievent-field full-width">';
		echo '<label for="tpw-fe-choice-label">' . esc_html__( 'Dish Name', 'tpw-core' ) . '</label>';
		echo '<input type="text" id="tpw-fe-choice-label" name="label" value="' . esc_attr( $label ) . '" required />';
		echo '</div>';
		echo '<div class="flexievent-field full-width">';
		echo '<label for="tpw-fe-choice-description">' . esc_html__( 'Dish Description', 'tpw-core' ) . '</label>';
		echo '<textarea id="tpw-fe-choice-description" name="description" rows="4">' . esc_textarea( $description ) . '</textarea>';
		echo '</div>';
		echo '<div class="flexievent-field">';
		echo '<label for="tpw-fe-choice-extra-cost">' . esc_html__( 'Extra Cost', 'tpw-core' ) . '</label>';
		echo '<input type="number" id="tpw-fe-choice-extra-cost" name="extra_cost" min="0" step="0.01" value="' . esc_attr( $extra_cost ) . '" />';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="flexievent-actions">';
		echo '<div class="flexievent-primary-actions">';
		echo '<button type="submit" class="tpw-btn tpw-btn-primary">' . esc_html( $is_edit ? __( 'Save Dish', 'tpw-core' ) : __( 'Create Dish', 'tpw-core' ) ) . '</button>';
		echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $page_url ) . '">' . esc_html__( 'Cancel', 'tpw-core' ) . '</a>';
		echo '</div>';
		if ( $is_edit ) {
			echo '<div class="flexievent-danger-actions">';
			echo '<form method="post" action="' . esc_url( self::admin_post_url() ) . '" onsubmit="return window.confirm(' . esc_attr( wp_json_encode( __( 'Delete this course choice?', 'tpw-core' ) ) ) . ');">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::DELETE_CHOICE_ACTION ) . '" />';
			echo '<input type="hidden" name="base_url" value="' . esc_attr( $base_url ) . '" />';
			echo '<input type="hidden" name="menu_id" value="' . esc_attr( (int) $menu->id ) . '" />';
			echo '<input type="hidden" name="choice_id" value="' . esc_attr( (int) $choice_id ) . '" />';
			wp_nonce_field( self::DELETE_CHOICE_ACTION . '_' . (int) $choice_id, 'tpw_fe_delete_course_choice_nonce' );
			echo '<button type="submit" class="tpw-btn tpw-btn-secondary flexievent-danger-button">' . esc_html__( 'Delete Dish', 'tpw-core' ) . '</button>';
			echo '</form>';
			echo '</div>';
		}
		echo '</div>';

		echo '</form>';
		echo '</div>';
	}

	private static function get_valid_menu( $menu_id ) {
		if ( $menu_id <= 0 ) {
			return null;
		}

		return TPW_Menus_Manager::get_menu_by_id( $menu_id );
	}

	private static function is_valid_course_number( $menu, $course_number ) {
		return $menu && $course_number > 0 && $course_number <= (int) $menu->number_of_courses;
	}

	private static function get_course_title( $menu_id, $course_number ) {
		$course_name = TPW_Menu_Courses_Manager::get_course_name( $menu_id, $course_number );

		if ( '' === trim( (string) $course_name ) ) {
			return sprintf( __( 'Course %d', 'tpw-core' ), (int) $course_number );
		}

		return self::normalise_text( $course_name );
	}

	private static function resolve_base_url_from_post() {
		$fallback = home_url( '/' );
		$base_url = isset( $_POST['base_url'] ) ? (string) wp_unslash( $_POST['base_url'] ) : '';

		if ( '' === $base_url && function_exists( 'wp_get_referer' ) ) {
			$base_url = (string) wp_get_referer();
		}

		if ( '' === $base_url ) {
			$base_url = $fallback;
		}

		$base_url = wp_validate_redirect( $base_url, $fallback );

		if ( function_exists( 'flexievent_fe_get_base_url' ) ) {
			return flexievent_fe_get_base_url( $base_url );
		}

		return (string) $base_url;
	}

	private static function page_url( $base_url = '', array $args = [] ) {
		$args = array_merge(
			[
				'section' => self::PAGE_SLUG,
				'view'    => 'list',
			],
			$args
		);

		if ( function_exists( 'flexievent_fe_admin_url' ) ) {
			return flexievent_fe_admin_url( $args, $base_url );
		}

		return add_query_arg( $args, $base_url );
	}

	private static function course_choices_url( $base_url, $menu_id, array $args = [] ) {
		return self::page_url(
			$base_url,
			array_merge(
				[
					'view'    => 'course-choices',
					'menu_id' => (int) $menu_id,
				],
				$args
			)
		);
	}

	private static function course_choice_url( $base_url, $menu_id, $course_number, $choice_id = 0, array $args = [] ) {
		$url_args = array_merge(
			[
				'view'          => 'course-choice',
				'menu_id'       => (int) $menu_id,
				'course_number' => (int) $course_number,
			],
			$args
		);

		if ( $choice_id > 0 ) {
			$url_args['choice_id'] = (int) $choice_id;
		}

		return self::page_url( $base_url, $url_args );
	}

	private static function admin_post_url() {
		return site_url( 'wp-admin/admin-post.php' );
	}

	private static function normalise_text( $value ) {
		$value = (string) $value;

		if ( function_exists( 'tpw_normalise_value' ) ) {
			return tpw_normalise_value( $value );
		}

		return $value;
	}

	private static function format_money( $amount ) {
		return '£' . number_format( (float) $amount, 2 );
	}

	private static function render_message_card( $title, $message, $action_url = '', $action_label = '' ) {
		echo '<div class="flexievent-toolbar">';
		echo '<div>';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		echo '<p class="description">' . esc_html( $message ) . '</p>';
		echo '</div>';
		if ( '' !== $action_url && '' !== $action_label ) {
			echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $action_url ) . '">' . esc_html( $action_label ) . '</a>';
		}
		echo '</div>';

		echo '<div class="flexievent-card">';
		echo '<p>' . esc_html( $message ) . '</p>';
		if ( '' !== $action_url && '' !== $action_label ) {
			echo '<div class="flexievent-actions"><div class="flexievent-primary-actions"><a class="tpw-btn tpw-btn-primary" href="' . esc_url( $action_url ) . '">' . esc_html( $action_label ) . '</a></div></div>';
		}
		echo '</div>';
	}
}