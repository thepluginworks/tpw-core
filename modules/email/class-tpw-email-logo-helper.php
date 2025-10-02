<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Helper for generating a base64 (data URI) version of a logo with safety constraints.
 */
class TPW_Email_Logo_Helper {
    const MAX_BYTES = 51200; // 50KB
    const MAX_WIDTH = 400;   // px

    /**
     * Generate a base64 data URI for a given image URL or file path.
     * - Accepts PNG/JPEG only
     * - Resizes to MAX_WIDTH if wider, maintaining aspect ratio
     * - Ensures final payload <= MAX_BYTES (approximate; may early-return empty if too large)
     *
     * @param string $url Absolute URL to image (or local path)
     * @return string Base64 data URI or empty string on failure/constraints not met
     */
    public static function generate_base64( $url ) {
        $url = (string) $url;
        if ( $url === '' ) return '';

        // Reject SVG/BMP and unknown
        $ext = strtolower( pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, [ 'png', 'jpg', 'jpeg' ], true ) ) {
            return '';
        }

        // Attempt to resolve to local file path if hosted on this site
        $path = self::resolve_local_path( $url );
        if ( ! $path || ! file_exists( $path ) ) {
            return '';
        }

        // Enforce source file size sanity (skip obviously huge files)
        $src_size = @filesize( $path );
        if ( $src_size && $src_size > ( 2 * self::MAX_BYTES ) ) {
            // Allow processing, but we know we must resize/re-encode heavily; still attempt
        }

        // Use WordPress image editor to resize if needed
        if ( ! function_exists( 'wp_get_image_editor' ) ) {
            return '';
        }
        $editor = wp_get_image_editor( $path );
        if ( is_wp_error( $editor ) ) {
            return '';
        }
        $size = $editor->get_size();
        if ( is_array( $size ) && ! empty( $size['width'] ) && $size['width'] > self::MAX_WIDTH ) {
            $editor->resize( self::MAX_WIDTH, null );
        }

        // Choose output mime based on extension; prefer PNG/JPEG
        $mime = ( $ext === 'png' ) ? 'image/png' : 'image/jpeg';
        $quality = ( $mime === 'image/jpeg' ) ? 82 : null; // balance size/quality
        $tmp = $editor->save( null, $mime, $quality );
        if ( is_wp_error( $tmp ) || empty( $tmp['path'] ) ) {
            return '';
        }

        $data = @file_get_contents( $tmp['path'] );
        if ( ! $data ) {
            return '';
        }
        if ( strlen( $data ) > self::MAX_BYTES ) {
            // Too large for safe inline use
            @unlink( $tmp['path'] );
            return '';
        }

        $b64 = base64_encode( $data );
        @unlink( $tmp['path'] );
        if ( ! $b64 ) return '';
        return sprintf( 'data:%s;base64,%s', $mime, $b64 );
    }

    /**
     * Resolve a local filesystem path for a given site URL (uploads dir only).
     */
    protected static function resolve_local_path( $url ) {
        $uploads = wp_get_upload_dir();
        if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
            return '';
        }
        // Only allow files under uploads baseurl
        if ( strpos( $url, $uploads['baseurl'] ) !== 0 ) {
            return '';
        }
        $rel = ltrim( substr( $url, strlen( $uploads['baseurl'] ) ), '/' );
        $path = trailingslashit( $uploads['basedir'] ) . $rel;
        return $path;
    }
}
