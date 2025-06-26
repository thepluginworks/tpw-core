<?php

class TPW_Payments_Manager {

    public static function get_active_methods() {
        $methods = [];

        // BACS
        if (get_option('tpw_bacs_enabled') === '1') {
            $methods[] = (object)[
                'name' => 'Bank Transfer (BACS)',
                'slug' => 'bacs',
            ];
        }

        // Cheque
        if (get_option('tpw_cheque_enabled') === '1') {
            $methods[] = (object)[
                'name' => 'Cheque',
                'slug' => 'cheque',
            ];
        }

        // SumUp
        if (get_option('tpw_sumup_enabled') === '1') {
            $methods[] = (object)[
                'name' => 'SumUp',
                'slug' => 'sumup',
            ];
        }

        // Square
        if (get_option('tpw_square_enabled') === '1') {
            $methods[] = (object)[
                'name' => 'Square',
                'slug' => 'square',
            ];
        }

        return $methods;
    }
}
