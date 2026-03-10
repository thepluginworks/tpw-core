<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-access.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-controller.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-field-loader.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-meta.php';

function tpw_member_profile_get_builtin_sections() {
  return [
    'profile' => [
      'slug'     => 'profile',
      'label'    => __( 'My Profile', 'tpw-core' ),
      'icon'     => '',
      'priority' => 10,
      'callback' => 'tpw_member_profile_render_profile_section',
      'show'     => true,
    ],
  ];
}

function tpw_member_profile_get_registered_sections() {
  $built_in_sections = tpw_member_profile_get_builtin_sections();
  $filtered_sections = apply_filters( 'tpw_core_register_profile_sections', $built_in_sections );

  if ( ! is_array( $filtered_sections ) ) {
    $filtered_sections = [];
  }

  $sections = array_replace( $built_in_sections, $filtered_sections );
  $sections = tpw_member_profile_normalize_sections( $sections );

  if ( ! isset( $sections['profile'] ) ) {
    $profile_section = tpw_member_profile_normalize_sections( tpw_member_profile_get_builtin_sections() );
    if ( isset( $profile_section['profile'] ) ) {
      $sections['profile'] = $profile_section['profile'];
      uasort( $sections, 'tpw_member_profile_sort_sections' );
    }
  }

  return $sections;
}

function tpw_member_profile_normalize_sections( array $sections ) {
  $normalized = [];

  foreach ( $sections as $key => $section ) {
    if ( ! is_array( $section ) ) {
      continue;
    }

    $slug = sanitize_key( isset( $section['slug'] ) ? (string) $section['slug'] : (string) $key );
    if ( $slug === '' ) {
      continue;
    }

    $show = array_key_exists( 'show', $section ) ? (bool) $section['show'] : true;
    if ( ! $show ) {
      continue;
    }

    $capability = isset( $section['capability'] ) ? (string) $section['capability'] : '';
    if ( $capability !== '' && ! current_user_can( $capability ) ) {
      continue;
    }

    $label = isset( $section['label'] ) ? trim( (string) $section['label'] ) : '';
    if ( $label === '' ) {
      continue;
    }

    $callback = $section['callback'] ?? null;
    $template = isset( $section['template'] ) ? tpw_member_profile_resolve_section_template( (string) $section['template'] ) : '';

    if ( ! is_callable( $callback ) && $template === '' ) {
      continue;
    }

    $normalized[ $slug ] = [
      'slug'       => $slug,
      'label'      => $label,
      'icon'       => isset( $section['icon'] ) ? (string) $section['icon'] : '',
      'priority'   => isset( $section['priority'] ) ? (int) $section['priority'] : 50,
      'callback'   => $callback,
      'template'   => $template,
      'capability' => $capability,
      'show'       => $show,
    ];
  }

  uasort( $normalized, 'tpw_member_profile_sort_sections' );

  return $normalized;
}

function tpw_member_profile_sort_sections( array $left, array $right ) {
  $left_priority = isset( $left['priority'] ) ? (int) $left['priority'] : 50;
  $right_priority = isset( $right['priority'] ) ? (int) $right['priority'] : 50;

  if ( $left_priority === $right_priority ) {
    return strcasecmp( (string) $left['label'], (string) $right['label'] );
  }

  return ( $left_priority < $right_priority ) ? -1 : 1;
}

function tpw_member_profile_resolve_section_template( $template ) {
  $template = trim( (string) $template );
  if ( $template === '' ) {
    return '';
  }

  if ( file_exists( $template ) ) {
    return $template;
  }

  $core_template = trailingslashit( TPW_CORE_PATH ) . ltrim( $template, '/' );
  if ( file_exists( $core_template ) ) {
    return $core_template;
  }

  return '';
}

function tpw_member_profile_get_active_section_slug( array $sections ) {
  $requested = isset( $_GET['section'] ) ? sanitize_key( (string) $_GET['section'] ) : 'profile';

  if ( $requested !== '' && isset( $sections[ $requested ] ) ) {
    return $requested;
  }

  if ( isset( $sections['profile'] ) ) {
    return 'profile';
  }

  $first_slug = array_key_first( $sections );
  return is_string( $first_slug ) ? $first_slug : 'profile';
}

function tpw_member_profile_get_section_url( $slug ) {
  if ( $slug === 'profile' ) {
    return remove_query_arg( 'section' );
  }

  return add_query_arg( 'section', $slug );
}

function tpw_member_profile_get_section_icon_html( $icon ) {
  $icon = trim( (string) $icon );
  if ( $icon === '' ) {
    return '';
  }

  if ( strpos( $icon, '<' ) !== false ) {
    return '<span class="tpw-member-profile-icon" aria-hidden="true">' . wp_kses_post( $icon ) . '</span>';
  }

  if ( strpos( $icon, 'dashicons' ) === false && strpos( $icon, ' ' ) === false ) {
    return '';
  }

  return '<span class="tpw-member-profile-icon ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
}

function tpw_member_profile_render_section_nav( array $sections, $active_slug ) {
  echo '<nav class="tpw-member-profile-nav" aria-label="Profile sections">';
  echo '  <ul class="tpw-tabs tpw-member-profile-menu">';

  foreach ( $sections as $slug => $section ) {
    $is_active = ( $slug === $active_slug );
    $link = tpw_member_profile_get_section_url( $slug );
    $icon_html = tpw_member_profile_get_section_icon_html( $section['icon'] ?? '' );

    echo '    <li><a class="tpw-tab tpw-member-profile-link' . ( $is_active ? ' active' : '' ) . '"' . ( $is_active ? ' aria-current="page"' : '' ) . ' href="' . esc_url( $link ) . '">';
    if ( $icon_html !== '' ) {
      echo $icon_html;
    }
    echo '<span>' . esc_html( $section['label'] ) . '</span>';
    echo '</a></li>';
  }

  echo '  </ul>';
  echo '</nav>';
}

function tpw_member_profile_invoke_section_callback( $callback, array $args ) {
  try {
    if ( is_array( $callback ) ) {
      $reflection = new ReflectionMethod( $callback[0], $callback[1] );
    } else {
      $reflection = new ReflectionFunction( $callback );
    }

    $accepted = $reflection->isVariadic() ? count( $args ) : $reflection->getNumberOfParameters();
    return call_user_func_array( $callback, array_slice( $args, 0, $accepted ) );
  } catch ( Throwable $exception ) {
    return call_user_func( $callback, $args[0] ?? null );
  }
}

function tpw_member_profile_render_active_section( array $section, $member, array $sections ) {
  ob_start();

  if ( isset( $section['callback'] ) && is_callable( $section['callback'] ) ) {
    $result = tpw_member_profile_invoke_section_callback( $section['callback'], [ $member, $section, $sections ] );
    if ( is_string( $result ) && $result !== '' ) {
      echo $result;
    }
  } elseif ( ! empty( $section['template'] ) ) {
    $active_section = $section;
    $profile_sections = $sections;
    include $section['template'];
  }

  return ob_get_clean();
}

function tpw_member_profile_render_profile_section( $member ) {
  // Settings-driven date/time formats
  $settings = get_option( 'flexievent_settings', [] );
  $date_format = $settings['date_format'] ?? 'd-m-Y';
  $time_format = $settings['time_format'] ?? 'H:i';

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
    if ( in_array( $key, ['password_hash','is_admin','is_committee','is_match_manager','is_noticeboard_admin','is_gallery_admin','is_volunteer','status'], true ) ) {
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

  // Member Photo section (settings-controlled)
  $photos_enabled = get_option('tpw_members_use_photos', '0') === '1';
  $photo_mode = get_option( 'tpw_member_profile_photo_mode', 'view' );
  if ( $photos_enabled ) {
    // Resolve current photo URL when present
    $photo_url = '';
    $rel = isset($member->member_photo) ? trim( (string) $member->member_photo ) : '';
    if ( $rel !== '' ) {
      if ( preg_match('#^https?://#i', $rel) ) {
        $photo_url = $rel;
      } else {
        $uploads = wp_get_upload_dir();
        if ( ! empty($uploads['baseurl']) ) {
          $photo_url = rtrim($uploads['baseurl'], '/') . '/' . ltrim($rel, '/');
        }
      }
    }
    echo '<div class="tpw-section" style="margin-top:16px;">';
    echo '  <fieldset class="tpw-section">';
    echo '    <legend class="tpw-section__legend">' . esc_html__( 'Member Photo', 'tpw-core' ) . '</legend>';

    if ( $photo_mode === 'edit' ) {
      // Editable layout mirrors admin edit form
      echo '<div class="form-group">';
      echo '  <label for="tpw-member-photo-input"><strong>' . esc_html__('Upload Member Photo','tpw-core') . '</strong></label>';
      echo '  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
      echo '    <input type="file" id="tpw-member-photo-input" accept=".jpg,.jpeg,.png" style="display:none;">';
      echo '    <button type="button" class="tpw-btn tpw-btn-light" id="tpw-member-photo-choose">' . esc_html__('Choose file','tpw-core') . '</button>';
      echo '    <span id="tpw-member-photo-filename" style="color:#555;">' . esc_html__('No file chosen','tpw-core') . '</span>';
      echo '  </div>';
      echo '  <p class="description" style="margin-top:6px;">' . esc_html__('Recommended size: 300x300px. Max file size: 2MB.','tpw-core') . '</p>';
      echo '  <div style="margin:8px 0; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">';
      if ( $photo_url ) {
        echo '    <img id="tpw-member-photo-current" src="' . esc_url($photo_url) . '" alt="" style="width:100px;height:100px;object-fit:cover;border-radius:8px;" />';
      } else {
        echo '    <img id="tpw-member-photo-current" src="" alt="" style="display:none;width:100px;height:100px;object-fit:cover;border-radius:8px;" />';
      }
      echo '    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">';
      echo '      <button type="button" class="tpw-btn tpw-btn-danger" id="tpw-member-photo-delete-btn">' . esc_html__('Delete','tpw-core') . '</button>';
      echo '      <button type="button" class="tpw-btn tpw-btn-secondary" id="tpw-member-photo-replace-btn">' . esc_html__('Replace','tpw-core') . '</button>';
      echo '    </div>';
      echo '  </div>';
      echo '  <div id="tpw-member-photo-error" style="display:none; font-size:12px; color:#b45309; background:#fff7ed; border:1px solid #fed7aa; padding:6px 8px; border-radius:4px; margin-top:6px;"></div>';
      echo '</div>';
    } else {
      // View-only mode: show image when available
      if ( $photo_url ) {
        echo '<img src="' . esc_url($photo_url) . '" alt="" style="width:120px;height:120px;object-fit:cover;border-radius:10px;" />';
      } else {
        echo '<p class="description">' . esc_html__('No photo uploaded yet.','tpw-core') . '</p>';
      }
    }

    echo '  </fieldset>';
    echo '</div>';
  }

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

  // Enqueue and localize assets
  $handle = 'tpw-member-profile';
  wp_enqueue_script( $handle, plugins_url('js/member-profile.js', __FILE__), ['jquery'], filemtime( plugin_dir_path(__FILE__) . 'js/member-profile.js' ), true );
  wp_localize_script( $handle, 'TPW_MEMBER_PROFILE', [
    'nonce'   => wp_create_nonce('tpw_member_profile_update'),
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'labels'  => [ 'edit' => __('Edit','tpw-core'), 'confirm' => __('Confirm','tpw-core') ],
  ] );
  wp_enqueue_style( 'tpw-member-admin-style', plugins_url('../assets/css/member-admin.css', __FILE__), [], filemtime( plugin_dir_path(__FILE__) . '../assets/css/member-admin.css' ) );

  // Inline JS for photo actions in edit mode (re-uses self-edit nonce)
  if ( $photos_enabled && $photo_mode === 'edit' ) {
    ?>
    <script>
    (function(){
      var AJAX_URL = <?php echo json_encode( admin_url('admin-ajax.php') ); ?>;
      var NONCE = <?php echo json_encode( wp_create_nonce('tpw_member_profile_update') ); ?>;
      var chooseBtn = document.getElementById('tpw-member-photo-choose');
      var fileInput = document.getElementById('tpw-member-photo-input');
      var fileNameEl = document.getElementById('tpw-member-photo-filename');
      var deleteBtn = document.getElementById('tpw-member-photo-delete-btn');
      var replaceBtn = document.getElementById('tpw-member-photo-replace-btn');
      var currentImg = document.getElementById('tpw-member-photo-current');
      var errorBox = document.getElementById('tpw-member-photo-error');
      if (chooseBtn && fileInput) chooseBtn.addEventListener('click', function(){ fileInput.click(); });
      if (replaceBtn && fileInput) replaceBtn.addEventListener('click', function(){ fileInput.click(); });
      if (deleteBtn) {
        deleteBtn.addEventListener('click', function(){
          setBusy(true, 'Deleting…');
          var fd = new FormData();
          fd.append('action','tpw_member_profile_photo_delete');
          fd.append('_wpnonce', NONCE);
          fetch(AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data){
            if (!data || !data.success) throw new Error((data && data.data && data.data.message) || 'Delete failed');
            if (currentImg) { currentImg.style.display = 'none'; currentImg.removeAttribute('src'); }
            if (fileInput) fileInput.value = '';
            if (fileNameEl) fileNameEl.textContent = 'No file chosen';
            hideErr();
          })
          .catch(function(err){ showErr(err && err.message ? err.message : 'Delete failed'); })
          .finally(function(){ setBusy(false); });
        });
      }
      if (fileInput) {
        fileInput.addEventListener('change', function(){
          if (fileNameEl) fileNameEl.textContent = (fileInput.files && fileInput.files[0]) ? fileInput.files[0].name : 'No file chosen';
          if (!fileInput.files || !fileInput.files[0]) return;
          var file = fileInput.files[0];
          const TWO_MB = 2*1024*1024;
          (file.size > TWO_MB ? (showErr('Image exceeds 2MB (' + mb(file.size) + '). Attempting to compress…'), compress(file, TWO_MB)) : Promise.resolve(file))
          .then(function(f){
            if (!f) throw new Error('Image exceeds 2MB and could not be compressed automatically. Please choose a smaller image.');
            if (f !== file) {
              var dt = new DataTransfer(); dt.items.add(f); fileInput.files = dt.files; file = f;
              if (fileNameEl) fileNameEl.textContent = f.name + ' (compressed)';
            }
            hideErr(); setBusy(true, 'Uploading…');
            var fd = new FormData();
            fd.append('action','tpw_member_profile_photo_replace');
            fd.append('_wpnonce', NONCE);
            fd.append('photo', file, file.name);
            return fetch(AJAX_URL, { method:'POST', body: fd, credentials:'same-origin' });
          })
          .then(function(r){ return r.json(); })
          .then(function(data){
            if (!data || !data.success || !data.data || !data.data.url) throw new Error((data && data.data && data.data.message) || 'Upload failed');
            if (currentImg) { currentImg.src = data.data.url; currentImg.style.display=''; }
            if (fileNameEl) fileNameEl.textContent = 'Uploaded';
            fileInput.value = '';
            hideErr();
          })
          .catch(function(err){ showErr(err && err.message ? err.message : 'Upload failed'); })
          .finally(function(){ setBusy(false); });
        });
      }
      function mb(b){ return (b/1024/1024).toFixed(2)+'MB'; }
      function showErr(m){ if(!errorBox) return; errorBox.textContent = m; errorBox.style.display='block'; }
      function hideErr(){ if(!errorBox) return; errorBox.textContent = ''; errorBox.style.display='none'; }
      function setBusy(b, label){ try {
        if (replaceBtn){ replaceBtn.disabled = !!b; if(label && b){ replaceBtn.dataset.prevText = replaceBtn.textContent; replaceBtn.textContent = label; } else if (!b && replaceBtn.dataset.prevText){ replaceBtn.textContent = replaceBtn.dataset.prevText; delete replaceBtn.dataset.prevText; } }
        if (deleteBtn){ deleteBtn.disabled = !!b; if(label && b){ deleteBtn.dataset.prevText = deleteBtn.textContent; deleteBtn.textContent = label; } else if (!b && deleteBtn.dataset.prevText){ deleteBtn.textContent = deleteBtn.dataset.prevText; delete deleteBtn.dataset.prevText; } }
        if (chooseBtn){ chooseBtn.disabled = !!b; }
      } catch(e){} }
      async function compress(file, maxBytes){
        try{
          const img = await loadBitmap(file);
          const dims = fit(img.width, img.height, 500, 500);
          const c = document.createElement('canvas'); c.width=dims.w; c.height=dims.h; const ctx=c.getContext('2d'); ctx.drawImage(img,0,0,dims.w,dims.h);
          for (let q of [0.8,0.7,0.6,0.5]){ const blob = await toBlob(c,'image/jpeg',q); if (blob && blob.size <= maxBytes) return new File([blob], rename(file.name), {type:'image/jpeg'}); }
          const last = await toBlob(c,'image/jpeg',0.5); if (last) return new File([last], rename(file.name), {type:'image/jpeg'});
        }catch(e){}
        return null;
      }
      function rename(name){ return name.replace(/\.[^.]+$/, '') + '-compressed.jpg'; }
      function fit(w,h,maxW,maxH){ var r=Math.min(maxW/w,maxH/h,1); return {w:Math.round(w*r), h:Math.round(h*r)}; }
      function loadBitmap(file){ if ('createImageBitmap' in window) return createImageBitmap(file); return new Promise(function(res,rej){ var img=new Image(); img.onload=function(){res(img)}; img.onerror=rej; var rd=new FileReader(); rd.onload=function(){ img.src=rd.result; }; rd.onerror=rej; rd.readAsDataURL(file); }); }
      function toBlob(canvas,type,q){ return new Promise(function(res){ if(canvas.toBlob){ canvas.toBlob(function(b){res(b);}, type, q); } else { var dataUrl=canvas.toDataURL(type,q); var parts=dataUrl.split(','); var byte=atob(parts[1]||''); var ab=new ArrayBuffer(byte.length); var ia=new Uint8Array(ab); for(var i=0;i<byte.length;i++){ ia[i]=byte.charCodeAt(i); } res(new Blob([ab],{type:type})); } }); }
    })();
    </script>
    <?php
  }

  return ob_get_clean();
}

add_shortcode('tpw_member_profile', function(){
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

  // UI styles are enqueued centrally in modules/members/members-init.php (late priority).
  $sections = tpw_member_profile_get_registered_sections();
  $active_slug = tpw_member_profile_get_active_section_slug( $sections );
  $active_section = $sections[ $active_slug ] ?? $sections['profile'] ?? [];

  if ( empty( $active_section ) ) {
    return '<div class="tpw-error">' . esc_html__('Profile section unavailable.', 'tpw-core') . '</div>';
  }

  ob_start();
  echo '<div class="tpw-profile">';
  tpw_member_profile_render_section_nav( $sections, $active_slug );
  echo tpw_member_profile_render_active_section( $active_section, $member, $sections );
  echo '</div>';

  return ob_get_clean();
});
