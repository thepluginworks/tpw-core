
<?php

$controller = new TPW_Member_Controller();
$settings = get_option( 'flexievent_settings', [] );
$date_format = tpw_core_get_date_format();
$time_format = tpw_core_get_time_format();

$member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$member = $controller->get_member($member_id);
$meta = TPW_Member_Meta::get_all_meta($member_id);

// Precompute Create WP User availability and ids for detached form wiring
$can_show_create_wp_user = ( empty($member->user_id) && ! empty($member->email) && current_user_can('manage_options') );
$create_wp_form_id = 'tpw-create-wp-user-form-' . (int) $member_id;
$create_wp_btn_id  = 'tpw-create-wp-user-btn-' . (int) $member_id;
$create_wp_admin_post = admin_url('admin-post.php');

$fields = TPW_Member_Field_Loader::get_all_enabled_fields();
$fields = array_filter($fields, fn($field) => $field['key'] !== 'password_hash');
// Ensure explicit sort by sort_order from settings
usort($fields, function($a, $b){
    $sa = isset($a['sort_order']) ? (int)$a['sort_order'] : PHP_INT_MAX;
    $sb = isset($b['sort_order']) ? (int)$b['sort_order'] : PHP_INT_MAX;
    if ($sa === $sb) {
        return strcasecmp((string)$a['label'], (string)$b['label']);
    }
    return $sa <=> $sb;
});

if ( ! $member ) {
    echo '<div class="tpw-error">Member not found.</div>';
    return;
}
?>

<div class="tpw-member-form">
    <h2>Edit Member</h2>
    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('tpw_edit_member_action', 'tpw_edit_member_nonce'); ?>
        <input type="hidden" name="member_id" value="<?php echo esc_attr($member_id); ?>">
        <input type="hidden" name="user_id" value="<?php echo esc_attr($member->user_id); ?>">
        <input type="hidden" name="society_id" value="<?php echo esc_attr($member->society_id); ?>">

        <?php
        // Visibility model note:
        // - Admins use this edit form and see all ENABLED fields here.
        // - Directory/modal visibility is NOT enforced in this form. It is controlled
        //   centrally via the tpw_member_field_visibility table and the
        //   tpw_can_group_view_field($group, $field) helper used by the directory UI and AJAX modal.
        ?>

        <?php 
        $known_checkbox_fields = ['is_committee', 'is_match_manager', 'is_admin', 'is_noticeboard_admin', 'is_gallery_admin', 'is_volunteer'];
        // Group by section after sorting
        $grouped = [];
        foreach ($fields as $f) {
            $sec = isset($f['section']) && $f['section'] !== '' ? $f['section'] : 'General';
            $grouped[$sec][] = $f;
        }
        foreach ( $grouped as $section_name => $section_fields ):
        ?>
            <fieldset class="tpw-section">
                <legend class="tpw-section__legend"><?php echo esc_html($section_name); ?></legend>
        <?php foreach ($section_fields as $field):
            if ( $field['key'] === 'user_id' ) {
                continue;
            }
            $key = $field['key'];
            $value = $field['is_core'] ? $member->$key ?? '' : $meta[$key] ?? '';
        ?>
            <?php
            // If FlexiGolf is active, hide the dedicated whi_updated field entirely.
            if (
                $key === 'whi_updated'
                && ( method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active() )
            ) {
                // Skip rendering this field block; we'll show the last-updated note under the WHI field.
                continue;
            }
            ?>
            <div class="form-group">
                <?php
                    $label_text = ($key === 'status') ? 'Member Status' : $field['label'];
                    if ($key === 'username') { $label_text .= ' (cannot be changed)'; }
                    $inline_checkbox = in_array($key, ['is_committee','is_match_manager','is_admin','is_noticeboard_admin','is_gallery_admin','is_volunteer'], true);
                    // For inline checkbox fields we'll render a combined label in the checkbox branches below
                    if ( ! $inline_checkbox || (isset($field['type']) && $field['type'] !== 'checkbox') ) {
                        echo '<label for="' . esc_attr($key) . '">' . esc_html($label_text) . '</label>';
                    }
                ?>

                <?php
                switch ( $field['type'] ) {
                    case 'textarea':
                        echo '<textarea name="' . esc_attr($key) . '" id="' . esc_attr($key) . '">' . esc_textarea($value) . '</textarea>';
                        break;

                    case 'select':
                        echo '<select name="' . esc_attr($key) . '" id="' . esc_attr($key) . '">';
                        echo '<option value="">-- Select --</option>';
                        if ( $key === 'status' ) {
                            $status_options = [
                                'Active',
                                'Inactive',
                                'Deceased',
                                'Honorary',
                                'Resigned',
                                'Suspended',
                                'Pending',
                                'Life Member',
                            ];
                            $cur = is_string($value) ? trim($value) : '';
                            $cur_l = strtolower($cur);
                            foreach ( $status_options as $opt ) {
                                $sel = ($cur_l === strtolower($opt)) ? ' selected' : '';
                                echo '<option value="' . esc_attr($opt) . '"' . $sel . '>' . esc_html($opt) . '</option>';
                            }
                        } elseif ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
                            $cur = is_string( $value ) ? trim( $value ) : '';
                            $has_cur = false;
                            foreach ( $field['options'] as $opt ) {
                                if ( (string) $opt === $cur ) {
                                    $has_cur = true;
                                    break;
                                }
                            }
                            if ( $cur !== '' && ! $has_cur ) {
                                echo '<option value="' . esc_attr( $cur ) . '" selected>' . esc_html( $cur ) . '</option>';
                            }
                            foreach ( $field['options'] as $opt ) {
                                $opt = (string) $opt;
                                $sel = ( $cur !== '' && $opt === $cur ) ? ' selected' : '';
                                echo '<option value="' . esc_attr( $opt ) . '"' . $sel . '>' . esc_html( $opt ) . '</option>';
                            }
                        } else {
                            // Generic select fallback: include current value
                            echo '<option value="' . esc_attr($value) . '" selected>' . esc_html($value) . '</option>';
                        }
                        echo '</select>';
                        break;

                    case 'checkbox':
                        $checked = $value == '1' ? 'checked' : '';
                        if ( $inline_checkbox ) {
                            echo '<div class="tpw-inline-checkbox" style="display:flex;align-items:center;gap:8px;">'
                                . '<input type="checkbox" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="1" ' . $checked . '>'
                                . '<label for="' . esc_attr($key) . '" style="margin:0;">' . esc_html($label_text) . '</label>'
                                . '</div>';
                        } else {
                            echo '<input type="checkbox" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="1" ' . $checked . '>';
                        }
                        break;

                    case 'date':
                        // Display using configured date format; JS datepicker is initialized with matching format
                        $display = '';
                        if ( ! empty($value) ) {
                            $display = tpw_format_date( $value );
                        }
                        $placeholder = tpw_core_date_placeholder( $date_format );
                        echo '<input type="text" class="tpw-date" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($display) . '" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off">';
                        echo '<div class="description" style="margin-top:4px;">' . esc_html( tpw_core_human_date_hint( $date_format ) ) . '</div>';
                        break;

                    default:
                        if ( $key === 'status' ) {
                            $status_options = [
                                'active'     => 'Active',
                                'inactive'   => 'Inactive',
                                'deceased'   => 'Deceased',
                                'honorary'   => 'Honorary',
                                'resigned'   => 'Resigned',
                                'suspended'  => 'Suspended',
                                'pending'    => 'Pending',
                                'life'       => 'Life Member'
                            ];
                            echo '<select name="status" id="status">';
                            echo '<option value="">-- Select Status --</option>';
                            $value_lower = strtolower( (string) $value );
                            foreach ( $status_options as $opt_value => $opt_label ) {
                                $selected = ($value_lower === $opt_value) ? 'selected' : '';
                                echo '<option value="' . esc_attr($opt_value) . '" ' . $selected . '>' . esc_html($opt_label) . '</option>';
                            }
                            echo '</select>';
                        } elseif ( in_array( $key, $known_checkbox_fields ) ) {
                            $checked = $value == '1' ? 'checked' : '';
                            if ( $inline_checkbox ) {
                                echo '<div class="tpw-inline-checkbox" style="display:flex;align-items:center;gap:8px;">'
                                    . '<input type="checkbox" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="1" ' . $checked . '>'
                                    . '<label for="' . esc_attr($key) . '" style="margin:0;">' . esc_html($label_text) . '</label>'
                                    . '</div>';
                            } else {
                                echo '<input type="checkbox" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="1" ' . $checked . '>';
                            }
                        } else {
                            // Postcode field gets a Lookup button next to the input
                            if ( $key === 'postcode' ) {
                                echo '<div class="tpw-inline-input-action">';
                                echo '<input type="text" name="postcode" id="postcode" value="' . esc_attr($value) . '">';
                                echo '<button type="button" id="tpw-postcode-lookup-btn" class="tpw-btn tpw-btn-secondary tpw-postcode-btn" aria-label="Lookup postcode">Lookup</button>';
                                echo '</div>';
                                echo '<div class="tpw-postcode-select-wrap" style="display:none;margin-top:6px;">';
                                echo '<select class="tpw-postcode-select" aria-label="Select address"></select>';
                                echo '</div>';
                                echo '<div class="tpw-postcode-message" style="display:none;margin-top:6px;color:#666;"></div>';
                            } else {
                                // Username is not editable on the Edit form
                                if ( $key === 'username' ) {
                                    $display_value = $value;
                                    if ( (string) $display_value === '' && ! empty( $member->user_id ) ) {
                                        $wp_user = get_user_by( 'id', (int) $member->user_id );
                                        if ( $wp_user && isset( $wp_user->user_login ) ) {
                                            $display_value = (string) $wp_user->user_login;
                                        }
                                    }
                                    echo '<input type="text" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($display_value) . '" readonly aria-readonly="true">';
                                } else {
                                    echo '<input type="text" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                                }
                                // Inline Create WP User control directly under Email when eligible
                                if (
                                    $key === 'email'
                                    && $can_show_create_wp_user
                                ) {
                                    echo '<div class="description" style="margin-top:8px;color:#7c2d12;background:#fffbeb;border:1px solid #fcd34d;padding:8px;border-radius:6px;">';
                                    echo '<strong>⚠️ This member has no WordPress account.</strong><br>Click the button to create one using the email above.';
                                    echo '</div>';
                                    echo '<div class="tpw-inline-create-user" style="margin-top:8px;display:flex;flex-wrap:wrap;align-items:center;gap:12px;">';
                                    echo '<label style="display:flex;align-items:center;gap:6px;font-weight:normal;margin:0 8px 0 0;">';
                                    echo '<input type="checkbox" name="send_credentials" value="1" form="' . esc_attr($create_wp_form_id) . '">';
                                    echo '<span>Send login credentials to this member</span>';
                                    echo '</label>';
                                    echo '<button type="button" id="' . esc_attr($create_wp_btn_id) . '" class="tpw-btn tpw-btn-secondary">Create WordPress User</button>';
                                    echo '</div>';
                                }
                            }
                            // If this is the WHI field and FlexiGolf is active, add a read-only last-updated note below it
                            if (
                                $key === 'whi'
                                && ( method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active() )
                            ) {
                                $wu = isset($member->whi_updated) ? (string) $member->whi_updated : '';
                                $display = $wu !== '' ? tpw_format_date( $wu ) : '';
                                echo '<div class="description" style="margin-top:4px;margin-bottom:12px;">WHI last updated: ' . esc_html($display ?: '—') . '</div>';
                            }
                        }
                }
                ?>
            </div>
        <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>

    <?php
    // Allow other plugins to extend the Edit Member form with extra fields.
    // Signature: ( string $context, int $member_id, object $member, array $meta )
    do_action( 'tpw_members_admin_form_extra_fields', 'edit', $member_id, $member, $meta );
    ?>

        <?php if ( get_option('tpw_members_use_photos', '0') === '1' ) : ?>
        <fieldset class="tpw-section">
            <legend class="tpw-section__legend">Upload Member Photo</legend>
        <div class="form-group">
            <label for="tpw-member-photo-input">Photo</label>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <input type="file" name="member_photo_file" id="tpw-member-photo-input" accept=".jpg,.jpeg,.png" style="display:none;">
                <button type="button" class="tpw-btn tpw-btn-light" id="tpw-member-photo-choose">Choose file</button>
                <span id="tpw-member-photo-filename" style="color:#555;">No file chosen</span>
            </div>
            <p class="description" style="margin-top:6px;">Recommended size: 300x300px. Max file size: 2MB.</p>
            <?php if ( !empty($member->member_photo) ):
                $photo_url = '';
                $rel = trim((string)$member->member_photo);
                if ( preg_match('#^https?://#i', $rel) ) {
                    $photo_url = $rel;
                } else {
                    $uploads = wp_get_upload_dir();
                    if ( ! empty($uploads['baseurl']) ) {
                        $photo_url = rtrim($uploads['baseurl'], '/') . '/' . ltrim($rel, '/');
                    }
                }
            ?>
            <input type="hidden" name="member_photo_delete" value="0" id="tpw-member-photo-delete">
            <div style="margin:8px 0; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <?php if ($photo_url): ?><img id="tpw-member-photo-current" src="<?php echo esc_url($photo_url); ?>" alt="Current photo" style="width:100px;height:100px;object-fit:cover;border-radius:8px;" /><?php endif; ?>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="tpw-btn tpw-btn-danger" id="tpw-member-photo-delete-btn">Delete</button>
                    <button type="button" class="tpw-btn tpw-btn-secondary" id="tpw-member-photo-replace-btn">Replace</button>
                </div>
            </div>
            <div id="tpw-member-photo-error" style="display:none; font-size:12px; color:#b45309; background:#fff7ed; border:1px solid #fed7aa; padding:6px 8px; border-radius:4px; margin-top:6px;"></div>
            <?php endif; ?>
        </div>
        </fieldset>
        <script>
        (function(){
            var AJAX_URL = <?php echo json_encode( admin_url('admin-ajax.php') ); ?>;
            var PHOTO_NONCE = <?php echo json_encode( wp_create_nonce('tpw_member_photo_nonce') ); ?>;
            var memberIdInput = document.querySelector('input[name="member_id"]');
            var MEMBER_ID = memberIdInput ? parseInt(memberIdInput.value, 10) : 0;
            var chooseBtn = document.getElementById('tpw-member-photo-choose');
            var fileInput = document.getElementById('tpw-member-photo-input');
            var fileNameEl = document.getElementById('tpw-member-photo-filename');
            var deleteInput = document.getElementById('tpw-member-photo-delete');
            var deleteBtn = document.getElementById('tpw-member-photo-delete-btn');
            var replaceBtn = document.getElementById('tpw-member-photo-replace-btn');
            var currentImg = document.getElementById('tpw-member-photo-current');
            var errorBox = document.getElementById('tpw-member-photo-error');
            var form = (fileInput ? fileInput.closest('form') : null);
            if (chooseBtn && fileInput) {
                chooseBtn.addEventListener('click', function(){ fileInput.click(); });
            }
            if (replaceBtn && fileInput) {
                replaceBtn.addEventListener('click', function(){
                    fileInput.click();
                });
            }
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function(){
                    if (!MEMBER_ID) return;
                    setBusy(true, 'Deleting…');
                    var fd = new FormData();
                    fd.append('action', 'tpw_member_photo_delete');
                    fd.append('member_id', String(MEMBER_ID));
                    fd.append('_wpnonce', PHOTO_NONCE);
                    fetch(AJAX_URL, { method: 'POST', body: fd })
                        .then(function(r){ return r.json(); })
                        .then(function(data){
                            if (!data || !data.success) throw new Error((data && data.data && data.data.message) || 'Delete failed');
                            if (currentImg) currentImg.style.display = 'none';
                            if (fileInput) fileInput.value = '';
                            if (fileNameEl) fileNameEl.textContent = 'No file chosen';
                            if (deleteInput) deleteInput.value = '0';
                            hidePhotoError();
                        })
                        .catch(function(err){ showPhotoError(err && err.message ? err.message : 'Delete failed'); })
                        .finally(function(){ setBusy(false); });
                });
            }
            if (fileInput) {
                fileInput.addEventListener('change', function(){
                    if (fileNameEl) {
                        if (fileInput.files && fileInput.files.length > 0) {
                            fileNameEl.textContent = fileInput.files[0].name;
                        } else {
                            fileNameEl.textContent = 'No file chosen';
                        }
                    }
                    if (!MEMBER_ID || !fileInput.files || !fileInput.files[0]) return;
                    const TWO_MB = 2 * 1024 * 1024;
                    let file = fileInput.files[0];
                    // If large, attempt client-side compress first
                    (file.size > TWO_MB ? (showPhotoError('Image exceeds 2MB (' + formatMB(file.size) + '). Attempting to compress…'), compressImage(file, TWO_MB)) : Promise.resolve(file))
                    .then(function(maybeFile){
                        if (!maybeFile) throw new Error('Image exceeds 2MB and could not be compressed automatically. Please choose a smaller image.');
                        // If compressed, update the input to reflect the new file
                        if (maybeFile !== file) {
                            const dt = new DataTransfer();
                            dt.items.add(maybeFile);
                            fileInput.files = dt.files;
                            file = maybeFile;
                            if (fileNameEl) fileNameEl.textContent = file.name + ' (compressed)';
                        }
                        hidePhotoError();
                        // Upload immediately
                        setBusy(true, 'Uploading…');
                        var fd = new FormData();
                        fd.append('action', 'tpw_member_photo_replace');
                        fd.append('member_id', String(MEMBER_ID));
                        fd.append('_wpnonce', PHOTO_NONCE);
                        fd.append('photo', file, file.name);
                        return fetch(AJAX_URL, { method: 'POST', body: fd });
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data || !data.success || !data.data || !data.data.url) throw new Error((data && data.data && data.data.message) || 'Upload failed');
                        if (currentImg) {
                            currentImg.src = data.data.url;
                            currentImg.style.display = '';
                        }
                        if (fileNameEl) fileNameEl.textContent = 'Uploaded';
                        // Clear input as we already saved it
                        fileInput.value = '';
                        if (deleteInput) deleteInput.value = '0';
                        hidePhotoError();
                    })
                    .catch(function(err){ showPhotoError(err && err.message ? err.message : 'Upload failed'); })
                    .finally(function(){ setBusy(false); });
                });
            }

            function formatMB(bytes){ return (bytes/1024/1024).toFixed(2) + 'MB'; }
            function showPhotoError(msg){ if(!errorBox) return; errorBox.textContent = msg; errorBox.style.display='block'; }
            function hidePhotoError(){ if(!errorBox) return; errorBox.textContent = ''; errorBox.style.display='none'; }

            async function compressImage(file, maxBytes){
                try {
                    const img = await loadImageBitmap(file);
                    const maxDim = 500; // match server resize target
                    const dims = scaleToFit(img.width, img.height, maxDim, maxDim);
                    const canvas = document.createElement('canvas');
                    canvas.width = dims.w; canvas.height = dims.h;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, dims.w, dims.h);
                    const qualities = [0.8, 0.7, 0.6, 0.5];
                    for (let q of qualities) {
                        const blob = await canvasToBlob(canvas, 'image/jpeg', q);
                        if (!blob) continue;
                        if (blob.size <= maxBytes) {
                            return new File([blob], renameToJpg(file.name), { type: 'image/jpeg' });
                        }
                    }
                    // Return best-effort even if still over limit
                    const last = await canvasToBlob(canvas, 'image/jpeg', 0.5);
                    if (last) return new File([last], renameToJpg(file.name), { type: 'image/jpeg' });
                } catch(e) { /* no-op */ }
                return null;
            }
            function renameToJpg(name){
                const base = name.replace(/\.[^.]+$/, '');
                return base + '-compressed.jpg';
            }
            function scaleToFit(w, h, maxW, maxH){
                let r = Math.min(maxW / w, maxH / h, 1);
                return { w: Math.round(w * r), h: Math.round(h * r) };
            }
            function loadImageBitmap(file){
                if ('createImageBitmap' in window) {
                    return createImageBitmap(file);
                }
                return new Promise(function(resolve, reject){
                    const img = new Image();
                    img.onload = function(){ resolve(img); };
                    img.onerror = reject;
                    const reader = new FileReader();
                    reader.onload = function(){ img.src = reader.result; };
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });
            }
            function canvasToBlob(canvas, type, quality){
                return new Promise(function(resolve){
                    if (canvas.toBlob) {
                        canvas.toBlob(function(b){ resolve(b); }, type, quality);
                    } else {
                        const dataUrl = canvas.toDataURL(type, quality);
                        const b = dataURLToBlob(dataUrl);
                        resolve(b);
                    }
                });
            }
            function dataURLToBlob(dataUrl){
                const parts = dataUrl.split(',');
                const byteString = atob(parts[1] || '');
                const mime = (parts[0].match(/:(.*?);/)||[])[1] || 'image/jpeg';
                const ab = new ArrayBuffer(byteString.length);
                const ia = new Uint8Array(ab);
                for (let i=0;i<byteString.length;i++){ ia[i]=byteString.charCodeAt(i); }
                return new Blob([ab], {type:mime});
            }

            // Final guard on submit
            if (form) {
                form.addEventListener('submit', function(e){
                    if (fileInput && fileInput.files && fileInput.files[0]) {
                        const f = fileInput.files[0];
                        const TWO_MB = 2 * 1024 * 1024;
                        if (f.size > TWO_MB) {
                            e.preventDefault();
                            showPhotoError('Image exceeds 2MB (' + formatMB(f.size) + '). Please choose a smaller image.');
                        }
                    }
                });
            }

            // Busy state helper to avoid double actions
            function setBusy(isBusy, label){
                try {
                    if (replaceBtn) { replaceBtn.disabled = !!isBusy; if(label && isBusy){ replaceBtn.dataset.prevText = replaceBtn.textContent; replaceBtn.textContent = label; } else if (!isBusy && replaceBtn.dataset.prevText){ replaceBtn.textContent = replaceBtn.dataset.prevText; delete replaceBtn.dataset.prevText; } }
                    if (deleteBtn)  { deleteBtn.disabled = !!isBusy;  if(label && isBusy){ deleteBtn.dataset.prevText = deleteBtn.textContent; deleteBtn.textContent = label; } else if (!isBusy && deleteBtn.dataset.prevText){ deleteBtn.textContent = deleteBtn.dataset.prevText; delete deleteBtn.dataset.prevText; } }
                    if (chooseBtn)  { chooseBtn.disabled = !!isBusy; }
                } catch(e){}
            }
        })();
        </script>
        <?php endif; ?>

        <div class="form-group">
            <button type="submit" class="tpw-btn tpw-btn-primary tpw-submit-btn">Save Changes</button>
        </div>
    </form>
</div>

<?php if ( $can_show_create_wp_user ) : ?>
<!-- Hidden detached form for Create WP User action -->
<form id="<?php echo esc_attr( $create_wp_form_id ); ?>" method="post" action="<?php echo esc_url( $create_wp_admin_post ); ?>" style="display:none;">
        <input type="hidden" name="action" value="tpw_create_wp_user">
        <input type="hidden" name="member_id" value="<?php echo (int) $member_id; ?>">
        <?php echo wp_nonce_field( 'tpw_create_wp_user', '_wpnonce', true, false ); ?>
        <!-- send_credentials checkbox is rendered near Email field and associated via form="..." -->
        <input type="submit" value="submit">
        <!-- Note: submit input ensures form.submit() is safe even if native submit is overridden -->
        <!-- The form remains hidden; submission is triggered by the button below via JS. -->
</form>
<script>
(function(){
    document.addEventListener('DOMContentLoaded', function(){
        var btn = document.getElementById(<?php echo json_encode( $create_wp_btn_id ); ?>);
        var form = document.getElementById(<?php echo json_encode( $create_wp_form_id ); ?>);
        if (!btn || !form) return;
        btn.addEventListener('click', function(e){
            e.preventDefault();
            try { btn.disabled = true; btn.dataset.prevText = btn.textContent; btn.textContent = 'Working…'; } catch(_){}
            // Prefer native submit if available
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });
})();
</script>
<?php endif; ?>

<!-- Postcode lookup script is enqueued and initialized via members-admin enqueue hook -->