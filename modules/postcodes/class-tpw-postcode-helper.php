<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Postcode lookup helper for TPW Core.
 *
 * Supports multiple providers (Postcodes.io, GetAddress.io, Google) and
 * exposes a single lookup method for basic town/county resolution and an
 * optional full address mode when supported. Provider and API keys are
 * filterable for integration flexibility.
 *
 * @since 1.0.0
 */
class TPW_Postcode_Helper {
    /**
     * Basic UK postcode pattern check (not exhaustive but practical for parsing).
     */
    protected static function looks_like_uk_postcode( $value ) {
        if ( ! is_string( $value ) ) return false;
        $v = strtoupper( trim( $value ) );
        return (bool) preg_match( '/^[A-Z]{1,2}[0-9][0-9A-Z]?\s*[0-9][A-Z]{2}$/', $v );
    }

    /**
     * Normalize a UK postcode to the standard spaced format (e.g., SW1A 1AA).
     */
    protected static function normalize_uk_postcode( $value ) {
        if ( ! is_string( $value ) ) return '';
        $v = strtoupper( preg_replace( '/\s+/', '', trim( $value ) ) );
        if ( strlen( $v ) < 5 ) return $v; // Bail if unexpected
        return substr( $v, 0, -3 ) . ' ' . substr( $v, -3 );
    }
    /**
     * Lookup a postcode and return location details.
     *
     * Filters:
     * - tpw_postcode_lookup_provider — change provider (`postcodesio`, `getaddress`, `google`)
     * - tpw_postcode_lookup_api_key — supply API keys for providers
     *
     * @since 1.0.0
     * @param string $postcode Raw postcode input
     * @param string $country  Country code (e.g., GB)
     * @param string $mode     'basic' or 'full' (full supported for google/getaddress)
     * @param string $street_prefix Optional street number prefix filter in full mode
     * @return array|false Success payload or false on fail
     */
    public static function lookup_postcode( $postcode, $country = 'GB', $mode = 'basic', $street_prefix = '' ) {
        $postcode = is_string( $postcode ) ? strtoupper( trim( $postcode ) ) : '';
        if ( $postcode === '' ) return false;
        // Normalise: remove spaces
        $pc_sane = preg_replace( '/\s+/', '', $postcode );

        $country = strtoupper( (string) $country );
        $settings = get_option( 'tpw_postcode_settings', [] );
        $provider = isset($settings['provider']) ? $settings['provider'] : 'postcodesio';
        $provider = apply_filters( 'tpw_postcode_lookup_provider', $provider );

        $mode = is_string($mode) ? strtolower(trim($mode)) : 'basic';
        $street_prefix = is_string($street_prefix) ? trim($street_prefix) : '';

        if ( ! in_array( $provider, [ 'postcodesio', 'getaddress', 'google' ], true ) ) {
            return [ 'error' => 'no_provider', 'message' => 'No valid postcode provider selected.' ];
        }

        // Full address list mode handling (Google & GetAddress)
        if ( $mode === 'full' ) {
            if ( $provider === 'google' ) {
                $api_key = isset($settings['google_api_key']) ? $settings['google_api_key'] : '';
                $api_key = apply_filters( 'tpw_postcode_lookup_api_key', $api_key, 'google' );
                if ( ! $api_key ) {
                    return [ 'error' => 'missing_api_key', 'message' => 'Google Maps API key missing.' ];
                }

                $url = add_query_arg( [
                    'address' => $postcode, // use spaced postcode for better matching
                    'key'     => $api_key,
                ], 'https://maps.googleapis.com/maps/api/geocode/json' );
            } elseif ( $provider === 'getaddress' ) {
                $api_key = isset($settings['getaddress_api_key']) ? $settings['getaddress_api_key'] : '';
                $api_key = apply_filters( 'tpw_postcode_lookup_api_key', $api_key, 'getaddress' );
                if ( ! $api_key ) {
                    return [ 'error' => 'missing_api_key', 'message' => 'GetAddress.io API key missing.' ];
                }
                // We'll handle GetAddress below using their endpoints; initialize placeholder URL for flow symmetry
                $url = '';
            } else {
                return [ 'error' => 'full_not_supported', 'message' => 'This provider does not support full address lists.' ];
            }

            // Provider-specific full-mode logic
            if ( $provider === 'getaddress' ) {
                // Prefer find/{postcode} first for a full list
                $find_url = 'https://api.getaddress.io/find/' . rawurlencode( $pc_sane ) . '?api-key=' . rawurlencode( $api_key );
                $resp = wp_remote_get( $find_url, [ 'timeout' => 12, 'headers' => [ 'Accept' => 'application/json' ] ] );
                $addresses = [];
                if ( ! is_wp_error($resp) && (int) wp_remote_retrieve_response_code($resp) === 200 ) {
                    $body = wp_remote_retrieve_body( $resp );
                    $data = json_decode( $body, true );
                    if ( is_array($data) && ! empty($data['addresses']) && is_array($data['addresses']) ) {
                        $pc_norm = isset($data['postcode']) ? (string) $data['postcode'] : $postcode;
                        $town    = isset($data['town_or_city']) ? (string) $data['town_or_city'] : '';
                        $county  = isset($data['county']) ? (string) $data['county'] : '';
                        foreach ( $data['addresses'] as $line ) {
                            $parts = array_map('trim', explode(',', (string) $line));
                            if ( empty($parts) ) continue;
                            $address1 = $parts[0];
                            $t = $town; $c = $county;
                            if ( ! $t || ! $c ) {
                                // Heuristic: last two segments are town and county if not provided
                                $count = count($parts);
                                if ( ! $t && $count >= 3 ) { $t = $parts[$count-2]; }
                                if ( ! $c && $count >= 2 ) { $c = $parts[$count-1]; }
                            }
                            $addresses[] = [
                                'label'    => $address1,
                                'address1' => $address1,
                                'town'     => $t,
                                'county'   => $c,
                                'postcode' => $pc_norm,
                                'country'  => ($country ?: 'GB'),
                            ];
                        }
                    }
                }
                // If find failed or empty, fallback to autocomplete/{query}
                if ( empty($addresses) ) {
                    $ac_url = 'https://api.getaddress.io/autocomplete/' . rawurlencode( $postcode ) . '?api-key=' . rawurlencode( $api_key );
                    $ac_resp = wp_remote_get( $ac_url, [ 'timeout' => 12, 'headers' => [ 'Accept' => 'application/json' ] ] );
                    if ( ! is_wp_error($ac_resp) && (int) wp_remote_retrieve_response_code($ac_resp) === 200 ) {
                        $ac_body = wp_remote_retrieve_body( $ac_resp );
                        $ac_data = json_decode( $ac_body, true );
                        if ( is_array($ac_data) && ! empty($ac_data['suggestions']) && is_array($ac_data['suggestions']) ) {
                            foreach ( $ac_data['suggestions'] as $s ) {
                                $addr = isset($s['address']) ? (string) $s['address'] : '';
                                if ( $addr === '' ) continue;
                                $parts = array_map('trim', explode(',', $addr));
                                $address1 = $parts[0] ?? '';
                                $t = ''; $c = '';
                                $cnt = count($parts);
                                // Detect and strip a trailing UK postcode from the suggestion tail
                                $pc_out = $postcode;
                                if ( $cnt > 0 ) {
                                    $last = $parts[$cnt-1];
                                    if ( self::looks_like_uk_postcode( $last ) ) {
                                        $pc_out = self::normalize_uk_postcode( $last );
                                        $cnt--; // virtually drop postcode from tail
                                    }
                                }
                                // After removing the postcode (if present), infer county and town from the tail
                                if ( $cnt >= 3 ) {
                                    $c = $parts[$cnt-1];       // county
                                    $t = $parts[$cnt-2];       // town/city just before county
                                } elseif ( $cnt === 2 ) {
                                    // Sometimes suggestions are like: address1, town
                                    $t = $parts[1];
                                }
                                $addresses[] = [
                                    'label'    => $address1 ?: $addr,
                                    'address1' => $address1 ?: $addr,
                                    'town'     => $t,
                                    'county'   => $c,
                                    'postcode' => $pc_out,
                                    'country'  => ($country ?: 'GB'),
                                ];
                            }
                        }
                    }
                }

                if ( empty($addresses) ) {
                    // Last resort: return basic success similar to Google empty behavior
                    if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][getaddress-full] No addresses; returning basic-only for ' . $postcode); }
                    $basic = self::lookup_postcode( $postcode, $country );
                    if ( is_array($basic) && empty($basic['error']) ) {
                        $basic['provider'] = 'getaddress';
                        $basic['supports_full'] = true;
                        $basic['addresses'] = [];
                        return $basic;
                    }
                    return [ 'error' => 'no_addresses', 'message' => 'No addresses returned for this postcode.' ];
                }

                return [
                    'postcode'      => $postcode,
                    'provider'      => 'getaddress',
                    'supports_full' => true,
                    'addresses'     => $addresses,
                ];
            }

            // Google full-mode flow (existing)
            $resp = wp_remote_get( $url, [ 'timeout' => 10, 'headers' => [ 'Accept' => 'application/json' ] ] );
            if ( is_wp_error( $resp ) ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][google-full] HTTP error: ' . $resp->get_error_message()); }
                return [ 'error' => 'http_error', 'message' => $resp->get_error_message() ];
            }
            $code = (int) wp_remote_retrieve_response_code( $resp );
            if ( $code !== 200 ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][google-full] HTTP status ' . $code); }
                return [ 'error' => 'http_status', 'message' => 'HTTP ' . $code . ' from Google' ];
            }
            $body = wp_remote_retrieve_body( $resp );
            $data = json_decode( $body, true );
            if ( ! is_array($data) ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][google-full] Invalid JSON'); }
                return [ 'error' => 'invalid_json', 'message' => 'Invalid response from Google' ];
            }
            if ( ! isset($data['status']) || $data['status'] !== 'OK' ) {
                $em = isset($data['error_message']) ? $data['error_message'] : ($data['status'] ?? 'UNKNOWN_ERROR');
                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][google-full] Status: ' . ($data['status'] ?? 'N/A') . ' Message: ' . $em); }
                return [ 'error' => 'google_status', 'message' => $em ];
            }

            $addresses = [];
            $first_components = [];
            $first_lat = 0.0; $first_lng = 0.0;
            foreach ( (array) ($data['results'] ?? []) as $idx => $res ) {
                $comps = isset($res['address_components']) ? $res['address_components'] : [];
                if ( empty($comps) ) continue;
                if ($idx === 0) {
                    $first_components = $comps;
                    $first_lat = isset($res['geometry']['location']['lat']) ? (float) $res['geometry']['location']['lat'] : 0.0;
                    $first_lng = isset($res['geometry']['location']['lng']) ? (float) $res['geometry']['location']['lng'] : 0.0;
                }
                $street_number = $route = $postal_town = $county = $region = $postal_code = $country_name = '';
                foreach ( $comps as $c ) {
                    $types = isset($c['types']) ? $c['types'] : [];
                    if ( in_array('street_number', $types, true) ) { $street_number = $c['long_name']; }
                    if ( in_array('route', $types, true) ) { $route = $c['long_name']; }
                    if ( in_array('postal_town', $types, true) || in_array('locality', $types, true) ) { $postal_town = $c['long_name']; }
                    if ( in_array('administrative_area_level_2', $types, true) ) { $county = $c['long_name']; }
                    if ( in_array('administrative_area_level_1', $types, true) ) { $region = $c['long_name']; }
                    if ( in_array('postal_code', $types, true) ) { $postal_code = $c['long_name']; }
                    if ( in_array('country', $types, true) ) { $country_name = $c['long_name']; }
                }
                if ( $street_number && $route ) {
                    // Optional prefix filter: only include those starting with given digits
                    if ( $street_prefix !== '' ) {
                        if ( strpos($street_number, (string) $street_prefix) !== 0 ) {
                            continue;
                        }
                    }
                    $label = trim($street_number . ' ' . $route);
                    $addresses[] = [
                        'label'    => $label,
                        'address1' => $label,
                        'town'     => $postal_town,
                        'county'   => $county ?: $region,
                        'postcode' => $postal_code ?: $postcode,
                        'country'  => $country_name,
                    ];
                }
            }

            // If we didn't get discrete street addresses, still return a success payload with basics
            if ( empty($addresses) ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][google-full] No street addresses for ' . $postcode . ' — returning basic details'); }
                $postal_town = $county = $region = $postal_code = $country_name = '';
                foreach ( (array) $first_components as $c ) {
                    $types = isset($c['types']) ? $c['types'] : [];
                    if ( in_array('postal_town', $types, true) || in_array('locality', $types, true) ) { $postal_town = $c['long_name']; }
                    if ( in_array('administrative_area_level_2', $types, true) ) { $county = $c['long_name']; }
                    if ( in_array('administrative_area_level_1', $types, true) ) { $region = $c['long_name']; }
                    if ( in_array('postal_code', $types, true) ) { $postal_code = $c['long_name']; }
                    if ( in_array('country', $types, true) ) { $country_name = $c['long_name']; }
                }
                return [
                    'postcode'      => $postal_code ?: $postcode,
                    'town'          => $postal_town,
                    'county'        => $county ?: $region,
                    'region'        => $region,
                    'latitude'      => $first_lat,
                    'longitude'     => $first_lng,
                    'country'       => $country_name,
                    'provider'      => 'google',
                    'supports_full' => true,
                    'addresses'     => [],
                ];
            }

            return [
                'postcode'  => $postcode,
                'provider'  => 'google',
                'supports_full' => true,
                'addresses' => $addresses,
            ];
        }

        // Switch providers (basic mode)
        if ( $provider === 'postcodesio' ) {
            if ( $country !== 'GB' ) {
                return [ 'error' => 'unsupported_country', 'message' => 'Postcodes.io supports GB only.' ];
            }
            $url = 'https://api.postcodes.io/postcodes/' . rawurlencode( $pc_sane );
            $resp = wp_remote_get( $url, [ 'timeout' => 8, 'headers' => [ 'Accept' => 'application/json' ] ] );
            if ( is_wp_error( $resp ) ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][google] HTTP error: ' . $resp->get_error_message()); }
                return [ 'error' => 'http_error', 'message' => $resp->get_error_message() ];
            }
            $code = (int) wp_remote_retrieve_response_code( $resp );
            if ( $code !== 200 ) {
                return false;
            }
            $body = wp_remote_retrieve_body( $resp );
            $data = json_decode( $body, true );
            if ( ! is_array( $data ) || empty( $data['status'] ) || empty( $data['result'] ) ) {
                return false;
            }
            $r = $data['result'];
            $town      = ! empty( $r['post_town'] ) ? (string) $r['post_town'] : ( ( $r['admin_district'] ?? '' ) ?: ( $r['parish'] ?? '' ) );
            $county    = ! empty( $r['admin_county'] ) ? (string) $r['admin_county'] : ( $r['admin_district'] ?? '' );
            $district  = isset( $r['admin_district'] ) && $r['admin_district'] ? $r['admin_district'] : ( $r['parish'] ?? '' );
            $region    = isset( $r['region'] ) && $r['region'] ? $r['region'] : '';
            $lat       = isset( $r['latitude'] ) ? (float) $r['latitude'] : 0.0;
            $lng       = isset( $r['longitude'] ) ? (float) $r['longitude'] : 0.0;
            $pc_norm   = isset( $r['postcode'] ) ? (string) $r['postcode'] : $postcode;

            return [
                'postcode'  => $pc_norm,
                'town'      => $town,
                'county'    => $county,
                'district'  => $district,
                'region'    => $region,
                'latitude'  => $lat,
                'longitude' => $lng,
            ];
        }

        if ( $provider === 'getaddress' ) {
            $api_key = isset($settings['getaddress_api_key']) ? $settings['getaddress_api_key'] : '';
            $api_key = apply_filters( 'tpw_postcode_lookup_api_key', $api_key, 'getaddress' );
            if ( ! $api_key ) {
                return [ 'error' => 'missing_api_key', 'message' => 'GetAddress.io API key missing.' ];
            }
            if ( $country !== 'GB' ) {
                return [ 'error' => 'unsupported_country', 'message' => 'GetAddress.io postcode lookup is GB focused.' ];
            }
            $url = 'https://api.getaddress.io/find/' . rawurlencode( $pc_sane ) . '?api-key=' . rawurlencode( $api_key );
            $resp = wp_remote_get( $url, [ 'timeout' => 10, 'headers' => [ 'Accept' => 'application/json' ] ] );
            if ( is_wp_error( $resp ) ) {
                return [ 'error' => 'http_error', 'message' => $resp->get_error_message() ];
            }
            $code = (int) wp_remote_retrieve_response_code( $resp );
            if ( $code !== 200 ) {
                $body = wp_remote_retrieve_body( $resp );
                $msg = '';
                if ( $body ) {
                    $jd = json_decode( $body, true );
                    if ( is_array($jd) ) {
                        $msg = $jd['Message'] ?? $jd['message'] ?? '';
                    } else {
                        $msg = trim( wp_strip_all_tags( $body ) );
                    }
                }
                $msg = $msg ?: 'HTTP ' . $code . ' from GetAddress.io';
                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][getaddress] ' . $msg . ' URL=' . $url); }
                // Fallback: if 404 Not Found, try Google basic lookup if key present to provide at least town/county
                if ( (int) $code === 404 ) {
                    $g_key = isset($settings['google_api_key']) ? $settings['google_api_key'] : '';
                    $g_key = apply_filters( 'tpw_postcode_lookup_api_key', $g_key, 'google' );
                    if ( $g_key ) {
                        $g_url = add_query_arg( [ 'address' => $postcode, 'key' => $g_key ], 'https://maps.googleapis.com/maps/api/geocode/json' );
                        $g_resp = wp_remote_get( $g_url, [ 'timeout' => 10, 'headers' => [ 'Accept' => 'application/json' ] ] );
                        if ( ! is_wp_error($g_resp) && (int) wp_remote_retrieve_response_code($g_resp) === 200 ) {
                            $g_body = wp_remote_retrieve_body( $g_resp );
                            $g_data = json_decode( $g_body, true );
                            if ( is_array($g_data) && isset($g_data['status']) && $g_data['status'] === 'OK' && ! empty($g_data['results'][0]) ) {
                                $resG = $g_data['results'][0];
                                $comps = isset($resG['address_components']) ? $resG['address_components'] : [];
                                $lat = isset($resG['geometry']['location']['lat']) ? (float) $resG['geometry']['location']['lat'] : 0.0;
                                $lng = isset($resG['geometry']['location']['lng']) ? (float) $resG['geometry']['location']['lng'] : 0.0;
                                $town = $county = $region = '';
                                foreach ( (array) $comps as $c ) {
                                    $types = isset($c['types']) ? $c['types'] : [];
                                    if ( in_array('postal_town', $types, true) || in_array('locality', $types, true) ) { $town = $c['long_name']; }
                                    if ( in_array('administrative_area_level_2', $types, true) ) { $county = $c['long_name']; }
                                    if ( in_array('administrative_area_level_1', $types, true) ) { $region = $c['long_name']; }
                                }
                                $pc_norm = $postcode;
                                foreach ( (array) $comps as $c ) {
                                    if ( isset($c['types']) && in_array('postal_code', $c['types'], true) ) { $pc_norm = $c['long_name']; break; }
                                }
                                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][getaddress->google-fallback] Returning basic details for ' . $postcode); }
                                return [
                                    'postcode'  => $pc_norm,
                                    'town'      => $town,
                                    'county'    => $county ?: $region,
                                    'district'  => '',
                                    'region'    => $region,
                                    'latitude'  => $lat,
                                    'longitude' => $lng,
                                    'fallback'  => 'google',
                                ];
                            }
                        }
                    }
                    // Secondary fallback for GB: postcodes.io (no key required)
                    if ( $country === 'GB' ) {
                        $pc_url = 'https://api.postcodes.io/postcodes/' . rawurlencode( $pc_sane );
                        $pc_resp = wp_remote_get( $pc_url, [ 'timeout' => 8, 'headers' => [ 'Accept' => 'application/json' ] ] );
                        if ( ! is_wp_error($pc_resp) && (int) wp_remote_retrieve_response_code($pc_resp) === 200 ) {
                            $pc_body = wp_remote_retrieve_body( $pc_resp );
                            $pc_data = json_decode( $pc_body, true );
                            if ( is_array($pc_data) && ! empty($pc_data['result']) ) {
                                $r = $pc_data['result'];
                                $town      = ! empty( $r['post_town'] ) ? (string) $r['post_town'] : ( ( $r['admin_district'] ?? '' ) ?: ( $r['parish'] ?? '' ) );
                                $county    = ! empty( $r['admin_county'] ) ? (string) $r['admin_county'] : ( $r['admin_district'] ?? '' );
                                $lat       = isset( $r['latitude'] ) ? (float) $r['latitude'] : 0.0;
                                $lng       = isset( $r['longitude'] ) ? (float) $r['longitude'] : 0.0;
                                $pc_norm   = isset( $r['postcode'] ) ? (string) $r['postcode'] : $postcode;
                                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][getaddress->postcodesio-fallback] Returning basic details for ' . $postcode); }
                                return [
                                    'postcode'  => $pc_norm,
                                    'town'      => $town,
                                    'county'    => $county,
                                    'district'  => isset($r['admin_district']) ? (string) $r['admin_district'] : '',
                                    'region'    => isset($r['region']) ? (string) $r['region'] : '',
                                    'latitude'  => $lat,
                                    'longitude' => $lng,
                                    'fallback'  => 'postcodesio',
                                ];
                            }
                        }
                    }
                }
                // Return explicit error so UI can show message
                return [ 'error' => 'getaddress_http', 'message' => $msg, 'status' => $code ];
            }
            $body = wp_remote_retrieve_body( $resp );
            $data = json_decode( $body, true );
            if ( ! is_array( $data ) || empty( $data['addresses'] ) ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][getaddress] No addresses in response for ' . $postcode); }
                return [ 'error' => 'no_addresses', 'message' => 'No addresses returned for this postcode.' ];
            }
            // getaddress returns many addresses; derive town/county from top-level fields if provided
            $town   = isset($data['town_or_city']) ? (string) $data['town_or_city'] : '';
            $county = isset($data['county']) ? (string) $data['county'] : '';
            // Fallback: parse first address line array if town/county are missing (best-effort)
            if ( (! $town || ! $county) && is_array($data['addresses']) && ! empty($data['addresses'][0]) ) {
                $first = explode(',', (string) $data['addresses'][0]);
                $first = array_map('trim', $first);
                // Heuristic: last elements are usually postcode (omitted), county, town
                if ( ! $town ) { $town = isset($first[count($first)-2]) ? $first[count($first)-2] : ''; }
                if ( ! $county ) { $county = isset($first[count($first)-1]) ? $first[count($first)-1] : ''; }
            }
            return [
                'postcode' => isset($data['postcode']) ? (string) $data['postcode'] : $postcode,
                'town'     => $town,
                'county'   => $county,
                'district' => '',
                'region'   => '',
                'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : 0.0,
                'longitude'=> isset($data['longitude']) ? (float) $data['longitude'] : 0.0,
            ];
        }

        if ( $provider === 'google' ) {
            $api_key = isset($settings['google_api_key']) ? $settings['google_api_key'] : '';
            $api_key = apply_filters( 'tpw_postcode_lookup_api_key', $api_key, 'google' );
            if ( ! $api_key ) {
                return [ 'error' => 'missing_api_key', 'message' => 'Google Maps API key missing.' ];
            }
            // Build Geocoding API URL. Country bias if provided.
            // Prefer components filter for postal_code + country, plus address for redundancy.
            $addr = $postcode; // keep spaced postcode for better matching
            $region_bias = ( $country === 'GB' ) ? 'uk' : strtolower($country);
            $components = 'postal_code:' . $pc_sane . '|country:' . $country;
            $url = add_query_arg( [
                'address'    => $addr,
                'components' => $components,
                'key'        => $api_key,
                'region'     => $region_bias,
            ], 'https://maps.googleapis.com/maps/api/geocode/json' );
            $resp = wp_remote_get( $url, [ 'timeout' => 10, 'headers' => [ 'Accept' => 'application/json' ] ] );
            if ( is_wp_error( $resp ) ) {
                return [ 'error' => 'http_error', 'message' => $resp->get_error_message() ];
            }
            $code = (int) wp_remote_retrieve_response_code( $resp );
            if ( $code !== 200 ) { if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][google] HTTP status ' . $code); } return [ 'error' => 'http_status', 'message' => 'HTTP ' . $code . ' from Google' ]; }
            $body = wp_remote_retrieve_body( $resp );
            $data = json_decode( $body, true );
            if ( ! is_array($data) ) { if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][google] Invalid JSON'); } return [ 'error' => 'invalid_json', 'message' => 'Invalid response from Google' ]; }
            if ( isset($data['status']) && $data['status'] !== 'OK' ) {
                // Fallback: retry without components filter if ZERO_RESULTS
                if ( $data['status'] === 'ZERO_RESULTS' ) {
                    $fallback_url = add_query_arg( [
                        'address' => $postcode . ' ' . $country,
                        'key'     => $api_key,
                        'region'  => $region_bias,
                    ], 'https://maps.googleapis.com/maps/api/geocode/json' );
                    $resp2 = wp_remote_get( $fallback_url, [ 'timeout' => 10, 'headers' => [ 'Accept' => 'application/json' ] ] );
                    if ( ! is_wp_error($resp2) && (int) wp_remote_retrieve_response_code($resp2) === 200 ) {
                        $body2 = wp_remote_retrieve_body( $resp2 );
                        $data2 = json_decode( $body2, true );
                        if ( is_array($data2) && isset($data2['status']) && $data2['status'] === 'OK' && ! empty($data2['results'][0]) ) {
                            $res = $data2['results'][0];
                            $comps = isset($res['address_components']) ? $res['address_components'] : [];
                            $lat = isset($res['geometry']['location']['lat']) ? (float) $res['geometry']['location']['lat'] : 0.0;
                            $lng = isset($res['geometry']['location']['lng']) ? (float) $res['geometry']['location']['lng'] : 0.0;
                            $town = $county = $district = $region = '';
                            foreach ( (array) $comps as $c ) {
                                $types = isset($c['types']) ? $c['types'] : [];
                                if ( in_array('postal_town', $types, true) || in_array('locality', $types, true) ) { $town = $c['long_name']; }
                                if ( in_array('administrative_area_level_2', $types, true) ) { $county = $c['long_name']; }
                                if ( in_array('administrative_area_level_1', $types, true) ) { $region = $c['long_name']; }
                                if ( in_array('sublocality', $types, true) ) { $district = $c['long_name']; }
                            }
                            $pc_norm = $postcode;
                            foreach ( (array) $comps as $c ) {
                                if ( isset($c['types']) && in_array('postal_code', $c['types'], true) ) { $pc_norm = $c['long_name']; break; }
                            }
                            return [
                                'postcode'  => $pc_norm,
                                'town'      => $town,
                                'county'    => $county,
                                'district'  => $district,
                                'region'    => $region,
                                'latitude'  => $lat,
                                'longitude' => $lng,
                            ];
                        }
                    }
                }
                $em = isset($data['error_message']) ? $data['error_message'] : $data['status'];
                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][google] Status: ' . $data['status'] . ' Message: ' . $em); }
                return [ 'error' => 'google_status', 'message' => $em ];
            }
            if ( empty($data['results'][0]) ) {
                // Try fallback call without components
                $fallback_url = add_query_arg( [
                    'address' => $postcode . ' ' . $country,
                    'key'     => $api_key,
                    'region'  => $region_bias,
                ], 'https://maps.googleapis.com/maps/api/geocode/json' );
                $resp2 = wp_remote_get( $fallback_url, [ 'timeout' => 10, 'headers' => [ 'Accept' => 'application/json' ] ] );
                if ( ! is_wp_error($resp2) && (int) wp_remote_retrieve_response_code($resp2) === 200 ) {
                    $body2 = wp_remote_retrieve_body( $resp2 );
                    $data2 = json_decode( $body2, true );
                    if ( is_array($data2) && isset($data2['status']) && $data2['status'] === 'OK' && ! empty($data2['results'][0]) ) {
                        $res = $data2['results'][0];
                        $comps = isset($res['address_components']) ? $res['address_components'] : [];
                        $lat = isset($res['geometry']['location']['lat']) ? (float) $res['geometry']['location']['lat'] : 0.0;
                        $lng = isset($res['geometry']['location']['lng']) ? (float) $res['geometry']['location']['lng'] : 0.0;
                        $town = $county = $district = $region = '';
                        foreach ( (array) $comps as $c ) {
                            $types = isset($c['types']) ? $c['types'] : [];
                            if ( in_array('postal_town', $types, true) || in_array('locality', $types, true) ) { $town = $c['long_name']; }
                            if ( in_array('administrative_area_level_2', $types, true) ) { $county = $c['long_name']; }
                            if ( in_array('administrative_area_level_1', $types, true) ) { $region = $c['long_name']; }
                            if ( in_array('sublocality', $types, true) ) { $district = $c['long_name']; }
                        }
                        $pc_norm = $postcode;
                        foreach ( (array) $comps as $c ) {
                            if ( isset($c['types']) && in_array('postal_code', $c['types'], true) ) { $pc_norm = $c['long_name']; break; }
                        }
                        return [
                            'postcode'  => $pc_norm,
                            'town'      => $town,
                            'county'    => $county,
                            'district'  => $district,
                            'region'    => $region,
                            'latitude'  => $lat,
                            'longitude' => $lng,
                        ];
                    }
                }
                if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log('[TPW Postcodes][google] Zero results for ' . $postcode); }
                return [ 'error' => 'zero_results', 'message' => 'No results for this postcode.' ];
            }
            $res = $data['results'][0];
            $comps = isset($res['address_components']) ? $res['address_components'] : [];
            $lat = isset($res['geometry']['location']['lat']) ? (float) $res['geometry']['location']['lat'] : 0.0;
            $lng = isset($res['geometry']['location']['lng']) ? (float) $res['geometry']['location']['lng'] : 0.0;
            $town = $county = $district = $region = '';
            foreach ( (array) $comps as $c ) {
                $types = isset($c['types']) ? $c['types'] : [];
                if ( in_array('postal_town', $types, true) || in_array('locality', $types, true) ) { $town = $c['long_name']; }
                if ( in_array('administrative_area_level_2', $types, true) ) { $county = $c['long_name']; }
                if ( in_array('administrative_area_level_1', $types, true) ) { $region = $c['long_name']; }
                if ( in_array('sublocality', $types, true) ) { $district = $c['long_name']; }
            }
            // Postcode may be in types 'postal_code'
            $pc_norm = $postcode;
            foreach ( (array) $comps as $c ) {
                if ( isset($c['types']) && in_array('postal_code', $c['types'], true) ) { $pc_norm = $c['long_name']; break; }
            }
            return [
                'postcode'  => $pc_norm,
                'town'      => $town,
                'county'    => $county,
                'district'  => $district,
                'region'    => $region,
                'latitude'  => $lat,
                'longitude' => $lng,
            ];
        }

        // Should not reach here
        return false;
    }
}
