<?php

$fields = TPW_Member_Field_Loader::get_all_enabled_fields();
$settings = get_option( 'flexievent_settings', [] );
$date_format = tpw_core_get_date_format();
$time_format = tpw_core_get_time_format();
$default_status = get_option('tpw_default_member_status', '');
usort($fields, function($a, $b) {
    return ($a['key'] === 'username') ? -1 : (($b['key'] === 'username') ? 1 : 0);
});

$excluded_keys = [ 'user_pass', 'password', 'password_hash' ];
?>

<div class="tpw-member-form">
    <h2>Add New Member</h2>
    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('tpw_add_member_action', 'tpw_add_member_nonce'); ?>

        <?php foreach ( $fields as $field ): ?>
            <?php
                if ( isset($field['is_enabled']) && $field['is_enabled'] == 0 ) continue;
                if ( in_array( $field['key'], $excluded_keys, true ) ) continue;
            ?>
            <div class="form-group">
                <label for="<?php echo esc_attr($field['key']); ?>">
                    <?php echo esc_html($field['key'] === 'status' ? 'Member Status' : $field['label']); ?>
                </label>

                <?php
                // Force known tinyint fields to checkbox if type is not explicitly set
                $tinyint_flags = ['is_committee', 'is_match_manager', 'is_admin', 'is_noticeboard_admin'];
                if (in_array($field['key'], $tinyint_flags, true)) {
                    $field['type'] = 'checkbox';
                }

                // Force 'status' field to render as a <select>
                if ( $field['key'] === 'status' ) {
                    $field['type'] = 'select';
                }

                switch ( $field['type'] ) {
                    case 'textarea':
                        echo '<textarea name="' . esc_attr($field['key']) . '" id="' . esc_attr($field['key']) . '"></textarea>';
                        echo '<div class="form-error" aria-live="polite"></div>';
                        break;

                    case 'select':
                        echo '<select name="' . esc_attr($field['key']) . '" id="' . esc_attr($field['key']) . '">';
                        echo '<option value="">-- Select --</option>';

                        if ( $field['key'] === 'status' ) {
                            $status_options = [
                                'Active'     => 'Active',
                                'Inactive'   => 'Inactive',
                                'Deceased'   => 'Deceased',
                                'Honorary'   => 'Honorary',
                                'Resigned'   => 'Resigned',
                                'Suspended'  => 'Suspended',
                                'Pending'    => 'Pending',
                                'Life Member'=> 'Life Member',
                            ];
                            foreach ( $status_options as $value => $label ) {
                                $selected = ($field['key'] === 'status' && $value === $default_status) ? ' selected' : '';
                                echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
                            }
                        }

                        echo '</select>';
                        echo '<div class="form-error" aria-live="polite"></div>';
                        break;

                    case 'checkbox':
                        echo '<input type="checkbox" name="' . esc_attr($field['key']) . '" id="' . esc_attr($field['key']) . '" value="1">';
                        echo '<div class="form-error" aria-live="polite"></div>';
                        break;

                    case 'date':
                        $placeholder = tpw_core_date_placeholder( $date_format );
                        echo '<input type="text" class="tpw-date" name="' . esc_attr($field['key']) . '" id="' . esc_attr($field['key']) . '" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off">';
                        echo '<div class="description" style="margin-top:4px;">' . esc_html( tpw_core_human_date_hint( $date_format ) ) . '</div>';
                        echo '<div class="form-error" aria-live="polite"></div>';
                        break;

                    default:
                        if ( $field['key'] === 'whi_updated' && ( method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active() ) ) {
                            echo '<input type="hidden" name="whi_updated" id="whi_updated" value="">';
                            echo '<div class="description">Last updated: —</div>';
                        } else {
                            if ( $field['key'] === 'postcode' ) {
                                echo '<div class="tpw-inline-input-action">';
                                echo '<input type="text" name="postcode" id="postcode">';
                                echo '<button type="button" id="tpw-postcode-lookup-btn" class="tpw-btn tpw-btn-secondary tpw-postcode-btn" aria-label="Lookup postcode">Lookup</button>';
                                echo '</div>';
                                echo '<div class="tpw-postcode-select-wrap" style="display:none;margin-top:6px;">';
                                echo '<select class="tpw-postcode-select" aria-label="Select address"></select>';
                                echo '</div>';
                                echo '<div class="tpw-postcode-message" style="display:none;margin-top:6px;color:#666;"></div>';
                            } else {
                                echo '<input type="text" name="' . esc_attr($field['key']) . '" id="' . esc_attr($field['key']) . '">';
                            }
                        }
                        echo '<div class="form-error" aria-live="polite"></div>';
                }
                ?>
            </div>
        <?php endforeach; ?>

        <?php if ( get_option('tpw_members_use_photos', '0') === '1' ) : ?>
        <div class="form-group">
            <label for="tpw-member-photo-input"><strong>Upload Member Photo</strong></label>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <input type="file" name="member_photo_file" id="tpw-member-photo-input" accept=".jpg,.jpeg,.png" style="display:none;">
                <button type="button" class="tpw-btn tpw-btn-secondary" id="tpw-member-photo-replace">Replace</button>
                <button type="button" class="tpw-btn tpw-btn-danger" id="tpw-member-photo-delete-btn">Delete</button>
                <span id="tpw-member-photo-filename" style="color:#555;">No file chosen</span>
            </div>
            <p class="description" style="margin-top:6px;">Recommended size: 300x300px. Max file size: 2MB.</p>
            <div id="tpw-member-photo-error" style="display:none; font-size:12px; color:#b45309; background:#fff7ed; border:1px solid #fed7aa; padding:6px 8px; border-radius:4px; margin-top:6px;"></div>
        </div>
        <script>
        (function(){
            var replaceBtn = document.getElementById('tpw-member-photo-replace');
            var deleteBtn = document.getElementById('tpw-member-photo-delete-btn');
            var fileInput = document.getElementById('tpw-member-photo-input');
            var fileNameEl = document.getElementById('tpw-member-photo-filename');
            var errorBox = document.getElementById('tpw-member-photo-error');
            var form = (fileInput ? fileInput.closest('form') : null);
            if (replaceBtn && fileInput) {
                replaceBtn.addEventListener('click', function(){ fileInput.click(); });
            }
            if (deleteBtn && fileInput) {
                deleteBtn.addEventListener('click', function(){
                    fileInput.value = '';
                    if (fileNameEl) fileNameEl.textContent = 'No file chosen';
                    hidePhotoError();
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
                    // Size check and attempt auto-compress if > 2MB
                    if (fileInput.files && fileInput.files[0]) {
                        const file = fileInput.files[0];
                        const TWO_MB = 2 * 1024 * 1024;
                        if (file.size > TWO_MB) {
                            showPhotoError('Image exceeds 2MB (' + formatMB(file.size) + '). Attempting to compress…');
                            compressImage(file, TWO_MB).then(function(newFile){
                                if (newFile && newFile.size < file.size) {
                                    const dt = new DataTransfer();
                                    dt.items.add(newFile);
                                    fileInput.files = dt.files;
                                    if (fileNameEl) fileNameEl.textContent = newFile.name + ' (compressed)';
                                    hidePhotoError();
                                } else {
                                    showPhotoError('Image exceeds 2MB and could not be compressed automatically. Please choose a smaller image.');
                                }
                            }).catch(function(){
                                showPhotoError('Image exceeds 2MB and could not be compressed automatically. Please choose a smaller image.');
                            });
                        } else {
                            hidePhotoError();
                        }
                    }
                });
            }
            function formatMB(bytes){ return (bytes/1024/1024).toFixed(2) + 'MB'; }
            function showPhotoError(msg){ if(!errorBox) return; errorBox.textContent = msg; errorBox.style.display='block'; }
            function hidePhotoError(){ if(!errorBox) return; errorBox.textContent = ''; errorBox.style.display='none'; }
            async function compressImage(file, maxBytes){
                try {
                    const img = await loadImageBitmap(file);
                    const maxDim = 500; // align with server-side resize
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
                    const last = await canvasToBlob(canvas, 'image/jpeg', 0.5);
                    if (last) return new File([last], renameToJpg(file.name), { type: 'image/jpeg' });
                } catch(e) { /* ignore */ }
                return null;
            }
            function renameToJpg(name){ const base = name.replace(/\.[^.]+$/, ''); return base + '-compressed.jpg'; }
            function scaleToFit(w, h, maxW, maxH){ let r = Math.min(maxW/w, maxH/h, 1); return { w: Math.round(w*r), h: Math.round(h*r) }; }
            function loadImageBitmap(file){
                if ('createImageBitmap' in window) { return createImageBitmap(file); }
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
        })();
        </script>
        <?php endif; ?>

    <?php
    // Allow other plugins to extend the Add Member form with extra fields.
    // Signature: ( string $context, int|null $member_id, object|null $member, array $meta )
    // For the add context there is no member yet, so pass nulls and an empty meta array.
    do_action( 'tpw_members_admin_form_extra_fields', 'add', null, null, [] );
    ?>

        <div class="form-group">
            <button type="submit" class="tpw-btn tpw-btn-primary tpw-submit-btn">Add Member</button>
        </div>
    </form>
</div>

<!-- Postcode lookup script is enqueued and initialized via members-admin enqueue hook -->