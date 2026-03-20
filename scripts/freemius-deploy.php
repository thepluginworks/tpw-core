<?php
// phpcs:ignoreFile -- Workflow-only CLI deployment helper.

declare( strict_types=1 );

function tpw_freemius_fail( string $message, $context = null ): void {
	fwrite( STDERR, $message . PHP_EOL );

	if ( null !== $context ) {
		if ( is_string( $context ) ) {
			fwrite( STDERR, $context . PHP_EOL );
		} else {
			$json = json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			if ( false !== $json ) {
				fwrite( STDERR, $json . PHP_EOL );
			}
		}
	}

	exit( 1 );
}

function tpw_freemius_require_env( string $name ): string {
	$value = getenv( $name );

	if ( false === $value || '' === $value ) {
		tpw_freemius_fail( "Missing required environment variable: {$name}" );
	}

	return $value;
}

function tpw_freemius_assert_api_success( $result, string $context ) {
	if ( is_object( $result ) && isset( $result->error ) ) {
		tpw_freemius_fail( "Freemius {$context} failed.", $result );
	}

	return $result;
}

$sdk_dir      = rtrim( tpw_freemius_require_env( 'FREEMIUS_SDK_DIR' ), '/' );
$zip_path     = tpw_freemius_require_env( 'ZIP_PATH' );
$version      = tpw_freemius_require_env( 'VERSION' );
$dev_id       = tpw_freemius_require_env( 'FREEMIUS_DEV_ID' );
$public_key   = tpw_freemius_require_env( 'FREEMIUS_PUBLIC_KEY' );
$secret_key   = tpw_freemius_require_env( 'FREEMIUS_SECRET_KEY' );
$plugin_id    = tpw_freemius_require_env( 'FREEMIUS_PLUGIN_ID' );
$release_mode = getenv( 'FREEMIUS_RELEASE_MODE' );


if ( false === $release_mode || '' === $release_mode ) {
	$release_mode = 'released';
}


if ( ! is_file( $zip_path ) ) {
	tpw_freemius_fail( "Freemius deployment package not found: {$zip_path}" );
}

$freemius_base = $sdk_dir . '/freemius/FreemiusBase.php';
$freemius_sdk  = $sdk_dir . '/freemius/Freemius.php';

if ( ! is_file( $freemius_base ) || ! is_file( $freemius_sdk ) ) {
	tpw_freemius_fail( 'Official Freemius PHP SDK files are missing from the downloaded SDK directory.', array(
		'sdk_dir' => $sdk_dir,
		'expected' => array(
			$freemius_base,
			$freemius_sdk,
		),
	));
}

require_once $freemius_base;
require_once $freemius_sdk;

$api_class = 'Freemius_Api';

if ( ! class_exists( $api_class ) ) {
	tpw_freemius_fail( 'Official Freemius PHP SDK did not load Freemius_Api as expected.' );
}

echo "Freemius deploy version: {$version}" . PHP_EOL;
echo "Freemius deploy package: {$zip_path}" . PHP_EOL;
echo "Freemius deploy plugin id: {$plugin_id}" . PHP_EOL;
echo "Freemius deploy release mode: {$release_mode}" . PHP_EOL;

try {
	$api = new $api_class( 'developer', (int) $dev_id, $public_key, $secret_key, false );

	$upload = tpw_freemius_assert_api_success(
		$api->Api(
			'plugins/' . $plugin_id . '/tags.json',
			'POST',
			array(
				'add_contributor' => false,
			),
			array(
				'file' => $zip_path,
			)
		),
		'upload'
	);

	if ( ! is_object( $upload ) || ! isset( $upload->id ) ) {
		tpw_freemius_fail( 'Freemius upload did not return a tag id.', $upload );
	}

	echo 'Freemius uploaded tag:' . PHP_EOL;
	$upload_json = json_encode( $upload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( false !== $upload_json ) {
		echo $upload_json . PHP_EOL;
	}

	$release = tpw_freemius_assert_api_success(
		$api->Api(
			'plugins/' . $plugin_id . '/tags/' . $upload->id . '.json',
			'PUT',
			array(
				'release_mode' => $release_mode,
			)
		),
		'release-mode update'
	);

	echo 'Freemius release result:' . PHP_EOL;
	$release_json = json_encode( $release, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( false !== $release_json ) {
		echo $release_json . PHP_EOL;
	}

	if ( is_object( $release ) && isset( $release->release_mode ) && $release->release_mode !== $release_mode ) {
		tpw_freemius_fail(
			'Freemius release mode did not update to the requested value.',
			array(
				'expected' => $release_mode,
				'actual' => $release->release_mode,
			)
		);
	}

	echo 'Freemius deployment completed successfully.' . PHP_EOL;
} catch ( Throwable $throwable ) {
	tpw_freemius_fail( 'Freemius deployment threw an exception.', array(
		'type' => get_class( $throwable ),
		'message' => $throwable->getMessage(),
		'file' => $throwable->getFile(),
		'line' => $throwable->getLine(),
	));
}