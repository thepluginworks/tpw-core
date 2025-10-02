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
.tpw-section{border:1px solid #ddd;border-radius:6px;padding:16px;margin:16px 0;background:#fff}
.tpw-section legend{font-weight:600;padding:0 8px}
.tpw-section .tpw-fieldset{margin-bottom:8px}
.tpw-text-muted{color:#666}
</style>';
?>

<div class="tpw-upl-wrap">
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
                        <div class="tpw-upl-actions" style="margin-top:8px">
                            <a href="#" class="tpw-btn tpw-btn-primary" data-tpw-close="#tpw-upl-vis-modal">Done</a>
                            <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-vis-modal">Cancel</a>
                        </div>
                    </div>
                </div>

                <div class="tpw-upl-actions" style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap">
                    <button class="tpw-btn tpw-btn-primary" type="submit">Save Page Details</button>
                    <button class="tpw-btn tpw-btn-danger" type="submit" name="tpw_control_upload_pages_action" value="delete_page" onclick="return confirm('Delete this page and all its files?');">Delete Page</button>
                </div>
            </fieldset>
        </form>

        <fieldset class="tpw-section tpw-upl-files">
            <legend>Files</legend>
            <div class="tpw-upl-actions" style="margin-bottom:8px">
                <a href="#tpw-upl-addfile-modal" class="tpw-btn tpw-btn-primary" role="button" data-tpw-open="#tpw-upl-addfile-modal">Add File</a>
            </div>
            <form method="post" id="tpw-upl-form-files">
                <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                <input type="hidden" name="tpw_control_upload_pages_action" value="update_files" />
                <input type="hidden" name="upload_page_id" value="<?php echo (int)$page->id; ?>" />
                <table>
                    <thead>
                        <tr>
                            <th style="width:30px"></th>
                            <th style="width:90px">Preview</th>
                            <th>Label</th>
                            <th style="width:110px">Year</th>
                            <th style="width:100px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tpw-upl-files-tbody">
                        <?php foreach ( $files as $idx => $f ): 
                            $url = $f->file_url;
                            $thumb = $f->thumbnail_url;
                            $file_type = $f->file_type;
                            $label = $f->label !== '' ? $f->label : basename( parse_url($url, PHP_URL_PATH) );
                            // Determine icon fallback
                            $icon = '';
                            $ext = strtolower( pathinfo( parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION ) );
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
                                <td class="tpw-upl-handle" title="Drag to reorder" style="cursor:move">&#9776;</td>
                                <td>
                                    <?php if ( TPW_Control_UI::user_has_access( [ 'logged_in' => true, 'flags_any' => ['is_committee','is_admin'] ] ) ): ?>
                                        <a href="<?php echo esc_url( $url ); ?>" class="tpw-upl-preview" data-index="<?php echo (int)$idx; ?>" data-type="<?php echo esc_attr( $file_type ); ?>" data-label="<?php echo esc_attr( $label ); ?>">
                                            <?php if ( $thumb ): ?>
                                                <img src="<?php echo esc_url( $thumb ); ?>" alt="" style="width:64px;height:auto;border:1px solid #eee;border-radius:4px" />
                                            <?php else: ?>
                                                    <img src="<?php echo esc_url( $icon ); ?>" alt="" style="width:48px;height:48px" />
                                            <?php endif; ?>
                                        </a>
                                    <?php else: ?>
                                        <?php if ( $thumb ): ?>
                                            <img src="<?php echo esc_url( $thumb ); ?>" alt="" style="width:64px;height:auto;border:1px solid #eee;border-radius:4px" />
                                        <?php else: ?>
                                                <img src="<?php echo esc_url( $icon ); ?>" alt="" style="width:48px;height:48px" />
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="text" name="file_label[<?php echo (int)$f->id; ?>]" value="<?php echo esc_attr( $f->label ); ?>" />
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
    <?php else: ?>
        <div class="tpw-upl-list">
            <div style="margin-bottom:12px">
                <a href="#tpw-upl-create-modal" class="tpw-btn tpw-btn-primary" role="button" data-tpw-open="#tpw-upl-create-modal">Create New Upload Page</a>
            </div>
            <?php
            // Pagination
            $all_pages = TPW_Control_Upload_Pages::get_pages();
            $total = is_array($all_pages) ? count($all_pages) : 0;
            $per_page = apply_filters( 'tpw_control/upload_pages_per_page', 10 );
            $current_pg = isset($_GET['pg']) ? max(1, (int) $_GET['pg']) : 1;
            $offset = ($current_pg - 1) * $per_page;
            $pages = array_slice( (array)$all_pages, $offset, $per_page );
            $total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
            if ( empty( $pages ) ): ?>
                <div class="tpw-empty-state">
                    <p>No Upload Pages yet.</p>
                    <p>Create your first page below to start adding files.</p>
                </div>
            <?php else: ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Files</th>
                        <th>Visibility</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $pages as $p ):
                    $file_count = count( TPW_Control_Upload_Pages::get_files( (int)$p->id ) );
                    $vis = json_decode( (string)$p->visibility, true ); if ( ! is_array($vis) ) $vis = [];
                    $vis_bits = [];
                    foreach ( ['is_admin'=>'Admin','is_committee'=>'Committee','is_match_manager'=>'Match','is_noticeboard_admin'=>'Noticeboard'] as $k=>$label ) {
                        if ( ! empty($vis[$k]) ) $vis_bits[] = $label;
                    }
                    if ( ! empty($vis['status']) && is_array($vis['status']) ) $vis_bits[] = 'Status(' . implode(', ', $vis['status']) . ')';
                ?>
                    <tr>
                        <td><?php echo esc_html( $p->title ); ?></td>
                        <td><?php echo esc_html( $p->slug ); ?></td>
                        <td><?php echo (int) $file_count; ?></td>
                        <td><?php echo esc_html( implode(' • ', $vis_bits ) ); ?></td>
                        <td>
                            <?php $edit_url = add_query_arg( [ 'sub' => 'edit', 'upload_page_id' => (int)$p->id ], TPW_Control_UI::menu_url('upload-pages') ); ?>
                            <button type="button" class="tpw-btn tpw-btn-secondary tpw-nav-btn" data-href="<?php echo esc_url( $edit_url ); ?>">Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
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
        </div>
    <?php endif; ?>
</div>
