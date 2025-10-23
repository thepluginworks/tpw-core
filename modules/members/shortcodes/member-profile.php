<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-access.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-controller.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-field-loader.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-meta.php';

add_shortcode('tpw_member_profile', function(){
  // Settings-driven date/time formats
  $settings = get_option( 'flexievent_settings', [] );
  $date_format = $settings['date_format'] ?? 'd-m-Y';
  $time_format = $settings['time_format'] ?? 'H:i';
    // Must be logged-in. Admins can be allowed to view via filter.
    if ( ! is_user_logged_in() ) {
        return '<div class="tpw-error">' . esc_html__('Access Denied', 'tpw-core') . '</div>';
    }

    $admin_can_view = apply_filters('tpw_members/wp_admin_can_view_profile', true);
    $allow_all_statuses = apply_filters('tpw_members/profile_allow_all_statuses', true);

    $current = wp_get_current_user();
    $controller = new TPW_Member_Controller();
    $member = $controller->get_member_by_user_id( (int) $current->ID );

    // If no linked member record, deny (admins get a clearer message)
    if ( ! $member ) {
        if ( current_user_can('manage_options') ) {
            return '<div class="tpw-error">' . esc_html__('Member record not found for your user. As an admin you can still preview via this page, but creating a member record is recommended.', 'tpw-core') . '</div>';
        }
        return '<div class="tpw-error">' . esc_html__('Member record not found for your user.', 'tpw-core') . '</div>';
    }

    // Enforce status only when opted-in via filter; admins can always view
    if ( ! ( current_user_can('manage_options') && $admin_can_view ) ) {
        if ( ! $allow_all_statuses && ! TPW_Member_Access::is_member_current() ) {
            return '<div class="tpw-error">' . esc_html__('Access Denied', 'tpw-core') . '</div>';
        }
    }

  $editable = get_option( 'tpw_member_editable_fields', [] );
  $editable = is_array($editable) ? $editable : [];
  $viewable = get_option( 'tpw_member_viewable_fields', [] );
  $viewable = is_array($viewable) ? $viewable : [];
  // Do NOT fallback to directory visibility rules. The member profile view is
  // controlled solely by the Profile tab settings (tpw_member_viewable_fields).
  // If viewable is empty, default is to show nothing.

    $fields = TPW_Member_Field_Loader::get_all_enabled_fields();
    $meta   = TPW_Member_Meta::get_all_meta( (int) $member->id );

    ob_start();
    echo '<div class="tpw-profile">';
  echo '<h2>' . esc_html__( 'My Profile', 'tpw-core' ) . '</h2>';

    echo '<div class="tpw-table">';
  echo '  <div class="tpw-table-header"><div class="tpw-table-cell">' . esc_html__( 'Field', 'tpw-core' ) . '</div><div class="tpw-table-cell">' . esc_html__( 'Value', 'tpw-core' ) . '</div><div class="tpw-table-cell">&nbsp;</div></div>';

    if ( empty($viewable) ) {
      // When no viewable fields are configured, show no rows and display a simple note.
      echo '<div class="tpw-table-row">';
      echo '  <div class="tpw-table-cell"><em>No profile fields are currently available to view.</em></div>';
      echo '  <div class="tpw-table-cell"></div>';
      echo '  <div class="tpw-table-cell"></div>';
      echo '</div>';
    }

  foreach ( $fields as $f ) {
        $key = $f['key'];
    if ( in_array( $key, ['password_hash','is_admin','is_committee','is_match_manager','is_noticeboard_admin','status'], true ) ) {
            // hide system/admin control fields
            continue;
        }
    // Respect 'Viewable by Member' setting; when empty, show nothing.
    // Directory visibility rules do not apply here.
    if ( empty($viewable) || ! in_array( $key, $viewable, true ) ) {
      continue;
    }
    $label = $f['label'];
        $is_meta = ! property_exists( $member, $key );
        $value = $is_meta ? ($meta[$key] ?? '') : ($member->$key ?? '');

    // Skip empty, non-editable fields on the My Profile page to avoid showing blank rows
    $is_editable = in_array( $key, $editable, true );
    $is_empty_value = ( $value === null ) || ( is_string( $value ) && trim( $value ) === '' ) || ( is_array( $value ) && count( array_filter( $value, function($v){ return ! ( is_string($v) ? trim($v) === '' : $v === null ); } ) ) === 0 );
    if ( ! $is_editable && $is_empty_value ) {
      continue;
    }

    // Hide WHI Updated field from the profile table when FlexiGolf active; show 'Last updated' under WHI instead
    if ( $key === 'whi_updated' && ( method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active() ) ) {
      continue;
    }

    echo '<div class="tpw-table-row">';
    echo '  <div class="tpw-table-cell">' . esc_html($label) . '</div>';
    // Display formatting: format known date fields using site settings
    $display_value = (string) $value;
    if ( isset($f['type']) && $f['type'] === 'date' ) {
      $display_value = tpw_format_date( $value );
    }
  echo '  <div class="tpw-table-cell">' . esc_html( $display_value ) . '</div>';
    echo '  <div class="tpw-table-cell">';
    if ( in_array( $key, $editable, true ) ) {
  echo '    <button class="tpw-btn tpw-btn-secondary tpw-profile-edit" data-key="' . esc_attr($key) . '">' . esc_html__( 'Edit', 'tpw-core' ) . '</button>';
    } else {
      echo '&nbsp;';
    }
    echo '  </div>';
    echo '</div>';

    // If this row is WHI, print a secondary line with Last updated: <date>
    if (
      $key === 'whi'
      && ( method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active() )
      && property_exists($member, 'whi_updated')
    ) {
      $raw = (string) ($member->whi_updated ?? '');
      $disp = $raw !== '' ? tpw_format_date( $raw ) : '';
      echo '<div class="tpw-table-row"><div class="tpw-table-cell"></div><div class="tpw-table-cell"><em>' . esc_html__( 'Last updated:', 'tpw-core' ) . ' ' . esc_html($disp ?: '—') . '</em></div><div class="tpw-table-cell"></div></div>';
    }
    }
  echo '</div>';

  // Insert point for additional profile sections (server-side). Downstream modules can hook here.
  do_action( 'tpw_member_profile_after', $member );
  // Also include a lightweight anchor to support compatibility moves via the_content.
  echo '<!-- TPW_MEMBER_PROFILE_AFTER -->';

    // Modal markup
    ?>
    <div id="tpw-profile-modal" class="tpw-dir-modal" hidden>
      <div class="tpw-dir-modal__dialog">
        <div class="tpw-dir-modal__header">
          <h3>Edit Field</h3>
          <button type="button" class="tpw-btn tpw-btn-light tpw-dir-modal-close">Close</button>
        </div>
        <div class="tpw-dir-modal__body">
          <form id="tpw-profile-form">
            <input type="hidden" name="field_key" value="">
            <div class="tpw-field-row">
              <label id="tpw-profile-label"></label>
              <input type="text" name="field_value" value="" />
            </div>
            <div id="tpw-profile-result" style="margin-top:8px;"></div>
            <div style="margin-top:10px;">
              <button type="submit" class="tpw-btn tpw-btn-primary">Confirm</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php

    echo '</div>';

    // Enqueue and localize assets
    $handle = 'tpw-member-profile';
    wp_enqueue_script( $handle, plugins_url('js/member-profile.js', __FILE__), ['jquery'], filemtime( plugin_dir_path(__FILE__) . 'js/member-profile.js' ), true );
  wp_localize_script( $handle, 'TPW_MEMBER_PROFILE', [
        'nonce'   => wp_create_nonce('tpw_member_profile_update'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'labels'  => [ 'edit' => __('Edit','tpw-core'), 'confirm' => __('Confirm','tpw-core') ],
    ] );
    wp_enqueue_style( 'tpw-member-admin-style', plugins_url('../assets/css/member-admin.css', __FILE__), [], filemtime( plugin_dir_path(__FILE__) . '../assets/css/member-admin.css' ) );

    return ob_get_clean();
});
