<?php
/**
 * Upload Pages section template
 * Built-in UI: manage pages and files with visibility settings.
 */

if ( ! class_exists( 'TPW_Control_Upload_Pages' ) ) return;

// Ensure tables exist (first render of section)
TPW_Control_Upload_Pages::ensure_tables();

$sub = isset($_GET['sub']) ? sanitize_key($_GET['sub']) : '';
$page_id = isset($_GET['upload_page_id']) ? (int) $_GET['upload_page_id'] : 0;
$editing = $sub === 'edit' && $page_id > 0;
$page = $editing ? TPW_Control_Upload_Pages::get_page_by_id( $page_id ) : null;
$files = $editing ? TPW_Control_Upload_Pages::get_files( $page_id ) : [];

function tpw_upl_vis_value( $page, $key ) {
    $vis = $page ? json_decode( (string)$page->visibility, true ) : [];
    if ( ! is_array( $vis ) ) $vis = [];
    if ( $key === 'status' ) return $vis['status'] ?? [];
    return ! empty( $vis[$key] );
}

// Simple styles inline to complement module CSS
echo '<style>
.tpw-upl-wrap{max-width:980px}
.tpw-upl-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.tpw-upl-files table{width:100%;border-collapse:collapse}
.tpw-upl-files th,.tpw-upl-files td{border-bottom:1px solid #ddd;padding:8px}
.tpw-upl-actions{display:flex;gap:8px;flex-wrap:wrap}
.tpw-upl-year{width:90px}
#tpw-upl-create-modal:target{display:block}
#tpw-upl-vis-modal:target{display:block}
#tpw-upl-addfile-modal:target{display:block}
#tpw-upl-help-modal:target{display:block}
.tpw-section{border:1px solid #ddd;border-radius:6px;padding:16px;margin:16px 0;background:#fff}
.tpw-section legend{font-weight:600;padding:0 8px}
.tpw-section .tpw-fieldset{margin-bottom:8px}
.tpw-text-muted{color:#666}
</style>';
?>

<div class="tpw-upl-wrap tpw-admin-ui">
    <?php if ( isset($_GET['open']) && $_GET['open'] === 'add_file' ) : ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var btn = document.querySelector('[data-tpw-open="#tpw-upl-addfile-modal"]');
            if (btn) { btn.click(); }
        });
        </script>
    <?php endif; ?>
    <div class="tpw-upl-head">
        <h2>Upload Pages</h2>
        <div>
            <?php if ( $editing ): ?>
                <a class="tpw-btn tpw-btn-secondary" href="<?php echo esc_url( TPW_Control_UI::menu_url('upload-pages') ); ?>">Back to list</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( $editing && $page ): ?>
        <form method="post" class="tpw-form" id="tpw-upl-form-page">
            <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
            <input type="hidden" name="tpw_control_upload_pages_action" value="update_page" />
            <input type="hidden" name="upload_page_id" value="<?php echo (int)$page->id; ?>" />
            <input type="hidden" name="_tpw_control_page_url" value="<?php echo esc_url( TPW_Control_UI::menu_url('upload-pages') ); ?>" />


            <fieldset class="tpw-section">
                <legend>Page Details</legend>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:32px;flex-wrap:wrap">
                    <div style="flex:1 1 320px;min-width:220px">
                        <div class="tpw-fieldset">
                            <label>Title<br />
                                <input type="text" name="title" value="<?php echo esc_attr( $page->title ); ?>" required />
                            </label>
                        </div>
                        <div class="tpw-fieldset">
                            <label>Slug<br />
                                <input type="text" name="slug" value="<?php echo esc_attr( $page->slug ); ?>" />
                            </label>
                        </div>
                        <?php
                        // Linked WP Page status and actions
                        $wp_page_id = isset($page->wp_page_id) ? (int)$page->wp_page_id : 0;
                        $wp_page = $wp_page_id ? get_post( $wp_page_id ) : null;
                        $wp_page_exists = $wp_page && $wp_page->post_type === 'page' && $wp_page->post_status !== 'trash';
                        echo '<div class="tpw-fieldset" style="margin-top:8px;padding:10px;border:1px solid #eee;border-radius:6px;background:#fafafa">';
                        if ( $wp_page_exists ) {
                            $plink = get_permalink( $wp_page_id );
                            $edit_link = get_edit_post_link( $wp_page_id, '' );
                            echo '<div>Linked WordPress Page: <a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noopener">#' . (int)$wp_page_id . '</a> · <a href="' . esc_url( $plink ) . '" target="_blank" rel="noopener">View</a> · <a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noopener">Edit</a></div>';
                            echo '<div class="tpw-text-muted">Title and shortcode are kept in sync automatically when you save.</div>';
                        } else {
                            // Recover Link UI
                            echo '<div style="font-weight:600;margin-bottom:6px">This Upload Page is not linked to a WordPress Page.</div>';
                            echo '<div class="tpw-text-muted" style="margin-bottom:8px">Create a new linked page or link to an existing one that contains the matching shortcode.</div>';

                            // Create New Linked Page button
                            echo '<form method="post" style="display:inline-block;margin-right:6px">';
                            wp_nonce_field( 'tpw_control_upload_pages' );
                            echo '<input type="hidden" name="tpw_control_upload_pages_action" value="create_linked_wp_page" />';
                            echo '<input type="hidden" name="upload_page_id" value="' . (int)$page->id . '" />';
                            echo '<button class="tpw-btn tpw-btn-primary" type="submit">Create New Linked Page</button>';
                            echo '</form>';

                            // Link Existing Page: dropdown of pages with matching shortcode
                            $candidates = method_exists('TPW_Control_Upload_Pages','find_wp_pages_for_slug') ? TPW_Control_Upload_Pages::find_wp_pages_for_slug( $page->slug ) : [];
                            echo '<form method="post" style="display:inline-block;margin-top:6px">';
                            wp_nonce_field( 'tpw_control_upload_pages' );
                            echo '<input type="hidden" name="tpw_control_upload_pages_action" value="link_existing_wp_page" />';
                            echo '<input type="hidden" name="upload_page_id" value="' . (int)$page->id . '" />';
                            echo '<label style="margin-right:6px">Link Existing Page</label>';
                            echo '<select name="selected_wp_page_id" style="min-width:220px">';
                            if ( empty($candidates) ) {
                                echo '<option value="">No matching published pages found</option>';
                            } else {
                                foreach ( $candidates as $row ) {
                                    echo '<option value="' . (int)$row->ID . '">#' . (int)$row->ID . ' — ' . esc_html( $row->post_title ) . '</option>';
                                }
                            }
                            echo '</select> ';
                            echo '<button class="tpw-btn tpw-btn-secondary" type="submit" ' . ( empty($candidates) ? 'disabled' : '' ) . '>Link Existing Page</button>';
                            echo '</form>';
                        }
                        echo '</div>';
                        ?>
                    </div>
                    <div style="min-width:220px;max-width:320px;flex:0 0 220px;align-self:flex-start">
                        <?php
                        // Visibility summary
                        $vis_raw = json_decode( (string) $page->visibility, true );
                        if ( ! is_array( $vis_raw ) ) $vis_raw = [];
                        $vis_bits = [];
                        foreach ( ['is_admin'=>'Admin','is_committee'=>'Committee','is_match_manager'=>'Match Managers','is_noticeboard_admin'=>'Noticeboard Admins'] as $k => $label ) {
                            if ( ! empty( $vis_raw[$k] ) ) $vis_bits[] = $label;
                        }
                        if ( ! empty( $vis_raw['status'] ) && is_array( $vis_raw['status'] ) ) {
                            $vis_bits[] = 'Status(' . implode(', ', $vis_raw['status'] ) . ')';
                        }
                        if ( empty( $vis_bits ) ) $vis_bits[] = 'Admin';
                        ?>
                        <div style="background:#f8f8f8;border:1px solid #eee;border-radius:6px;padding:12px 16px 10px 16px;margin-bottom:8px;">
                            <div style="font-weight:600">Visibility</div>
                            <div class="tpw-text-muted" style="margin-bottom:8px">Visible to: <?php echo esc_html( implode(' • ', $vis_bits ) ); ?></div>
                            <a href="#tpw-upl-vis-modal" class="tpw-btn tpw-btn-secondary" role="button" data-tpw-open="#tpw-upl-vis-modal" style="width:100%">Edit Visibility</a>
                        </div>

                        <?php $layout = isset($page->layout) ? $page->layout : 'table'; ?>
                        <div style="background:#f8f8f8;border:1px solid #eee;border-radius:6px;padding:12px 16px 10px 16px;margin-bottom:8px;">
                            <div style="font-weight:600">Layout Style</div>
                            <label for="tpw-upl-layout" class="screen-reader-text">Layout Style</label>
                            <select id="tpw-upl-layout" name="layout" style="width:100%;margin-top:6px">
                                <option value="table" <?php selected($layout,'table'); ?>>Table (default)</option>
                                <option value="list" <?php selected($layout,'list'); ?>>Bullet List</option>
                                <option value="cards" <?php selected($layout,'cards'); ?>>Card View</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="tpw-fieldset" style="margin-top:12px">
                    <label style="display:block;margin-bottom:6px">Description</label>
                    <?php
                    $editor_id = 'tpw_upl_desc_' . (int) $page->id;
                    $editor_settings = [
                        'textarea_name'   => 'description',
                        'textarea_rows'   => 12,
                        'media_buttons'   => true,
                        'drag_drop_upload'=> true,
                        'teeny'           => false,
                        // Add our UI class so toolbar/buttons inherit TPW styles instead of the theme
                        'editor_class'    => 'tpw-admin-ui',
                        'quicktags'       => true,
                        'tinymce'         => [
                            'toolbar1'          => 'formatselect bold italic underline bullist numlist alignleft aligncenter alignright link unlink removeformat',
                            'toolbar2'          => 'fontsizeselect forecolor backcolor blockquote',
                            'fontsize_formats'  => '12px 14px 16px 18px 24px 32px',
                            'branding'          => false,
                        ],
                    ];
                    // Render TinyMCE editor for description
                    if ( function_exists( 'wp_editor' ) ) {
                        wp_editor( (string) $page->description, $editor_id, $editor_settings );
                        // Hint the media modal/uploader to tag uploads from this screen
                        echo '<script>(function(){ try { window.tpwUplEditor = true; } catch(e){} })();</script>';
                    } else {
                        echo '<textarea name="description" rows="10" style="width:100%">' . esc_textarea( (string) $page->description ) . '</textarea>';
                    }
                    ?>
                    <p class="description">Use headings, bold text, lists, and sizing to format the page text members will see.</p>
                </div>

                <!-- Visibility Modal (fields are within the main form so Save Page will persist) -->
                <div id="tpw-upl-vis-modal" class="tpw-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;">
                    <div class="tpw-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-vis-title" style="background:#fff;max-width:560px;margin:10vh auto;padding:16px;border-radius:6px;">
                        <div class="tpw-modal__header" style="display:flex;justify-content:space-between;align-items:center;">
                            <h3 id="tpw-upl-vis-title" style="margin:0">Edit Visibility</h3>
                            <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-vis-modal">Close</a>
                        </div>
                        <div class="tpw-fieldset" style="margin-top:12px">
                            <label><input type="checkbox" name="visibility_is_admin" <?php checked( tpw_upl_vis_value($page,'is_admin') ); ?> /> Admins</label>
                            <label style="margin-left:8px"><input type="checkbox" name="visibility_is_committee" <?php checked( tpw_upl_vis_value($page,'is_committee') ); ?> /> Committee</label>
                            <label style="margin-left:8px"><input type="checkbox" name="visibility_is_match_manager" <?php checked( tpw_upl_vis_value($page,'is_match_manager') ); ?> /> Match Managers</label>
                            <label style="margin-left:8px"><input type="checkbox" name="visibility_is_noticeboard_admin" <?php checked( tpw_upl_vis_value($page,'is_noticeboard_admin') ); ?> /> Noticeboard Admins</label>
                        </div>
                        <div class="tpw-fieldset" style="margin-top:8px">
                            <label>Status access</label>
                            <?php
                            $statuses = apply_filters( 'tpw_members/known_statuses', [ 'Active','Honorary','Life Member','Junior','Student' ] );
                            $sel = tpw_upl_vis_value( $page, 'status' );
                            echo '<div class="tpw-status-checkboxes" role="group" aria-label="Status access" style="border:1px solid #eee;padding:8px;border-radius:4px;max-height:180px;overflow:auto">';
                            foreach ( $statuses as $s ) {
                                $checked = checked( in_array( $s, (array) $sel, true ), true, false );
                                echo '<label style="display:block;margin:2px 0"><input type="checkbox" name="visibility_status[]" value="' . esc_attr( $s ) . '" ' . $checked . ' /> ' . esc_html( $s ) . '</label>';
                            }
                            echo '</div>';
                            ?>
                            <p class="description">Leave empty for none. Admins always have access.</p>
                        </div>
                        <div class="tpw-upl-actions" style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <button class="tpw-btn tpw-btn-primary" type="submit" name="tpw_control_upload_pages_action" value="update_page">Save Visibility</button>
                            <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-vis-modal">Cancel</a>
                            <span class="tpw-text-muted" style="margin-left:6px">Saving also updates Page Details.</span>
                        </div>
                    </div>
                </div>

                <div class="tpw-upl-actions" style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap">
                    <button class="tpw-btn tpw-btn-primary" type="submit">Save Page Details</button>
                    <?php $has_files = ! empty( $files ); ?>
                    <button class="tpw-btn <?php echo $has_files ? 'tpw-btn-light' : 'tpw-btn-danger'; ?>" type="submit" name="tpw_control_upload_pages_action" value="delete_page" <?php echo $has_files ? 'disabled aria-disabled="true" title="No files must be attached before deleting this page."' : 'onclick="return confirm(\'Delete this page?\');"'; ?>>Delete Page</button>
                </div>
            </fieldset>
        </form>

        <?php $categories = TPW_Control_Upload_Pages::get_categories( (int)$page->id ); ?>
        <fieldset class="tpw-section tpw-upl-files">
            <legend>Files</legend>
            <div class="tpw-upl-actions" style="margin-bottom:8px">
                <a href="#tpw-upl-addfile-modal" class="tpw-btn tpw-btn-primary" role="button" data-tpw-open="#tpw-upl-addfile-modal">Add File</a>
                <a href="#tpw-upl-import-modal" class="tpw-btn tpw-btn-secondary" role="button" data-tpw-open="#tpw-upl-import-modal">Bulk Import (CSV + ZIP)</a>
            </div>
            <?php
            // Show import report if available
            $report = null; $current_user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
            if ( $current_user_id > 0 ) {
                $tkey = 'tpw_upl_import_report_' . $current_user_id . '_' . (int) $page->id;
                $rep = get_transient( $tkey );
                if ( is_array( $rep ) ) { $report = $rep; delete_transient( $tkey ); }
            }
            if ( $report ) {
                echo '<div class="notice" style="border:1px solid #ddd;background:#f7fbff;padding:10px;border-radius:6px;margin-bottom:10px">';
                echo '<div style="font-weight:600;margin-bottom:6px">Bulk Import Report</div>';
                echo '<div>Imported: ' . (int) ($report['success_count'] ?? 0) . ' file(s)';
                if ( ! empty( $report['created_categories'] ) ) echo ' · Categories created: ' . (int) $report['created_categories'];
                echo '</div>';
                if ( ! empty( $report['warnings'] ) ) { echo '<ul style="margin:6px 0 0 18px; list-style:disc; color:#7a5;">'; foreach ( (array)$report['warnings'] as $w ) echo '<li>' . esc_html( (string) $w ) . '</li>'; echo '</ul>'; }
                if ( ! empty( $report['errors'] ) ) { echo '<ul style="margin:6px 0 0 18px; list-style:disc; color:#b00020;">'; foreach ( (array)$report['errors'] as $e ) echo '<li>' . esc_html( (string) $e ) . '</li>'; echo '</ul>'; }
                echo '</div>';
            }
            ?>
            <form method="post" id="tpw-upl-form-files">
                <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                <input type="hidden" name="tpw_control_upload_pages_action" value="update_files" />
                <input type="hidden" name="upload_page_id" value="<?php echo (int)$page->id; ?>" />
                <div class="tpw-upl-actions" style="margin:6px 0; gap:8px; align-items:center; flex-wrap:wrap;">
                    <select name="bulk_action" style="min-width:160px">
                        <option value="">Bulk actions…</option>
                        <option value="assign">Assign to Page…</option>
                        <option value="unlink">Delete (move to Trash)</option>
                        <!-- Permanent delete is available in the Trash panel only -->
                    </select>
                    <select name="target_page_id" style="min-width:220px">
                        <option value="">— Select target page —</option>
                        <?php foreach ( (array) TPW_Control_Upload_Pages::get_pages() as $pg ) echo '<option value="' . (int)$pg->id . '">' . esc_html( $pg->title ) . '</option>'; ?>
                    </select>
                    <input type="number" name="assign_year" placeholder="Year override (optional)" style="width:160px" />
                    <select name="assign_category_mode" title="Category handling on assign">
                        <option value="inherit">Inherit category</option>
                        <option value="none">No category</option>
                        <option value="general">General on target</option>
                    </select>
                    <button class="tpw-btn tpw-btn-light" type="submit" formaction="" onclick="this.form.tpw_control_upload_pages_action.value='bulk_files'">Apply</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:24px"><input type="checkbox" onclick="document.querySelectorAll('#tpw-upl-files-tbody input[type=checkbox]').forEach(cb=>cb.checked=this.checked)" /></th>
                            <th style="width:30px"></th>
                            <th style="width:90px">Preview</th>
                            <th>Label</th>
                            <th style="width:160px">Category</th>
                            <th style="width:110px">Year</th>
                            <th style="width:100px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tpw-upl-files-tbody">
                        <?php foreach ( $files as $idx => $f ): 
                            $url = method_exists('TPW_Control_Upload_Pages','build_served_url') ? TPW_Control_Upload_Pages::build_served_url( (int)$f->id, 'file', 900, false ) : $f->file_url;
                            $thumb = $f->thumbnail_url;
                            $file_type = $f->file_type;
                            $label = $f->label !== '' ? $f->label : basename( parse_url($f->file_url, PHP_URL_PATH) );
                            // Determine icon fallback
                            $icon = '';
                            $ext = strtolower( pathinfo( parse_url($f->file_url, PHP_URL_PATH), PATHINFO_EXTENSION ) );
                            $img_base = defined('TPW_CORE_URL') ? TPW_CORE_URL . 'assets/images/' : '';
                            if ( ! $thumb ) {
                                if ( in_array( $ext, ['jpg','jpeg','png'], true ) ) {
                                    $icon = $img_base . 'image-regular-full.svg';
                                } elseif ( $ext === 'pdf' ) {
                                    $icon = $img_base . 'file-pdf-regular-full.svg';
                                } elseif ( in_array( $ext, ['doc','docx'], true ) ) {
                                    $icon = $img_base . 'file-word-regular-full.svg';
                                } elseif ( in_array( $ext, ['xls','xlsx'], true ) ) {
                                    $icon = $img_base . 'file-excel-regular-full.svg';
                                } elseif ( $ext === 'mp4' ) {
                                    $icon = $img_base . 'file-video-regular-full.svg';
                                } else {
                                    $icon = $img_base . 'file-regular-full.svg';
                                }
                            }
                            ?>
                            <tr data-file-id="<?php echo (int)$f->id; ?>">
                                <td><input type="checkbox" name="selected_links[]" value="<?php echo (int)$f->id; ?>" /></td>
                                <td class="tpw-upl-handle" title="Drag to reorder" style="cursor:move">&#9776;</td>
                                <td>
                                    <?php if ( TPW_Control_UI::user_has_access( [ 'logged_in' => true, 'flags_any' => ['is_committee','is_admin'] ] ) ): ?>
                                        <a href="<?php echo esc_url( $url ); ?>" class="tpw-upl-preview" data-index="<?php echo (int)$idx; ?>" data-type="<?php echo esc_attr( $file_type ); ?>" data-label="<?php echo esc_attr( $label ); ?>">
                                            <?php if ( $thumb ): ?>
                                                <?php $thumb_served = method_exists('TPW_Control_Upload_Pages','build_served_url') ? TPW_Control_Upload_Pages::build_served_url( (int)$f->id, 'thumb', 900, false ) : $thumb; ?>
                                                <img src="<?php echo esc_url( $thumb_served ); ?>" alt="" style="width:64px;height:auto;border:1px solid #eee;border-radius:4px" />
                                            <?php else: ?>
                                                    <img src="<?php echo esc_url( $icon ); ?>" alt="" style="width:48px;height:48px" />
                                            <?php endif; ?>
                                        </a>
                                    <?php else: ?>
                                        <?php if ( $thumb ): ?>
                                            <?php $thumb_served = method_exists('TPW_Control_Upload_Pages','build_served_url') ? TPW_Control_Upload_Pages::build_served_url( (int)$f->id, 'thumb', 900, false ) : $thumb; ?>
                                            <img src="<?php echo esc_url( $thumb_served ); ?>" alt="" style="width:64px;height:auto;border:1px solid #eee;border-radius:4px" />
                                        <?php else: ?>
                                                <img src="<?php echo esc_url( $icon ); ?>" alt="" style="width:48px;height:48px" />
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="text" name="file_label[<?php echo (int)$f->id; ?>]" value="<?php echo esc_attr( $f->label ); ?>" />
                                </td>
                                <td>
                                    <select name="file_category[<?php echo (int)$f->id; ?>]">
                                        <option value="">— None —</option>
                                        <?php foreach ( (array)$categories as $cat ): $sel = isset($f->category_id) && (int)$f->category_id === (int)$cat->category_id ? 'selected' : ''; ?>
                                            <option value="<?php echo (int)$cat->category_id; ?>" <?php echo $sel; ?>><?php echo esc_html( $cat->category_name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input class="tpw-upl-year" type="number" name="file_year[<?php echo (int)$f->id; ?>]" value="<?php echo esc_attr( $f->year ); ?>" />
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;align-items:center;justify-content:flex-start">
                                        <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="tpw-btn tpw-btn-light" style="padding:2px 8px;font-size:12px">Open</a>
                                        <button type="button" class="tpw-btn tpw-btn-danger tpw-upl-file-delete" data-file-id="<?php echo (int)$f->id; ?>" data-page-id="<?php echo (int)$page->id; ?>" style="padding:2px 8px;font-size:12px">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="tpw-upl-actions" style="margin-top:8px">
                    <button class="tpw-btn tpw-btn-secondary" type="submit">Save file changes</button>
                </div>
            </form>

            <?php
            // Trash (soft-deleted links)
            global $wpdb; $links_table = $wpdb->prefix . 'tpw_upload_pages_files'; $files_table = $wpdb->prefix . 'tpw_files';
            $trashed = $wpdb->get_results( $wpdb->prepare( "SELECT l.*, f.file_type, f.file_url, f.thumbnail_url FROM {$links_table} l JOIN {$files_table} f ON f.file_id=l.file_id WHERE l.page_id=%d AND l.is_deleted=1 ORDER BY l.deleted_at DESC", (int)$page->id ) );
            if ( ! empty( $trashed ) ): ?>
            <div id="tpw-upl-trash-section" class="tpw-section" style="margin-top:16px">
                <h4 style="margin:0 0 8px">Trash</h4>
                <form method="post">
                    <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                    <input type="hidden" name="tpw_control_upload_pages_action" value="bulk_files" />
                    <input type="hidden" name="upload_page_id" value="<?php echo (int)$page->id; ?>" />
                    <div class="tpw-upl-actions" style="margin:6px 0; gap:8px; align-items:center; flex-wrap:wrap;">
                        <select name="bulk_action" style="min-width:160px">
                            <option value="">Bulk actions…</option>
                            <option value="restore">Restore</option>
                            <option value="delete_permanent">Delete permanently</option>
                        </select>
                        <button class="tpw-btn tpw-btn-light" type="submit">Apply</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th style="width:24px">
                                    <input type="checkbox" title="Select all" aria-label="Select all in Trash" onclick="(function(hd){var tbl=hd.closest('table');if(tbl){tbl.querySelectorAll('tbody input[type=checkbox]').forEach(function(cb){cb.checked=hd.checked;});}})(this);" />
                                </th>
                                <th>Label</th>
                                <th style="width:120px">Deleted</th>
                                <th style="width:120px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $trashed as $t ): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_links[]" value="<?php echo (int)$t->id; ?>" /></td>
                                    <td><?php echo esc_html( $t->label ); ?></td>
                                    <td><?php echo esc_html( $t->deleted_at ); ?></td>
                                    <td>
                                        <button class="tpw-btn tpw-btn-secondary" name="bulk_action" value="restore" type="submit" onclick="(function(btn){var row=btn.closest('tr');var table=btn.closest('table');if(table){table.querySelectorAll('tbody input[type=checkbox]').forEach(function(cb){cb.checked=false;});}var cb=row?row.querySelector('input[type=checkbox]'):null;if(cb){cb.checked=true;}})(this);">Restore</button>
                                        <button class="tpw-btn tpw-btn-danger" name="bulk_action" value="delete_permanent" type="submit" style="margin-top:6px" onclick="(function(btn){var row=btn.closest('tr');var table=btn.closest('table');if(table){table.querySelectorAll('tbody input[type=checkbox]').forEach(function(cb){cb.checked=false;});}var cb=row?row.querySelector('input[type=checkbox]'):null;if(cb){cb.checked=true;}})(this); return confirm('Delete permanently? This cannot be undone.');">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>
            <?php else: ?>
            <div id="tpw-upl-trash-section"></div>
            <?php endif; ?>
        </fieldset>
        
        <!-- Add File Modal -->
        <div id="tpw-upl-addfile-modal" class="tpw-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;">
            <div class="tpw-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-addfile-title" style="background:#fff;max-width:560px;margin:10vh auto;padding:16px;border-radius:6px;">
                <div class="tpw-modal__header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 id="tpw-upl-addfile-title" style="margin:0">Add File</h3>
                    <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-addfile-modal">Close</a>
                </div>
                <form method="post" enctype="multipart/form-data" id="tpw-upl-form-add" style="margin-top:12px">
                    <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                    <input type="hidden" name="tpw_control_upload_pages_action" value="add_files" />
                    <input type="hidden" name="tpw_ajax" value="1" />
                    <input type="hidden" name="upload_page_id" value="<?php echo (int)$page->id; ?>" />
                    <div class="tpw-fieldset"><input type="file" name="upload_files[]" multiple /></div>
                    <div class="tpw-fieldset"><input class="tpw-upl-year" type="number" name="upload_year" placeholder="Year" /></div>
                    <div class="tpw-fieldset"><label>Category
                        <select name="upload_category_id" class="tpw-upl-cat-select">
                            <option value="">— None —</option>
                            <?php foreach ( (array)$categories as $cat ): ?>
                                <option value="<?php echo (int)$cat->category_id; ?>"><?php echo esc_html( $cat->category_name ); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <button type="button" class="tpw-btn tpw-btn-light" style="margin-left:8px" data-tpw-open="#tpw-upl-addcat-modal">Add Category</button>
                    </div>
                    <div class="tpw-fieldset"><input type="text" name="upload_label" placeholder="Label (optional, defaults to filename)" style="min-width:240px" /></div>
                    <div id="tpw-upl-upload-feedback" style="display:none;margin-top:8px">
                        <div class="tpw-progress" style="margin-bottom:6px">
                            <progress id="tpw-upl-progress" max="100" value="0" style="width:100%">0%</progress>
                            <div class="tpw-text-muted"><span id="tpw-upl-progress-text">Uploading… 0%</span></div>
                        </div>
                        <div id="tpw-upl-upload-error" style="color:#b00020;display:none"></div>
                    </div>
                    <div class="tpw-upl-actions" style="margin-top:8px">
                        <button class="tpw-btn tpw-btn-primary" type="submit">Upload</button>
                        <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-addfile-modal">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Import Modal -->
        <div id="tpw-upl-import-modal" class="tpw-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;">
            <div class="tpw-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-import-title" style="background:#fff;max-width:620px;margin:10vh auto;padding:16px;border-radius:6px;">
                <div class="tpw-modal__header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 id="tpw-upl-import-title" style="margin:0">Bulk Import (CSV + ZIP)</h3>
                    <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-import-modal">Close</a>
                </div>
                <div class="tpw-modal__body" style="margin-top:10px">
                    <p class="description">Upload a ZIP archive containing your documents and a CSV manifest named <code>manifest.csv</code>.</p>
                    <ul style="margin:6px 0 10px 18px; list-style:disc;">
                        <li>manifest.csv columns (case-insensitive): <code>filename</code>, <code>label</code>, <code>year</code>, <code>category</code></li>
                        <li>Files in the ZIP must match the <code>filename</code> values. Unreferenced files are reported but ignored.</li>
                        <li>New categories in the CSV are created automatically for this Upload Page.</li>
                        <li>Max file size per file respects your Upload Pages setting.</li>
                    </ul>
                    <?php
                        $tpl_url = add_query_arg(
                            [ 'action' => 'tpw_control_download_import_template', '_wpnonce' => wp_create_nonce( 'tpw_control_upload_pages' ) ],
                            admin_url( 'admin-ajax.php' )
                        );
                        echo '<p><a class="tpw-btn tpw-btn-light" href="' . esc_url( $tpl_url ) . '" target="_blank" rel="noopener">Download Template CSV</a></p>';
                    ?>
                </div>
                <form method="post" enctype="multipart/form-data" style="margin-top:8px">
                    <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                    <input type="hidden" name="tpw_control_upload_pages_action" value="bulk_import" />
                    <input type="hidden" name="upload_page_id" value="<?php echo (int) $page->id; ?>" />
                    <div class="tpw-fieldset"><label>ZIP file<br/><input type="file" name="bulk_zip" accept=".zip,application/zip" required /></label></div>
                    <div class="tpw-upl-actions" style="margin-top:8px">
                        <button class="tpw-btn tpw-btn-primary" type="submit">Run Import</button>
                        <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-import-modal">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Add Category Modal -->
        <div id="tpw-upl-addcat-modal" class="tpw-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10000;">
            <div class="tpw-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-addcat-title" style="background:#fff;max-width:480px;margin:12vh auto;padding:16px;border-radius:6px;">
                <div class="tpw-modal__header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 id="tpw-upl-addcat-title" style="margin:0">Add Category</h3>
                    <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-addcat-modal">Close</a>
                </div>
                <form method="post" id="tpw-upl-form-addcat" style="margin-top:12px">
                    <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                    <input type="hidden" name="tpw_control_upload_pages_action" value="add_category" />
                    <input type="hidden" name="upload_page_id" value="<?php echo (int)$page->id; ?>" />
                    <div class="tpw-fieldset">
                        <label>Category name<br/>
                            <input type="text" name="category_name" required placeholder="e.g. Policies" style="min-width:260px" />
                        </label>
                    </div>
                    <div id="tpw-upl-addcat-error" style="display:none;color:#b00020;margin-top:4px"></div>
                    <div class="tpw-upl-actions" style="margin-top:10px">
                        <button class="tpw-btn tpw-btn-primary" type="submit">Add Category</button>
                        <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-addcat-modal">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="tpw-upl-list">
            <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <a href="#tpw-upl-create-modal" class="tpw-btn tpw-btn-primary" role="button" data-tpw-open="#tpw-upl-create-modal">Create New Upload Page</a>
                <a href="#tpw-upl-help-modal" class="tpw-btn tpw-btn-light" role="button" data-tpw-open="#tpw-upl-help-modal">Help / Shortcodes</a>
            </div>
            <?php if ( isset($_GET['err']) && $_GET['err'] === 'has_files' ): ?>
                <div class="notice notice-error" style="border:1px solid #cc0000;background:#fff3f3;padding:10px;border-radius:6px;margin-bottom:10px;">
                    <p style="margin:0;">Cannot delete this Upload Page because it still has files attached. Remove all files first.</p>
                </div>
            <?php endif; ?>
            <?php
            // Pagination
            $current_pg = isset($_GET['pg']) ? max(1, (int) $_GET['pg']) : 1;
            $all_pages = TPW_Control_Upload_Pages::get_pages();
            $total = is_array($all_pages) ? count($all_pages) : 0;
            $per_page = apply_filters( 'tpw_control/upload_pages_per_page', 10 );
            $offset = ($current_pg - 1) * $per_page;
            $pages = array_slice( (array)$all_pages, $offset, $per_page );
            $total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
            if ( empty( $pages ) ):
            ?>
                <div class="tpw-empty-state">
                    <p>No Upload Pages yet.</p>
                    <p>Create your first page below to start adding files.</p>
                </div>
            <?php else: ?>
            <div class="table-container">
                <div class="table-row table-head">
                    <div class="table-cell" style="flex:2">Title</div>
                    <div class="table-cell" style="flex:1">Slug</div>
                    <div class="table-cell" style="flex:0 0 80px">Files</div>
                    <div class="table-cell" style="flex:2">Visibility</div>
                    <div class="table-cell" style="flex:0 0 180px">Actions</div>
                </div>
                <?php foreach ( $pages as $p ):
                    $file_count = count( TPW_Control_Upload_Pages::get_files( (int)$p->id ) );
                    $vis = json_decode( (string)$p->visibility, true ); if ( ! is_array($vis) ) $vis = [];
                    $vis_bits = [];
                    foreach ( ['is_admin'=>'Admin','is_committee'=>'Committee','is_match_manager'=>'Match','is_noticeboard_admin'=>'Noticeboard'] as $k=>$label ) {
                        if ( ! empty($vis[$k]) ) $vis_bits[] = $label;
                    }
                    if ( ! empty($vis['status']) && is_array($vis['status']) ) $vis_bits[] = 'Status(' . implode(', ', $vis['status']) . ')';
                    $edit_url = add_query_arg( [ 'sub' => 'edit', 'upload_page_id' => (int)$p->id ], TPW_Control_UI::menu_url('upload-pages') );
                    $can_delete = (int)$file_count === 0;
                ?>
                <div class="table-row">
                    <div class="table-cell" style="flex:2"><?php echo esc_html( $p->title ); ?></div>
                    <div class="table-cell" style="flex:1"><?php echo esc_html( $p->slug ); ?></div>
                    <div class="table-cell" style="flex:0 0 80px"><?php echo (int) $file_count; ?></div>
                    <div class="table-cell" style="flex:2"><?php echo esc_html( implode(' • ', $vis_bits ) ); ?></div>
                    <div class="table-cell" style="flex:0 0 180px; gap:6px">
                        <a class="tpw-btn tpw-btn-secondary" href="<?php echo esc_url( $edit_url ); ?>">Edit</a>
                        <form method="post" style="display:inline">
                            <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                            <input type="hidden" name="tpw_control_upload_pages_action" value="delete_page" />
                            <input type="hidden" name="upload_page_id" value="<?php echo (int)$p->id; ?>" />
                            <button type="submit" class="tpw-btn <?php echo $can_delete ? 'tpw-btn-danger' : 'tpw-btn-light'; ?>" <?php echo $can_delete ? 'onclick="return confirm(\'Delete this page?\');"' : 'disabled aria-disabled="true" title="No files must be attached before deleting this page."'; ?>>Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ( $total_pages > 1 ): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $base_url = TPW_Control_UI::menu_url('upload-pages');
                        echo '<span class="displaying-num">' . (int)$total . ' items</span>';
                        echo '<span class="pagination-links">';
                        $first_url = add_query_arg( 'pg', 1, $base_url );
                        $prev_url  = add_query_arg( 'pg', max(1, $current_pg-1), $base_url );
                        $next_url  = add_query_arg( 'pg', min($total_pages, $current_pg+1), $base_url );
                        $last_url  = add_query_arg( 'pg', $total_pages, $base_url );
                        echo '<a class="first-page button" href="' . esc_url($first_url) . '" ' . ( $current_pg===1 ? 'aria-disabled="true"' : '' ) . '>&laquo;</a>';
                        echo '<a class="prev-page button" href="' . esc_url($prev_url) . '" ' . ( $current_pg===1 ? 'aria-disabled="true"' : '' ) . '>&lsaquo;</a>';
                        echo '<span class="paging-input">' . (int)$current_pg . ' of <span class="total-pages">' . (int)$total_pages . '</span></span>';
                        echo '<a class="next-page button" href="' . esc_url($next_url) . '" ' . ( $current_pg===$total_pages ? 'aria-disabled="true"' : '' ) . '>&rsaquo;</a>';
                        echo '<a class="last-page button" href="' . esc_url($last_url) . '" ' . ( $current_pg===$total_pages ? 'aria-disabled="true"' : '' ) . '>&raquo;</a>';
                        echo '</span>';
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Create Modal -->
            <div id="tpw-upl-create-modal" class="tpw-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;">
                <div class="tpw-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-create-title" style="background:#fff;max-width:560px;margin:10vh auto;padding:16px;border-radius:6px;">
                    <div class="tpw-modal__header" style="display:flex;justify-content:space-between;align-items:center;">
                        <h3 id="tpw-upl-create-title" style="margin:0">Create new Upload Page</h3>
                        <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-create-modal">Close</a>
                    </div>
                    <form method="post" class="tpw-upl-new tpw-form" style="margin-top:12px">
                        <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                        <input type="hidden" name="tpw_control_upload_pages_action" value="create_page" />
                        <input type="hidden" name="_tpw_control_page_url" value="<?php echo esc_url( TPW_Control_UI::menu_url('upload-pages') ); ?>" />
                        <div class="tpw-fieldset"><label>Title<br/><input type="text" name="title" required /></label></div>
                        <div class="tpw-fieldset"><label>Slug (optional)<br/><input type="text" name="slug" /></label></div>
                        <div class="tpw-fieldset"><label>Description (optional)<br/><textarea name="description" rows="3" style="width:100%"></textarea></label></div>
                        <fieldset class="tpw-fieldset"><legend>Visibility</legend>
                            <label><input type="checkbox" name="visibility_is_admin" checked /> Admins</label>
                            <label style="margin-left:8px"><input type="checkbox" name="visibility_is_committee" /> Committee</label>
                            <label style="margin-left:8px"><input type="checkbox" name="visibility_is_match_manager" /> Match Managers</label>
                            <label style="margin-left:8px"><input type="checkbox" name="visibility_is_noticeboard_admin" /> Noticeboard Admins</label>
                            <div style="margin-top:8px">
                                <?php $statuses = apply_filters( 'tpw_members/known_statuses', [ 'Active','Honorary','Life Member','Junior','Student' ] ); ?>
                                <div class="tpw-status-checkboxes" role="group" aria-label="Status access" style="border:1px solid #eee;padding:8px;border-radius:4px;max-height:140px;overflow:auto">
                                    <?php foreach ( $statuses as $s ) echo '<label style="display:block;margin:2px 0"><input type="checkbox" name="visibility_status[]" value="' . esc_attr( $s ) . '" /> ' . esc_html( $s ) . '</label>'; ?>
                                </div>
                                <p class="description">Admins always have access. Leave empty to restrict to selected flags only.</p>
                            </div>
                        </fieldset>
                        <div class="tpw-upl-actions" style="margin-top:8px">
                            <button class="tpw-btn tpw-btn-primary" type="submit">Create Page</button>
                            <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-create-modal">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Help / Shortcodes Modal -->
            <div id="tpw-upl-help-modal" class="tpw-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;">
                <div class="tpw-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-help-title" style="background:#fff;max-width:700px;margin:8vh auto;padding:16px;border-radius:6px;">
                    <div class="tpw-modal__header" style="display:flex;justify-content:space-between;align-items:center;">
                        <h3 id="tpw-upl-help-title" style="margin:0">Upload Pages – Help & Shortcodes</h3>
                        <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-help-modal">Close</a>
                    </div>
                    <div class="tpw-modal__body" style="margin-top:8px">
                        <ol style="padding-left:18px; margin:0 0 10px 0;">
                            <li style="margin-bottom:6px;">Create an Upload Page (title, optional slug/description, and visibility).</li>
                            <li style="margin-bottom:6px;">Add one or more files to the page.</li>
                            <li style="margin-bottom:6px;">Embed the Upload Page on your site using the shortcode below (Shortcode block), or click “Create New Linked Page” on the edit screen to auto-create a WordPress Page containing the shortcode.</li>
                        </ol>
                        <div style="background:#f8f8f8;border:1px solid #eee;border-radius:6px;padding:10px;margin:10px 0;">
                            <div style="font-weight:600;margin-bottom:4px;">Shortcode</div>
                            <code style="display:block;white-space:pre;">
'[tpw_upload_page slug="your-slug"]'
                            </code>
                            <p class="description" style="margin:8px 0 0;">Replace <code>your-slug</code> with the slug of your Upload Page. The page layout (table, list, or cards) is controlled by the Upload Page settings.</p>
                        </div>
                        <div style="margin-top:10px;">
                            <div style="font-weight:600;">Notes</div>
                            <ul style="margin:6px 0 0 18px; list-style: disc;">
                                <li>Direct file URLs are protected; all downloads are served securely with permission checks.</li>
                                <li>Visibility options restrict who can access the files on the embedded page.</li>
                                <li>You can manage and link a WordPress Page from the “Page Details” panel on the edit screen.</li>
                                <li>You cannot delete an Upload Page while it still has files attached. Remove all files first, then delete the page.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
