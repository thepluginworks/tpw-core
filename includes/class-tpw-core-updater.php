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
	 * Register updater hooks.
	 *
	 * @return void
	 */
	public static function init() {
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
		$sections = self::get_plugin_information_sections();

		return (object) array(
			'name'          => 'TPW Core',
			'slug'          => self::PLUGIN_SLUG,
			'plugin_name'   => 'TPW Core',
			'version'       => $version,
			'author'        => '<a href="' . esc_url( self::HOMEPAGE ) . '">ThePluginWorks</a>',
			'homepage'      => self::HOMEPAGE,
			'download_link' => $download_link,
			'external'      => true,
			'sections'      => $sections,
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
	 * Clear updater caches on demand for WordPress check-again requests.
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
			unset( $context );
			return self::cache_manifest_failure();
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			unset( $context, $status_code );
			return self::cache_manifest_failure();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			unset( $context );
			return self::cache_manifest_failure();
		}

		$manifest = array(
			'version'      => isset( $data['version'] ) ? trim( (string) $data['version'] ) : '',
			'download_url' => isset( $data['download_url'] ) ? esc_url_raw( (string) $data['download_url'] ) : '',
		);

		if ( '' === $manifest['version'] || '' === $manifest['download_url'] ) {
			unset( $context );
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

		unset( $context );
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
	 * Build plugin information modal sections from the bundled readme.
	 *
	 * @return array<string, string>
	 */
	private static function get_plugin_information_sections() {
		$sections = self::get_readme_sections();

		$description = ! empty( $sections['description'] )
			? self::format_readme_section_html( $sections['description'] )
			: '<p>TPW Core provides shared RSVP, member, payment, branding, and system-page functionality for the TPW plugin ecosystem.</p>';

		$changelog = ! empty( $sections['changelog'] )
			? self::format_readme_section_html( $sections['changelog'] )
			: '<p>See the bundled plugin readme for recent release notes.</p>';

		return array(
			'description' => $description,
			'changelog'   => $changelog,
		);
	}

	/**
	 * Extract named sections from the bundled readme.txt file.
	 *
	 * @return array<string, string>
	 */
	private static function get_readme_sections() {
		$readme_file = TPW_CORE_PATH . 'readme.txt';

		if ( ! file_exists( $readme_file ) || ! is_readable( $readme_file ) ) {
			return array();
		}

		$contents = file_get_contents( $readme_file );
		if ( ! is_string( $contents ) || '' === $contents ) {
			return array();
		}

		$contents = self::normalize_readme_line_endings( $contents );
		$matches = array();

		if ( ! preg_match_all( '/^==\s*(.+?)\s*==\s*$/m', $contents, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$sections = array();
		$total_matches = count( $matches[0] );

		for ( $index = 0; $index < $total_matches; $index++ ) {
			$section_name = strtolower( trim( (string) $matches[1][ $index ][0] ) );
			$section_start = $matches[0][ $index ][1] + strlen( (string) $matches[0][ $index ][0] );
			$section_end = isset( $matches[0][ $index + 1 ] )
				? $matches[0][ $index + 1 ][1]
				: strlen( $contents );

			$section_body = trim( substr( $contents, $section_start, $section_end - $section_start ) );

			if ( '' !== $section_body ) {
				$sections[ $section_name ] = $section_body;
			}
		}

		return $sections;
	}

	/**
	 * Normalize readme line endings for section parsing.
	 *
	 * @param string $contents Raw readme contents.
	 * @return string
	 */
	private static function normalize_readme_line_endings( $contents ) {
		return str_replace( array( "\r\n", "\r" ), "\n", $contents );
	}

	/**
	 * Format a readme section into safe HTML for the plugin details modal.
	 *
	 * @param string $content Readme section body.
	 * @return string
	 */
	private static function format_readme_section_html( $content ) {
		$content = trim( self::normalize_readme_line_endings( $content ) );

		if ( '' === $content ) {
			return '';
		}

		$lines = explode( "\n", $content );
		$html = array();
		$paragraph_lines = array();
		$list_items = array();

		foreach ( $lines as $line ) {
			$trimmed_line = trim( $line );

			if ( '' === $trimmed_line ) {
				self::flush_readme_list_items( $html, $list_items );
				self::flush_readme_paragraph_lines( $html, $paragraph_lines );
				continue;
			}

			if ( preg_match( '/^=\s*(.+?)\s*=$/', $trimmed_line, $matches ) ) {
				self::flush_readme_list_items( $html, $list_items );
				self::flush_readme_paragraph_lines( $html, $paragraph_lines );
				$html[] = '<h4>' . esc_html( $matches[1] ) . '</h4>';
				continue;
			}

			if ( preg_match( '/^[-*]\s+(.+)$/', $trimmed_line, $matches ) ) {
				self::flush_readme_paragraph_lines( $html, $paragraph_lines );
				$list_items[] = $matches[1];
				continue;
			}

			$paragraph_lines[] = $trimmed_line;
		}

		self::flush_readme_list_items( $html, $list_items );
		self::flush_readme_paragraph_lines( $html, $paragraph_lines );

		return wp_kses_post( implode( "\n", $html ) );
	}

	/**
	 * Flush accumulated readme paragraph lines into HTML output.
	 *
	 * @param array<int, string> $html            Output HTML fragments.
	 * @param array<int, string> $paragraph_lines Buffered paragraph lines.
	 * @return void
	 */
	private static function flush_readme_paragraph_lines( &$html, &$paragraph_lines ) {
		if ( empty( $paragraph_lines ) ) {
			return;
		}

		$text = trim( implode( ' ', array_map( 'trim', $paragraph_lines ) ) );
		$paragraph_lines = array();

		if ( '' === $text ) {
			return;
		}

		$html[] = '<p>' . esc_html( $text ) . '</p>';
	}

	/**
	 * Flush accumulated readme list items into HTML output.
	 *
	 * @param array<int, string> $html       Output HTML fragments.
	 * @param array<int, string> $list_items Buffered list items.
	 * @return void
	 */
	private static function flush_readme_list_items( &$html, &$list_items ) {
		if ( empty( $list_items ) ) {
			return;
		}

		$html[] = '<ul>';

		foreach ( $list_items as $item ) {
			$html[] = '<li>' . esc_html( trim( $item ) ) . '</li>';
		}

		$html[] = '</ul>';
		$list_items = array();
	}
}