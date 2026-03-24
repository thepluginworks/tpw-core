<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fetchify provider scaffold.
 */
class TPW_Postcode_Provider_Fetchify extends TPW_Postcode_Provider_Abstract {
    /**
     * @param array<string, string> $settings Normalized settings.
     * @return bool
     */
    public static function is_configured( $settings ) {
        return ! empty( $settings['fetchify_access_token'] );
    }

    /**
     * Fetchify is scaffolded only in this release, so lookup UI stays disabled.
     *
     * @param array<string, string> $settings Normalized settings.
     * @return bool
     */
    public static function supports_lookup( $settings ) {
        return false;
    }

    /**
     * @param string                $postcode Postcode.
     * @param string                $country Country code.
     * @param string                $mode Lookup mode.
     * @param string                $street_prefix Prefix filter.
     * @param array<string, string> $settings Normalized settings.
     * @return array<string, mixed>
     */
    public static function lookup( $postcode, $country, $mode, $street_prefix, $settings ) {
        return self::error(
            'provider_not_wired',
            __( 'Fetchify credentials can be stored in Core, but live Fetchify address lookup is not wired in this release.', 'tpw-core' )
        );
    }
}