<?php

class TPW_Payments_Manager {

    /**
     * Keep persisted Square availability coherent with runtime add-on state.
     *
     * When the add-on is inactive, Square should remain configured but not be
     * persisted as an active front-end method. The prior requested state is
     * preserved and restored when the add-on becomes active again.
     *
     * @return void
     */
    public static function reconcile_square_runtime_state() : void {
        static $did_run = false;

        if ( $did_run ) {
            return;
        }

        $did_run = true;

        global $wpdb;

        if ( ! $wpdb || ! isset( $wpdb->prefix ) ) {
            return;
        }

        $table = $wpdb->prefix . 'tpw_payment_methods';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $table_exists !== $table ) {
            return;
        }

        $has_active = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'active'" );
        if ( ! $has_active ) {
            return;
        }

        $raw_active = $wpdb->get_var( $wpdb->prepare( "SELECT active FROM {$table} WHERE slug = %s LIMIT 1", 'square' ) );
        if ( null === $raw_active ) {
            return;
        }

        $addon_active = function_exists( 'tpw_core_is_square_gateway_addon_active' )
            && tpw_core_is_square_gateway_addon_active();
        $current_active = self::value_is_enabled( $raw_active ) ? '1' : '0';
        $remembered_preference = get_option( 'tpw_square_requested_active', null );
        $has_remembered_preference = false !== $remembered_preference && null !== $remembered_preference && '' !== $remembered_preference;

        if ( ! $addon_active ) {
            if ( ! $has_remembered_preference ) {
                update_option( 'tpw_square_requested_active', $current_active, false );
            }

            if ( '1' === $current_active ) {
                $wpdb->update( $table, [ 'active' => 0 ], [ 'slug' => 'square' ] );
            }

            if ( self::value_is_enabled( get_option( 'tpw_square_enabled', '0' ) ) ) {
                update_option( 'tpw_square_enabled', '0', false );
            }

            return;
        }

        if ( ! $has_remembered_preference ) {
            return;
        }

        $restore_active = self::value_is_enabled( $remembered_preference ) ? 1 : 0;
        if ( (int) $raw_active !== $restore_active ) {
            $wpdb->update( $table, [ 'active' => $restore_active ], [ 'slug' => 'square' ] );
        }

        update_option( 'tpw_square_enabled', (string) $restore_active, false );
        delete_option( 'tpw_square_requested_active' );
    }

    /**
     * Check whether the stored Square method is configured in Core settings.
     *
     * @return bool
     */
    public static function square_has_stored_configuration() : bool {
        foreach ( [ 'tpw_square_app_id', 'tpw_square_access_token', 'tpw_square_location_id' ] as $option_name ) {
            $value = get_option( $option_name, '' );
            if ( is_string( $value ) && trim( $value ) !== '' ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether a stored flag value represents an enabled state.
     *
     * @param mixed $value Stored value.
     * @return bool
     */
    private static function value_is_enabled( $value ) : bool {
        return in_array( strtolower( (string) $value ), [ '1', 'yes', 'on', 'true', 'enabled' ], true );
    }

    /**
     * Check whether a payment method is currently available for front-end use.
     *
     * Stored method rows/options remain intact for compatibility. This gate only
     * controls runtime availability.
     *
     * @param string $slug Payment method slug.
     * @return bool
     */
    public static function is_method_available( $slug ) : bool {
        $slug = sanitize_key( (string) $slug );
        if ( '' === $slug ) {
            return false;
        }

        if ( 'square' === $slug ) {
            return function_exists( 'tpw_core_is_square_gateway_addon_active' )
                ? tpw_core_is_square_gateway_addon_active()
                : false;
        }

        return true;
    }

    /**
     * Filter a method list down to runtime-available methods.
     *
     * @param array $methods List of method objects.
     * @return array<int,object{slug:string,name:string}>
     */
    private static function filter_available_methods( array $methods ) : array {
        $available = [];

        foreach ( $methods as $method ) {
            $slug = isset( $method->slug ) ? (string) $method->slug : '';
            if ( '' === $slug ) {
                continue;
            }

            if ( ! self::is_method_available( $slug ) ) {
                continue;
            }

            $available[] = $method;
        }

        return $available;
    }

    /**
     * Return the list of active payment methods.
     * Prefers the tpw_payment_methods table (supports slug+active and legacy method_key+enabled)
     * and falls back to legacy options for older sites.
     *
     * @return array<int,object{slug:string,name:string}>
     */
    public static function get_active_methods() {
        self::reconcile_square_runtime_state();

        global $wpdb;
        $out = [];

        // 1) DB-first detection
        $table = $wpdb->prefix . 'tpw_payment_methods';
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( $table_exists === $table ) {
            $has_slug       = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'slug'" );
            $has_key        = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'method_key'" );
            $has_active     = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'active'" );
            $has_enabled    = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'enabled'" );
            $has_name       = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'name'" );
            $has_sort       = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'sort_order'" );

            $col_slug = $has_slug ? 'slug' : ( $has_key ? 'method_key' : '' );
            $col_flag = $has_active ? 'active' : ( $has_enabled ? 'enabled' : '' );
            $col_name = $has_name ? 'name' : '';

            if ( $col_slug && $col_flag ) {
                $select_name = $col_name ? ", {$col_name} AS name" : '';
                $order_by = $has_sort ? 'ORDER BY sort_order ASC' : ( $col_name ? 'ORDER BY name ASC' : '' );
                if ( $order_by && $has_sort && $col_name ) { $order_by .= ', name ASC'; }
                $rows = (array) $wpdb->get_results(
                    "SELECT {$col_slug} AS slug{$select_name}, {$col_flag} AS enabled FROM {$table} WHERE {$col_flag} IN (1,'1','yes','on','true','enabled') {$order_by}"
                );
                foreach ( $rows as $r ) {
                    $slug = isset($r->slug) ? (string) $r->slug : '';
                    if ( $slug === '' ) { continue; }
                    $name = isset($r->name) && $r->name !== '' ? (string) $r->name : ucwords( str_replace( ['-', '_'], ' ', $slug ) );
                    $out[] = (object) [ 'slug' => $slug, 'name' => $name ];
                }
            }
        }

        // 2) Legacy fallback via options (only if DB returned nothing)
        if ( empty( $out ) ) {
            $map = [
                'bacs'             => [ 'opt' => 'tpw_bacs_enabled',   'name' => 'Bank Transfer (BACS)' ],
                'cheque'           => [ 'opt' => 'tpw_cheque_enabled', 'name' => 'Cheque' ],
                'sumup'            => [ 'opt' => 'tpw_sumup_enabled',  'name' => 'SumUp' ],
                'square'           => [ 'opt' => 'tpw_square_enabled', 'name' => 'Square' ],
                'cash'             => [ 'opt' => 'tpw_cash_enabled',   'name' => 'Cash' ],
                'card-on-the-day'  => [ 'opt' => 'tpw_card_on_the_day_enabled', 'name' => 'Card on the day' ],
            ];
            foreach ( $map as $slug => $meta ) {
                $val = get_option( $meta['opt'] );
                if ( in_array( strtolower( (string) $val ), [ '1', 'yes', 'on', 'true', 'enabled' ], true ) ) {
                    $out[] = (object) [ 'slug' => $slug, 'name' => $meta['name'] ];
                }
            }
        }

        return self::filter_available_methods( $out );
    }

    /**
     * Convenience helper to check if any methods are active.
     */
    public static function has_active_methods() : bool {
        $methods = self::get_active_methods();
        return is_array( $methods ) && ! empty( $methods );
    }
}
