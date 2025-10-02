<?php

class TPW_Member_Fields_Installer {
	public static function insert_default_fields() {
		global $wpdb;

		$table = $wpdb->prefix . 'tpw_field_settings';
		$existing = $wpdb->get_col( "SELECT field_key FROM $table" );

		$core_fields = [
			'first_name'            => 'First Name',
			'surname'             => 'Last Name',
			'initials'              => 'Initials',
			'title'                 => 'Title',
			'email'                 => 'Email',
			'mobile'                => 'Mobile',
			'landline'              => 'Landline',
			'address1'              => 'Address Line 1',
			'address2'              => 'Address Line 2',
			'town'                  => 'Town/City',
			'county'                => 'County',
			'postcode'              => 'Postcode',
			'country'               => 'Country',
			'date_joined'           => 'Date Joined',
			'status'                => 'Status',
			'is_committee'          => 'Committee Member',
			'is_match_manager'      => 'Match Manager',
			'is_admin'              => 'Administrator',
			'is_noticeboard_admin' => 'Noticeboard Admin',
			'username'              => 'Username',
			'password_hash'         => 'Password Hash',
		];

		$sort = 0;
		foreach ( $core_fields as $field_key => $label ) {
			if ( ! in_array( $field_key, $existing, true ) ) {
					$wpdb->insert(
						$table,
						[
							'field_key'    => $field_key,
							'is_enabled'   => 1,
							'custom_label' => $label,
							'sort_order'   => $sort++,
						],
						[ '%s', '%d', '%s', '%d' ]
					);
			}
		}
	}
}