<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Live Ideal Postcodes provider.
 */
class TPW_Postcode_Provider_Ideal_Postcodes extends TPW_Postcode_Provider_Abstract {
    /**
     * Normalize common UK labels back to GB for provider requests.
     *
     * @param string $country Raw country value.
     * @return string
     */
    protected static function normalize_lookup_country( $country ) {
        $country = strtoupper( trim( (string) $country ) );
        $country = preg_replace( '/\s+/', ' ', $country );

        if ( in_array( $country, array( 'GB', 'UK', 'GBR', 'UNITED KINGDOM', 'GREAT BRITAIN', 'ENGLAND', 'SCOTLAND', 'WALES', 'NORTHERN IRELAND' ), true ) ) {
            return 'GB';
        }

        return $country;
    }

    /**
     * @param array<string, string> $settings Normalized settings.
     * @return bool
     */
    public static function is_configured( $settings ) {
        return ! empty( $settings['ideal_postcodes_api_key'] );
    }

    /**
     * @return bool
     */
    public static function supports_full() {
        return true;
    }

    /**
     * Perform a postcode lookup.
     *
     * @param string                $postcode Postcode.
     * @param string                $country Country code.
     * @param string                $mode Lookup mode.
     * @param string                $street_prefix Optional street prefix.
     * @param array<string, string> $settings Normalized settings.
     * @return array<string, mixed>
     */
    public static function lookup( $postcode, $country, $mode, $street_prefix, $settings ) {
        $requested_country  = strtoupper( trim( (string) $country ) );
        $normalized_country = self::normalize_lookup_country( $requested_country );

        if ( 'GB' !== $normalized_country ) {
            return self::error( 'unsupported_country', __( 'Ideal Postcodes supports GB lookup only.', 'tpw-core' ) );
        }

        $api_key = isset( $settings['ideal_postcodes_api_key'] ) ? (string) $settings['ideal_postcodes_api_key'] : '';
        $api_key = apply_filters( 'tpw_postcode_lookup_api_key', $api_key, 'ideal_postcodes' );

        if ( '' === $api_key ) {
            return self::error( 'missing_api_key', __( 'Ideal Postcodes API key missing.', 'tpw-core' ) );
        }

        $postcode_compact = self::sanitize_postcode( $postcode );
        $url              = add_query_arg(
            array(
                'api_key' => $api_key,
            ),
            'https://api.ideal-postcodes.co.uk/v1/postcodes/' . rawurlencode( $postcode_compact )
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 12,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return self::error( 'http_error', $response->get_error_message() );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $data   = json_decode( $body, true );

        if ( 404 === $status ) {
            $message = is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : __( 'Postcode not found.', 'tpw-core' );

            if ( is_array( $data ) && ! empty( $data['suggestions'] ) && is_array( $data['suggestions'] ) ) {
                $message .= ' ' . sprintf(
                    /* translators: %s: suggestion list */
                    __( 'Suggestions: %s', 'tpw-core' ),
                    implode( ', ', array_map( 'sanitize_text_field', $data['suggestions'] ) )
                );
            }

            return self::error( 'postcode_not_found', $message, 404 );
        }

        if ( 200 !== $status ) {
            $message = is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : sprintf( __( 'HTTP %d from Ideal Postcodes.', 'tpw-core' ), $status );
            return self::error( 'http_status', $message, $status );
        }

        if ( ! is_array( $data ) || empty( $data['result'] ) || ! is_array( $data['result'] ) ) {
            return self::error( 'no_addresses', __( 'No addresses returned for this postcode.', 'tpw-core' ) );
        }

        $addresses = array();

        foreach ( $data['result'] as $result ) {
            if ( ! is_array( $result ) ) {
                continue;
            }

            $address = self::normalize_address( $result, $postcode, $normalized_country );

            if ( '' !== trim( (string) $street_prefix ) ) {
                $prefix = trim( (string) $street_prefix );
                if ( 0 !== strpos( $address['address1'], $prefix ) ) {
                    continue;
                }
            }

            $addresses[] = $address;
        }

        if ( empty( $addresses ) ) {
            return self::error( 'no_addresses', __( 'No matching addresses were returned for this postcode.', 'tpw-core' ) );
        }

        $first = $addresses[0];

        $payload = array(
            'postcode'      => $first['postcode'],
            'address1'      => $first['address1'],
            'address2'      => isset( $first['address2'] ) ? $first['address2'] : '',
            'town'          => $first['town'],
            'county'        => $first['county'],
            'district'      => '',
            'region'        => $first['county'],
            'country'       => $first['country'],
            'latitude'      => isset( $first['latitude'] ) ? $first['latitude'] : '',
            'longitude'     => isset( $first['longitude'] ) ? $first['longitude'] : '',
            'provider'      => 'ideal_postcodes',
            'supports_full' => true,
        );

        if ( 'full' === $mode ) {
            $payload['addresses'] = $addresses;
        }

        return self::success( 'ideal_postcodes', $payload );
    }

    /**
     * Convert an API result into the shared address shape.
     *
     * @param array<string, mixed> $result Result row.
     * @param string               $fallback_postcode Requested postcode.
     * @param string               $country Country code.
     * @return array<string, string>
     */
    protected static function normalize_address( $result, $fallback_postcode, $country ) {
        $line_1       = self::join_parts( array( isset( $result['line_1'] ) ? $result['line_1'] : '' ), '' );
        $line_2       = self::join_parts(
            array(
                isset( $result['line_2'] ) ? $result['line_2'] : '',
                isset( $result['line_3'] ) ? $result['line_3'] : '',
            )
        );
        $town         = isset( $result['post_town'] ) ? (string) $result['post_town'] : ( isset( $result['town'] ) ? (string) $result['town'] : '' );
        $county       = isset( $result['county'] ) ? (string) $result['county'] : ( isset( $result['administrative_county'] ) ? (string) $result['administrative_county'] : '' );
        $postcode     = isset( $result['postcode'] ) ? (string) $result['postcode'] : self::normalize_uk_postcode( $fallback_postcode );
        $country_name = isset( $result['country'] ) ? (string) $result['country'] : ( 'GB' === $country ? 'United Kingdom' : $country );
        $label        = self::join_parts( array( $line_1, $line_2, $town, $postcode ) );

        return array(
            'label'     => $label,
            'address1'  => $line_1,
            'address2'  => $line_2,
            'town'      => $town,
            'county'    => $county,
            'postcode'  => $postcode,
            'country'   => $country_name,
            'latitude'  => isset( $result['latitude'] ) ? (string) $result['latitude'] : '',
            'longitude' => isset( $result['longitude'] ) ? (string) $result['longitude'] : '',
        );
    }
}