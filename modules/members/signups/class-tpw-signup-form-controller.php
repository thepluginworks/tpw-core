<?php
/**
 * Thin Join form shortcode controller.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Form_Controller {
	/**
	 * Singleton instance.
	 *
	 * @var TPW_Signup_Form_Controller|null
	 */
	private static $instance = null;

	/**
	 * Renderer instance.
	 *
	 * @var TPW_Signup_Form_Renderer
	 */
	private $renderer;

	/**
	 * Validator instance.
	 *
	 * @var TPW_Signup_Form_Validator
	 */
	private $validator;

	/**
	 * Payload builder instance.
	 *
	 * @var TPW_Signup_Payload_Builder
	 */
	private $payload_builder;

	/**
	 * Attempt service instance.
	 *
	 * @var TPW_Signup_Attempts_Service
	 */
	private $attempt_service;

	/**
	 * In-request submission state for invalid POSTs.
	 *
	 * @var array<string, mixed>|null
	 */
	private $submission_state = null;

	/**
	 * Get the singleton controller.
	 *
	 * @return TPW_Signup_Form_Controller
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the public Join form hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$instance = self::get_instance();

		add_shortcode( TPW_Join_Page::SHORTCODE_TAG, array( $instance, 'render_shortcode' ) );
		add_action( 'template_redirect', array( $instance, 'maybe_handle_submission' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $instance, 'maybe_enqueue_assets' ), 999 );
		add_action( 'send_headers', array( $instance, 'maybe_disable_cache' ) );
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->renderer        = new TPW_Signup_Form_Renderer();
		$this->validator       = new TPW_Signup_Form_Validator();
		$this->payload_builder = new TPW_Signup_Payload_Builder();
		$this->attempt_service = TPW_Signup_Attempts_Service::get_instance();
	}

	/**
	 * Render the Join form shortcode.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		if ( ! TPW_Signup_Field_Schema::signups_enabled() ) {
			return $this->renderer->render_disabled();
		}

		$schema = TPW_Signup_Field_Schema::get_public_signup_schema();
		if ( empty( $schema['nodes'] ) ) {
			return $this->renderer->render_disabled();
		}

		$success_attempt = $this->get_success_attempt();
		if ( is_array( $success_attempt ) ) {
			return $this->renderer->render_success( $success_attempt );
		}

		$state = array(
			'values'     => array(),
			'errors'     => array(),
			'form_error' => '',
		);

		if ( is_array( $this->submission_state ) ) {
			$state = $this->submission_state;
		}

		return $this->renderer->render_form( $schema, $state );
	}

	/**
	 * Handle Join form submissions before template rendering.
	 *
	 * @return void
	 */
	public function maybe_handle_submission() {
		if ( ! $this->is_join_submission() || ! $this->page_contains_join_form() || ! TPW_Signup_Field_Schema::signups_enabled() ) {
			return;
		}

		$schema = TPW_Signup_Field_Schema::get_public_signup_schema();
		if ( empty( $schema['nodes'] ) ) {
			return;
		}

		$state = $this->handle_submission( $schema );

		if ( ! empty( $state['redirect_url'] ) ) {
			wp_safe_redirect( $state['redirect_url'] );
			exit;
		}

		$this->submission_state = $state;
	}

	/**
	 * Enqueue shared TPW styles on pages rendering the Join form.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets() {
		if ( ! $this->page_contains_join_form() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		$this->enqueue_style_if_needed( 'tpw-ui', TPW_CORE_PATH . 'assets/css/tpw-ui.css', TPW_CORE_URL . 'assets/css/tpw-ui.css' );
		$this->enqueue_style_if_needed( 'tpw-admin-ui', TPW_CORE_PATH . 'assets/css/tpw-admin-ui.css', TPW_CORE_URL . 'assets/css/tpw-admin-ui.css', array( 'tpw-ui' ) );
		$this->enqueue_style_if_needed( 'tpw-buttons', TPW_CORE_PATH . 'assets/css/tpw-buttons.css', TPW_CORE_URL . 'assets/css/tpw-buttons.css', array( 'tpw-ui' ) );
	}

	/**
	 * Disable caching for Join form pages.
	 *
	 * @return void
	 */
	public function maybe_disable_cache() {
		if ( ! $this->page_contains_join_form() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * Handle a Join form POST.
	 *
	 * @param array $schema Public signup schema.
	 * @return array<string, mixed>
	 */
	private function handle_submission( $schema ) {
		$state     = array(
			'values'     => array(),
			'errors'     => array(),
			'form_error' => '',
		);
		$submitted = isset( $_POST['tpw_signup'] ) && is_array( $_POST['tpw_signup'] ) ? wp_unslash( $_POST['tpw_signup'] ) : array();

		if ( empty( $_POST['tpw_join_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tpw_join_nonce'] ) ), 'tpw_join_submit' ) ) {
			$state['form_error'] = __( 'Security check failed. Please refresh the page and try again.', 'tpw-core' );
			return $state;
		}

		$validation = $this->validator->validate( $schema, $submitted );
		$state['values'] = $validation['values'];
		$state['errors'] = $validation['errors'];

		if ( ! empty( $validation['errors'] ) ) {
			return $state;
		}

		$payloads = $this->payload_builder->build(
			$schema,
			$validation['normalized_values'],
			array(
				'page_id'    => get_queried_object_id(),
				'page_url'   => get_permalink( get_queried_object_id() ),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '',
			)
		);

		if ( empty( $payloads['attempt_data']['email'] ) || ! is_email( $payloads['attempt_data']['email'] ) ) {
			$state['form_error'] = __( 'A valid email address is required to continue.', 'tpw-core' );
			return $state;
		}

		$attempt = $this->attempt_service->create_attempt( $payloads['attempt_data'] );
		if ( is_wp_error( $attempt ) ) {
			$state['form_error'] = __( 'The join request could not be recorded right now. Please try again.', 'tpw-core' );
			return $state;
		}

		$state['redirect_url'] = add_query_arg(
			array(
				'tpw_signup_success' => '1',
				'tpw_signup_token'   => $attempt['public_token'],
			),
			$this->get_current_form_url()
		);

		return $state;
	}

	/**
	 * Resolve a success attempt from the query string.
	 *
	 * @return array|null
	 */
	private function get_success_attempt() {
		if ( empty( $_GET['tpw_signup_success'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['tpw_signup_success'] ) ) ) {
			return null;
		}

		if ( empty( $_GET['tpw_signup_token'] ) ) {
			return null;
		}

		$token   = sanitize_text_field( wp_unslash( $_GET['tpw_signup_token'] ) );
		$attempt = $this->attempt_service->load_attempt_by_public_token( $token );

		if ( is_wp_error( $attempt ) ) {
			return null;
		}

		if ( 'members_join' !== $attempt['flow_key'] || 'tpw-core' !== $attempt['plugin_key'] ) {
			return null;
		}

		return $attempt;
	}

	/**
	 * Check whether the current request is a Join form submission.
	 *
	 * @return bool
	 */
	private function is_join_submission() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
			return false;
		}

		if ( empty( $_POST['tpw_signup_action'] ) ) {
			return false;
		}

		return 'join_submit' === sanitize_key( wp_unslash( $_POST['tpw_signup_action'] ) );
	}

	/**
	 * Determine whether the current page contains the Join form.
	 *
	 * @return bool
	 */
	private function page_contains_join_form() {
		if ( ! is_singular() ) {
			return false;
		}

		global $post;
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		$content = (string) $post->post_content;

		if ( function_exists( 'has_shortcode' ) && has_shortcode( $content, TPW_Join_Page::SHORTCODE_TAG ) ) {
			return true;
		}

		return false !== strpos( $content, '[' . TPW_Join_Page::SHORTCODE_TAG );
	}

	/**
	 * Enqueue a stylesheet if it is not already present.
	 *
	 * @param string $handle Style handle.
	 * @param string $file_path Absolute file path.
	 * @param string $file_url File URL.
	 * @param array  $deps Optional dependencies.
	 * @return void
	 */
	private function enqueue_style_if_needed( $handle, $file_path, $file_url, $deps = array() ) {
		if ( function_exists( 'wp_style_is' ) && wp_style_is( $handle, 'enqueued' ) ) {
			return;
		}

		$version = file_exists( $file_path ) ? filemtime( $file_path ) : null;
		wp_enqueue_style( $handle, $file_url, $deps, $version );
	}

	/**
	 * Get the current form URL without the token parameter.
	 *
	 * @return string
	 */
	private function get_current_form_url() {
		$permalink = get_permalink( get_queried_object_id() );
		$permalink = is_string( $permalink ) && '' !== $permalink ? $permalink : home_url( '/' );

		return remove_query_arg( array( 'tpw_signup_success', 'tpw_signup_token' ), $permalink );
	}
}