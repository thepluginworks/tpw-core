<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Backwards-compatible façade for Core address lookup.
 */
class TPW_Postcode_Helper {
    /**
     * Get normalized lookup settings.
     *
     * @return array<string, string>
     */
    public static function get_settings() {
        return TPW_Postcode_Provider_Registry::get_settings();
    }

    /**
     * Get the active provider key.
     *
     * @return string
     */
    public static function get_provider() {
        return TPW_Postcode_Provider_Registry::get_selected_provider();
    }

    /**
     * Determine whether live lookup is enabled.
     *
     * @return bool
     */
    public static function is_lookup_enabled() {
        return TPW_Postcode_Provider_Registry::is_lookup_enabled();
    }

    /**
     * Central UI gate for Core forms.
     *
     * @return bool
     */
    public static function should_render_lookup_ui() {
        return TPW_Postcode_Provider_Registry::should_render_lookup_ui();
    }

    /**
     * Shared front-end config.
     *
     * @return array<string, mixed>
     */
    public static function get_frontend_config() {
        return TPW_Postcode_Provider_Registry::get_frontend_config();
    }

    /**
     * Lookup a postcode and return normalized address data.
     *
     * Filters:
     * - tpw_postcode_lookup_provider — override provider key.
     * - tpw_postcode_lookup_api_key — override provider credentials.
     *
     * @param string $postcode Raw postcode input.
     * @param string $country Country code.
     * @param string $mode Lookup mode (`basic` or `full`).
     * @param string $street_prefix Optional prefix filter for full address results.
     * @return array<string, mixed>|false
     */
    public static function lookup_postcode( $postcode, $country = 'GB', $mode = 'basic', $street_prefix = '' ) {
        return TPW_Postcode_Provider_Registry::lookup( $postcode, $country, $mode, $street_prefix );
    }
}