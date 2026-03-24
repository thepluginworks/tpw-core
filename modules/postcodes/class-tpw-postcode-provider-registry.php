<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Central registry and settings normalizer for Core address lookup providers.
 */
class TPW_Postcode_Provider_Registry {
    const OPTION_KEY = 'tpw_postcode_settings';

    /**
     * @var array<string, array<string, mixed>>|null
     */
    protected static $providers = null;

    /**
     * Get the registered provider definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_registered_providers() {
        if ( null !== self::$providers ) {
            return self::$providers;
        }

        self::$providers = array(
            'none'            => array(
                'key'              => 'none',
                'label'            => __( 'None', 'tpw-core' ),
                'class'            => '',
                'supports_lookup'  => false,
                'supports_full'    => false,
                'settings_fields'  => array(),
                'settings_notice'  => __( 'Lookup controls stay hidden and address entry remains manual only.', 'tpw-core' ),
                'inactive_message' => __( 'Address lookup is disabled.', 'tpw-core' ),
            ),
            'ideal_postcodes' => array(
                'key'              => 'ideal_postcodes',
                'label'            => __( 'Ideal Postcodes', 'tpw-core' ),
                'class'            => 'TPW_Postcode_Provider_Ideal_Postcodes',
                'supports_lookup'  => true,
                'supports_full'    => true,
                'settings_fields'  => array(
                    array(
                        'key'         => 'ideal_postcodes_api_key',
                        'label'       => __( 'Ideal Postcodes API Key', 'tpw-core' ),
                        'description' => __( 'Required for live GB address lookup. Available from your Ideal Postcodes dashboard.', 'tpw-core' ),
                    ),
                ),
                'settings_notice'  => __( 'Ideal Postcodes is fully wired for live GB address lookup when a valid API key is configured.', 'tpw-core' ),
                'inactive_message' => __( 'Ideal Postcodes is selected but no API key has been configured yet.', 'tpw-core' ),
            ),
            'fetchify'        => array(
                'key'              => 'fetchify',
                'label'            => __( 'Fetchify', 'tpw-core' ),
                'class'            => 'TPW_Postcode_Provider_Fetchify',
                'supports_lookup'  => false,
                'supports_full'    => false,
                'settings_fields'  => array(
                    array(
                        'key'         => 'fetchify_access_token',
                        'label'       => __( 'Fetchify Access Token', 'tpw-core' ),
                        'description' => __( 'Stored in Core now so the provider can be selected safely. Live Fetchify lookup is not wired in this release.', 'tpw-core' ),
                    ),
                ),
                'settings_notice'  => __( 'Fetchify settings are scaffolded only in this Core release. Selecting Fetchify keeps manual address entry active and does not expose lookup UI yet.', 'tpw-core' ),
                'inactive_message' => __( 'Fetchify credentials can be stored here, but live Fetchify address lookup is not wired yet.', 'tpw-core' ),
            ),
        );

        return self::$providers;
    }

    /**
     * Get select options keyed by provider value.
     *
     * @return array<string, string>
     */
    public static function get_provider_choices() {
        $choices = array();

        foreach ( self::get_registered_providers() as $provider_key => $definition ) {
            $choices[ $provider_key ] = isset( $definition['label'] ) ? (string) $definition['label'] : $provider_key;
        }

        return $choices;
    }

    /**
     * Normalize persisted or filtered provider values.
     *
     * Removed legacy providers safely fall back to `none`.
     *
     * @param mixed $provider Raw provider value.
     * @return string
     */
    public static function normalize_provider( $provider ) {
        $provider = sanitize_key( (string) $provider );

        if ( in_array( $provider, array( 'postcodesio', 'getaddress', 'google' ), true ) ) {
            return 'none';
        }

        $providers = self::get_registered_providers();

        if ( ! isset( $providers[ $provider ] ) ) {
            return 'none';
        }

        return $provider;
    }

    /**
     * Load and normalize stored lookup settings.
     *
     * @return array<string, string>
     */
    public static function get_settings() {
        $stored = get_option( self::OPTION_KEY, array() );
        $stored = is_array( $stored ) ? $stored : array();

        return array(
            'provider'                => self::normalize_provider( isset( $stored['provider'] ) ? $stored['provider'] : 'none' ),
            'ideal_postcodes_api_key' => isset( $stored['ideal_postcodes_api_key'] ) ? sanitize_text_field( (string) $stored['ideal_postcodes_api_key'] ) : '',
            'fetchify_access_token'   => isset( $stored['fetchify_access_token'] ) ? sanitize_text_field( (string) $stored['fetchify_access_token'] ) : '',
        );
    }

    /**
     * Sanitize settings payloads before saving.
     *
     * @param array<string, mixed> $settings Raw settings payload.
     * @return array<string, string>
     */
    public static function sanitize_settings( $settings ) {
        $settings = is_array( $settings ) ? $settings : array();

        return array(
            'provider'                => self::normalize_provider( isset( $settings['provider'] ) ? $settings['provider'] : 'none' ),
            'ideal_postcodes_api_key' => isset( $settings['ideal_postcodes_api_key'] ) ? sanitize_text_field( wp_unslash( (string) $settings['ideal_postcodes_api_key'] ) ) : '',
            'fetchify_access_token'   => isset( $settings['fetchify_access_token'] ) ? sanitize_text_field( wp_unslash( (string) $settings['fetchify_access_token'] ) ) : '',
        );
    }

    /**
     * Resolve the active provider after applying filters.
     *
     * @param array<string, string>|null $settings Optional normalized settings.
     * @return string
     */
    public static function get_selected_provider( $settings = null ) {
        $settings = is_array( $settings ) ? $settings : self::get_settings();
        $provider = isset( $settings['provider'] ) ? self::normalize_provider( $settings['provider'] ) : 'none';
        $provider = apply_filters( 'tpw_postcode_lookup_provider', $provider );

        return self::normalize_provider( $provider );
    }

    /**
     * Get a provider definition by key.
     *
     * @param string|null $provider Provider key or null for active provider.
     * @return array<string, mixed>
     */
    public static function get_provider_definition( $provider = null ) {
        $providers = self::get_registered_providers();
        $provider  = null === $provider ? self::get_selected_provider() : self::normalize_provider( $provider );

        return isset( $providers[ $provider ] ) ? $providers[ $provider ] : $providers['none'];
    }

    /**
     * Determine whether live lookup is available for a provider.
     *
     * @param string|null                $provider Provider key or null for active provider.
     * @param array<string, string>|null $settings Optional normalized settings.
     * @return bool
     */
    public static function is_lookup_enabled( $provider = null, $settings = null ) {
        $settings = is_array( $settings ) ? $settings : self::get_settings();
        $provider = null === $provider ? self::get_selected_provider( $settings ) : self::normalize_provider( $provider );

        if ( 'none' === $provider ) {
            return false;
        }

        $class = self::get_provider_class( $provider );

        if ( '' === $class || ! is_callable( array( $class, 'supports_lookup' ) ) ) {
            return false;
        }

        return (bool) call_user_func( array( $class, 'supports_lookup' ), $settings );
    }

    /**
     * Determine whether the selected provider supports full address lists.
     *
     * @param string|null $provider Provider key or null for active provider.
     * @return bool
     */
    public static function provider_supports_full( $provider = null ) {
        $class = self::get_provider_class( $provider );

        if ( '' === $class || ! is_callable( array( $class, 'supports_full' ) ) ) {
            return false;
        }

        return (bool) call_user_func( array( $class, 'supports_full' ) );
    }

    /**
     * Central UI gate used by Core forms.
     *
     * @return bool
     */
    public static function should_render_lookup_ui() {
        return self::is_lookup_enabled();
    }

    /**
     * Shared front-end config for lookup consumers.
     *
     * @return array<string, mixed>
     */
    public static function get_frontend_config() {
        $provider   = self::get_selected_provider();
        $definition = self::get_provider_definition( $provider );

        return array(
            'enabled'       => self::is_lookup_enabled( $provider ),
            'provider'      => $provider,
            'providerLabel' => isset( $definition['label'] ) ? (string) $definition['label'] : $provider,
            'supportsFull'  => self::provider_supports_full( $provider ),
        );
    }

    /**
     * Execute a lookup against the active provider.
     *
     * @param string $postcode Raw postcode.
     * @param string $country Country code.
     * @param string $mode Lookup mode.
     * @param string $street_prefix Optional prefix filter for full address lists.
     * @return array<string, mixed>|false
     */
    public static function lookup( $postcode, $country = 'GB', $mode = 'basic', $street_prefix = '' ) {
        $postcode = is_string( $postcode ) ? trim( $postcode ) : '';

        if ( '' === $postcode ) {
            return false;
        }

        $settings = self::get_settings();
        $provider = self::get_selected_provider( $settings );

        if ( 'none' === $provider ) {
            return array(
                'error'   => 'lookup_disabled',
                'message' => __( 'Address lookup is disabled.', 'tpw-core' ),
            );
        }

        $definition = self::get_provider_definition( $provider );
        $class      = self::get_provider_class( $provider );

        if ( '' === $class || ! is_callable( array( $class, 'lookup' ) ) ) {
            return array(
                'error'   => 'no_provider',
                'message' => __( 'No valid address lookup provider is configured.', 'tpw-core' ),
            );
        }

        if ( ! self::is_lookup_enabled( $provider, $settings ) ) {
            return array(
                'error'   => 'lookup_unavailable',
                'message' => isset( $definition['inactive_message'] ) ? (string) $definition['inactive_message'] : __( 'Address lookup is not available for the selected provider.', 'tpw-core' ),
            );
        }

        $mode = is_string( $mode ) ? strtolower( trim( $mode ) ) : 'basic';

        if ( 'full' === $mode && ! self::provider_supports_full( $provider ) ) {
            $mode = 'basic';
        }

        return call_user_func( array( $class, 'lookup' ), $postcode, $country, $mode, $street_prefix, $settings );
    }

    /**
     * Resolve the implementation class for a provider.
     *
     * @param string|null $provider Provider key or null for active provider.
     * @return string
     */
    protected static function get_provider_class( $provider = null ) {
        $definition = self::get_provider_definition( $provider );
        $class      = isset( $definition['class'] ) ? (string) $definition['class'] : '';

        if ( '' === $class || ! class_exists( $class ) ) {
            return '';
        }

        return $class;
    }
}