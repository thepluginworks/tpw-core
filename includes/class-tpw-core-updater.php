<?php
/**
 * Lightweight GitHub-based updater for TPW Core.
 *
 * Reads the public version manifest, caches it, injects updates into the
 * standard WordPress plugin update transient, and supplies plugin information
 * for the details modal.
 *
 * @since 1.14.42
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Core_Updater {
	const MANIFEST_URL = 'https://thepluginworks.github.io/tpw-core/tpw-core.json';
	const PLUGIN_SLUG = 'tpw-core';
	const PLUGIN_BASENAME = 'tpw-core/tpw-core.php';
	const CACHE_KEY = 'tpw_core_update_manifest';
	const REFRESH_QUERY_ARG = 'tpw_core_refresh_updater';
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;
	const FAILURE_CACHE_TTL = HOUR_IN_SECONDS;
	const HOMEPAGE = 'https://thepluginworks.com/';
	const DOWNLOAD_URL = 'https://github.com/thepluginworks/tpw-core/releases/latest/download/tpw-core.zip';

	/**
	 * Per-request manifest cache so a forced refresh only performs one remote request.
	 *
	 * @var array<string, string|int>|null
	 */
	private static $request_manifest = null;

	/**
	 * Track whether the manifest cache was explicitly bypassed in this request.
	 *
	 * @var bool
	 */
	private static $did_bypass_manifest_cache = false;

	/**
	 * Request-scoped trace state for a TPW Core upgrade attempt.
	 *
	 * @var array<string, mixed>
	 */
	private static $upgrade_trace = array(
		'active'                         => false,
		'package'                        => '',
		'stages'                         => array(),
		'filesystem_method'              => '',
		'filesystem_credentials_prompted' => false,
		'filesystem_credentials_error'   => array(),
		'http_error'                     => array(),
		'last_error'                     => array(),
	);

	/**
	 * Register updater hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );
		add_filter( 'upgrader_package_options', array( __CLASS__, 'log_upgrader_package_options' ) );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'log_upgrader_pre_download' ), 10, 4 );
		add_filter( 'upgrader_pre_install', array( __CLASS__, 'log_upgrader_pre_install' ), 999, 2 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'log_upgrader_source_selection' ), 999, 4 );
		add_filter( 'upgrader_clear_destination', array( __CLASS__, 'log_upgrader_clear_destination' ), 999, 4 );
		add_filter( 'upgrader_post_install', array( __CLASS__, 'log_upgrader_post_install' ), 999, 3 );
		add_filter( 'upgrader_install_package_result', array( __CLASS__, 'log_upgrader_install_package_result' ), 999, 2 );
		add_filter( 'request_filesystem_credentials', array( __CLASS__, 'log_request_filesystem_credentials' ), 10, 7 );
		add_filter( 'filesystem_method', array( __CLASS__, 'log_filesystem_method' ), 10, 4 );
		add_action( 'http_api_debug', array( __CLASS__, 'log_http_api_debug' ), 10, 5 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_manifest_cache_on_upgrade' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'log_upgrader_process_complete' ), 20, 2 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_force_refresh' ) );
		add_action( 'shutdown', array( __CLASS__, 'log_upgrade_trace_summary' ) );
	}

	/**
	 * Inject TPW Core update metadata into the WordPress plugin updates transient.
	 *
	 * @param object $transient WordPress update transient.
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		$installed_version = self::get_installed_version( $transient );
		$manifest = self::get_manifest( self::get_manifest_request_args() );

		if ( empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
			self::clear_transient_entries( $transient );
			return $transient;
		}

		if ( '' === $installed_version ) {
			return $transient;
		}

		if ( ! version_compare( $manifest['version'], $installed_version, '>' ) ) {
			self::clear_transient_entries( $transient );
			$transient->no_update[ self::PLUGIN_BASENAME ] = (object) array(
				'slug'        => self::PLUGIN_SLUG,
				'plugin'      => self::PLUGIN_BASENAME,
				'new_version' => $installed_version,
				'package'     => '',
				'url'         => self::HOMEPAGE,
				'id'          => self::HOMEPAGE . '#tpw-core',
			);
			return $transient;
		}

		$transient->response[ self::PLUGIN_BASENAME ] = (object) array(
			'slug'        => self::PLUGIN_SLUG,
			'plugin'      => self::PLUGIN_BASENAME,
			'new_version' => $manifest['version'],
			'package'     => $manifest['download_url'],
			'url'         => self::HOMEPAGE,
			'id'          => self::HOMEPAGE . '#tpw-core',
		);

		if ( isset( $transient->no_update[ self::PLUGIN_BASENAME ] ) ) {
			unset( $transient->no_update[ self::PLUGIN_BASENAME ] );
		}

		self::log( 'Update object injected into transient.', array(
			'new_version' => $manifest['version'],
		) );

		return $transient;
	}

	/**
	 * Provide plugin details for the WordPress plugin information modal.
	 *
	 * @param false|object|array $result Existing API result.
	 * @param string             $action Requested action.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object|array
	 */
	public static function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! is_object( $args ) || empty( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$manifest = self::get_manifest();
		$version = ! empty( $manifest['version'] ) ? $manifest['version'] : TPW_CORE_VERSION;
		$download_link = ! empty( $manifest['download_url'] ) ? $manifest['download_url'] : self::DOWNLOAD_URL;

		return (object) array(
			'name'          => 'TPW Core',
			'slug'          => self::PLUGIN_SLUG,
			'plugin_name'   => 'TPW Core',
			'version'       => $version,
			'author'        => '<a href="' . esc_url( self::HOMEPAGE ) . '">ThePluginWorks</a>',
			'homepage'      => self::HOMEPAGE,
			'download_link' => $download_link,
			'external'      => true,
			'sections'      => array(
				'description' => '<p>TPW Core provides shared RSVP, member, payment, branding, and system-page functionality for the TPW plugin ecosystem.</p>',
				'changelog'   => self::build_changelog_section(),
			),
		);
	}

	/**
	 * Clear the manifest cache after TPW Core is upgraded.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array    $options  Upgrade options.
	 * @return void
	 */
	public static function clear_manifest_cache_on_upgrade( $upgrader, $options ) {
		unset( $upgrader );

		if ( ! is_array( $options ) ) {
			return;
		}

		if ( empty( $options['action'] ) || 'update' !== $options['action'] ) {
			return;
		}

		if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}

		if ( empty( $options['plugins'] ) || ! is_array( $options['plugins'] ) ) {
			return;
		}

		if ( in_array( self::PLUGIN_BASENAME, $options['plugins'], true ) ) {
			self::clear_update_caches();
		}
	}

	/**
	 * Capture the exact package URL and hook context handed to the upgrader.
	 *
	 * @param array $options Upgrader run options.
	 * @return array
	 */
	public static function log_upgrader_package_options( $options ) {
		if ( ! is_array( $options ) ) {
			return $options;
		}

		$hook_extra = isset( $options['hook_extra'] ) && is_array( $options['hook_extra'] ) ? $options['hook_extra'] : array();
		$package = isset( $options['package'] ) ? (string) $options['package'] : '';

		if ( ! self::should_trace_upgrade( $hook_extra, $package ) ) {
			return $options;
		}

		self::activate_upgrade_trace( $package, $hook_extra );
		self::mark_upgrade_stage( 'package_options' );

		self::log(
			'Upgrader received TPW Core package options.',
			array(
				'package'            => $package,
				'destination'        => isset( $options['destination'] ) ? (string) $options['destination'] : '',
				'clear_destination'  => ! empty( $options['clear_destination'] ),
				'clear_working'      => ! empty( $options['clear_working'] ),
				'abort_if_exists'    => ! empty( $options['abort_if_destination_exists'] ),
				'is_multi'           => ! empty( $options['is_multi'] ),
				'hook_extra'         => self::summarize_hook_extra( $hook_extra ),
			)
		);

		return $options;
	}

	/**
	 * Capture the package URL immediately before WordPress downloads it.
	 *
	 * @param bool|mixed   $reply      Short-circuit reply.
	 * @param string       $package    Package URL or local file path.
	 * @param WP_Upgrader  $upgrader   Upgrader instance.
	 * @param array        $hook_extra Extra hook arguments.
	 * @return mixed
	 */
	public static function log_upgrader_pre_download( $reply, $package, $upgrader, $hook_extra ) {
		unset( $upgrader );

		if ( ! self::should_trace_upgrade( $hook_extra, $package ) ) {
			return $reply;
		}

		self::activate_upgrade_trace( (string) $package, $hook_extra );
		self::mark_upgrade_stage( 'pre_download' );

		self::log(
			'WordPress is about to download the TPW Core update package.',
			array(
				'package'    => (string) $package,
				'reply'      => self::normalize_log_value( $reply ),
				'hook_extra' => self::summarize_hook_extra( $hook_extra ),
			)
		);

		return $reply;
	}

	/**
	 * Log the state just before install_package() begins.
	 *
	 * @param bool|WP_Error $response  Installation response.
	 * @param array         $hook_extra Extra hook arguments.
	 * @return bool|WP_Error
	 */
	public static function log_upgrader_pre_install( $response, $hook_extra ) {
		if ( ! self::should_trace_upgrade( $hook_extra ) ) {
			return $response;
		}

		self::mark_upgrade_stage( 'pre_install' );

		$context = array(
			'hook_extra' => self::summarize_hook_extra( $hook_extra ),
		);

		if ( is_wp_error( $response ) ) {
			$context['wp_error'] = self::describe_wp_error( $response );
			self::record_upgrade_error( 'pre_install', $response );
			self::log( 'TPW Core upgrade failed before installation began.', $context );
		} else {
			$context['response'] = (bool) $response;
			self::log( 'TPW Core upgrade passed the pre-install stage.', $context );
		}

		return $response;
	}

	/**
	 * Log source selection results, including incompatible archive failures.
	 *
	 * @param string|WP_Error $source        Selected source path or WP_Error.
	 * @param string          $remote_source Original unpacked source path.
	 * @param WP_Upgrader     $upgrader      Upgrader instance.
	 * @param array           $hook_extra    Extra hook arguments.
	 * @return string|WP_Error
	 */
	public static function log_upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extra ) {
		unset( $upgrader );

		if ( ! self::should_trace_upgrade( $hook_extra ) ) {
			return $source;
		}

		self::mark_upgrade_stage( 'source_selection' );

		$context = array(
			'remote_source' => (string) $remote_source,
			'hook_extra'    => self::summarize_hook_extra( $hook_extra ),
		);

		if ( is_wp_error( $source ) ) {
			$context['wp_error'] = self::describe_wp_error( $source );
			self::record_upgrade_error( 'source_selection', $source );
			self::log( 'TPW Core upgrade failed while validating or selecting the unpacked source.', $context );
		} else {
			$context['source'] = (string) $source;
			self::log( 'TPW Core package downloaded and unpacked successfully.', $context );
		}

		return $source;
	}

	/**
	 * Log failures when WordPress tries to clear the destination plugin directory.
	 *
	 * @param true|WP_Error $removed            Whether destination clearing succeeded.
	 * @param string        $local_destination  Local destination path.
	 * @param string        $remote_destination Remote destination path.
	 * @param array         $hook_extra         Extra hook arguments.
	 * @return true|WP_Error
	 */
	public static function log_upgrader_clear_destination( $removed, $local_destination, $remote_destination, $hook_extra ) {
		if ( ! self::should_trace_upgrade( $hook_extra ) ) {
			return $removed;
		}

		self::mark_upgrade_stage( 'clear_destination' );

		$context = array(
			'local_destination'  => (string) $local_destination,
			'remote_destination' => (string) $remote_destination,
			'hook_extra'         => self::summarize_hook_extra( $hook_extra ),
		);

		if ( is_wp_error( $removed ) ) {
			$context['wp_error'] = self::describe_wp_error( $removed );
			self::record_upgrade_error( 'clear_destination', $removed );
			self::log( 'TPW Core upgrade failed while removing the old plugin files.', $context );
		} else {
			$context['removed'] = true;
			self::log( 'TPW Core destination directory cleared successfully.', $context );
		}

		return $removed;
	}

	/**
	 * Log the result immediately after install_package() completes.
	 *
	 * @param bool|WP_Error $response  Installation response.
	 * @param array         $hook_extra Extra hook arguments.
	 * @param array         $result    Installation result data.
	 * @return bool|WP_Error
	 */
	public static function log_upgrader_post_install( $response, $hook_extra, $result ) {
		if ( ! self::should_trace_upgrade( $hook_extra ) ) {
			return $response;
		}

		self::mark_upgrade_stage( 'post_install' );

		$context = array(
			'hook_extra' => self::summarize_hook_extra( $hook_extra ),
			'result'     => self::summarize_upgrader_result( $result ),
		);

		if ( is_wp_error( $response ) ) {
			$context['wp_error'] = self::describe_wp_error( $response );
			self::record_upgrade_error( 'post_install', $response );
			self::log( 'TPW Core upgrade failed in the post-install step.', $context );
		} else {
			$context['response'] = (bool) $response;
			self::log( 'TPW Core post-install step completed.', $context );
		}

		return $response;
	}

	/**
	 * Log the final install_package() result handed back by WP_Upgrader::run().
	 *
	 * @param array|WP_Error $result     Install package result.
	 * @param array          $hook_extra Extra hook arguments.
	 * @return array|WP_Error
	 */
	public static function log_upgrader_install_package_result( $result, $hook_extra ) {
		if ( ! self::should_trace_upgrade( $hook_extra ) ) {
			return $result;
		}

		self::mark_upgrade_stage( 'install_package_result' );

		$context = array(
			'hook_extra' => self::summarize_hook_extra( $hook_extra ),
		);

		if ( is_wp_error( $result ) ) {
			$context['wp_error'] = self::describe_wp_error( $result );
			self::record_upgrade_error( 'install_package_result', $result );
			self::log( 'WordPress returned a WP_Error for the TPW Core install package result.', $context );
		} else {
			$context['result'] = self::summarize_upgrader_result( $result );
			self::log( 'WordPress returned a successful install package result for TPW Core.', $context );
		}

		return $result;
	}

	/**
	 * Log filesystem credential prompts or connection errors for the update request.
	 *
	 * @param mixed         $credentials                  Filtered credentials result.
	 * @param string        $form_post                    Form post target.
	 * @param string        $type                         Filesystem method.
	 * @param bool|WP_Error $error                        Filesystem error object if one exists.
	 * @param string        $context                      Filesystem context path.
	 * @param array         $extra_fields                 Extra POST fields.
	 * @param bool          $allow_relaxed_file_ownership Whether relaxed ownership is allowed.
	 * @return mixed
	 */
	public static function log_request_filesystem_credentials( $credentials, $form_post, $type, $error, $context, $extra_fields, $allow_relaxed_file_ownership ) {
		unset( $form_post, $extra_fields, $allow_relaxed_file_ownership );

		if ( ! self::is_upgrade_trace_active() ) {
			return $credentials;
		}

		self::mark_upgrade_stage( 'request_filesystem_credentials' );
		self::$upgrade_trace['filesystem_credentials_prompted'] = true;

		$context_data = array(
			'filesystem_method' => (string) $type,
			'context'           => (string) $context,
			'credentials'       => self::normalize_log_value( $credentials ),
		);

		if ( is_wp_error( $error ) ) {
			$context_data['wp_error'] = self::describe_wp_error( $error );
			self::$upgrade_trace['filesystem_credentials_error'] = $context_data['wp_error'];
			self::record_upgrade_error( 'filesystem_credentials', $error );
			self::log( 'WordPress requested filesystem credentials after a connection error during the TPW Core upgrade.', $context_data );
		} else {
			$context_data['error'] = (bool) $error;
			self::log( 'WordPress requested filesystem credentials for the TPW Core upgrade.', $context_data );
		}

		return $credentials;
	}

	/**
	 * Log which filesystem method WordPress selected for the update.
	 *
	 * @param string $method                         Filesystem method.
	 * @param array  $args                           Filesystem args.
	 * @param string $context                        Filesystem context path.
	 * @param bool   $allow_relaxed_file_ownership   Whether relaxed ownership is allowed.
	 * @return string
	 */
	public static function log_filesystem_method( $method, $args, $context, $allow_relaxed_file_ownership ) {
		unset( $args, $allow_relaxed_file_ownership );

		if ( ! self::is_upgrade_trace_active() ) {
			return $method;
		}

		self::$upgrade_trace['filesystem_method'] = (string) $method;
		self::mark_upgrade_stage( 'filesystem_method' );

		self::log(
			'WordPress selected a filesystem method for the TPW Core upgrade.',
			array(
				'filesystem_method' => (string) $method,
				'context'           => (string) $context,
			)
		);

		return $method;
	}

	/**
	 * Log package download HTTP responses and transport failures.
	 *
	 * @param array|WP_Error $response    HTTP response or WP_Error.
	 * @param string         $context     HTTP debug context.
	 * @param string         $class       Transport class.
	 * @param array          $parsed_args Request args.
	 * @param string         $url         Request URL.
	 * @return void
	 */
	public static function log_http_api_debug( $response, $context, $class, $parsed_args, $url ) {
		unset( $parsed_args );

		if ( ! self::is_upgrade_trace_active() || 'response' !== $context || self::MANIFEST_URL === $url ) {
			return;
		}

		if ( ! self::looks_like_package_http_request( $url ) ) {
			return;
		}

		self::mark_upgrade_stage( 'http_response' );

		$context_data = array(
			'url'       => (string) $url,
			'transport' => (string) $class,
		);

		if ( is_wp_error( $response ) ) {
			$context_data['wp_error'] = self::describe_wp_error( $response );
			self::$upgrade_trace['http_error'] = $context_data['wp_error'];
			self::record_upgrade_error( 'http_response', $response );
			self::log( 'HTTP transport returned a WP_Error while fetching the TPW Core package.', $context_data );
			return;
		}

		$context_data['status_code'] = (int) wp_remote_retrieve_response_code( $response );
		$context_data['headers'] = array(
			'content_type' => wp_remote_retrieve_header( $response, 'content-type' ),
			'content_length' => wp_remote_retrieve_header( $response, 'content-length' ),
			'location' => wp_remote_retrieve_header( $response, 'location' ),
		);

		self::log( 'HTTP transport returned a response while fetching the TPW Core package.', $context_data );
	}

	/**
	 * Log the upgrader result object exposed at process completion.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Upgrade options.
	 * @return void
	 */
	public static function log_upgrader_process_complete( $upgrader, $options ) {
		if ( ! self::should_trace_upgrade( $options ) ) {
			return;
		}

		self::mark_upgrade_stage( 'process_complete' );

		$context = array(
			'hook_extra' => self::summarize_hook_extra( $options ),
			'result'     => isset( $upgrader->result ) ? self::summarize_upgrader_result( $upgrader->result ) : array(),
		);

		if ( isset( $upgrader->skin ) && is_object( $upgrader->skin ) && method_exists( $upgrader->skin, 'get_errors' ) ) {
			$skin_errors = $upgrader->skin->get_errors();
			if ( is_wp_error( $skin_errors ) && $skin_errors->has_errors() ) {
				$context['skin_errors'] = self::describe_wp_error( $skin_errors );
				self::record_upgrade_error( 'process_complete_skin', $skin_errors );
			}
		}

		self::log( 'TPW Core upgrader process completed.', $context );
	}

	/**
	 * Log a single end-of-request summary for the traced upgrade attempt.
	 *
	 * @return void
	 */
	public static function log_upgrade_trace_summary() {
		if ( ! self::is_upgrade_trace_active() ) {
			return;
		}

		$summary = self::build_upgrade_trace_summary();
		self::log( 'TPW Core upgrade trace summary.', $summary );
	}

	/**
	 * Clear updater caches on demand for manual testing requests.
	 *
	 * @return void
	 */
	public static function maybe_force_refresh() {
		if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( self::is_manual_check_again_request() ) {
			self::clear_update_caches();
			return;
		}

		if ( empty( $_GET[ self::REFRESH_QUERY_ARG ] ) ) {
			return;
		}

		check_admin_referer( self::REFRESH_QUERY_ARG );

		self::clear_update_caches();

		$redirect_url = remove_query_arg(
			array( self::REFRESH_QUERY_ARG, '_wpnonce' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Get cached manifest data or fetch a fresh copy.
	 *
	 * @param array<string, bool|string> $args Manifest retrieval arguments.
	 * @return array<string, string>
	 */
	private static function get_manifest( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'force_refresh' => false,
				'context'       => 'default',
			)
		);

		$force_refresh = ! empty( $args['force_refresh'] );
		$context = isset( $args['context'] ) ? (string) $args['context'] : 'default';

		if ( $force_refresh && is_array( self::$request_manifest ) ) {
			return self::normalize_manifest_response( self::$request_manifest );
		}

		if ( ! $force_refresh ) {
			$cached = get_site_transient( self::CACHE_KEY );

			if ( is_array( $cached ) ) {
				return self::normalize_manifest_response( $cached );
			}
		} else {
			self::bypass_manifest_cache( $context );
		}

		$manifest = self::fetch_manifest_from_remote( $context );
		self::$request_manifest = $manifest;

		return self::normalize_manifest_response( $manifest );
	}

	/**
	 * Fetch a fresh manifest from the remote source.
	 *
	 * @param string $context Manifest request context for logging.
	 * @return array<string, string|int>
	 */
	private static function fetch_manifest_from_remote( $context ) {
		$response = wp_remote_get(
			self::MANIFEST_URL,
			array(
				'timeout'    => 10,
				'user-agent' => 'TPW Core Updater/' . TPW_CORE_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log( 'Manifest request failed.', array(
				'context' => $context,
				'error' => $response->get_error_message(),
			) );
			return self::cache_manifest_failure();
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			self::log( 'Manifest request returned a non-200 response.', array(
				'context'     => $context,
				'status_code' => $status_code,
			) );
			return self::cache_manifest_failure();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			self::log( 'Manifest response could not be decoded as JSON.', array(
				'context' => $context,
			) );
			return self::cache_manifest_failure();
		}

		$manifest = array(
			'version'      => isset( $data['version'] ) ? trim( (string) $data['version'] ) : '',
			'download_url' => isset( $data['download_url'] ) ? esc_url_raw( (string) $data['download_url'] ) : '',
		);

		if ( '' === $manifest['version'] || '' === $manifest['download_url'] ) {
			$manifest['context'] = $context;
			self::log( 'Manifest was missing required fields.', $manifest );
			return self::cache_manifest_failure();
		}

		set_site_transient( self::CACHE_KEY, $manifest, self::CACHE_TTL );

		return $manifest;
	}

	/**
	 * Cache a manifest fetch failure briefly to avoid repeated remote requests.
	 *
	 * @return array<string, int>
	 */
	private static function cache_manifest_failure() {
		$failure_marker = array(
			'_error' => 1,
		);

		set_site_transient(
			self::CACHE_KEY,
			$failure_marker,
			self::FAILURE_CACHE_TTL
		);

		return $failure_marker;
	}

	/**
	 * Resolve manifest retrieval behaviour for the current request.
	 *
	 * @return array<string, bool|string>
	 */
	private static function get_manifest_request_args() {
		$hook = current_filter();
		$args = array(
			'force_refresh' => false,
			'context'       => $hook ? $hook : 'default',
		);

		if ( 'pre_set_site_transient_update_plugins' === $hook ) {
			$args['force_refresh'] = true;
			$args['context'] = 'wp_update_plugins';
		} elseif ( 'site_transient_update_plugins' === $hook && self::is_manual_check_again_request() ) {
			$args['force_refresh'] = true;
			$args['context'] = 'dashboard_check_again';
		} elseif ( 'site_transient_update_plugins' === $hook && function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			$args['force_refresh'] = true;
			$args['context'] = 'wp_cron_update_check';
		}

		return $args;
	}

	/**
	 * Normalize cached or request-scoped manifest responses.
	 *
	 * @param array<string, string|int> $manifest Manifest or failure marker.
	 * @return array<string, string>
	 */
	private static function normalize_manifest_response( $manifest ) {
		if ( ! is_array( $manifest ) || ! empty( $manifest['_error'] ) ) {
			return array();
		}

		return $manifest;
	}

	/**
	 * Bypass the persistent manifest cache for active update checks.
	 *
	 * @param string $context Manifest request context for logging.
	 * @return void
	 */
	private static function bypass_manifest_cache( $context ) {
		if ( self::$did_bypass_manifest_cache ) {
			return;
		}

		delete_site_transient( self::CACHE_KEY );
		self::$did_bypass_manifest_cache = true;

		self::log( 'Bypassing cached manifest for active update check.', array(
			'context'   => $context,
			'cache_use' => 'bypass_persistent_manifest_cache',
		) );
	}

	/**
	 * Determine whether the current admin request is Dashboard > Updates > Check Again.
	 *
	 * @return bool
	 */
	private static function is_manual_check_again_request() {
		if ( ! is_admin() ) {
			return false;
		}

		global $pagenow;

		if ( 'update-core.php' !== $pagenow ) {
			return false;
		}

		return null !== filter_input( INPUT_GET, 'force-check', FILTER_DEFAULT );
	}

	/**
	 * Resolve the installed plugin version from WordPress.
	 *
	 * @param object $transient WordPress update transient.
	 * @return string
	 */
	private static function get_installed_version( $transient ) {
		if ( is_object( $transient ) && ! empty( $transient->checked ) && is_array( $transient->checked ) ) {
			if ( ! empty( $transient->checked[ self::PLUGIN_BASENAME ] ) ) {
				return trim( (string) $transient->checked[ self::PLUGIN_BASENAME ] );
			}
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_BASENAME;
		if ( file_exists( $plugin_file ) && is_readable( $plugin_file ) ) {
			$plugin_data = get_plugin_data( $plugin_file, false, false );
			if ( ! empty( $plugin_data['Version'] ) ) {
				return trim( (string) $plugin_data['Version'] );
			}
		}

		if ( defined( 'TPW_CORE_VERSION' ) ) {
			return trim( (string) TPW_CORE_VERSION );
		}

		return '';
	}

	/**
	 * Remove TPW Core entries from the update transient.
	 *
	 * @param object $transient WordPress update transient.
	 * @return void
	 */
	private static function clear_transient_entries( $transient ) {
		if ( isset( $transient->response[ self::PLUGIN_BASENAME ] ) ) {
			unset( $transient->response[ self::PLUGIN_BASENAME ] );
		}

		if ( isset( $transient->no_update[ self::PLUGIN_BASENAME ] ) ) {
			unset( $transient->no_update[ self::PLUGIN_BASENAME ] );
		}
	}

	/**
	 * Clear TPW Core updater caches and refresh the plugin update cache.
	 *
	 * @return void
	 */
	private static function clear_update_caches() {
		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );

		if ( ! function_exists( 'wp_clean_plugins_cache' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		wp_clean_plugins_cache( true );
	}

	/**
	 * Write updater diagnostics to debug.log when WordPress debugging is enabled.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional scalar context fields.
	 * @return void
	 */
	private static function log( $message, $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$payload = '';
		if ( ! empty( $context ) ) {
			$payload = ' ' . wp_json_encode( $context );
		}

		error_log( '[TPW Core Updater] ' . $message . $payload );
	}

	/**
	 * Activate request-scoped tracing for the current TPW Core upgrade.
	 *
	 * @param string $package    Package URL.
	 * @param array  $hook_extra Upgrader hook context.
	 * @return void
	 */
	private static function activate_upgrade_trace( $package, $hook_extra ) {
		self::$upgrade_trace['active'] = true;
		if ( '' !== $package ) {
			self::$upgrade_trace['package'] = $package;
		}

		if ( ! empty( $hook_extra ) ) {
			self::$upgrade_trace['hook_extra'] = self::summarize_hook_extra( $hook_extra );
		}
	}

	/**
	 * Determine whether a TPW Core upgrade trace is active for this request.
	 *
	 * @return bool
	 */
	private static function is_upgrade_trace_active() {
		return ! empty( self::$upgrade_trace['active'] );
	}

	/**
	 * Mark a traced upgrade stage as reached.
	 *
	 * @param string $stage Stage name.
	 * @return void
	 */
	private static function mark_upgrade_stage( $stage ) {
		if ( ! self::is_upgrade_trace_active() ) {
			return;
		}

		self::$upgrade_trace['stages'][ $stage ] = true;
	}

	/**
	 * Record the latest upgrade-stage error for summary logging.
	 *
	 * @param string   $stage Stage name.
	 * @param WP_Error $error Error object.
	 * @return void
	 */
	private static function record_upgrade_error( $stage, $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}

		self::$upgrade_trace['last_error'] = array(
			'stage'    => $stage,
			'wp_error' => self::describe_wp_error( $error ),
		);
	}

	/**
	 * Determine whether the current upgrader hook targets TPW Core.
	 *
	 * @param array  $hook_extra Upgrader hook context.
	 * @param string $package    Package URL.
	 * @return bool
	 */
	private static function should_trace_upgrade( $hook_extra = array(), $package = '' ) {
		if ( ! empty( $hook_extra['plugin'] ) && self::PLUGIN_BASENAME === $hook_extra['plugin'] ) {
			return true;
		}

		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) && in_array( self::PLUGIN_BASENAME, $hook_extra['plugins'], true ) ) {
			return true;
		}

		if ( ! empty( $hook_extra['temp_backup']['slug'] ) && self::PLUGIN_SLUG === $hook_extra['temp_backup']['slug'] ) {
			return true;
		}

		if ( '' !== $package && self::looks_like_package_http_request( $package ) ) {
			return true;
		}

		return self::is_upgrade_trace_active();
	}

	/**
	 * Determine whether a URL/path looks like the TPW Core package request.
	 *
	 * @param string $url URL or path.
	 * @return bool
	 */
	private static function looks_like_package_http_request( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		if ( false !== strpos( $url, '/tpw-core.zip' ) ) {
			return true;
		}

		if ( false !== strpos( $url, '/tpw-core/releases/' ) ) {
			return true;
		}

		return false !== strpos( $url, self::PLUGIN_SLUG );
	}

	/**
	 * Build a compact hook_extra summary for logging.
	 *
	 * @param array $hook_extra Upgrader hook context.
	 * @return array<string, mixed>
	 */
	private static function summarize_hook_extra( $hook_extra ) {
		if ( ! is_array( $hook_extra ) ) {
			return array();
		}

		$summary = array();

		foreach ( array( 'action', 'type', 'plugin', 'bulk' ) as $key ) {
			if ( isset( $hook_extra[ $key ] ) ) {
				$summary[ $key ] = self::normalize_log_value( $hook_extra[ $key ] );
			}
		}

		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$summary['plugins'] = array_values( $hook_extra['plugins'] );
		}

		if ( ! empty( $hook_extra['temp_backup'] ) && is_array( $hook_extra['temp_backup'] ) ) {
			$summary['temp_backup'] = array(
				'slug' => isset( $hook_extra['temp_backup']['slug'] ) ? (string) $hook_extra['temp_backup']['slug'] : '',
				'dir'  => isset( $hook_extra['temp_backup']['dir'] ) ? (string) $hook_extra['temp_backup']['dir'] : '',
			);
		}

		return $summary;
	}

	/**
	 * Build a compact upgrader result summary for logging.
	 *
	 * @param array|WP_Error|mixed $result Upgrader result.
	 * @return array<string, mixed>
	 */
	private static function summarize_upgrader_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return array(
				'wp_error' => self::describe_wp_error( $result ),
			);
		}

		if ( ! is_array( $result ) ) {
			return array(
				'value' => self::normalize_log_value( $result ),
			);
		}

		$summary = array();
		foreach ( array( 'source', 'destination', 'destination_name', 'local_destination', 'remote_destination', 'clear_destination' ) as $key ) {
			if ( isset( $result[ $key ] ) ) {
				$summary[ $key ] = self::normalize_log_value( $result[ $key ] );
			}
		}

		return $summary;
	}

	/**
	 * Convert a WP_Error to structured log data.
	 *
	 * @param WP_Error $error Error object.
	 * @return array<string, mixed>
	 */
	private static function describe_wp_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return array();
		}

		$details = array(
			'codes'    => array(),
			'messages' => array(),
			'data'     => array(),
		);

		foreach ( $error->get_error_codes() as $code ) {
			$details['codes'][] = $code;
			$details['messages'][ $code ] = $error->get_error_message( $code );
			$details['data'][ $code ] = self::normalize_log_value( $error->get_error_data( $code ) );
		}

		return $details;
	}

	/**
	 * Normalize values into compact, log-safe scalars and arrays.
	 *
	 * @param mixed $value Log value.
	 * @param int   $depth Current recursion depth.
	 * @return mixed
	 */
	private static function normalize_log_value( $value, $depth = 0 ) {
		if ( $depth > 2 ) {
			return 'depth_limit';
		}

		if ( is_wp_error( $value ) ) {
			return self::describe_wp_error( $value );
		}

		if ( is_scalar( $value ) || null === $value ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			$normalized = array();
			foreach ( $value as $key => $item ) {
				$normalized[ $key ] = self::normalize_log_value( $item, $depth + 1 );
			}

			return $normalized;
		}

		if ( is_object( $value ) ) {
			return array(
				'class' => get_class( $value ),
			);
		}

		return (string) $value;
	}

	/**
	 * Build a compact final summary that identifies the most likely failure point.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_upgrade_trace_summary() {
		$stages = isset( self::$upgrade_trace['stages'] ) && is_array( self::$upgrade_trace['stages'] ) ? array_keys( self::$upgrade_trace['stages'] ) : array();
		$failure = array(
			'failure_point' => 'unknown',
			'issue_type'    => 'unknown',
			'package'       => isset( self::$upgrade_trace['package'] ) ? self::$upgrade_trace['package'] : '',
			'stages'        => $stages,
			'filesystem_method' => isset( self::$upgrade_trace['filesystem_method'] ) ? self::$upgrade_trace['filesystem_method'] : '',
			'wp_error'      => isset( self::$upgrade_trace['last_error']['wp_error'] ) ? self::$upgrade_trace['last_error']['wp_error'] : array(),
		);

		if ( ! empty( self::$upgrade_trace['last_error']['stage'] ) ) {
			$failure['failure_point'] = self::$upgrade_trace['last_error']['stage'];
		}

		$error_codes = ! empty( $failure['wp_error']['codes'] ) ? $failure['wp_error']['codes'] : array();

		if ( ! empty( self::$upgrade_trace['http_error'] ) || array_intersect( array( 'download_failed', 'http_request_failed', 'http_no_url' ), $error_codes ) ) {
			$failure['issue_type'] = 'remote_download_or_redirect';
		} elseif ( ! empty( self::$upgrade_trace['filesystem_credentials_prompted'] ) || array_intersect( array( 'fs_unavailable', 'fs_error', 'fs_no_content_dir', 'fs_no_plugins_dir', 'files_not_writable', 'mkdir_failed_destination', 'fs_temp_backup_mkdir', 'fs_temp_backup_move' ), $error_codes ) ) {
			$failure['issue_type'] = 'filesystem_or_write_access';
		} elseif ( array_intersect( array( 'incompatible_archive', 'incompatible_archive_empty', 'incompatible_archive_no_plugins', 'source_read_failed', 'new_source_read_failed' ), $error_codes ) ) {
			$failure['issue_type'] = 'zip_or_package_structure';
		} elseif ( array_intersect( array( 'remove_old_failed', 'folder_exists' ), $error_codes ) ) {
			$failure['issue_type'] = 'plugin_replacement_or_install_step';
		} elseif ( in_array( 'process_complete', $stages, true ) && empty( $error_codes ) ) {
			$failure['failure_point'] = 'none';
			$failure['issue_type'] = 'success';
		}

		if ( ! empty( self::$upgrade_trace['filesystem_credentials_error'] ) ) {
			$failure['filesystem_credentials_error'] = self::$upgrade_trace['filesystem_credentials_error'];
		}

		if ( ! empty( self::$upgrade_trace['http_error'] ) ) {
			$failure['http_error'] = self::$upgrade_trace['http_error'];
		}

		return $failure;
	}

	/**
	 * Build a simple changelog section for the plugin information modal.
	 *
	 * @return string
	 */
	private static function build_changelog_section() {
		$changelog_file = TPW_CORE_PATH . 'CHANGELOG.md';

		if ( ! file_exists( $changelog_file ) || ! is_readable( $changelog_file ) ) {
			return '<p>See the project changelog for recent release notes.</p>';
		}

		$contents = file_get_contents( $changelog_file );
		if ( ! is_string( $contents ) || '' === $contents ) {
			return '<p>See the project changelog for recent release notes.</p>';
		}

		$sections = preg_split( '/\R\R+/', trim( $contents ) );
		$top = array_slice( $sections, 0, 3 );
		$text = implode( "\n\n", $top );

		return wpautop( esc_html( $text ) );
	}
}