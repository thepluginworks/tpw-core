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
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_manifest_cache_on_upgrade' ), 10, 2 );
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

		if ( empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}

		$manifest = self::get_manifest();

		if ( empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
			return $transient;
		}

		if ( ! version_compare( $manifest['version'], TPW_CORE_VERSION, '>' ) ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
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
			delete_site_transient( self::CACHE_KEY );
		}
	}

	/**
	 * Get cached manifest data or fetch a fresh copy.
	 *
	 * @return array<string, string>
	 */
	private static function get_manifest() {
		$cached = get_site_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
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
			self::cache_manifest_failure();
			return array();
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			self::cache_manifest_failure();
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			self::cache_manifest_failure();
			return array();
		}

		$manifest = array(
			'version'      => isset( $data['version'] ) ? trim( (string) $data['version'] ) : '',
			'download_url' => isset( $data['download_url'] ) ? esc_url_raw( (string) $data['download_url'] ) : '',
		);

		if ( '' === $manifest['version'] || '' === $manifest['download_url'] ) {
			self::cache_manifest_failure();
			return array();
		}

		set_site_transient( self::CACHE_KEY, $manifest, self::CACHE_TTL );

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