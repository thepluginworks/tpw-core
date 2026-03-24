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
            'dob'                   => 'Date of Birth',
            'date_joined'           => 'Date Joined',
            'status'                => 'Status',
            'is_committee'          => 'Is Committee',
            'is_match_manager'      => 'Is Match Manager',
            'is_admin'              => 'Is Admin',
            'is_noticeboard_admin'  => 'Is Noticeboard Admin',
            'is_gallery_admin'      => 'Gallery Admin',
            'is_manage_members'     => 'Members Manager',
            'is_volunteer'          => 'Volunteer',
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
            'dob'                 => 'date',
            'date_joined'          => 'date',
            'status'               => 'select',
            'is_committee'         => 'checkbox',
            'is_match_manager'     => 'checkbox',
            'is_admin'             => 'checkbox',
            'is_noticeboard_admin' => 'checkbox',
            'is_gallery_admin'     => 'checkbox',
            'is_manage_members'    => 'checkbox',
            'is_volunteer'         => 'checkbox',
            // FlexiGolf additions
            'whi'                  => 'text',
            'whi_updated'          => 'date',
            'cdh_id'               => 'text',
        ];

        // Load optional section mapping stored in options (no schema change)
        $sections_map = get_option('tpw_member_field_sections', []);
        if (!is_array($sections_map)) { $sections_map = []; }

        foreach ( $results as $row ) {
            $is_core = array_key_exists( $row->field_key, $core_fields );
            $type = $row->field_type;
            if ( $is_core && isset( $core_default_types[ $row->field_key ] ) && $type === 'tinyint(1)' ) {
                $type = $core_default_types[ $row->field_key ];
            }
            // If type is missing or generic, fall back to sensible defaults for core fields
            if ( ! $type || $type === 'text' ) {
                if ( $is_core && isset( $core_default_types[ $row->field_key ] ) ) {
                    $type = $core_default_types[ $row->field_key ];
                } else {
                    $type = 'text';
                }
            }

            // Prefer custom label when provided; otherwise fall back to core label if this is a core field
            $label = !empty($row->custom_label)
                ? $row->custom_label
                : ( $is_core ? $core_fields[ $row->field_key ] : ucwords( str_replace( '_', ' ', $row->field_key ) ) );

            $options = [];
            if ( $type === 'select' && ! empty( $row->field_options ) && is_string( $row->field_options ) ) {
                $lines = preg_split( '/\r\n|\r|\n/', $row->field_options );
                $lines = is_array( $lines ) ? $lines : [];
                foreach ( $lines as $line ) {
                    $opt = trim( (string) $line );
                    if ( $opt === '' ) {
                        continue;
                    }
                    $options[] = $opt;
                }
                $options = array_values( array_unique( $options ) );
            }

            $enabled_fields[] = [
                'key'        => $row->field_key,
                'label'      => $label,
                'type'       => $type,
                'is_core'    => $is_core,
                'sort_order' => (int) $row->sort_order,
                'section'    => isset($sections_map[$row->field_key]) && $sections_map[$row->field_key] !== '' ? (string) $sections_map[$row->field_key] : 'General',
                'options'    => $options,
            ];
        }

        return $enabled_fields;
    }

    public static function get_condition_eligible_custom_fields() {
        $conditional_fields = get_option( 'tpw_conditional_fields', [] );
        $conditional_fields = is_array( $conditional_fields ) ? array_values( array_unique( array_filter( array_map( 'sanitize_key', $conditional_fields ) ) ) ) : [];
        $allowed_lookup     = array_fill_keys( $conditional_fields, true );
        $eligible_fields    = [];

        foreach ( self::get_all_enabled_fields() as $field ) {
            if ( ! empty( $field['is_core'] ) ) {
                continue;
            }

            if ( 'checkbox' !== (string) $field['type'] ) {
                continue;
            }

            if ( ! isset( $allowed_lookup[ $field['key'] ] ) ) {
                continue;
            }

            $eligible_fields[] = [
                'key'   => (string) $field['key'],
                'label' => (string) $field['label'],
                'type'  => (string) $field['type'],
            ];
        }

        return $eligible_fields;
    }

    /**
     * Get column names for a table.
     */
    private static function get_table_columns( $table_name ) {
        global $wpdb;
		$table_name = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table_name );
		if ( '' === $table_name ) {
			return [];
		}

		$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_name}`" );
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