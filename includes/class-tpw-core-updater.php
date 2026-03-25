<?php
/**
 * Lightweight GitHub-based updater for TPW Core.
 *
 * Reads the public version manifest, caches it, injects updates into the
 * standard WordPress plugin update transient, and supplies basic plugin info
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
	 * Register updater hooks.
	 *
	 * @return void
	 */
	public static function init() {
		self::log( 'Updater class loaded and hooks registered.', array(
			'plugin_basename' => self::PLUGIN_BASENAME,
			'slug'            => self::PLUGIN_SLUG,
		) );

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_manifest_cache_on_upgrade' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_force_refresh' ) );
	}

	/**
	 * Inject TPW Core update metadata into the WordPress plugin updates transient.
	 *
	 * @param object $transient WordPress update transient.
	 * @return object
	 */
	public static function inject_update( $transient ) {
		self::log( 'Update transient hook fired.', array(
			'hook'            => current_filter(),
			'plugin_basename' => self::PLUGIN_BASENAME,
		) );

		if ( ! is_object( $transient ) ) {
			self::log( 'Update transient was not an object; skipping injection.' );
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		$installed_version = self::get_installed_version( $transient );
		$manifest = self::get_manifest();
		$manifest_version = ! empty( $manifest['version'] ) ? $manifest['version'] : '';
		$comparison = '';

		if ( '' !== $installed_version && '' !== $manifest_version ) {
			$comparison = version_compare( $manifest_version, $installed_version, '>' ) ? 'remote_newer' : 'installed_current';
		}

		self::log( 'Version state resolved.', array(
			'installed_version' => $installed_version,
			'manifest_version'  => $manifest_version,
			'comparison'        => $comparison,
		) );

		if ( empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
			self::clear_transient_entries( $transient );
			self::log( 'Manifest was empty or invalid; no update injected.' );
			return $transient;
		}

		if ( '' === $installed_version ) {
			self::log( 'Installed version could not be resolved; skipping injection.' );
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
			self::log( 'Remote version is not newer; no update injected.', array(
				'installed_version' => $installed_version,
				'manifest_version'  => $manifest['version'],
			) );
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
			'plugin_basename' => self::PLUGIN_BASENAME,
			'new_version'     => $manifest['version'],
			'package'         => $manifest['download_url'],
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
		self::log( 'plugins_api called.', array(
			'action' => $action,
			'slug'   => is_object( $args ) && isset( $args->slug ) ? $args->slug : '',
		) );

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
			self::log( 'Plugin upgrade completed; clearing updater caches.', array(
				'plugin_basename' => self::PLUGIN_BASENAME,
			) );
			self::clear_update_caches();
		}
	}

	/**
	 * Temporarily clear updater caches on demand for manual testing.
	 *
	 * @return void
	 */
	public static function maybe_force_refresh() {
		if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( empty( $_GET[ self::REFRESH_QUERY_ARG ] ) ) {
			return;
		}

		check_admin_referer( self::REFRESH_QUERY_ARG );

		self::clear_update_caches();

		self::log( 'Temporary updater refresh helper triggered.', array(
			'user_id' => get_current_user_id(),
		) );

		$redirect_url = remove_query_arg(
			array( self::REFRESH_QUERY_ARG, '_wpnonce' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Get cached manifest data or fetch a fresh copy.
	 *
	 * @return array<string, string>
	 */
	private static function get_manifest() {
		$cached = get_site_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			self::log( 'Using cached manifest response.', array(
				'has_error' => ! empty( $cached['_error'] ) ? 'yes' : 'no',
			) );

			if ( ! empty( $cached['_error'] ) ) {
				return array();
			}

			return $cached;
		}

		$response = wp_remote_get(
			self::MANIFEST_URL,
			array(
				'timeout'    => 10,
				'user-agent' => 'TPW Core Updater/' . TPW_CORE_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log( 'Manifest request failed.', array(
				'error' => $response->get_error_message(),
			) );
			self::cache_manifest_failure();
			return array();
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			self::log( 'Manifest request returned a non-200 response.', array(
				'status_code' => $status_code,
			) );
			self::cache_manifest_failure();
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			self::log( 'Manifest response could not be decoded as JSON.' );
			self::cache_manifest_failure();
			return array();
		}

		$manifest = array(
			'version'      => isset( $data['version'] ) ? trim( (string) $data['version'] ) : '',
			'download_url' => isset( $data['download_url'] ) ? esc_url_raw( (string) $data['download_url'] ) : '',
		);

		if ( '' === $manifest['version'] || '' === $manifest['download_url'] ) {
			self::log( 'Manifest was missing required fields.', $manifest );
			self::cache_manifest_failure();
			return array();
		}

		set_site_transient( self::CACHE_KEY, $manifest, self::CACHE_TTL );
		self::log( 'Fetched and cached manifest.', $manifest );

		return $manifest;
	}

	/**
	 * Cache a manifest fetch failure briefly to avoid repeated remote requests.
	 *
	 * @return void
	 */
	private static function cache_manifest_failure() {
		set_site_transient(
			self::CACHE_KEY,
			array(
				'_error' => 1,
			),
			self::FAILURE_CACHE_TTL
		);

		self::log( 'Cached manifest failure marker.', array(
			'cache_key' => self::CACHE_KEY,
		) );
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