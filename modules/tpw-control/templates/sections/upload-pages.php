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
                <div class="tpw-upl-page-layout">
                    <div class="tpw-upl-page-main">
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
                        echo '<div class="tpw-fieldset tpw-upl-panel tpw-upl-panel--linked">';
                        if ( $wp_page_exists ) {
                            $plink = get_permalink( $wp_page_id );
                            $edit_link = get_edit_post_link( $wp_page_id, '' );
                            echo '<div>Linked WordPress Page: <a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noopener">#' . (int)$wp_page_id . '</a> · <a href="' . esc_url( $plink ) . '" target="_blank" rel="noopener">View</a> · <a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noopener">Edit</a></div>';
                            echo '<div class="tpw-text-muted">Title and shortcode are kept in sync automatically when you save.</div>';
                        } else {
                            // Recover Link UI
                            echo '<div class="tpw-upl-panel-title tpw-upl-panel-title--spaced">This Upload Page is not linked to a WordPress Page.</div>';
                            echo '<div class="tpw-text-muted tpw-upl-panel-copy">Create a new linked page or link to an existing one that contains the matching shortcode.</div>';

                            // Create New Linked Page button
                            echo '<form method="post" class="tpw-upl-inline-form tpw-upl-inline-form--end-space">';
                            wp_nonce_field( 'tpw_control_upload_pages' );
                            echo '<input type="hidden" name="tpw_control_upload_pages_action" value="create_linked_wp_page" />';
                            echo '<input type="hidden" name="upload_page_id" value="' . (int)$page->id . '" />';
                            echo '<button class="tpw-btn tpw-btn-primary" type="submit">Create New Linked Page</button>';
                            echo '</form>';

                            // Link Existing Page: dropdown of pages with matching shortcode
                            $candidates = method_exists('TPW_Control_Upload_Pages','find_wp_pages_for_slug') ? TPW_Control_Upload_Pages::find_wp_pages_for_slug( $page->slug ) : [];
                            echo '<form method="post" class="tpw-upl-inline-form tpw-upl-inline-form--top-space">';
                            wp_nonce_field( 'tpw_control_upload_pages' );
                            echo '<input type="hidden" name="tpw_control_upload_pages_action" value="link_existing_wp_page" />';
                            echo '<input type="hidden" name="upload_page_id" value="' . (int)$page->id . '" />';
                            echo '<label class="tpw-upl-inline-label">Link Existing Page</label>';
                            echo '<select name="selected_wp_page_id" class="tpw-upl-select-medium">';
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
                    <div class="tpw-upl-page-side">
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
                        <div class="tpw-upl-panel tpw-upl-panel--side">
                            <div class="tpw-upl-panel-title">Visibility</div>
                            <div class="tpw-text-muted tpw-upl-panel-copy">Visible to: <?php echo esc_html( implode(' • ', $vis_bits ) ); ?></div>
                            <a href="#tpw-upl-vis-modal" class="tpw-btn tpw-btn-secondary tpw-upl-full-width" role="button" data-tpw-open="#tpw-upl-vis-modal">Edit Visibility</a>
                        </div>

                        <?php $layout = isset($page->layout) ? $page->layout : 'table'; ?>
                        <div class="tpw-upl-panel tpw-upl-panel--side">
                            <div class="tpw-upl-panel-title">Layout Style</div>
                            <label for="tpw-upl-layout" class="screen-reader-text">Layout Style</label>
                            <select id="tpw-upl-layout" name="layout" class="tpw-upl-select-full">
                                <option value="table" <?php selected($layout,'table'); ?>>Table (default)</option>
                                <option value="list" <?php selected($layout,'list'); ?>>Bullet List</option>
                                <option value="cards" <?php selected($layout,'cards'); ?>>Card View</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="tpw-fieldset tpw-upl-fieldset-top">
                    <label class="tpw-upl-label">Description</label>
                    <div class="tpw-admin-editor wp-core-ui">
                    <?php
                    $editor_id = 'tpw_upl_desc_' . (int) $page->id;
                    $editor_settings = [
                        'textarea_name'   => 'description',
                        'textarea_rows'   => 12,
                        'media_buttons'   => true,
                        'drag_drop_upload'=> true,
                        'teeny'           => false,
                        'editor_class'    => 'tpw-admin-editor__textarea',
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
                        echo '<textarea name="description" rows="10" class="tpw-upl-textarea-full">' . esc_textarea( (string) $page->description ) . '</textarea>';
                    }
                    ?>
                    </div>
                    <p class="description">Use headings, bold text, lists, and sizing to format the page text members will see.</p>
                </div>

                <!-- Visibility Modal (fields are within the main form so Save Page will persist) -->
                <div id="tpw-upl-vis-modal" class="tpw-modal tpw-upl-modal">
                    <div class="tpw-modal__dialog tpw-upl-modal__dialog tpw-upl-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-vis-title">
                        <div class="tpw-modal__header tpw-upl-modal__header">
                            <h3 id="tpw-upl-vis-title" class="tpw-upl-modal__title">Edit Visibility</h3>
                            <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-vis-modal">Close</a>
                        </div>
                        <div class="tpw-fieldset tpw-upl-fieldset-top">
                            <label><input type="checkbox" name="visibility_is_admin" <?php checked( tpw_upl_vis_value($page,'is_admin') ); ?> /> Admins</label>
                            <label class="tpw-upl-inline-choice"><input type="checkbox" name="visibility_is_committee" <?php checked( tpw_upl_vis_value($page,'is_committee') ); ?> /> Committee</label>
                            <label class="tpw-upl-inline-choice"><input type="checkbox" name="visibility_is_match_manager" <?php checked( tpw_upl_vis_value($page,'is_match_manager') ); ?> /> Match Managers</label>
                            <label class="tpw-upl-inline-choice"><input type="checkbox" name="visibility_is_noticeboard_admin" <?php checked( tpw_upl_vis_value($page,'is_noticeboard_admin') ); ?> /> Noticeboard Admins</label>
                        </div>
                        <div class="tpw-fieldset tpw-upl-fieldset-top-sm">
                            <label>Status access</label>
                            <?php
                            $statuses = apply_filters( 'tpw_members/known_statuses', [ 'Active','Honorary','Life Member','Junior','Student' ] );
                            $sel = tpw_upl_vis_value( $page, 'status' );
                            echo '<div class="tpw-status-checkboxes tpw-upl-status-checkboxes tpw-upl-status-checkboxes--tall" role="group" aria-label="Status access">';
                            foreach ( $statuses as $s ) {
                                $checked = checked( in_array( $s, (array) $sel, true ), true, false );
                                echo '<label class="tpw-upl-status-option"><input type="checkbox" name="visibility_status[]" value="' . esc_attr( $s ) . '" ' . $checked . ' /> ' . esc_html( $s ) . '</label>';
                            }
                            echo '</div>';
                            ?>
                            <p class="description">Leave empty for none. Admins always have access.</p>
                        </div>
                        <div class="tpw-upl-actions tpw-upl-actions--top-sm tpw-upl-actions--items">
                            <button class="tpw-btn tpw-btn-primary" type="submit" name="tpw_control_upload_pages_action" value="update_page">Save Visibility</button>
                            <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-vis-modal">Cancel</a>
                            <span class="tpw-text-muted tpw-upl-actions-note">Saving also updates Page Details.</span>
                        </div>
                    </div>
                </div>

                <div class="tpw-upl-actions tpw-upl-actions--top-lg tpw-upl-actions--wide-gap">
                    <button class="tpw-btn tpw-btn-primary" type="submit">Save Page Details</button>
                    <?php $has_files = ! empty( $files ); ?>
                    <button class="tpw-btn <?php echo $has_files ? 'tpw-btn-light' : 'tpw-btn-danger'; ?>" type="submit" name="tpw_control_upload_pages_action" value="delete_page" <?php echo $has_files ? 'disabled aria-disabled="true" title="No files must be attached before deleting this page."' : 'onclick="return confirm(\'Delete this page?\');"'; ?>>Delete Page</button>
                </div>
            </fieldset>
        </form>

        <?php $categories = TPW_Control_Upload_Pages::get_categories( (int)$page->id ); ?>
        <fieldset class="tpw-section tpw-upl-files">
            <legend>Files</legend>
            <div class="tpw-upl-actions tpw-upl-actions--bottom-sm">
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
                echo '<div class="notice tpw-upl-import-report">';
                echo '<div class="tpw-upl-import-report-title">Bulk Import Report</div>';
                echo '<div>Imported: ' . (int) ($report['success_count'] ?? 0) . ' file(s)';
                if ( ! empty( $report['created_categories'] ) ) echo ' · Categories created: ' . (int) $report['created_categories'];
                echo '</div>';
                if ( ! empty( $report['warnings'] ) ) { echo '<ul class="tpw-upl-report-list tpw-upl-report-list--warning">'; foreach ( (array)$report['warnings'] as $w ) echo '<li>' . esc_html( (string) $w ) . '</li>'; echo '</ul>'; }
                if ( ! empty( $report['errors'] ) ) { echo '<ul class="tpw-upl-report-list tpw-upl-report-list--error">'; foreach ( (array)$report['errors'] as $e ) echo '<li>' . esc_html( (string) $e ) . '</li>'; echo '</ul>'; }
                echo '</div>';
            }
            ?>
            <form method="post" id="tpw-upl-form-files">
                <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                <input type="hidden" name="tpw_control_upload_pages_action" value="update_files" />
                <input type="hidden" name="upload_page_id" value="<?php echo (int)$page->id; ?>" />
                <div class="tpw-upl-actions tpw-upl-actions--toolbar">
                    <select name="bulk_action" class="tpw-upl-bulk-action-select">
                        <option value="">Bulk actions…</option>
                        <option value="assign">Assign to Page…</option>
                        <option value="unlink">Delete (move to Trash)</option>
                        <!-- Permanent delete is available in the Trash panel only -->
                    </select>
                    <select name="target_page_id" class="tpw-upl-target-page-select">
                        <option value="">— Select target page —</option>
                        <?php foreach ( (array) TPW_Control_Upload_Pages::get_pages() as $pg ) echo '<option value="' . (int)$pg->id . '">' . esc_html( $pg->title ) . '</option>'; ?>
                    </select>
                    <input type="number" name="assign_year" placeholder="Year override (optional)" class="tpw-upl-assign-year" />
                    <select name="assign_category_mode" title="Category handling on assign">
                        <option value="inherit">Inherit category</option>
                        <option value="none">No category</option>
                        <option value="general">General on target</option>
                    </select>
                    <button class="tpw-btn tpw-btn-light" type="submit" formaction="" onclick="this.form.tpw_control_upload_pages_action.value='bulk_files'">Apply</button>
                </div>
                <?php
                $category_names = [];
                $category_positions = [];
                foreach ( (array) $categories as $cat ) {
                    $category_names[ (int) $cat->category_id ] = (string) $cat->category_name;
                    $category_positions[ (int) $cat->category_id ] = count( $category_positions );
                }
                $file_groups = [];
                $preview_index = 0;
                foreach ( (array) $files as $f ) {
                    $group_cat = ! empty( $f->category_id ) ? (int) $f->category_id : 0;
                    $group_year = ! empty( $f->year ) ? (int) $f->year : 0;
                    if ( ! isset( $file_groups[ $group_cat ] ) ) $file_groups[ $group_cat ] = [];
                    if ( ! isset( $file_groups[ $group_cat ][ $group_year ] ) ) {
                        $file_groups[ $group_cat ][ $group_year ] = [];
                    }
                    $file_groups[ $group_cat ][ $group_year ][] = $f;
                }
                $groups_to_render = [];
                foreach ( $file_groups as $group_cat => $year_groups ) {
                    foreach ( $year_groups as $group_year => $group_files ) {
                        $groups_to_render[] = [
                            'category_id' => (int) $group_cat,
                            'year' => (int) $group_year,
                            'files' => $group_files,
                            'category_position' => isset( $category_positions[ (int) $group_cat ] ) ? (int) $category_positions[ (int) $group_cat ] : PHP_INT_MAX,
                        ];
                    }
                }
                usort( $groups_to_render, function( $left, $right ) {
                    $year_compare = (int) $right['year'] <=> (int) $left['year'];
                    if ( $year_compare !== 0 ) return $year_compare;

                    $category_compare = (int) $left['category_position'] <=> (int) $right['category_position'];
                    if ( $category_compare !== 0 ) return $category_compare;

                    return (int) $left['category_id'] <=> (int) $right['category_id'];
                } );
                $latest_group_year = 0;
                $filter_categories = [];
                $filter_years = [];
                foreach ( $groups_to_render as $group_item ) {
                    if ( (int) $group_item['year'] > $latest_group_year ) $latest_group_year = (int) $group_item['year'];
                    $cat_key = (int) $group_item['category_id'];
                    if ( ! isset( $filter_categories[ $cat_key ] ) ) {
                        $filter_categories[ $cat_key ] = [
                            'label' => $cat_key > 0 && isset( $category_names[ $cat_key ] ) ? $category_names[ $cat_key ] : 'No Category',
                            'position' => isset( $category_positions[ $cat_key ] ) ? (int) $category_positions[ $cat_key ] : PHP_INT_MAX,
                        ];
                    }
                    $year_key = (int) $group_item['year'];
                    if ( ! isset( $filter_years[ $year_key ] ) ) $filter_years[ $year_key ] = true;
                }
                uasort( $filter_categories, function( $left, $right ) {
                    $position_compare = (int) $left['position'] <=> (int) $right['position'];
                    if ( $position_compare !== 0 ) return $position_compare;
                    return strcmp( (string) $left['label'], (string) $right['label'] );
                } );
                $filter_year_values = array_keys( $filter_years );
                rsort( $filter_year_values, SORT_NUMERIC );
                ?>
                <div class="tpw-upl-files-toolbar" aria-label="Filter file groups">
                    <label>Category
                        <select id="tpw-upl-filter-category">
                            <option value="">All categories</option>
                            <?php foreach ( $filter_categories as $cat_id => $cat_meta ) : ?>
                                <option value="<?php echo (int) $cat_id > 0 ? (int) $cat_id : 'none'; ?>"><?php echo esc_html( $cat_meta['label'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Year
                        <select id="tpw-upl-filter-year">
                            <option value="">All years</option>
                            <?php foreach ( $filter_year_values as $filter_year ) : ?>
                                <option value="<?php echo (int) $filter_year > 0 ? (int) $filter_year : 'none'; ?>"><?php echo esc_html( (int) $filter_year > 0 ? (string) $filter_year : 'No Year' ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Search
                        <input type="text" id="tpw-upl-filter-search" placeholder="Search file labels" />
                    </label>
                    <button type="button" class="tpw-btn tpw-btn-light" id="tpw-upl-filter-reset">Reset filters</button>
                </div>
                <?php if ( ! empty( $files ) ) : ?>
                    <?php foreach ( $groups_to_render as $group_index => $group_data ) : ?>
                        <?php
                            $group_cat = (int) $group_data['category_id'];
                            $group_year = (int) $group_data['year'];
                            $group_files = $group_data['files'];
                            $group_parts = [];
                            $group_parts[] = $group_cat > 0 && isset( $category_names[ $group_cat ] ) ? $category_names[ $group_cat ] : 'No Category';
                            $group_parts[] = $group_year > 0 ? (string) $group_year : 'No Year';
                            $group_title = implode( ' - ', $group_parts );
                            $group_count = count( $group_files );
                            $group_filter_cat = $group_cat > 0 ? (string) $group_cat : 'none';
                            $group_filter_year = $group_year > 0 ? (string) $group_year : 'none';
                            $default_open = $latest_group_year > 0 ? ( $group_year === $latest_group_year ) : ( $group_index < 2 );
                        ?>
                        <div class="tpw-upl-group tpw-upl-group-spaced <?php echo $default_open ? 'is-expanded' : 'is-collapsed'; ?>" data-category-id="<?php echo esc_attr( $group_filter_cat ); ?>" data-year="<?php echo esc_attr( $group_filter_year ); ?>" data-default-expanded="<?php echo $default_open ? '1' : '0'; ?>">
                            <button type="button" class="tpw-upl-group-toggle tpw-btn tpw-btn-secondary tpw-btn-outline tpw-btn-text-left" aria-expanded="<?php echo $default_open ? 'true' : 'false'; ?>">
                                <span class="tpw-upl-group-titlewrap">
                                    <span class="tpw-upl-group-caret" aria-hidden="true">▾</span>
                                    <span><?php echo esc_html( $group_title ); ?></span>
                                </span>
                                <span class="tpw-upl-group-meta"><?php echo esc_html( sprintf( _n( '%d file', '%d files', $group_count, 'tpw-core' ), $group_count ) ); ?></span>
                            </button>
                            <div class="tpw-upl-group-body"<?php echo $default_open ? '' : ' hidden'; ?>>
                            <table>
                                <thead>
                                    <tr>
                                        <th class="tpw-upl-col-select"><input type="checkbox" onclick="(function(hd){var tbl=hd.closest('table');if(tbl){tbl.querySelectorAll('tbody input[type=checkbox]').forEach(function(cb){cb.checked=hd.checked;});}})(this);" /></th>
                                        <th class="tpw-upl-col-handle"></th>
                                        <th class="tpw-upl-col-preview">Preview</th>
                                        <th>Label</th>
                                        <th class="tpw-upl-col-category">Category</th>
                                        <th class="tpw-upl-col-year">Year</th>
                                        <th class="tpw-upl-col-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="tpw-upl-files-group" data-page-id="<?php echo (int) $page->id; ?>" data-category-id="<?php echo $group_cat > 0 ? (int) $group_cat : ''; ?>" data-year="<?php echo $group_year > 0 ? (int) $group_year : ''; ?>">
                                    <?php foreach ( $group_files as $f ) :
                                        $url = method_exists('TPW_Control_Upload_Pages','build_served_url') ? TPW_Control_Upload_Pages::build_served_url( (int)$f->id, 'file', 900, false ) : $f->file_url;
                                        $thumb = $f->thumbnail_url;
                                        $file_type = $f->file_type;
                                        $label = $f->label !== '' ? $f->label : basename( parse_url($f->file_url, PHP_URL_PATH) );
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
                                        <td><input type="checkbox" name="selected_links[]" value="<?php echo (int)$f->id; ?>" /><input type="hidden" name="file_order[<?php echo (int)$f->id; ?>]" value="<?php echo isset( $f->sort_order ) ? (int) $f->sort_order : 0; ?>" /></td>
                                        <td class="tpw-upl-handle" title="Drag to reorder">&#9776;</td>
                                        <td>
                                            <?php if ( TPW_Control_UI::user_has_access( [ 'logged_in' => true, 'flags_any' => ['is_committee','is_admin'] ] ) ): ?>
                                                <a href="<?php echo esc_url( $url ); ?>" class="tpw-upl-preview" data-index="<?php echo (int) $preview_index; ?>" data-type="<?php echo esc_attr( $file_type ); ?>" data-label="<?php echo esc_attr( $label ); ?>">
                                                    <?php if ( $thumb ): ?>
                                                        <?php $thumb_served = method_exists('TPW_Control_Upload_Pages','build_served_url') ? TPW_Control_Upload_Pages::build_served_url( (int)$f->id, 'thumb', 900, false ) : $thumb; ?>
                                                        <img src="<?php echo esc_url( $thumb_served ); ?>" alt="" class="tpw-upl-thumb" />
                                                    <?php else: ?>
                                                        <img src="<?php echo esc_url( $icon ); ?>" alt="" class="tpw-upl-icon" />
                                                    <?php endif; ?>
                                                </a>
                                            <?php else: ?>
                                                <?php if ( $thumb ): ?>
                                                    <?php $thumb_served = method_exists('TPW_Control_Upload_Pages','build_served_url') ? TPW_Control_Upload_Pages::build_served_url( (int)$f->id, 'thumb', 900, false ) : $thumb; ?>
                                                    <img src="<?php echo esc_url( $thumb_served ); ?>" alt="" class="tpw-upl-thumb" />
                                                <?php else: ?>
                                                    <img src="<?php echo esc_url( $icon ); ?>" alt="" class="tpw-upl-icon" />
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
                                            <div class="tpw-upl-row-actions">
                                                <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="tpw-btn tpw-btn-light tpw-upl-btn-compact">Open</a>
                                                <button type="button" class="tpw-btn tpw-btn-danger tpw-upl-file-delete tpw-upl-btn-compact" data-file-id="<?php echo (int)$f->id; ?>" data-page-id="<?php echo (int)$page->id; ?>">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $preview_index++; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="tpw-upl-filter-empty" id="tpw-upl-filter-empty">No matching files found for the current filters.</div>
                <?php else: ?>
                    <p class="tpw-text-muted">No files uploaded yet.</p>
                <?php endif; ?>
                <div class="tpw-upl-actions tpw-upl-actions--top-sm">
                    <button class="tpw-btn tpw-btn-secondary" type="submit">Save file changes</button>
                    <span class="tpw-upl-unsaved" id="tpw-upl-unsaved-indicator" hidden aria-live="polite">Unsaved changes</span>
                </div>
            </form>

            <?php
            // Trash (soft-deleted links)
            global $wpdb; $links_table = $wpdb->prefix . 'tpw_upload_pages_files'; $files_table = $wpdb->prefix . 'tpw_files';
            $trashed = $wpdb->get_results( $wpdb->prepare( "SELECT l.*, f.file_type, f.file_url, f.thumbnail_url FROM {$links_table} l JOIN {$files_table} f ON f.file_id=l.file_id WHERE l.page_id=%d AND l.is_deleted=1 ORDER BY l.deleted_at DESC", (int)$page->id ) );
            if ( ! empty( $trashed ) ): ?>
            <div id="tpw-upl-trash-section" class="tpw-section tpw-upl-trash-section">
                <h4 class="tpw-upl-trash-title">Trash</h4>
                <form method="post">
                    <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                    <input type="hidden" name="tpw_control_upload_pages_action" value="bulk_files" />
                    <input type="hidden" name="upload_page_id" value="<?php echo (int)$page->id; ?>" />
                    <div class="tpw-upl-actions tpw-upl-actions--toolbar">
                        <select name="bulk_action" class="tpw-upl-bulk-action-select">
                            <option value="">Bulk actions…</option>
                            <option value="restore">Restore</option>
                            <option value="delete_permanent">Delete permanently</option>
                        </select>
                        <button class="tpw-btn tpw-btn-light" type="submit">Apply</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th class="tpw-upl-col-select">
                                    <input type="checkbox" title="Select all" aria-label="Select all in Trash" onclick="(function(hd){var tbl=hd.closest('table');if(tbl){tbl.querySelectorAll('tbody input[type=checkbox]').forEach(function(cb){cb.checked=hd.checked;});}})(this);" />
                                </th>
                                <th>Label</th>
                                <th class="tpw-upl-col-actions-wide">Deleted</th>
                                <th class="tpw-upl-col-actions-wide">Actions</th>
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
                                        <button class="tpw-btn tpw-btn-danger tpw-upl-trash-delete" name="bulk_action" value="delete_permanent" type="submit" onclick="(function(btn){var row=btn.closest('tr');var table=btn.closest('table');if(table){table.querySelectorAll('tbody input[type=checkbox]').forEach(function(cb){cb.checked=false;});}var cb=row?row.querySelector('input[type=checkbox]'):null;if(cb){cb.checked=true;}})(this); return confirm('Delete permanently? This cannot be undone.');">Delete</button>
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
        <div id="tpw-upl-addfile-modal" class="tpw-modal tpw-upl-modal">
            <div class="tpw-modal__dialog tpw-upl-modal__dialog tpw-upl-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-addfile-title">
                <div class="tpw-modal__header tpw-upl-modal__header">
                    <h3 id="tpw-upl-addfile-title" class="tpw-upl-modal__title">Add File</h3>
                    <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-addfile-modal">Close</a>
                </div>
                <form method="post" enctype="multipart/form-data" id="tpw-upl-form-add" class="tpw-upl-modal__form--md">
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
                        <button type="button" class="tpw-btn tpw-btn-light tpw-upl-inline-choice" data-tpw-open="#tpw-upl-addcat-modal">Add Category</button>
                    </div>
                    <div class="tpw-fieldset"><input type="text" name="upload_label" placeholder="Label (optional, defaults to filename)" class="tpw-upl-input-wide" /></div>
                    <div id="tpw-upl-upload-feedback" class="tpw-upl-upload-feedback">
                        <div class="tpw-progress tpw-upl-progress">
                            <progress id="tpw-upl-progress" max="100" value="0" class="tpw-upl-progress-bar">0%</progress>
                            <div class="tpw-text-muted"><span id="tpw-upl-progress-text">Uploading… 0%</span></div>
                        </div>
                        <div id="tpw-upl-upload-error" class="tpw-upl-inline-error"></div>
                    </div>
                    <div class="tpw-upl-actions tpw-upl-actions--top-sm">
                        <button class="tpw-btn tpw-btn-primary" type="submit">Upload</button>
                        <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-addfile-modal">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Import Modal -->
        <div id="tpw-upl-import-modal" class="tpw-modal tpw-upl-modal">
            <div class="tpw-modal__dialog tpw-upl-modal__dialog tpw-upl-modal__dialog--large" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-import-title">
                <div class="tpw-modal__header tpw-upl-modal__header">
                    <h3 id="tpw-upl-import-title" class="tpw-upl-modal__title">Bulk Import (CSV + ZIP)</h3>
                    <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-import-modal">Close</a>
                </div>
                <div class="tpw-modal__body tpw-upl-modal__body--md">
                    <p class="description">Upload a ZIP archive containing your documents and a CSV manifest named <code>manifest.csv</code>.</p>
                    <ul class="tpw-upl-help-list tpw-upl-help-list--spaced">
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
                <form method="post" enctype="multipart/form-data" class="tpw-upl-modal__form--sm">
                    <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                    <input type="hidden" name="tpw_control_upload_pages_action" value="bulk_import" />
                    <input type="hidden" name="upload_page_id" value="<?php echo (int) $page->id; ?>" />
                    <div class="tpw-fieldset"><label>ZIP file<br/><input type="file" name="bulk_zip" accept=".zip,application/zip" required /></label></div>
                    <div class="tpw-upl-actions tpw-upl-actions--top-sm">
                        <button class="tpw-btn tpw-btn-primary" type="submit">Run Import</button>
                        <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-import-modal">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Add Category Modal -->
        <div id="tpw-upl-addcat-modal" class="tpw-modal tpw-upl-modal tpw-upl-modal--stacked">
            <div class="tpw-modal__dialog tpw-upl-modal__dialog tpw-upl-modal__dialog--small" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-addcat-title">
                <div class="tpw-modal__header tpw-upl-modal__header">
                    <h3 id="tpw-upl-addcat-title" class="tpw-upl-modal__title">Add Category</h3>
                    <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-addcat-modal">Close</a>
                </div>
                <form method="post" id="tpw-upl-form-addcat" class="tpw-upl-modal__form--md">
                    <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                    <input type="hidden" name="tpw_control_upload_pages_action" value="add_category" />
                    <input type="hidden" name="upload_page_id" value="<?php echo (int)$page->id; ?>" />
                    <div class="tpw-fieldset">
                        <label>Category name<br/>
                            <input type="text" name="category_name" required placeholder="e.g. Policies" class="tpw-upl-select-wide" />
                        </label>
                    </div>
                    <div id="tpw-upl-addcat-error" class="tpw-upl-inline-error tpw-upl-inline-error--top"></div>
                    <div class="tpw-upl-actions tpw-upl-actions--top-md">
                        <button class="tpw-btn tpw-btn-primary" type="submit">Add Category</button>
                        <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-addcat-modal">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="tpw-upl-list">
            <div class="tpw-upl-list-actions">
                <a href="#tpw-upl-create-modal" class="tpw-btn tpw-btn-primary" role="button" data-tpw-open="#tpw-upl-create-modal">Create New Upload Page</a>
                <a href="#tpw-upl-help-modal" class="tpw-btn tpw-btn-light" role="button" data-tpw-open="#tpw-upl-help-modal">Help / Shortcodes</a>
            </div>
            <?php if ( isset($_GET['err']) && $_GET['err'] === 'has_files' ): ?>
                <div class="notice notice-error tpw-upl-notice-error">
                    <p class="tpw-upl-notice-copy">Cannot delete this Upload Page because it still has files attached. Remove all files first.</p>
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
                    <div class="table-cell tpw-upl-list-col-title">Title</div>
                    <div class="table-cell tpw-upl-list-col-slug">Slug</div>
                    <div class="table-cell tpw-upl-list-col-files">Files</div>
                    <div class="table-cell tpw-upl-list-col-visibility">Visibility</div>
                    <div class="table-cell tpw-upl-list-col-actions">Actions</div>
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
                    <div class="table-cell tpw-upl-list-col-title"><?php echo esc_html( $p->title ); ?></div>
                    <div class="table-cell tpw-upl-list-col-slug"><?php echo esc_html( $p->slug ); ?></div>
                    <div class="table-cell tpw-upl-list-col-files"><?php echo (int) $file_count; ?></div>
                    <div class="table-cell tpw-upl-list-col-visibility"><?php echo esc_html( implode(' • ', $vis_bits ) ); ?></div>
                    <div class="table-cell tpw-upl-list-col-actions tpw-upl-table-actions-cell">
                        <a class="tpw-btn tpw-btn-secondary" href="<?php echo esc_url( $edit_url ); ?>">Edit</a>
                        <form method="post" class="tpw-upl-delete-form">
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
            <div id="tpw-upl-create-modal" class="tpw-modal tpw-upl-modal">
                <div class="tpw-modal__dialog tpw-upl-modal__dialog tpw-upl-modal__dialog--medium" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-create-title">
                    <div class="tpw-modal__header tpw-upl-modal__header">
                        <h3 id="tpw-upl-create-title" class="tpw-upl-modal__title">Create new Upload Page</h3>
                        <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-create-modal">Close</a>
                    </div>
                    <form method="post" class="tpw-upl-new tpw-form tpw-upl-modal__form--md">
                        <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
                        <input type="hidden" name="tpw_control_upload_pages_action" value="create_page" />
                        <input type="hidden" name="_tpw_control_page_url" value="<?php echo esc_url( TPW_Control_UI::menu_url('upload-pages') ); ?>" />
                        <div class="tpw-fieldset"><label>Title<br/><input type="text" name="title" required /></label></div>
                        <div class="tpw-fieldset"><label>Slug (optional)<br/><input type="text" name="slug" /></label></div>
                        <div class="tpw-fieldset"><label>Description (optional)<br/><textarea name="description" rows="3" class="tpw-upl-textarea-full"></textarea></label></div>
                        <fieldset class="tpw-fieldset"><legend>Visibility</legend>
                            <label><input type="checkbox" name="visibility_is_admin" checked /> Admins</label>
                            <label class="tpw-upl-inline-choice"><input type="checkbox" name="visibility_is_committee" /> Committee</label>
                            <label class="tpw-upl-inline-choice"><input type="checkbox" name="visibility_is_match_manager" /> Match Managers</label>
                            <label class="tpw-upl-inline-choice"><input type="checkbox" name="visibility_is_noticeboard_admin" /> Noticeboard Admins</label>
                            <div class="tpw-upl-fieldset-top-sm">
                                <?php $statuses = apply_filters( 'tpw_members/known_statuses', [ 'Active','Honorary','Life Member','Junior','Student' ] ); ?>
                                <div class="tpw-status-checkboxes tpw-upl-status-checkboxes tpw-upl-status-checkboxes--compact" role="group" aria-label="Status access">
                                    <?php foreach ( $statuses as $s ) echo '<label class="tpw-upl-status-option"><input type="checkbox" name="visibility_status[]" value="' . esc_attr( $s ) . '" /> ' . esc_html( $s ) . '</label>'; ?>
                                </div>
                                <p class="description">Admins always have access. Leave empty to restrict to selected flags only.</p>
                            </div>
                        </fieldset>
                        <div class="tpw-upl-actions tpw-upl-actions--top-sm">
                            <button class="tpw-btn tpw-btn-primary" type="submit">Create Page</button>
                            <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-create-modal">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Help / Shortcodes Modal -->
            <div id="tpw-upl-help-modal" class="tpw-modal tpw-upl-modal">
                <div class="tpw-modal__dialog tpw-upl-modal__dialog tpw-upl-modal__dialog--help" role="dialog" aria-modal="true" aria-labelledby="tpw-upl-help-title">
                    <div class="tpw-modal__header tpw-upl-modal__header">
                        <h3 id="tpw-upl-help-title" class="tpw-upl-modal__title">Upload Pages – Help & Shortcodes</h3>
                        <a href="#" class="tpw-btn tpw-btn-light" data-tpw-close="#tpw-upl-help-modal">Close</a>
                    </div>
                    <div class="tpw-modal__body tpw-upl-modal__body--sm">
                        <ol class="tpw-upl-help-steps">
                            <li class="tpw-upl-help-step">Create an Upload Page (title, optional slug/description, and visibility).</li>
                            <li class="tpw-upl-help-step">Add one or more files to the page.</li>
                            <li class="tpw-upl-help-step">Embed the Upload Page on your site using the shortcode below (Shortcode block), or click “Create New Linked Page” on the edit screen to auto-create a WordPress Page containing the shortcode.</li>
                        </ol>
                        <div class="tpw-upl-help-box">
                            <div class="tpw-upl-help-box-title">Shortcode</div>
                            <code class="tpw-upl-help-code">
'[tpw_upload_page slug="your-slug"]'
                            </code>
                            <p class="description tpw-upl-help-copy">Replace <code>your-slug</code> with the slug of your Upload Page. The page layout (table, list, or cards) is controlled by the Upload Page settings.</p>
                        </div>
                        <div class="tpw-upl-help-notes">
                            <div class="tpw-upl-help-notes-title">Notes</div>
                            <ul class="tpw-upl-help-list">
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
