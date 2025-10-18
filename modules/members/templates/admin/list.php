<?php
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$paged_q = get_query_var('paged');
$page_q  = get_query_var('page'); // WP sometimes uses 'page' for hierarchical pages
$page = 1;
if ( $paged_q ) {
    $page = max(1, (int) $paged_q);
} elseif ( $page_q ) {
    $page = max(1, (int) $page_q);
} elseif ( isset($_GET['paged']) ) {
    $page = max(1, (int) $_GET['paged']);
} elseif ( isset($_SERVER['REQUEST_URI']) && preg_match('#/page/(\d+)/?#', $_SERVER['REQUEST_URI'], $m) ) {
    $page = max(1, (int) $m[1]);
}
$site_default_view = get_option('tpw_members_default_view', 'list');
$initial_view = ( isset($_GET['view']) && sanitize_text_field($_GET['view']) === 'card' ) ? 'card' : ( $site_default_view === 'card' ? 'card' : 'list' );
$current_view = $initial_view;
$default_per_page = (int) get_option('tpw_members_default_per_page', 25);
$default_per_page_card = (int) get_option('tpw_members_default_per_page_card', 24);
// Initial hide flag; may be refined after we detect other filters
if ( $current_view === 'card' ) {
    $hide_initial = ($default_per_page_card === 0 && $search_query === '');
} else {
    $hide_initial = ($default_per_page === 0 && $search_query === '');
}
// Determine per-page: explicit GET overrides; else per-view default if > 0; else fallback 25
if ( isset($_GET['per_page']) ) {
    $per_page = max(1, (int) $_GET['per_page']);
} else {
    $per_page = ($current_view === 'card')
        ? ( ($default_per_page_card > 0) ? max(1, $default_per_page_card) : 25 )
        : ( ($default_per_page > 0) ? max(1, $default_per_page) : 25 );
}
$is_admin = !empty($GLOBALS['tpw_members_is_admin']);
// Determine visibility group used by tpw_can_group_view_field for non-admin directory users
$vis_group = isset($GLOBALS['tpw_members_vis_group']) ? sanitize_key($GLOBALS['tpw_members_vis_group']) : ($is_admin ? 'admin' : 'member');
$adv_for_members = get_option('tpw_members_enable_advanced_search', '0') === '1';
$selected_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Build selected has_value filters from GET based on configured searchable fields
$searchable_opt = get_option('tpw_member_searchable_fields', []);
if (!is_array($searchable_opt)) { $searchable_opt = []; }
$has_value_selected = [];
foreach ($searchable_opt as $key => $conf) {
    if (isset($conf['search_type']) && $conf['search_type'] === 'has_value') {
        $param = 'has_' . $key;
        if (isset($_GET[$param]) && $_GET[$param] === '1') {
            $has_value_selected[] = sanitize_key($key);
        }
    }
}

$adv_text = [];
$adv_select = [];
$adv_date_range = [];
$adv_checkbox = [];
$adv_select_meta = [];
// Build advanced text/select/date_range filters from GET
global $wpdb; $members_table = $wpdb->prefix . 'tpw_members';
$tpw_cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $members_table, 0 );
foreach ($searchable_opt as $skey => $sconf) {
    $stype = isset($sconf['search_type']) ? $sconf['search_type'] : 'text';
    $admin_only = !empty($sconf['admin_only']);
    if (!$is_admin && $admin_only) { continue; }
    $col = sanitize_key($skey);
    if ($stype === 'text') {
        // Currently supported only for core columns
        if (in_array($col, $tpw_cols, true) && isset($_GET['adv_txt_'.$col]) && $_GET['adv_txt_'.$col] !== '') {
            $adv_text[$col] = sanitize_text_field($_GET['adv_txt_'.$col]);
        }
    } elseif ($stype === 'select') {
        if (isset($_GET['adv_sel_'.$col]) && $_GET['adv_sel_'.$col] !== '') {
            $valSel = sanitize_text_field($_GET['adv_sel_'.$col]);
            if (in_array($col, $tpw_cols, true)) {
                $adv_select[$col] = $valSel;
            } else {
                // Custom field (meta)
                $adv_select_meta[$col] = $valSel;
            }
        }
    } elseif ($stype === 'date_range') {
        if (!in_array($col, $tpw_cols, true)) { continue; }
        $from = isset($_GET['adv_from_'.$col]) ? sanitize_text_field($_GET['adv_from_'.$col]) : '';
        $to   = isset($_GET['adv_to_'.$col]) ? sanitize_text_field($_GET['adv_to_'.$col]) : '';
        $norm = function($d){
            if (!$d) return '';
            if (strpos($d,'/') !== false) {
                $parts = explode('/', $d);
                if (count($parts) === 3) {
                    return sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
                }
            }
            return $d;
        };
        $fromN = $norm($from); $toN = $norm($to);
        if ($fromN !== '' || $toN !== '') {
            $adv_date_range[$col] = ['from'=>$fromN, 'to'=>$toN];
        }
    } elseif ($stype === 'checkbox') {
        // If checked, include this column in adv_checkbox; otherwise ignore.
        if (in_array($col, $tpw_cols, true)) {
            if (isset($_GET[$col]) && $_GET[$col] === '1') { $adv_checkbox[] = $col; }
        }
    }
}

// Also honor Basic Search fields (tpw_field_settings.basic_search = 1) even if not configured as 'searchable'
try {
    global $wpdb;
    $fs_tbl = $wpdb->prefix . 'tpw_field_settings';
    $basic_rows_merge = $wpdb->get_results( "SELECT field_key FROM {$fs_tbl} WHERE is_enabled = 1 AND basic_search = 1" );
    if (!empty($basic_rows_merge)) {
        // Use configured searchable options to infer control types for these fields
        $searchable_opt_inline_merge = get_option('tpw_member_searchable_fields', []);
        foreach ($basic_rows_merge as $row_merge) {
            $bk = sanitize_key($row_merge->field_key);
            // Determine search type; default to text
            $stype = isset($searchable_opt_inline_merge[$bk]['search_type']) ? $searchable_opt_inline_merge[$bk]['search_type'] : 'text';
            if ($stype === 'text') {
                // Only for core columns
                if (in_array($bk, $tpw_cols, true) && isset($_GET['adv_txt_'.$bk]) && $_GET['adv_txt_'.$bk] !== '') {
                    if (!isset($adv_text[$bk])) { $adv_text[$bk] = sanitize_text_field($_GET['adv_txt_'.$bk]); }
                }
            } elseif ($stype === 'select') {
                if (isset($_GET['adv_sel_'.$bk]) && $_GET['adv_sel_'.$bk] !== '') {
                    $valSel = sanitize_text_field($_GET['adv_sel_'.$bk]);
                    if (in_array($bk, $tpw_cols, true)) {
                        if (!isset($adv_select[$bk])) { $adv_select[$bk] = $valSel; }
                    } else {
                        if (!isset($adv_select_meta[$bk])) { $adv_select_meta[$bk] = $valSel; }
                    }
                }
            } elseif ($stype === 'date_range') {
                // Only for core columns
                if (!in_array($bk, $tpw_cols, true)) { continue; }
                $from = isset($_GET['adv_from_'.$bk]) ? sanitize_text_field($_GET['adv_from_'.$bk]) : '';
                $to   = isset($_GET['adv_to_'.$bk]) ? sanitize_text_field($_GET['adv_to_'.$bk]) : '';
                $norm = function($d){
                    if (!$d) return '';
                    if (strpos($d,'/') !== false) {
                        $parts = explode('/', $d);
                        if (count($parts) === 3) {
                            return sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
                        }
                    }
                    return $d;
                };
                $fromN = $norm($from); $toN = $norm($to);
                if ($fromN !== '' || $toN !== '') {
                    if (!isset($adv_date_range[$bk])) { $adv_date_range[$bk] = ['from'=>$fromN, 'to'=>$toN]; }
                }
            } elseif ($stype === 'has_value') {
                $param = 'has_' . $bk;
                if (isset($_GET[$param]) && $_GET[$param] === '1') {
                    if (!in_array($bk, $has_value_selected, true)) { $has_value_selected[] = $bk; }
                }
            } elseif ($stype === 'checkbox') {
                if (in_array($bk, $tpw_cols, true)) {
                    if (isset($_GET[$bk]) && $_GET[$bk] === '1') { if (!in_array($bk, $adv_checkbox, true)) { $adv_checkbox[] = $bk; } }
                }
            } else {
                // Unknown type: attempt text for core, meta as select-like into adv_select_meta (text fallback)
                if (in_array($bk, $tpw_cols, true)) {
                    if (isset($_GET['adv_txt_'.$bk]) && $_GET['adv_txt_'.$bk] !== '') {
                        if (!isset($adv_text[$bk])) { $adv_text[$bk] = sanitize_text_field($_GET['adv_txt_'.$bk]); }
                    }
                } else {
                    if (isset($_GET['adv_sel_'.$bk]) && $_GET['adv_sel_'.$bk] !== '') {
                        $valSel = sanitize_text_field($_GET['adv_sel_'.$bk]);
                        if (!isset($adv_select_meta[$bk])) { $adv_select_meta[$bk] = $valSel; }
                    }
                }
            }
        }
    }
} catch (\Throwable $e) {
    // Fail silently; basic search merge is best-effort
}

$controller = new TPW_Member_Controller();
$all_statuses = $is_admin ? $controller->get_statuses() : [];
$args = [
    'search'   => $search_query,
    'page'     => $page,
    'per_page' => $per_page,
];
if ( ! empty($has_value_selected) ) {
    $args['has_value'] = $has_value_selected;
}
if ( ! empty($adv_text) ) { $args['adv_text'] = $adv_text; }
if ( ! empty($adv_select) ) { $args['adv_select'] = $adv_select; }
if ( ! empty($adv_date_range) ) { $args['adv_date_range'] = $adv_date_range; }
if ( ! empty($adv_checkbox) ) { $args['adv_checkbox'] = $adv_checkbox; }
if ( ! empty($adv_select_meta) ) { $args['adv_select_meta'] = $adv_select_meta; }
if ( ! $is_admin ) {
    // Limit directory to allowed statuses for members
    if ( class_exists('TPW_Member_Access') && method_exists('TPW_Member_Access', 'get_allowed_statuses') ) {
        $args['status_in'] = TPW_Member_Access::get_allowed_statuses();
    } else {
        $args['status_in'] = TPW_Member_Access::ALLOWED_STATUSES; // fallback
    }
}
// Admins can filter by any existing status
if ( $is_admin && $selected_status !== '' ) {
    $args['status'] = $selected_status;
}
// Recompute hide flag: if any filter besides plain empty search is present, do not hide
$has_any_filters = (
    $search_query !== '' ||
    ($is_admin && $selected_status !== '') ||
    !empty($has_value_selected) ||
    !empty($adv_text) || !empty($adv_select) || !empty($adv_date_range) || !empty($adv_checkbox) || !empty($adv_select_meta)
);
if ( $has_any_filters ) { $hide_initial = false; }
// Allow a bypass via URL param ?show_all=1
if ( isset($_GET['show_all']) && $_GET['show_all'] === '1' ) { $hide_initial = false; }
$members = $hide_initial ? [] : $controller->get_members( $args );
// Determine current view (computed earlier)
$current_view = $initial_view;
// $hide_initial is computed above
?>
<div class="tpw-table-container">
<div class="tpw-members-list">
    <?php if ( $is_admin ) : ?>
    <?php
        // Build Export CSV URL, preserving current filters
        $export_args = [
            'action'   => 'export_csv',
            'search'   => $search_query,
            'status'   => $is_admin ? $selected_status : '',
            '_wpnonce' => wp_create_nonce('tpw_export_members'),
        ];
        if (!empty($has_value_selected)) {
            foreach ($has_value_selected as $hv) { $export_args['has_' . $hv] = '1'; }
        }
        $export_url = add_query_arg(
            array_filter($export_args, function($v){ return $v !== '' && $v !== null; }),
            get_permalink()
        );
    ?>
    <div class="tpw-top-actions">
        <div class="tpw-action-group">
            <div class="tpw-action-group__title">Admin</div>
            <div class="tpw-action-group__buttons">
                <button type="button" class="tpw-btn tpw-btn-primary tpw-nav-btn" data-href="<?php echo esc_url( add_query_arg( 'action', 'add', get_permalink() ) ); ?>">Add New Member</button>
                <button id="tpw-link-user-btn" class="tpw-btn tpw-btn-secondary">Create from WP User</button>
                <?php
                /**
                 * Fires at the end of the Admin button group on /manage-members/.
                 *
                 * Use this hook to output additional action buttons that will
                 * appear next to the built‑in Admin actions (e.g. "Add New Member").
                 *
                 * The callback should echo HTML for any buttons/links. Use
                 * class "tpw-btn" with one of the variants (tpw-btn-primary,
                 * tpw-btn-secondary, tpw-btn-light, tpw-btn-admin) for visual
                 * consistency.
                 *
                 * @since 1.0.0 Add hook to extend Admin buttons collection
                 * @param array $context Useful context for building URLs.
                 *                        Keys: page_url, current_view, selected_status,
                 *                        search, per_page, is_admin.
                 */
                do_action( 'tpw_members_admin_buttons_end', [
                    'page_url'        => get_permalink(),
                    'current_view'    => $current_view,
                    'selected_status' => $selected_status,
                    'search'          => $search_query,
                    'per_page'        => $per_page,
                    'is_admin'        => $is_admin,
                ] );
                ?>
            </div>
        </div>
        <div class="tpw-action-group">
            <div class="tpw-action-group__title">Tools</div>
            <div class="tpw-action-group__buttons">
                <button type="button" class="tpw-btn tpw-btn-secondary tpw-nav-btn" data-href="<?php echo esc_url( $export_url ); ?>">Export CSV</button>
                <button type="button" class="tpw-btn tpw-btn-secondary tpw-nav-btn" data-href="<?php echo esc_url( add_query_arg( 'action', 'import_csv', get_permalink() ) ); ?>">Import CSV</button>
                <button type="button" class="tpw-btn tpw-btn-secondary tpw-btn-admin tpw-nav-btn" data-href="?action=settings">Member Settings</button>
                <?php
                /**
                 * Fires at the end of the Tools button group on /manage-members/.
                 *
                 * Use this hook to output additional tools (buttons/links) to
                 * appear after the built‑in tools (Export/Import/Settings).
                 *
                 * @since 1.0.0 Add hook to extend Tools buttons collection
                 * @param array $context Useful context for building URLs.
                 *                        Keys: page_url, export_url, current_view,
                 *                        selected_status, search, per_page, is_admin.
                 */
                do_action( 'tpw_members_tools_buttons_end', [
                    'page_url'        => get_permalink(),
                    'export_url'      => $export_url,
                    'current_view'    => $current_view,
                    'selected_status' => $selected_status,
                    'search'          => $search_query,
                    'per_page'        => $per_page,
                    'is_admin'        => $is_admin,
                ] );
                ?>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var navBtns = document.querySelectorAll('.tpw-nav-btn');
        navBtns.forEach(function(b){
            b.addEventListener('click', function(){
                var href = b.getAttribute('data-href');
                if (href) window.location.href = href;
            });
        });
    })();
    </script>
    <?php endif; ?>
    <h2>All Members</h2>
    <?php if ( isset($_GET['saved']) && $_GET['saved'] === '1' ): ?>
        <div class="notice notice-success" style="margin:10px 0;"><p>Member Saved Successfully</p></div>
    <?php endif; ?>

    <?php if ( $is_admin ) : ?>
    <div id="tpw-link-user-modal" class="tpw-member-link-modal" hidden>
        <div class="tpw-member-link-modal__dialog">
            <div class="tpw-member-link-modal__header">
                <h3>Create from WP User</h3>
                <button type="button" class="tpw-btn tpw-btn-light" id="tpw-link-user-close">Close</button>
            </div>
            <div class="tpw-member-link-modal__body">
                <input type="text" id="tpw-link-user-search" placeholder="Search users by name, login or email..." />
                <div id="tpw-link-user-results" class="tpw-member-link-results">Type to search users...</div>
                <div class="tpw-member-link-selected">Selected: <span id="tpw-selected-user">None</span></div>
            </div>
            <div class="tpw-member-link-modal__footer">
                <button type="button" class="tpw-btn tpw-btn-primary" id="tpw-link-user-confirm" disabled>Create Member</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $is_admin ) : ?>
    <?php endif; ?>

    <form method="get" action="<?php echo esc_url( get_permalink() ); ?>" class="tpw-member-search-form tpw-admin-ui" style="margin-bottom: 20px;">
        <input type="hidden" name="view" value="<?php echo esc_attr($current_view); ?>">
        <div class="tpw-card tpw-section-card" style="margin-bottom:12px;">
            <div class="tpw-section-title" style="display:flex; align-items:center; gap:8px;">
                <span aria-hidden="true">🔍</span>
                <span>Member Search</span>
            </div>
            <div class="tpw-basic-search" style="display:flex; flex-wrap:wrap; gap:10px; margin:10px 0;">
                <div class="tpw-basic-item" style="display:flex; flex-direction:column; gap:6px; min-width:220px; flex:1 1 260px;">
                    <label for="tpw-filter-search"><strong>Search members</strong></label>
                    <input id="tpw-filter-search" type="text" name="search" placeholder="Search members..." value="<?php echo esc_attr($search_query); ?>">
                </div>
                <?php /* Basic search fields follow below; moved buttons after them */ ?>
            </div>
        <?php
            // Render Basic Search fields (visible inline) based on tpw_field_settings.basic_search = 1
            global $wpdb; $fs_tbl = $wpdb->prefix . 'tpw_field_settings';
            $basic_rows = $wpdb->get_results( "SELECT field_key, custom_label FROM {$fs_tbl} WHERE is_enabled = 1 AND basic_search = 1 ORDER BY sort_order ASC" );
            $members_table = $wpdb->prefix . 'tpw_members';
            $tpw_cols_inline = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $members_table, 0 );
            // Visibility guard for members
            $can_view = function($key) use ($is_admin, $vis_group) {
                if ($is_admin) return true;
                if ( function_exists('tpw_can_group_view_field') ) {
                    return tpw_can_group_view_field($vis_group, $key);
                }
                return true;
            };
            if (!empty($basic_rows)):
        ?>
        <div class="tpw-basic-search" style="display:flex; flex-wrap:wrap; gap:10px; margin:10px 0;">
            <?php foreach ($basic_rows as $r):
                $bk = sanitize_key($r->field_key);
                if (!$can_view($bk)) continue;
                $label = $r->custom_label ?: ucwords(str_replace('_',' ', $bk));
                $val_text = isset($_GET['adv_txt_'.$bk]) ? sanitize_text_field($_GET['adv_txt_'.$bk]) : '';
                $val_select = isset($_GET['adv_sel_'.$bk]) ? sanitize_text_field($_GET['adv_sel_'.$bk]) : '';
                // Decide control type: reuse existing config if present; default to text
                $searchable_opt_inline = get_option('tpw_member_searchable_fields', []);
                $stype_inline = isset($searchable_opt_inline[$bk]['search_type']) ? $searchable_opt_inline[$bk]['search_type'] : 'text';
                $source_inline = isset($searchable_opt_inline[$bk]['options_source']) ? $searchable_opt_inline[$bk]['options_source'] : 'static';
                $options_inline = isset($searchable_opt_inline[$bk]['options']) ? (array) $searchable_opt_inline[$bk]['options'] : [];
                $depends_on_inline = isset($searchable_opt_inline[$bk]['depends_on']) ? sanitize_key($searchable_opt_inline[$bk]['depends_on']) : '';
            ?>
                <div class="tpw-basic-item" style="display:flex; flex-direction:column; gap:6px; min-width:220px; flex:1 1 220px;">
                    <label><strong><?php echo esc_html($label); ?></strong></label>
                    <?php if ($stype_inline === 'select'):
                        $opts = [];
                        if ($source_inline === 'dynamic') {
                            // Build options dynamically from DB (core column or meta)
                            if (in_array($bk, $tpw_cols_inline, true)) {
                                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                $sql = "SELECT DISTINCT `{$bk}` AS v FROM {$members_table} WHERE `{$bk}` IS NOT NULL AND `{$bk}` <> '' ORDER BY `{$bk}`";
                                $opts = (array) $wpdb->get_col($sql);
                            } else {
                                $meta_table = $wpdb->prefix . 'tpw_member_meta';
                                $rows = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value AS v FROM {$meta_table} WHERE meta_key = %s AND meta_value IS NOT NULL AND meta_value <> '' ORDER BY meta_value", $bk ) );
                                $opts = (array) $rows;
                            }
                        } else {
                            $opts = $options_inline;
                        }
                    ?>
                        <?php
                            // Only disable dependent child selects if no preselected value exists
                            $is_child_dep = !empty($depends_on_inline);
                            $disable_child_attr = ($is_child_dep && $val_select === '') ? ' disabled' : '';
                        ?>
                        <select name="<?php echo esc_attr('adv_sel_'.$bk); ?>" data-field-key="<?php echo esc_attr($bk); ?>"<?php echo $is_child_dep ? ' data-depends-on="'.esc_attr($depends_on_inline).'"' : ''; ?><?php echo $disable_child_attr; ?>>
                            <option value="">— Any —</option>
                            <?php foreach ($opts as $opt): ?>
                                <option value="<?php echo esc_attr($opt); ?>" <?php selected($val_select, (string)$opt); ?>><?php echo esc_html($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($stype_inline === 'date_range' && in_array($bk, $tpw_cols_inline, true)):
                        $from = isset($_GET['adv_from_'.$bk]) ? sanitize_text_field($_GET['adv_from_'.$bk]) : '';
                        $to   = isset($_GET['adv_to_'.$bk]) ? sanitize_text_field($_GET['adv_to_'.$bk]) : '';
                    ?>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="text" name="<?php echo esc_attr('adv_from_'.$bk); ?>" value="<?php echo esc_attr($from); ?>" placeholder="From" class="tpw-adv-date" style="width:120px;" data-field-key="<?php echo esc_attr($bk); ?>" />
                            <span>to</span>
                            <input type="text" name="<?php echo esc_attr('adv_to_'.$bk); ?>" value="<?php echo esc_attr($to); ?>" placeholder="To" class="tpw-adv-date" style="width:120px;" data-field-key="<?php echo esc_attr($bk); ?>" />
                        </div>
                    <?php elseif ($stype_inline === 'checkbox' && in_array($bk, $tpw_cols_inline, true)):
                        $isChecked = isset($_GET[$bk]) && $_GET[$bk] === '1';
                    ?>
                        <label style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="<?php echo esc_attr($bk); ?>" value="1" <?php checked($isChecked); ?> data-field-key="<?php echo esc_attr($bk); ?>" />
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php elseif ($stype_inline === 'has_value'):
                        $isChecked = isset($_GET['has_'.$bk]) && $_GET['has_'.$bk] === '1';
                    ?>
                        <label style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="<?php echo esc_attr('has_'.$bk); ?>" value="1" <?php checked($isChecked); ?> data-field-key="<?php echo esc_attr($bk); ?>" />
                            <span><?php echo esc_html('Has ' . $label); ?></span>
                        </label>
                    <?php else: // default text for core columns only ?>
                        <?php if (in_array($bk, $tpw_cols_inline, true)): ?>
                            <input type="text" name="<?php echo esc_attr('adv_txt_'.$bk); ?>" value="<?php echo esc_attr($val_text); ?>" class="regular-text" data-field-key="<?php echo esc_attr($bk); ?>" />
                        <?php else: ?>
                            <input type="text" name="<?php echo esc_attr('adv_sel_'.$bk); ?>" value="<?php echo esc_attr($val_select); ?>" class="regular-text" data-field-key="<?php echo esc_attr($bk); ?>" />
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
            <div class="tpw-actions" style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="tpw-btn tpw-btn-primary">Search</button>
                <button type="button" class="tpw-btn tpw-btn-light" id="tpw-clear-filters">Clear</button>
                <?php if ( $is_admin || $adv_for_members ) : ?>
                    <button type="button" class="tpw-btn tpw-btn-secondary" id="open-advanced-search">Advanced Search</button>
                <?php endif; ?>
            </div>
        </div> <!-- .tpw-card -->
        <?php endif; ?>
        <div class="tpw-filter-row">
            <?php if ( $is_admin ): ?>
            <label for="tpw-filter-status">Status:</label>
            <select id="tpw-filter-status" name="status" onchange="this.form.submit()">
                <option value="" <?php selected($selected_status, ''); ?>>All statuses</option>
                <?php foreach ( $all_statuses as $st ): ?>
                    <option value="<?php echo esc_attr($st); ?>" <?php selected($selected_status, $st); ?>><?php echo esc_html($st); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <label for="tpw-filter-per-page">Per page:</label>
            <select id="tpw-filter-per-page" name="per_page" onchange="this.form.submit()" <?php echo $hide_initial ? 'disabled' : ''; ?>>
                <?php
                    // Use grid-friendly options in card view; classic options in list view
                    $options_pp = ($current_view === 'card') ? [8,16,24] : [10,25,50,100];
                    // Always include site default if non-standard and > 0
                    if ($default_per_page > 0 && !in_array($default_per_page, $options_pp, true)) { $options_pp[] = (int)$default_per_page; }
                    // Ensure current selection is present so it stays selected when toggling views
                    if ($per_page > 0 && !in_array((int)$per_page, $options_pp, true)) { $options_pp[] = (int)$per_page; }
                    sort($options_pp);
                    $options_pp = array_values(array_unique(array_map('intval', $options_pp)));
                    foreach ($options_pp as $pp): ?>
                    <option value="<?php echo $pp; ?>" <?php selected($per_page, $pp); ?>><?php echo $pp; ?></option>
                <?php endforeach; ?>
            </select>
            <button
                type="button"
                id="tpw-toggle-view-btn"
                class="tpw-btn tpw-btn-light button-icon <?php echo $current_view === 'card' ? 'is-card' : 'is-list'; ?>"
                aria-pressed="<?php echo $current_view === 'card' ? 'true' : 'false'; ?>"
                aria-label="<?php echo $current_view === 'card' ? 'Switch to List View' : 'Switch to Card View'; ?>"
                title="<?php echo $current_view === 'card' ? 'Switch to List View' : 'Switch to Card View'; ?>"
                <?php echo $hide_initial ? 'disabled' : ''; ?>
            >
                <span class="screen-reader-text"><?php echo $current_view === 'card' ? 'Switch to List View' : 'Switch to Card View'; ?></span>
                <!-- List icon (3 lines) shows when target is list (current is card) -->
                <svg class="icon icon-list" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z"></path></svg>
                <!-- Grid icon (2x2) shows when target is card (current is list) -->
                <svg class="icon icon-grid" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"></path></svg>
            </button>
        </div>
                </form>
                <script>
                // Mandatory adaptive per-page options: always recalc based on current view and grid columns
                (function(){
                    function debounce(fn, wait){ let t; return function(){ clearTimeout(t); t=setTimeout(fn, wait); }; }
                    function isCardViewVisible(){
                        var grid = document.getElementById('tpw-card-view');
                        if (!grid) return false;
                        var style = window.getComputedStyle(grid);
                        return style && style.display !== 'none';
                    }
                    function calcColumns(){
                        var grid = document.getElementById('tpw-card-view');
                        if (!grid) return 0;
                        var cards = grid.querySelectorAll('.member-card');
                        if (!cards.length) return 0;
                        var firstTop = cards[0].getBoundingClientRect().top;
                        var cols = 0;
                        for (var i=0;i<cards.length;i++){
                            var rectTop = cards[i].getBoundingClientRect().top;
                            if (Math.abs(rectTop - firstTop) < 2) cols++; else break;
                        }
                        return cols;
                    }
                    function getOptionsForList(){ return [10,25,50,100]; }
                    function getOptionsForCols(cols){
                        // Map exact column counts per acceptance criteria
                        if (cols >= 4) return [8,16,24,48];
                        if (cols === 3) return [9,15,21,30];
                        if (cols === 2) return [6,12,18,24];
                        // Fallback for <=1 or unknown: reasonable mobile set
                        return [6,10,14,20];
                    }
                    function arraysEqual(a,b){ if (a.length!==b.length) return false; for(var i=0;i<a.length;i++){ if (a[i]!==b[i]) return false; } return true; }
                                function rebuildSelect(sel, values, preserve){
                                    var current = parseInt(sel.value, 10) || 0;
                        while (sel.options.length) sel.remove(0);
                        var found = false;
                        values.forEach(function(v){
                            var o = document.createElement('option'); o.value = String(v); o.textContent = String(v);
                            if (preserve && current === v){ o.selected = true; found = true; }
                            sel.add(o);
                        });
                                    if (preserve && !found){ sel.value = String(values[0]); }
                                    return { before: current, after: parseInt(sel.value, 10) || 0 };
                    }
                    function readSelectValues(sel){ var out=[]; for(var i=0;i<sel.options.length;i++){ out.push(parseInt(sel.options[i].value,10)); } return out; }
                    var hasSubmittedAdapt = false; // prevent multiple rapid submits
                    var isInitialLoad = true; // do not submit on first render
                    function adaptPerPage(){
                        var sel = document.getElementById('tpw-filter-per-page');
                        if (!sel) return;
                        var cardVisible = isCardViewVisible();
                        var targetValues;
                        if (cardVisible){
                            var cols = calcColumns();
                            // If columns cannot be measured (e.g., no cards yet), guess based on container width
                            if (!cols){
                                var grid = document.getElementById('tpw-card-view');
                                var w = grid ? grid.clientWidth : window.innerWidth;
                                cols = (w >= 1200) ? 4 : (w >= 768 ? 3 : 2);
                            }
                            targetValues = getOptionsForCols(cols);
                        } else {
                            targetValues = getOptionsForList();
                        }
                                    var currentValues = readSelectValues(sel);
                                    var changed = false;
                                    var delta = { before: parseInt(sel.value,10)||0, after: parseInt(sel.value,10)||0 };
                                    if (!arraysEqual(currentValues, targetValues)){
                                        delta = rebuildSelect(sel, targetValues, true);
                                        changed = (delta.before !== delta.after);
                                    } else {
                                        // Ensure selection remains valid; if not, default to first
                                        var valid = targetValues.indexOf(parseInt(sel.value,10)) !== -1;
                                        if (!valid) { sel.value = String(targetValues[0]); changed = true; delta.after = parseInt(sel.value,10)||0; }
                                    }
                                    // If selection changed due to adaptation, submit form to refresh results count
                                    if (changed && !hasSubmittedAdapt && !isInitialLoad) {
                                        hasSubmittedAdapt = true;
                                        var form = document.querySelector('.tpw-member-search-form');
                                        if (form) form.submit();
                                    }
                    }
                                var run = debounce(adaptPerPage, 80);
                    // Do not auto-submit on initial load; just recalc options silently
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', function(){
                            run();
                            hasSubmittedAdapt = false;
                            // flip initial flag after a tick so dependent dropdowns can initialize
                            setTimeout(function(){ isInitialLoad = false; }, 250);
                        });
                    } else {
                        run();
                        hasSubmittedAdapt = false;
                        setTimeout(function(){ isInitialLoad = false; }, 250);
                    }
                    window.addEventListener('resize', run);
                                var toggle = document.getElementById('tpw-toggle-view-btn');
                            if (toggle){ toggle.addEventListener('click', function(){ setTimeout(run, 150); }); }
                            // When options change and selected value changes due to adaptation, submit the form to update results count
                            var sel = document.getElementById('tpw-filter-per-page');
                            function maybeSubmit(){
                                if (!sel) return;
                                if (hasSubmittedAdapt) return;
                                // Trigger submit only if value changed programmatically and matches a defined option
                                var form = document.querySelector('.tpw-member-search-form');
                                if (form) { if (!isInitialLoad) { hasSubmittedAdapt = true; form.submit(); } }
                            }
                            // Rebuild wrapper overrides selection and we set value; hook after each run
                            var origRun = run;
                            run = debounce(function(){ origRun(); maybeSubmit(); }, 0);
                })();
                </script>
    <?php if ($is_admin || $adv_for_members): ?>
        <!-- Advanced Search Modal -->
        <div id="tpw-advanced-search-modal" class="tpw-dir-modal" hidden>
            <div class="tpw-dir-modal__dialog" style="max-width:720px;">
                <div class="tpw-dir-modal__header">
                    <h3>Advanced Search</h3>
                    <button type="button" class="tpw-btn tpw-btn-light tpw-dir-modal-close" id="tpw-adv-close">Close</button>
                </div>
                <div class="tpw-dir-modal__body">
                    <form method="get" action="<?php echo esc_url( get_permalink() ); ?>" id="tpw-advanced-search-form">
                        <input type="hidden" name="view" value="<?php echo esc_attr($current_view); ?>">
                        <input type="hidden" name="search" value="<?php echo esc_attr($search_query); ?>">
                        <input type="hidden" name="status" value="<?php echo esc_attr($selected_status); ?>">
                        <input type="hidden" name="per_page" value="<?php echo (int)$per_page; ?>">
                        <div class="tpw-adv-grid" style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($searchable_opt as $key => $conf):
                            $stype = isset($conf['search_type']) ? $conf['search_type'] : 'text';
                            $label = !empty($conf['label']) ? $conf['label'] : $key;
                            $admin_only = !empty($conf['admin_only']);
                            $key_sane = sanitize_key($key);
                            if ($admin_only && !$is_admin) continue;
                            if ($stype === 'text'):
                                // Text filters are only supported for core columns
                                if (!in_array($key_sane, $tpw_cols, true)) { continue; }
                                $val = isset($_GET['adv_txt_'.$key_sane]) ? sanitize_text_field($_GET['adv_txt_'.$key_sane]) : '';
                        ?>
                            <div class="tpw-adv-row" style="display:flex; flex-direction:column; gap:6px;">
                                <label><strong><?php echo esc_html($label); ?></strong></label>
                                <input type="text" name="<?php echo esc_attr('adv_txt_'.$key_sane); ?>" value="<?php echo esc_attr($val); ?>" class="regular-text" data-field-key="<?php echo esc_attr($key_sane); ?>" />
                            </div>
                        <?php elseif ($stype === 'select'):
                                $options = [];
                                $val = isset($_GET['adv_sel_'.$key_sane]) ? sanitize_text_field($_GET['adv_sel_'.$key_sane]) : '';
                                $source = isset($conf['options_source']) ? $conf['options_source'] : 'static';
                                if ($source === 'dynamic') {
                                    global $wpdb; $members_table = $wpdb->prefix . 'tpw_members';
                                    $cache_key = 'tpw_dynamic_options_' . $key_sane;
                                    $options = get_transient($cache_key);
                                    if ($options === false) {
                                        // Determine source: core column or custom meta
                                        $tpw_cols_dyn = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $members_table, 0 );
                                        if (in_array($key_sane, $tpw_cols_dyn, true)) {
                                            // Core column: distinct values from members table
                                            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                            $sql = "SELECT DISTINCT `{$key_sane}` AS v FROM {$members_table} WHERE `{$key_sane}` IS NOT NULL AND `{$key_sane}` <> '' ORDER BY `{$key_sane}`";
                                            $rows = $wpdb->get_col($sql);
                                            $options = array_values( array_filter( array_map('strval', (array) $rows ) ) );
                                        } else {
                                            // Custom field: distinct values from member meta
                                            $meta_table = $wpdb->prefix . 'tpw_member_meta';
                                            // Prepared statement: filter by meta_key (string)
                                            $sql = "SELECT DISTINCT meta_value AS v FROM {$meta_table} WHERE meta_key = %s AND meta_value IS NOT NULL AND meta_value <> '' ORDER BY meta_value";
                                            $rows = $wpdb->get_col( $wpdb->prepare( $sql, $key_sane ) );
                                            $options = array_values( array_filter( array_map('strval', (array) $rows ) ) );
                                            // Use a different cache scope? We can reuse the same cache key per field key safely
                                        }
                                        set_transient($cache_key, $options, 6 * HOUR_IN_SECONDS);
                                    }
                                } else {
                                    $options = isset($conf['options']) && is_array($conf['options']) ? $conf['options'] : [];
                                }
                        ?>
                            <div class="tpw-adv-row" style="display:flex; flex-direction:column; gap:6px;">
                                <label><strong><?php echo esc_html($label); ?></strong></label>
                                <?php $depends_on_adv = isset($conf['depends_on']) ? sanitize_key($conf['depends_on']) : ''; ?>
                                <?php
                                    // Only disable dependent child selects if no preselected value exists
                                    $is_child_adv = !empty($depends_on_adv);
                                    $disable_child_adv_attr = ($is_child_adv && $val === '') ? ' disabled' : '';
                                ?>
                                <select name="<?php echo esc_attr('adv_sel_'.$key_sane); ?>" data-field-key="<?php echo esc_attr($key_sane); ?>"<?php echo $is_child_adv ? ' data-depends-on="'.esc_attr($depends_on_adv).'"' : ''; ?><?php echo $disable_child_adv_attr; ?>>
                                    <option value="">— Any —</option>
                                    <?php foreach ($options as $opt): ?>
                                        <option value="<?php echo esc_attr($opt); ?>" <?php selected($val, $opt); ?>><?php echo esc_html($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif ($stype === 'date_range'):
                                // Date range filters are only supported for core columns
                                if (!in_array($key_sane, $tpw_cols, true)) { continue; }
                                $from = isset($_GET['adv_from_'.$key_sane]) ? sanitize_text_field($_GET['adv_from_'.$key_sane]) : '';
                                $to   = isset($_GET['adv_to_'.$key_sane]) ? sanitize_text_field($_GET['adv_to_'.$key_sane]) : '';
                        ?>
                            <div class="tpw-adv-row" style="display:flex; flex-direction:column; gap:6px;">
                                <label><strong><?php echo esc_html($label); ?></strong></label>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <input type="text" name="<?php echo esc_attr('adv_from_'.$key_sane); ?>" value="<?php echo esc_attr($from); ?>" placeholder="From" class="tpw-adv-date" style="width:120px;" data-field-key="<?php echo esc_attr($key_sane); ?>" />
                                    <span>to</span>
                                    <input type="text" name="<?php echo esc_attr('adv_to_'.$key_sane); ?>" value="<?php echo esc_attr($to); ?>" placeholder="To" class="tpw-adv-date" style="width:120px;" data-field-key="<?php echo esc_attr($key_sane); ?>" />
                                </div>
                            </div>
                        <?php elseif ($stype === 'has_value'):
                                // Has value is supported for both core and custom meta; UI is always allowed
                                $checked = isset($_GET['has_'.$key_sane]) && $_GET['has_'.$key_sane] === '1';
                        ?>
                            <div class="tpw-adv-row" style="display:flex; flex-direction:column; gap:6px;">
                                <label style="display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" name="<?php echo esc_attr('has_'.$key_sane); ?>" value="1" <?php checked($checked); ?> data-field-key="<?php echo esc_attr($key_sane); ?>" />
                                    <span><strong><?php echo esc_html('Has ' . $label); ?></strong></span>
                                </label>
                            </div>
                        <?php elseif ($stype === 'checkbox'):
                                // Boolean checkbox filters are only supported for core columns
                                if (!in_array($key_sane, $tpw_cols, true)) { continue; }
                                $isChecked = isset($_GET[$key_sane]) && $_GET[$key_sane] === '1';
                        ?>
                            <div class="tpw-adv-row" style="display:flex; flex-direction:column; gap:6px;">
                                <label style="display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" name="<?php echo esc_attr($key_sane); ?>" value="1" <?php checked($isChecked); ?> data-field-key="<?php echo esc_attr($key_sane); ?>" />
                                    <span><strong><?php echo esc_html($label); ?></strong></span>
                                </label>
                            </div>
                        <?php endif; endforeach; ?>
                        </div>
                        <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="submit" class="tpw-btn tpw-btn-primary">Apply Filters</button>
                            <button type="button" class="tpw-btn tpw-btn-light" id="tpw-adv-clear">Clear Filters</button>
                            <button type="button" class="tpw-btn tpw-btn-secondary" id="tpw-adv-cancel">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var btn = document.getElementById('open-advanced-search');
            var modal = document.getElementById('tpw-advanced-search-modal');
            var closeBtns = [document.getElementById('tpw-adv-close'), document.getElementById('tpw-adv-cancel')].filter(Boolean);
            function open(){ if(modal) modal.removeAttribute('hidden'); }
            function close(){ if(modal) modal.setAttribute('hidden',''); }
            if (btn) btn.addEventListener('click', open);
            closeBtns.forEach(function(b){ if(b) b.addEventListener('click', close); });

            // Clear Filters: wipe all advanced inputs and redirect to base permalink (preserving non-advanced params like view/per_page)
            var clearBtn = document.getElementById('tpw-adv-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function(){
                    try {
                        var base = new URL(<?php echo json_encode( esc_url( get_permalink() ) ); ?>);
                        // Preserve optional baseline (view/per_page) from current form hidden inputs
                        var form = document.getElementById('tpw-advanced-search-form');
                        if (form) {
                            var view = form.querySelector('input[name="view"]');
                            var per = form.querySelector('input[name="per_page"]');
                            var status = form.querySelector('input[name="status"]');
                            var search = form.querySelector('input[name="search"]');
                            if (view && view.value) base.searchParams.set('view', view.value);
                            if (per && per.value) base.searchParams.set('per_page', per.value);
                            if (status && status.value) base.searchParams.set('status', status.value);
                            if (search && search.value) base.searchParams.set('search', search.value);
                        }
                        window.location.href = base.toString();
                    } catch(e) {
                        // Fallback
                        window.location.href = <?php echo json_encode( esc_url( get_permalink() ) ); ?>;
                    }
                });
            }
        })();
        </script>
        <script>
        // Dependency logic rewrite: listen on any select with data-field-key; update children on change; rebind per modal open
        (function(){
            const ajaxUrl = <?php echo json_encode( esc_url( admin_url('admin-ajax.php') ) ); ?>;

            function clearChild(childSel){
                if (!childSel) return;
                // Preserve first placeholder option if exists, remove others
                while (childSel.options.length > 1) childSel.remove(1);
            }

            function setNoOptions(childSel){
                if (!childSel) return;
                // Remove all then add single disabled message
                while (childSel.options.length) childSel.remove(0);
                childSel.add(new Option('No options found',''));
                childSel.setAttribute('disabled','disabled');
            }

            function fetchAndPopulate(childSel, parentKey, parentVal){
                const childKey = childSel.getAttribute('data-field-key');
                if (!childKey) return;
                // Preserve current selection before rebuilding
                const prevSelected = (childSel.value || '').toString();
                childSel.setAttribute('disabled','disabled');
                clearChild(childSel);
                // When parentVal is empty/'Any', we still fetch all child values
                if (!parentVal || parentVal.toLowerCase() === 'any') {
                    parentVal = ''; // backend treats blank as no filter
                }
                const fd = new FormData();
                fd.append('action','tpw_member_dependent_options');
                fd.append('field', childKey);
                fd.append('depends_on_field', parentKey);
                fd.append('depends_on_value', parentVal);
                fetch(ajaxUrl, { method:'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (!data || !data.success || !data.data || !Array.isArray(data.data.options)) { setNoOptions(childSel); return; }
                        const opts = data.data.options;
                        if (opts.length === 0){ setNoOptions(childSel); return; }
                        // Re-add placeholder first
                        childSel.add(new Option('— Any —',''));
                        opts.forEach(o => {
                            if (o && typeof o.value !== 'undefined') {
                                childSel.add(new Option(o.label, o.value));
                            }
                        });
                        // Attempt to restore previous selection if still present
                        if (prevSelected && prevSelected !== '') {
                            childSel.value = prevSelected;
                        }
                        // Enable only if there is at least one real option beyond placeholder
                        if (childSel.options.length > 1) {
                            childSel.removeAttribute('disabled');
                        } else {
                            setNoOptions(childSel);
                        }
                    })
                    .catch(() => { setNoOptions(childSel); });
            }

            function bindScope(scope){
                if (!scope) return;
                // For each parent select, listen for changes
                const allSelects = scope.querySelectorAll('select[data-field-key]');
                allSelects.forEach(sel => {
                    sel.addEventListener('change', onParentChange);
                });
                // Initialize children based on current parent values
                const childSelects = scope.querySelectorAll('select[data-depends-on]');
                childSelects.forEach(child => {
                    const parentKey = child.getAttribute('data-depends-on');
                    if (!parentKey) return;
                    const parentEl = scope.querySelector('select[data-field-key="'+parentKey+'"]');
                    if (!parentEl) return;
                    fetchAndPopulate(child, parentKey, parentEl.value.trim());
                });
            }

            function onParentChange(e){
                const parentSel = e.target;
                if (!parentSel || !parentSel.matches('select[data-field-key]')) return;
                const parentKey = parentSel.getAttribute('data-field-key');
                let parentVal = parentSel.value.trim();
                // Find child selects anywhere in document that depend on this parent within same logical scope (basic or modal)
                // Determine scope: traverse up to advanced modal or basic search container
                let scope = parentSel.closest('#tpw-advanced-search-modal, .tpw-basic-search');
                if (!scope) scope = document; // fallback global
                const childSelects = scope.querySelectorAll('select[data-depends-on="'+parentKey+'"]');
                childSelects.forEach(child => {
                    // Always fetch (blank parent returns all options)
                    if (!parentVal || parentVal.toLowerCase() === 'any') parentVal = '';
                    fetchAndPopulate(child, parentKey, parentVal);
                });
            }

            document.addEventListener('DOMContentLoaded', function(){
                // Bind for every basic search section (search text and field filters)
                const basicScopes = document.querySelectorAll('.tpw-basic-search');
                basicScopes.forEach(function(scope){ bindScope(scope); });
                const advBtn = document.getElementById('open-advanced-search');
                if (advBtn){
                    advBtn.addEventListener('click', function(){
                        const modal = document.getElementById('tpw-advanced-search-modal');
                        if (!modal) return;
                        // Delay to allow modal content injection (if rebuilt)
                        setTimeout(() => { bindScope(modal); }, 50);
                    });
                }

                // Ensure dependent selects with a chosen value are enabled on submit so values are included
                try {
                    var mainForm = document.querySelector('.tpw-member-search-form');
                    if (mainForm){
                        mainForm.addEventListener('submit', function(){
                            var sels = mainForm.querySelectorAll('select[data-depends-on]');
                            sels.forEach(function(s){ if (s.hasAttribute('disabled') && s.value && s.value !== '') { s.removeAttribute('disabled'); } });
                        });
                    }
                    var advForm = document.getElementById('tpw-advanced-search-form');
                    if (advForm){
                        advForm.addEventListener('submit', function(){
                            var sels = advForm.querySelectorAll('select[data-depends-on]');
                            sels.forEach(function(s){ if (s.hasAttribute('disabled') && s.value && s.value !== '') { s.removeAttribute('disabled'); } });
                        });
                    }
                } catch(e){}
            });
        })();
        </script>
        <?php endif; ?>
        <script>
        (function(){
            var btn = document.getElementById('tpw-clear-filters');
            if (!btn) return;
            btn.addEventListener('click', function(ev){
                ev.preventDefault();
                window.location.href = <?php echo json_encode( esc_url( get_permalink() ) ); ?>;
            });
        })();
        </script>

        <script>
        (function(){
            function init(){
                var toggleBtn = document.getElementById('tpw-toggle-view-btn');
                if (!toggleBtn) return;
                var formHidden = document.querySelector('.tpw-member-search-form input[name="view"]');
                var current = <?php echo json_encode($current_view); ?>;

                function setView(view, pushUrl){
                    var listView = document.getElementById('tpw-list-view');
                    var cardView = document.getElementById('tpw-card-view');
                    if (view === 'card'){
                        if (listView) listView.style.display = 'none';
                        if (cardView) cardView.style.display = '';
                        toggleBtn.classList.add('is-card');
                        toggleBtn.classList.remove('is-list');
                        toggleBtn.setAttribute('aria-pressed','true');
                        toggleBtn.setAttribute('aria-label','Switch to List View');
                        toggleBtn.setAttribute('title','Switch to List View');
                        var srt = toggleBtn.querySelector('.screen-reader-text'); if (srt) srt.textContent = 'Switch to List View';
                    } else {
                        if (cardView) cardView.style.display = 'none';
                        if (listView) listView.style.display = '';
                        toggleBtn.classList.add('is-list');
                        toggleBtn.classList.remove('is-card');
                        toggleBtn.setAttribute('aria-pressed','false');
                        toggleBtn.setAttribute('aria-label','Switch to Card View');
                        toggleBtn.setAttribute('title','Switch to Card View');
                        var srt2 = toggleBtn.querySelector('.screen-reader-text'); if (srt2) srt2.textContent = 'Switch to Card View';
                    }
                    if (formHidden) formHidden.value = view;
                    try { localStorage.setItem('tpwMembersView', view); } catch(e){}
                    if (pushUrl){
                        try {
                            var url = new URL(window.location.href);
                            url.searchParams.set('view', view);
                            window.history.replaceState({}, '', url.toString());
                        } catch(e){}
                    }
                }

                toggleBtn.addEventListener('click', function(){
                    current = (current === 'card') ? 'list' : 'card';
                    setView(current, true);
                });

                // Respect localStorage if no explicit view param
                try {
                    var url = new URL(window.location.href);
                    var hasParam = url.searchParams.get('view');
                    if (!hasParam){
                        var saved = localStorage.getItem('tpwMembersView');
                        if (saved && (saved === 'card' || saved === 'list') && saved !== current){
                            current = saved;
                            setView(current, true);
                        }
                    }
                } catch(e){}
            }
            if (document.readyState === 'loading'){
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>

    
        <?php if ( $hide_initial ): ?>
            <div class="tpw-info-box" style="margin:10px 0; padding:10px 12px; border:1px solid #c8e1ff; background:#f0f7ff; color:#1a3a5a; border-radius:6px;">
                No members are shown by default. Please enter a search and click Search to view results.
                <?php $show_all_url = esc_url( add_query_arg( 'show_all', '1', get_permalink() ) ); ?>
                <div style="margin-top:6px;"><a class="tpw-btn tpw-btn-light" href="<?php echo $show_all_url; ?>">Show All</a></div>
            </div>
        <?php endif; ?>

        <div class="tpw-table" id="tpw-list-view" style="<?php echo ($current_view === 'card' || $hide_initial) ? 'display:none;' : ''; ?>">
        <div class="tpw-table-header">
            <div class="tpw-table-cell">Name</div>
            <?php if ( $is_admin || tpw_can_group_view_field($vis_group,'email') ): ?>
            <div class="tpw-table-cell">Email</div>
            <?php endif; ?>
            <?php if ( $is_admin || tpw_can_group_view_field($vis_group,'mobile') || tpw_can_group_view_field($vis_group,'landline') ): ?>
            <div class="tpw-table-cell">Telephone</div>
            <?php endif; ?>
            <?php if ( $is_admin ): ?>
                <div class="tpw-table-cell">Status</div>
            <?php endif; ?>
            <?php if ( $is_admin ): ?>
                <div class="tpw-table-cell">Actions</div>
            <?php endif; ?>
        </div>

        <?php if ( empty( $members ) ): ?>
            <div class="tpw-table-row"><div class="tpw-table-cell" colspan="4">No members found.</div></div>
        <?php else: ?>
            <?php foreach ( $members as $member ): ?>
                <div class="tpw-table-row" data-member-id="<?php echo (int)$member->id; ?>">
                    <div class="tpw-table-cell" data-label="Name">
                        <a href="#" class="tpw-member-name-link" data-member-id="<?php echo (int)$member->id; ?>"><?php echo esc_html( function_exists('tpw_members_get_display_name') ? tpw_members_get_display_name($member) : ( $member->first_name . ' ' . $member->surname ) ); ?></a>
                    </div>
                    <?php if ( $is_admin || tpw_can_group_view_field($vis_group,'email') ): ?>
                    <div class="tpw-table-cell" data-label="Email">
                        <?php if ( ! empty( $member->email ) ): ?>
                            <a
                                href="#"
                                class="tpw-member-email-link"
                                data-member-id="<?php echo (int)$member->id; ?>"
                                data-recipient-name="<?php echo esc_attr( trim(($member->first_name ?? '') . ' ' . ($member->surname ?? '')) ); ?>"
                                data-recipient-email="<?php echo esc_attr( $member->email ); ?>"
                            ><?php echo esc_html( $member->email ); ?></a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ( $is_admin || tpw_can_group_view_field($vis_group,'mobile') || tpw_can_group_view_field($vis_group,'landline') ): ?>
                    <div class="tpw-table-cell" data-label="Telephone">
                        <?php if ( !empty($member->mobile) && ( $is_admin || tpw_can_group_view_field($vis_group,'mobile') ) ) echo esc_html($member->mobile) . '<br>'; ?>
                        <?php if ( !empty($member->landline) && ( $is_admin || tpw_can_group_view_field($vis_group,'landline') ) ) echo esc_html($member->landline); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ( $is_admin ): ?>
                        <div class="tpw-table-cell" data-label="Status"><?php echo esc_html( $member->status ); ?></div>
                    <?php endif; ?>
                    <?php if ( $is_admin ): ?>
                        <div class="tpw-table-cell actions-cell" data-label="Actions">
                            <div class="tpw-actions-group">
                                <a class="tpw-btn tpw-btn-secondary small tpw-action tpw-action-edit" href="?action=edit_form&id=<?php echo (int) $member->id; ?>" role="button">Edit</a>
                                <?php if ( get_option( 'tpw_members_allow_deletion', true ) ): ?>
                                    <a class="tpw-btn tpw-btn-danger small tpw-action tpw-action-delete" href="?action=delete&id=<?php echo (int) $member->id; ?>" onclick="return confirm('Are you sure you want to delete this member?');" role="button">Delete</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Card View Grid -->
    <div id="tpw-card-view" class="tpw-card-grid" style="<?php echo ($current_view === 'card' && !$hide_initial) ? '' : 'display:none;'; ?>">
        <?php if ( empty( $members ) ): ?>
            <div>No members found.</div>
        <?php else: ?>
            <?php $use_photos = get_option('tpw_members_use_photos', '0') === '1'; ?>
            <?php foreach ( $members as $member ): ?>
                <div class="member-card" data-member-id="<?php echo (int)$member->id; ?>">
                    <?php if ( $use_photos ): ?>
                        <div class="member-card-media" aria-hidden="false">
                            <?php
                            $photo_url = '';
                            if ( !empty($member->member_photo) ) {
                                $rel = trim((string)$member->member_photo);
                                if ( preg_match('#^https?://#i', $rel) ) {
                                    $photo_url = $rel; // already absolute
                                } else {
                                    $uploads = wp_get_upload_dir();
                                    if ( ! empty($uploads['baseurl']) ) {
                                        $photo_url = rtrim($uploads['baseurl'], '/') . '/' . ltrim($rel, '/');
                                    }
                                }
                            }
                            ?>
                            <?php if ( !empty($photo_url) ): ?>
                                <img class="member-card-photo" src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr( trim(($member->first_name ?? '') . ' ' . ($member->surname ?? '')) ); ?> photo" width="100" height="100">
                            <?php else: ?>
                                <?php
                                $fi = isset($member->first_name[0]) ? strtoupper($member->first_name[0]) : '';
                                $li = isset($member->surname[0]) ? strtoupper($member->surname[0]) : '';
                                $initials = trim($fi . $li);
                                ?>
                                <div class="member-card-initials" role="img" aria-label="<?php echo esc_attr( trim(($member->first_name ?? '') . ' ' . ($member->surname ?? '')) ); ?> initials"><?php echo esc_html($initials ?: '?'); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="card-title">
                        <a href="#" class="tpw-member-name-link" data-member-id="<?php echo (int)$member->id; ?>"><?php echo esc_html( function_exists('tpw_members_get_display_name') ? tpw_members_get_display_name($member) : ( $member->first_name . ' ' . $member->surname ) ); ?></a>
                    </div>
                    <div class="card-content">
                        <?php if ( ( $is_admin || tpw_can_group_view_field($vis_group,'email') ) && !empty($member->email) ): ?>
                            <div><strong>Email:</strong> <a href="#"
                                class="tpw-member-email-link"
                                data-member-id="<?php echo (int)$member->id; ?>"
                                data-recipient-name="<?php echo esc_attr( trim(($member->first_name ?? '') . ' ' . ($member->surname ?? '')) ); ?>"
                                data-recipient-email="<?php echo esc_attr( $member->email ); ?>"
                            ><?php echo esc_html($member->email); ?></a></div>
                        <?php endif; ?>
                        <?php if ( ( $is_admin || tpw_can_group_view_field($vis_group,'mobile') || tpw_can_group_view_field($vis_group,'landline') ) && ( !empty($member->mobile) || !empty($member->landline) ) ): ?>
                            <div><strong>Telephone:</strong> <?php echo esc_html( trim(($member->mobile ?? '') . ((($member->mobile ?? '') && ($member->landline ?? '')) ? ' / ' : '') . ($member->landline ?? '')) ); ?></div>
                        <?php endif; ?>
                        <?php if ( $is_admin ): ?>
                            <div><strong>Status:</strong> <?php echo esc_html( $member->status ); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ( $is_admin ): ?>
                    <div class="card-actions tpw-actions-group">
                        <a class="tpw-btn tpw-btn-secondary small tpw-action tpw-action-edit" href="?action=edit_form&id=<?php echo (int)$member->id; ?>" role="button">Edit</a>
                        <?php if ( get_option( 'tpw_members_allow_deletion', true ) ): ?>
                        <a class="tpw-btn tpw-btn-danger small tpw-action tpw-action-delete" href="?action=delete&id=<?php echo (int)$member->id; ?>" onclick="return confirm('Are you sure you want to delete this member?');" role="button">Delete</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php
    if ( $hide_initial ) {
        $total_members = 0;
        $total_pages = 1;
    } else {
        $count_args = [ 'search' => $search_query ];
        if ( ! empty($has_value_selected) ) { $count_args['has_value'] = $has_value_selected; }
        if ( ! empty($adv_text) ) { $count_args['adv_text'] = $adv_text; }
        if ( ! empty($adv_select) ) { $count_args['adv_select'] = $adv_select; }
    if ( ! empty($adv_date_range) ) { $count_args['adv_date_range'] = $adv_date_range; }
    if ( ! empty($adv_select_meta) ) { $count_args['adv_select_meta'] = $adv_select_meta; }
        if ( ! $is_admin ) {
            if ( class_exists('TPW_Member_Access') && method_exists('TPW_Member_Access', 'get_allowed_statuses') ) {
                $count_args['status_in'] = TPW_Member_Access::get_allowed_statuses();
            } else {
                $count_args['status_in'] = TPW_Member_Access::ALLOWED_STATUSES;
            }
        }
        if ( $is_admin && $selected_status !== '' ) { $count_args['status'] = $selected_status; }
        $total_members = $controller->get_total_members_count( $count_args );
        $total_pages = max(1, (int) ceil($total_members / max(1, (int)$per_page)));
    }
    if (!$hide_initial && $total_pages > 1):
        // Build pagination URLs preserving current filters on the same page permalink
        $base_url = get_permalink();
        $base_args = [
            'search'   => $search_query,
            'status'   => $selected_status,
            'per_page' => (int) $per_page,
            'view'     => $current_view === 'card' ? 'card' : '',
        ];
        // Preserve advanced filters in pagination
        foreach ($has_value_selected as $hv) { $base_args['has_' . $hv] = '1'; }
        foreach ($adv_text as $k=>$v) { $base_args['adv_txt_'.$k] = $v; }
        foreach ($adv_select as $k=>$v) { $base_args['adv_sel_'.$k] = $v; }
        foreach ($adv_date_range as $k=>$o) {
            if (!empty($o['from'])) { $base_args['adv_from_'.$k] = $o['from']; }
            if (!empty($o['to'])) { $base_args['adv_to_'.$k] = $o['to']; }
        }
        // Preserve checkbox filters: add param k=1 when checked
        foreach ($adv_checkbox as $k) { $base_args[$k] = '1'; }
        // Preserve meta select filters
        foreach ($adv_select_meta as $k=>$v) { $base_args['adv_sel_'.$k] = $v; }
        $build_page_url = function($p) use ($base_url, $base_args) {
            $p = (int) $p;
            $args = $base_args;
            $permalink = get_option('permalink_structure');
            // Build query string without 'paged' when pretty permalinks are used
            if ( $permalink ) {
                $url = trailingslashit( $base_url );
                if ( $p > 1 ) {
                    $url .= 'page/' . $p . '/';
                }
                $qs = array_filter($args, function($v){ return $v !== '' && $v !== null; });
                return esc_url( add_query_arg( $qs, $url ) );
            } else {
                $args['paged'] = $p;
                return esc_url( add_query_arg( array_filter($args, function($v){ return $v !== '' && $v !== null; }), $base_url ) );
            }
        };
        $prev = max(1, $page - 1);
        $next = min($total_pages, $page + 1);
        $range = 2; // how many pages to show around current
        $start = max(1, $page - $range);
        $end   = min($total_pages, $page + $range);
        ?>
        <div class="tablenav-pages">
            <button type="button" class="tpw-btn tpw-btn-light tpw-page-btn first-page<?php echo $page === 1 ? ' disabled' : ''; ?>" data-href="<?php echo esc_url( $build_page_url(1) ); ?>" aria-label="First page" <?php echo $page === 1 ? 'disabled' : ''; ?>>&laquo;</button>
            <button type="button" class="tpw-btn tpw-btn-light tpw-page-btn prev-page<?php echo $page === 1 ? ' disabled' : ''; ?>" data-href="<?php echo esc_url( $build_page_url($prev) ); ?>" aria-label="Previous page" <?php echo $page === 1 ? 'disabled' : ''; ?>>&lsaquo;</button>

            <?php if ($start > 1): ?>
                <button type="button" class="tpw-btn tpw-btn-light tpw-page-btn" data-href="<?php echo esc_url( $build_page_url(1) ); ?>">1</button>
                <?php if ($start > 2): ?><span class="page-numbers dots">&hellip;</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i === $page): ?>
                    <button type="button" class="tpw-btn tpw-btn-light current" disabled><?php echo $i; ?></button>
                <?php else: ?>
                    <button type="button" class="tpw-btn tpw-btn-light tpw-page-btn" data-href="<?php echo esc_url( $build_page_url($i) ); ?>"><?php echo $i; ?></button>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $total_pages): ?>
                <?php if ($end < $total_pages - 1): ?><span class="page-numbers dots">&hellip;</span><?php endif; ?>
                <button type="button" class="tpw-btn tpw-btn-light tpw-page-btn" data-href="<?php echo esc_url( $build_page_url($total_pages) ); ?>"><?php echo $total_pages; ?></button>
            <?php endif; ?>

                        <button type="button" class="tpw-btn tpw-btn-light tpw-page-btn next-page<?php echo $page >= $total_pages ? ' disabled' : ''; ?>" data-href="<?php echo esc_url( $build_page_url($next) ); ?>" aria-label="Next page" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>&rsaquo;</button>
                        <button type="button" class="tpw-btn tpw-btn-light tpw-page-btn last-page<?php echo $page >= $total_pages ? ' disabled' : ''; ?>" data-href="<?php echo esc_url( $build_page_url($total_pages) ); ?>" aria-label="Last page" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>&raquo;</button>
        </div>
                <script>
                (function(){
                    var btns = document.querySelectorAll('.tpw-page-btn');
                    btns.forEach(function(b){
                        if (b.disabled) return;
                        b.addEventListener('click', function(){
                            var href = b.getAttribute('data-href');
                            if (href) window.location.href = href;
                        });
                    });
                })();
                </script>
        <?php endif; ?>
        </div> <!-- .tpw-table -->

        <!-- Member Details modal (for admins and members) -->
        <div id="tpw-member-details-modal" class="tpw-dir-modal" hidden>
            <div class="tpw-dir-modal__dialog">
                <div class="tpw-dir-modal__header">
                    <h3 style="font-size: var(--tpw-h3-size, 1.25rem); font-weight: var(--tpw-h3-weight, 600); color: var(--tpw-h3-color, currentColor); margin:0;">Member Details</h3>
                    <button type="button" class="tpw-btn tpw-btn-light tpw-dir-modal-close">Close</button>
                </div>
                <div class="tpw-dir-modal__body" id="tpw-member-details-content">Loading...</div>
            </div>
        </div>

        <?php
            // Render reusable email modal for Members module
            if ( class_exists('TPW_Email_Form') ) {
                $current_user = wp_get_current_user();
                // Attempt to use sender's TPW member details when available
                $sender_name  = '';
                $sender_email = '';
                if ( $current_user && $current_user->ID ) {
                    if ( class_exists('TPW_Member_Controller') ) {
                        $ctrl = new TPW_Member_Controller();
                        $sender_m = $ctrl->get_member_by_user_id( (int) $current_user->ID );
                        if ( $sender_m ) {
                            $sender_name  = trim( (string) ($sender_m->first_name ?? '') . ' ' . (string) ($sender_m->surname ?? '') );
                            $sender_email = (string) ($sender_m->email ?? '');
                        }
                    }
                    if ( $sender_name === '' ) { $sender_name = $current_user->display_name; }
                    if ( $sender_email === '' ) { $sender_email = $current_user->user_email; }
                }
                echo TPW_Email_Form::render([
                    'recipient_name'  => '',          // set dynamically on click
                    'recipient_email' => '',          // set dynamically on click
                    'from_name'       => $sender_name,
                    'from_email'      => $sender_email,
                    'subject'         => '',
                    'message'         => '',
                    'plugin_slug'     => 'tpw-members',
                    'modal_id'        => 'tpw-members-email-modal',
                    'send_copy'       => true,
                    'from_readonly'   => true,        // lock sender fields
                ]);
            }
        ?>
    </div> <!-- .tpw-table-container -->