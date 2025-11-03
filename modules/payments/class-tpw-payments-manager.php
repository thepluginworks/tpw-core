<?php

class TPW_Payments_Manager {

    /**
     * Return the list of active payment methods.
     * Prefers the tpw_payment_methods table (supports slug+active and legacy method_key+enabled)
     * and falls back to legacy options for older sites.
     *
     * @return array<int,object{slug:string,name:string}>
     */
    public static function get_active_methods() {
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

            $col_slug = $has_slug ? 'slug' : ( $has_key ? 'method_key' : '' );
            $col_flag = $has_active ? 'active' : ( $has_enabled ? 'enabled' : '' );
            $col_name = $has_name ? 'name' : '';

            if ( $col_slug && $col_flag ) {
                $select_name = $col_name ? ", {$col_name} AS name" : '';
                $rows = (array) $wpdb->get_results(
                    "SELECT {$col_slug} AS slug{$select_name}, {$col_flag} AS enabled FROM {$table} WHERE {$col_flag} IN (1,'1','yes','on','true','enabled')"
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

        return $out;
    }

    /**
     * Convenience helper to check if any methods are active.
     */
    public static function has_active_methods() : bool {
        $methods = self::get_active_methods();
        return is_array( $methods ) && ! empty( $methods );
    }
}
