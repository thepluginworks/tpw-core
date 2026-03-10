<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

require_once TPW_CORE_PATH . 'modules/members/includes/admin-settings-tabs.php';
?>
<div class="tpw-admin-wrapper">
  <div class="tpw-settings-card">
    <h2>Member Settings</h2>
    <?php tpw_members_render_settings_tabs( 'visibility' ); ?>

  <h3>Directory Field Visibility</h3>
  <p class="description" style="max-width: 72ch;">
    Control which groups can see each field in the member directory. These settings affect directory listings and member detail modals, not a member’s own profile view or editing rights.
  </p>

  <?php
  if ( ! class_exists( 'TPW_Member_Access' ) ) {
      require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-access.php';
  }
  if ( ! TPW_Member_Access::can_manage_members_current() ) {
      echo '<div class="notice notice-error"><p>Access denied.</p></div>';
      return;
  }

  global $wpdb;
  $vis_table   = $wpdb->prefix . 'tpw_member_field_visibility';
  $settings_tbl= $wpdb->prefix . 'tpw_field_settings';

  // Groups/columns definition
  $groups = [
      'admin'     => 'Admin',
      'committee' => 'Committee',
      'member'    => 'Member',
      'guest'     => 'Guest',
  ];

  $saved_notice = false; $error_notice = '';
  if ( isset($_POST['tpw_member_visibility_nonce']) && wp_verify_nonce( $_POST['tpw_member_visibility_nonce'], 'tpw_member_visibility_matrix' ) ) {
      // Collect enabled field keys for scoped updates
      $enabled_rows = $wpdb->get_results( "SELECT field_key, custom_label FROM {$settings_tbl} WHERE is_enabled = 1 ORDER BY sort_order ASC" );
      $enabled_keys = array_map( function($r){ return sanitize_key($r->field_key); }, (array) $enabled_rows );

      if ( ! is_array($enabled_keys) ) { $enabled_keys = []; }

      $posted = isset($_POST['visibility']) && is_array($_POST['visibility']) ? $_POST['visibility'] : [];
      foreach ( $groups as $gk => $glabel ) {
          $selected = isset($posted[$gk]) && is_array($posted[$gk]) ? array_map('sanitize_key', $posted[$gk]) : [];
          if ( empty($enabled_keys) ) { continue; }

          // Delete existing mappings only for currently enabled fields in this group
          // Build IN clause safely
          $placeholders = implode( ',', array_fill(0, count($enabled_keys), '%s') );
          $params = array_merge( [ $gk ], $enabled_keys );
          // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
          $sql_del = $wpdb->prepare( "DELETE FROM {$vis_table} WHERE `group` = %s AND field_key IN ({$placeholders})", $params );
          $wpdb->query( $sql_del );

          // Insert selected mappings
          foreach ( $selected as $field_key ) {
              if ( ! in_array( $field_key, $enabled_keys, true ) ) continue;
              $wpdb->insert( $vis_table, [
                  'field_key'  => $field_key,
                  'group'      => $gk,
                  'is_visible' => 1,
              ], [ '%s', '%s', '%d' ] );
          }
      }
      $saved_notice = true;
  } elseif ( isset($_POST['tpw_member_visibility_nonce']) ) {
      $error_notice = 'Invalid request. Please try again.';
  }

  // Load enabled fields
  $rows = $wpdb->get_results( "SELECT field_key, custom_label FROM {$settings_tbl} WHERE is_enabled = 1 ORDER BY sort_order ASC" );
  $enabled_fields = array_map( function($r){ return [ 'key' => $r->field_key, 'label' => $r->custom_label ]; }, (array) $rows );

  // Load current visibility for our groups
  if ( ! empty($enabled_fields) ) {
      $group_keys = array_keys( $groups );
      $place_g = implode( ',', array_fill(0, count($group_keys), '%s') );
      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
      $sql_vis = $wpdb->prepare( "SELECT field_key, `group` FROM {$vis_table} WHERE is_visible = 1 AND `group` IN ({$place_g})", $group_keys );
      $vis_rows = $wpdb->get_results( $sql_vis );
  } else {
      $vis_rows = [];
  }
  $visible = [];
  foreach ( (array) $vis_rows as $vr ) {
      $g = sanitize_key( $vr->group );
      $fk= sanitize_key( $vr->field_key );
      if ( $g && $fk ) {
          if ( ! isset($visible[$g]) ) $visible[$g] = [];
          $visible[$g][$fk] = true;
      }
  }
  ?>

  <?php if ( $saved_notice ): ?>
    <div class="notice notice-success"><p>Directory field visibility saved.</p></div>
  <?php endif; ?>
  <?php if ( $error_notice ): ?>
    <div class="notice notice-error"><p><?php echo esc_html( $error_notice ); ?></p></div>
  <?php endif; ?>

  <style>
  /* Responsive labels for visibility matrix */
  #tpw-visibility-matrix .tpw-matrix-label { display:inline-flex; align-items:center; gap:8px; }
  #tpw-visibility-matrix .tpw-matrix-text { display:none; color:#555; }
  @media (max-width: 880px) {
    #tpw-visibility-matrix .tpw-table-header { display:none; }
    #tpw-visibility-matrix .tpw-table-row { display:block; padding:10px; margin:10px 0; border:1px solid #eee; border-radius:8px; background:#fafafa; }
    #tpw-visibility-matrix .tpw-table-row .tpw-table-cell { text-align:left !important; padding:6px 4px; }
    #tpw-visibility-matrix .tpw-table-row .tpw-table-cell:first-child { font-weight:600; }
    #tpw-visibility-matrix .tpw-matrix-label { display:flex; }
    #tpw-visibility-matrix .tpw-matrix-text { display:inline; }
  }
  </style>

  <form method="post">
    <?php wp_nonce_field( 'tpw_member_visibility_matrix', 'tpw_member_visibility_nonce' ); ?>
    <div class="tpw-table" id="tpw-visibility-matrix">
      <div class="tpw-table-header">
        <div class="tpw-table-cell" style="min-width:220px;">Field</div>
        <?php foreach ( $groups as $gk => $glabel ): ?>
          <div class="tpw-table-cell" style="text-align:center;">
            <?php echo esc_html( $glabel ); ?>
            <div>
              <label style="font-weight:normal;">
                <input type="checkbox" class="tpw-select-all" data-group="<?php echo esc_attr($gk); ?>"> Select All
              </label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php foreach ( $enabled_fields as $f ): $fk = sanitize_key($f['key']); $fl = $f['label']; ?>
        <div class="tpw-table-row">
          <div class="tpw-table-cell"><code><?php echo esc_html( $fk ); ?></code> &mdash; <?php echo esc_html( $fl ); ?></div>
          <?php foreach ( $groups as $gk => $glabel ): $checked = !empty($visible[$gk][$fk]); ?>
            <div class="tpw-table-cell" style="text-align:center;">
              <label class="tpw-matrix-label">
                <input type="checkbox" aria-label="<?php echo esc_attr( $glabel ); ?>" class="tpw-matrix cb-<?php echo esc_attr($gk); ?>" name="visibility[<?php echo esc_attr($gk); ?>][]" value="<?php echo esc_attr($fk); ?>" <?php checked( $checked ); ?> />
                <span class="tpw-matrix-text"><?php echo esc_html( $glabel ); ?></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <p style="margin-top:12px;"><button type="submit" class="tpw-btn tpw-btn-primary">Save Visibility</button></p>
  </form>

  <script>
  (function(){
    function updateHeaderState(group){
      var all = document.querySelectorAll('.cb-'+group);
      var header = document.querySelector('.tpw-select-all[data-group="'+group+'"]');
      if (!header || !all.length) return;
      var checked = 0; all.forEach(function(cb){ if (cb.checked) checked++; });
      header.checked = (checked === all.length);
      header.indeterminate = (checked > 0 && checked < all.length);
    }
    document.querySelectorAll('.tpw-select-all').forEach(function(hdr){
      var group = hdr.getAttribute('data-group');
      updateHeaderState(group);
      hdr.addEventListener('change', function(){
        var all = document.querySelectorAll('.cb-'+group);
        all.forEach(function(cb){ cb.checked = hdr.checked; });
      });
    });
    document.querySelectorAll('.tpw-matrix').forEach(function(cb){
      cb.addEventListener('change', function(){
        var classes = this.className.split(/\s+/);
        var g = null; classes.forEach(function(c){ if (c.indexOf('cb-')===0) g = c.substring(3); });
        if (g) updateHeaderState(g);
      });
    });
  })();
  </script>
  </div>
</div>
<?php ?>
