<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shared helpers for provider implementations.
 */
abstract class TPW_Postcode_Provider_Abstract {
    /**
     * Determine whether lookup can run for the provider.
     *
     * @param array<string, string> $settings Normalized settings.
     * @return bool
     */
    public static function supports_lookup( $settings ) {
        return static::is_configured( $settings );
    }

    /**
     * Determine whether the provider has enough configuration to run.
     *
     * @param array<string, string> $settings Normalized settings.
     * @return bool
     */
    public static function is_configured( $settings ) {
        return true;
    }

    /**
     * Whether the provider supports full address lists.
     *
     * @return bool
     */
    public static function supports_full() {
        return false;
    }

    /**
     * Build a standard error payload.
     *
     * @param string   $code Error code.
     * @param string   $message Human-readable message.
     * @param int|null $status Optional HTTP-like status.
     * @return array<string, mixed>
     */
    protected static function error( $code, $message, $status = null ) {
        $payload = array(
            'error'   => (string) $code,
            'message' => (string) $message,
        );

        if ( null !== $status ) {
            $payload['status'] = (int) $status;
        }

        return $payload;
    }

    /**
     * Build a standard success payload.
     *
     * @param string               $provider Provider key.
     * @param array<string, mixed> $data Provider-specific payload.
     * @return array<string, mixed>
     */
    protected static function success( $provider, $data = array() ) {
        $defaults = array(
            'postcode'      => '',
            'address1'      => '',
            'address2'      => '',
            'town'          => '',
            'county'        => '',
            'district'      => '',
            'region'        => '',
            'country'       => 'GB',
            'latitude'      => '',
            'longitude'     => '',
            'provider'      => $provider,
            'supports_full' => static::supports_full(),
            'addresses'     => array(),
        );

        return array_merge( $defaults, is_array( $data ) ? $data : array() );
    }

    /**
     * Remove whitespace for provider requests.
     *
     * @param string $postcode Raw postcode.
     * @return string
     */
    protected static function sanitize_postcode( $postcode ) {
        return strtoupper( preg_replace( '/\s+/', '', trim( (string) $postcode ) ) );
    }

    /**
     * Format a UK postcode in a readable spaced form.
     *
     * @param string $postcode Raw postcode.
     * @return string
     */
    protected static function normalize_uk_postcode( $postcode ) {
        $postcode = self::sanitize_postcode( $postcode );

        if ( strlen( $postcode ) < 5 ) {
            return $postcode;
        }

        return substr( $postcode, 0, -3 ) . ' ' . substr( $postcode, -3 );
    }

    /**
     * Join address fragments, skipping empties.
     *
     * @param array<int, mixed> $parts Parts to join.
     * @param string            $separator Separator.
     * @return string
     */
    protected static function join_parts( $parts, $separator = ', ' ) {
        $parts = array_filter(
            array_map(
                static function( $part ) {
                    return trim( (string) $part );
                },
                is_array( $parts ) ? $parts : array()
            ),
            static function( $part ) {
                return '' !== $part;
            }
        );

        return implode( $separator, $parts );
    }
}