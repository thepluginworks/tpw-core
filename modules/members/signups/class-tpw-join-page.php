<?php
/**
 * Join page registration and provisioning.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Join_Page {
	/**
	 * Built-in Core provider key.
	 */
	const CORE_PROVIDER_KEY = 'core';

	/**
	 * Shortcode tag for the public Join form.
	 */
	const SHORTCODE_TAG = 'tpw_join_form';

	/**
	 * System page slug.
	 */
	const SYSTEM_PAGE_KEY = 'join';

	/**
	 * Runtime provider registry.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static $providers = array();

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_builtin_provider' ), 15 );
		add_action( 'init', array( __CLASS__, 'register_system_page' ), 20 );
		add_action( 'init', array( __CLASS__, 'maybe_provision_join_page' ), 25 );
	}

	/**
	 * Register a Join provider at runtime.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $args Provider arguments.
	 * @return void
	 */
	public static function register_provider( $provider_key, $args = array() ) {
		$key = sanitize_key( (string) $provider_key );
		if ( '' === $key ) {
			return;
		}

		$args = is_array( $args ) ? $args : array();
		$render_type = isset( $args['render_type'] ) ? sanitize_key( (string) $args['render_type'] ) : 'shortcode';
		if ( ! in_array( $render_type, array( 'internal', 'shortcode' ), true ) ) {
			$render_type = 'shortcode';
		}

		$render_target = isset( $args['render_target'] ) ? trim( (string) $args['render_target'] ) : '';
		$label         = isset( $args['label'] ) ? sanitize_text_field( (string) $args['label'] ) : ucwords( str_replace( array( '-', '_' ), ' ', $key ) );
		$plugin        = isset( $args['plugin'] ) ? sanitize_key( (string) $args['plugin'] ) : 'tpw-core';

		self::$providers[ $key ] = array(
			'key'           => $key,
			'label'         => $label,
			'plugin'        => $plugin,
			'render_type'   => $render_type,
			'render_target' => $render_target,
		);
	}

	/**
	 * Register the built-in Core provider.
	 *
	 * @return void
	 */
	public static function register_builtin_provider() {
		self::register_provider(
			self::CORE_PROVIDER_KEY,
			array(
				'label'         => __( 'Core', 'tpw-core' ),
				'plugin'        => 'tpw-core',
				'render_type'   => 'internal',
				'render_target' => self::CORE_PROVIDER_KEY,
			)
		);
	}

	/**
	 * Get all registered Join providers.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_registered_providers() {
		if ( empty( self::$providers ) || ! isset( self::$providers[ self::CORE_PROVIDER_KEY ] ) ) {
			self::register_builtin_provider();
		}

		return self::$providers;
	}

	/**
	 * Get the stored provider key.
	 *
	 * @return string
	 */
	public static function get_active_provider_key() {
		$settings = self::normalize_settings();
		$key      = isset( $settings['join_provider_key'] ) ? sanitize_key( (string) $settings['join_provider_key'] ) : '';

		if ( '' === $key ) {
			return self::CORE_PROVIDER_KEY;
		}

		return $key;
	}

	/**
	 * Resolve the active Join provider, falling back to Core.
	 *
	 * @return array<string, string>
	 */
	public static function resolve_active_provider() {
		$providers = self::get_registered_providers();
		$key       = self::get_active_provider_key();

		if ( ! isset( $providers[ $key ] ) || ! is_array( $providers[ $key ] ) ) {
			return $providers[ self::CORE_PROVIDER_KEY ];
		}

		$provider = $providers[ $key ];

		if ( empty( $provider['render_type'] ) || empty( $provider['render_target'] ) ) {
			return $providers[ self::CORE_PROVIDER_KEY ];
		}

		if ( 'shortcode' === $provider['render_type'] ) {
			$shortcode_tag = self::parse_shortcode_tag( $provider['render_target'] );
			if ( '' === $shortcode_tag ) {
				return $providers[ self::CORE_PROVIDER_KEY ];
			}

			if ( function_exists( 'shortcode_exists' ) && ! shortcode_exists( $shortcode_tag ) ) {
				return $providers[ self::CORE_PROVIDER_KEY ];
			}
		}

		return $provider;
	}

	/**
	 * Register the Join page with the system pages registry.
	 *
	 * @return void
	 */
	public static function register_system_page() {
		if ( ! class_exists( 'TPW_Core_System_Pages' ) ) {
			return;
		}

		TPW_Core_System_Pages::register_page(
			self::SYSTEM_PAGE_KEY,
			array(
				'title'     => __( 'Join', 'tpw-core' ),
				'shortcode' => '[' . self::SHORTCODE_TAG . ']',
				'plugin'    => 'tpw-core',
				'required'  => 0,
			)
		);
	}

	/**
	 * Provision the Join page when sign-ups are enabled.
	 *
	 * @return void
	 */
	public static function maybe_provision_join_page() {
		if ( ! class_exists( 'TPW_Signup_Field_Schema' ) || ! TPW_Signup_Field_Schema::signups_enabled() ) {
			return;
		}

		self::reconcile_settings();
	}

	/**
	 * Reconcile the configured Join page against settings and system pages.
	 *
	 * @param array|null $settings Optional signup settings.
	 * @return int
	 */
	public static function reconcile_settings( $settings = null ) {
		$settings = self::normalize_settings( $settings );

		if ( '1' !== $settings['enable_signups'] ) {
			return self::validate_page_id( $settings['signup_page_id'] );
		}

		$page_id = self::validate_page_id( $settings['signup_page_id'] );
		if ( $page_id > 0 ) {
			self::ensure_shortcode_on_page( $page_id );
			self::persist_join_page_id( $page_id );

			return $page_id;
		}

		$page_id = self::get_system_page_id();
		if ( $page_id > 0 ) {
			self::ensure_shortcode_on_page( $page_id );
			self::persist_join_page_id( $page_id );

			return $page_id;
		}

		$page_id = self::find_shortcode_page_id();
		if ( $page_id > 0 ) {
			self::persist_join_page_id( $page_id );

			return $page_id;
		}

		$page_id = self::create_join_page();
		if ( $page_id > 0 ) {
			self::persist_join_page_id( $page_id );
		}

		return $page_id;
	}

	/**
	 * Get the configured Join page ID.
	 *
	 * @return int
	 */
	public static function get_join_page_id() {
		$settings = self::normalize_settings();
		$page_id  = self::validate_page_id( $settings['signup_page_id'] );

		if ( $page_id > 0 ) {
			return $page_id;
		}

		return self::get_system_page_id();
	}

	/**
	 * Normalize signup settings.
	 *
	 * @param array|null $settings Settings array.
	 * @return array<string, string|int>
	 */
	private static function normalize_settings( $settings = null ) {
		if ( is_array( $settings ) ) {
			return array(
				'enable_signups' => ! empty( $settings['enable_signups'] ) ? '1' : '0',
				'signup_page_id' => isset( $settings['signup_page_id'] ) ? absint( $settings['signup_page_id'] ) : 0,
				'join_provider_key' => isset( $settings['join_provider_key'] ) ? sanitize_key( (string) $settings['join_provider_key'] ) : self::CORE_PROVIDER_KEY,
			);
		}

		if ( class_exists( 'TPW_Signup_Field_Schema' ) ) {
			return TPW_Signup_Field_Schema::get_members_signup_settings();
		}

		$stored = get_option( 'tpw_members_settings', array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'enable_signups' => ! empty( $stored['enable_signups'] ) ? '1' : '0',
			'signup_page_id' => isset( $stored['signup_page_id'] ) ? absint( $stored['signup_page_id'] ) : 0,
			'join_provider_key' => isset( $stored['join_provider_key'] ) ? sanitize_key( (string) $stored['join_provider_key'] ) : self::CORE_PROVIDER_KEY,
		);
	}

	/**
	 * Parse the first shortcode tag from a render target.
	 *
	 * @param string $shortcode Shortcode string.
	 * @return string
	 */
	private static function parse_shortcode_tag( $shortcode ) {
		if ( ! is_string( $shortcode ) || '' === trim( $shortcode ) ) {
			return '';
		}

		if ( preg_match( '/\[([A-Za-z0-9_-]+)/', $shortcode, $matches ) ) {
			return sanitize_key( $matches[1] );
		}

		return '';
	}

	/**
	 * Validate a page ID.
	 *
	 * @param int $page_id Page ID.
	 * @return int
	 */
	private static function validate_page_id( $page_id ) {
		$page_id = absint( $page_id );
		if ( $page_id < 1 ) {
			return 0;
		}

		$page = get_post( $page_id );
		if ( ! $page || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
			return 0;
		}

		return (int) $page->ID;
	}

	/**
	 * Get the system page mapping for Join.
	 *
	 * @return int
	 */
	private static function get_system_page_id() {
		if ( ! class_exists( 'TPW_Core_System_Pages' ) ) {
			return 0;
		}

		return self::validate_page_id( TPW_Core_System_Pages::get_id( self::SYSTEM_PAGE_KEY ) );
	}

	/**
	 * Find an existing published page containing the Join shortcode.
	 *
	 * @return int
	 */
	private static function find_shortcode_page_id() {
		$query = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				's'              => '[' . self::SHORTCODE_TAG,
			)
		);

		if ( ! $query->have_posts() ) {
			return 0;
		}

		foreach ( $query->posts as $page_id ) {
			$content = (string) get_post_field( 'post_content', (int) $page_id );
			if ( function_exists( 'has_shortcode' ) && has_shortcode( $content, self::SHORTCODE_TAG ) ) {
				return (int) $page_id;
			}

			if ( false !== strpos( $content, '[' . self::SHORTCODE_TAG ) ) {
				return (int) $page_id;
			}
		}

		return 0;
	}

	/**
	 * Create the managed Join page.
	 *
	 * @return int
	 */
	private static function create_join_page() {
		$page_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => __( 'Join', 'tpw-core' ),
				'post_name'      => self::SYSTEM_PAGE_KEY,
				'post_content'   => '[' . self::SHORTCODE_TAG . ']',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return 0;
		}

		return (int) $page_id;
	}

	/**
	 * Persist the Join page ID to members settings and system pages.
	 *
	 * @param int $page_id Page ID.
	 * @return void
	 */
	private static function persist_join_page_id( $page_id ) {
		$page_id  = self::validate_page_id( $page_id );
		$settings = get_option( 'tpw_members_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		if ( $page_id < 1 ) {
			return;
		}

		$settings['signup_page_id'] = $page_id;
		update_option( 'tpw_members_settings', $settings );

		$overrides = get_option( 'tpw_core_system_pages', array() );
		$overrides = is_array( $overrides ) ? $overrides : array();
		$overrides[ self::SYSTEM_PAGE_KEY ] = array(
			'wp_page_id' => $page_id,
		);
		update_option( 'tpw_core_system_pages', $overrides );
	}

	/**
	 * Ensure the Join shortcode is present on a configured page.
	 *
	 * @param int $page_id Page ID.
	 * @return void
	 */
	private static function ensure_shortcode_on_page( $page_id ) {
		$page_id = self::validate_page_id( $page_id );
		if ( $page_id < 1 ) {
			return;
		}

		$page    = get_post( $page_id );
		$content = (string) get_post_field( 'post_content', $page_id );
		$has_tag  = ( function_exists( 'has_shortcode' ) && has_shortcode( $content, self::SHORTCODE_TAG ) ) || false !== strpos( $content, '[' . self::SHORTCODE_TAG );
		$update   = array(
			'ID' => $page_id,
		);

		if ( $page && 'publish' !== $page->post_status ) {
			$update['post_status'] = 'publish';
		}

		if ( ! $has_tag ) {
			$content                = trim( $content );
			$update['post_content'] = '' === $content ? '[' . self::SHORTCODE_TAG . ']' : $content . "\n\n[" . self::SHORTCODE_TAG . ']';
		}

		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}
	}
}