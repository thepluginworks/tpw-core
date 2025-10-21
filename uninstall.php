<?php
/**
 * Uninstall handler for TPW Core.
 *
 * Removes plugin data only if the user opted-in via settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Only proceed if user chose to remove data on uninstall.
$option_key = 'tpw_core_delete_data_on_uninstall';
$should_delete = get_option( $option_key, '0' ) === '1';

if ( ! $should_delete ) {
    return;
}

// Helper to safely delete an option.
function tpw_core_delete_option( $key ) {
    if ( get_option( $key, null ) !== null ) {
        delete_option( $key );
        delete_site_option( $key );
    }
}

// List of options to remove (core + modules)
$options = [
    'tpw_members_db_version',
    'tpw_login_redirect_page_id',
    'tpw_core_default_login_page',
    'tpw_core_rewrite_flushed_v1',
    'tpw_core_rewrite_flushed_v2',

    // Branding / UI
    'tpw_core_branding',
    'tpw_ui_theme_settings',
    'tpw_heading_styles',
    'tpw_brand_title',

    // Members
    'tpw_members_settings',
    'tpw_members_allow_deletion',
    'tpw_default_member_status',
    'tpw_members_use_photos',
    'tpw_members_enable_advanced_search',
    'tpw_members_manage_access',
    'tpw_members_default_view',
    'tpw_members_default_per_page',
    'tpw_members_default_per_page_card',
    'tpw_member_searchable_fields',
    'tpw_member_editable_fields',
    'tpw_member_viewable_fields',
    'tpw_member_profile_page_id',
    'tpw_member_field_sections',
    'tpw_conditional_fields',

    // Postcodes
    'tpw_postcode_settings',

    // Payments
    'tpw_active_payment_methods',
    'tpw_bacs_account_name',
    'tpw_bacs_account_number',
    'tpw_bacs_sort_code',
    'tpw_cheque_enabled',
    'tpw_cheque_payable_to',
    'tpw_cheque_post_name',
    'tpw_cheque_address1',
    'tpw_cheque_address2',
    'tpw_cheque_address3',
    'tpw_cheque_town',
    'tpw_cheque_county',
    'tpw_cheque_postcode',
    'tpw_cash_enabled',
    'tpw_cash_message',

    // Square / SumUp
    'tpw_square_access_token',
    'tpw_square_sandbox_mode',
    'tpw_square_location_id',
    'tpw_sumup_access_token',
    'tpw_sumup_client_id',
    'tpw_sumup_client_secret',
    'tpw_sumup_merchant_code',
    'tpw_sumup_email',
    'tpw_sumup_password',

    // Legacy / misc
    'flexievent_settings',
    'flexievent_currency_symbol',
    'flexievent_currency_code',
    'flexievent_date_format',
    'flexievent_time_format',
    'tpw_core_member_menu_seeded',
    'tpw_core_profile_page_seeded',
    'tpw_gallery_db_version',
];

foreach ( $options as $key ) {
    tpw_core_delete_option( $key );
}

// Optionally, remove transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tpw_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_tpw_%'" );

// Note: We do not delete posts, pages, or media created by the plugin to avoid accidental data loss.
