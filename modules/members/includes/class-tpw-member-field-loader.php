<?php

/**
 * TPW_Member_Field_Loader
 *
 * This class is responsible for retrieving all enabled member fields 
 * (both core and custom) for use in front-end rendering — such as 
 * Add/Edit Member forms or profile views.
 *
 * It is intentionally decoupled from the admin field configuration logic 
 * in TPW_Member_Fields to preserve separation of concerns.
 */

class TPW_Member_Field_Loader {

    /**
     * Return a static list of core fields.
     */
    public static function get_core_fields() {
        global $wpdb;

        $core = [
            'first_name'            => 'First Name',
            'surname'               => 'Surname',
            'initials'              => 'Initials',
            'title'                 => 'Title',
            'decoration'            => 'Decoration',
            'email'                 => 'Email',
            'mobile'                => 'Mobile',
            'landline'              => 'Landline',
            'address1'              => 'Address Line 1',
            'address2'              => 'Address Line 2',
            'town'                  => 'Town',
            'county'                => 'County',
            'postcode'              => 'Postcode',
            'country'               => 'Country',
            'date_joined'           => 'Date Joined',
            'status'                => 'Status',
            'is_committee'          => 'Is Committee',
            'is_match_manager'      => 'Is Match Manager',
            'is_admin'              => 'Is Admin',
            'is_noticeboard_admin'  => 'Is Noticeboard Admin',
            'username'              => 'Username',
            'password_hash'         => 'Password Hash',
        ];

        // Only expose FlexiGolf fields if plugin is active AND columns exist
        if ( self::is_flexigolf_active() ) {
            $table = $wpdb->prefix . 'tpw_members';
            $cols = self::get_table_columns( $table );
            if ( in_array( 'whi', $cols, true ) ) {
                $core['whi'] = 'WHI';
            }
            if ( in_array( 'whi_updated', $cols, true ) ) {
                $core['whi_updated'] = 'WHI Updated';
            }
            if ( in_array( 'cdh_id', $cols, true ) ) {
                $core['cdh_id'] = 'CDH ID';
            }
        }

        return $core;
    }

    /**
     * Return an array of all enabled fields (core + custom), sorted.
     */
    public static function get_all_enabled_fields() {
        global $wpdb;

        $field_settings_table = $wpdb->prefix . 'tpw_field_settings';
        $results = $wpdb->get_results( "
            SELECT * FROM $field_settings_table
            WHERE is_enabled = 1
            ORDER BY sort_order ASC
        " );

        // Guard: If FlexiGolf is not active, strip its fields even if enabled in settings
        // Also, if active but DB columns are missing, strip those specific fields to avoid rendering/saving issues
        $fg_keys = ['whi','whi_updated','cdh_id'];
        if ( ! self::is_flexigolf_active() ) {
            $results = array_values( array_filter( (array) $results, function( $row ) use ( $fg_keys ) {
                return ! in_array( $row->field_key, $fg_keys, true );
            } ) );
        } else {
            $table = $wpdb->prefix . 'tpw_members';
            $cols  = self::get_table_columns( $table );
            $results = array_values( array_filter( (array) $results, function( $row ) use ( $fg_keys, $cols ) {
                if ( ! in_array( $row->field_key, $fg_keys, true ) ) return true;
                return in_array( $row->field_key, (array) $cols, true );
            } ) );
        }

        $core_fields = self::get_core_fields();
        $enabled_fields = [];

        // Default input types for known core fields (form rendering types)
        $core_default_types = [
            'date_joined'          => 'date',
            'status'               => 'select',
            'is_committee'         => 'checkbox',
            'is_match_manager'     => 'checkbox',
            'is_admin'             => 'checkbox',
            'is_noticeboard_admin' => 'checkbox',
            // FlexiGolf additions
            'whi'                  => 'text',
            'whi_updated'          => 'date',
            'cdh_id'               => 'text',
        ];

        foreach ( $results as $row ) {
            $is_core = array_key_exists( $row->field_key, $core_fields );
            $type = $row->field_type;
            // If type is missing or generic, fall back to sensible defaults for core fields
            if ( ! $type || $type === 'text' ) {
                if ( $is_core && isset( $core_default_types[ $row->field_key ] ) ) {
                    $type = $core_default_types[ $row->field_key ];
                } else {
                    $type = 'text';
                }
            }

            $enabled_fields[] = [
                'key'        => $row->field_key,
                'label'      => $row->custom_label,
                'type'       => $type,
                'is_core'    => $is_core,
                'sort_order' => (int) $row->sort_order,
            ];
        }

        // Ensure any core fields missing from settings are still exposed as enabled with sensible defaults
        $present_keys = array_map( function($r){ return $r->field_key; }, (array) $results );
        $order = count( $enabled_fields );
        foreach ( $core_fields as $key => $label ) {
            if ( in_array( $key, $present_keys, true ) ) continue;
            $is_core = true;
            $type = isset($core_default_types[$key]) ? $core_default_types[$key] : 'text';
            $enabled_fields[] = [
                'key'        => $key,
                'label'      => $label,
                'type'       => $type,
                'is_core'    => $is_core,
                'sort_order' => ++$order,
            ];
        }

        return $enabled_fields;
    }

    /**
     * Get column names for a table.
     */
    private static function get_table_columns( $table_name ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table_name ) );
        $cols = [];
        if ( $rows ) {
            foreach ( $rows as $r ) {
                if ( isset($r->Field) ) $cols[] = $r->Field;
            }
        }
        return $cols;
    }

    /**
     * Detect if FlexiGolf plugin is active. Allows override via filter.
     */
    public static function is_flexigolf_active() {
        // Let sites decide explicitly if needed
        $override = apply_filters('tpw_members/is_flexigolf_active', null);
        if ( $override !== null ) {
            return (bool) $override;
        }

        // Common markers
        if ( defined('FLEXIGOLF_VERSION') || class_exists('FlexiGolf') ) {
            return true;
        }
        if ( ! function_exists('is_plugin_active') ) {
            // is_plugin_active is in admin include; safe to include on frontend
            if ( file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
        }
        if ( function_exists('is_plugin_active') ) {
            $candidates = [
                // Generic/legacy slugs
                'flexigolf/flexigolf.php',
                'flexigolf/flexigolf-main.php',
                'flexigolf/flexigolf-plugin.php',
                // TPW FlexiGolf expected slugs
                'tpw-flexigolf/tpw-flexigolf.php',
                'tpw-flexigolf/tpw-flexigolf-main.php',
            ];
            foreach ( $candidates as $slug ) {
                if ( is_plugin_active( $slug ) ) return true;
            }
        }
        return false;
    }
}