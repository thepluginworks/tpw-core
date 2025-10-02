<?php

require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-controller.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-meta.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-form-handler.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-roles.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-field-loader.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-ajax.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-access.php';

add_action('wp_enqueue_scripts', function () {
    if (is_page() && has_shortcode(get_post()->post_content, 'tpw_manage_members')) {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

        // Load scripts and styles for all relevant member admin views
    $needs_member_admin_style = ['add', 'edit_form', 'list', 'settings', 'field_settings', 'member-field-visibility', 'import_csv'];
        if (in_array($action, $needs_member_admin_style)) {
            $inherit_global = function_exists('tpw_core_inherit_global_frontend_enabled') ? tpw_core_inherit_global_frontend_enabled() : false;
            // Shared Admin UI styles (scoped to .tpw-admin-ui)
            if ( ! $inherit_global && defined( 'TPW_CORE_URL' ) && defined( 'TPW_CORE_PATH' ) ) {
                $tpw_ui_url = TPW_CORE_URL . 'assets/css/tpw-admin-ui.css';
                $tpw_ui_path = TPW_CORE_PATH . 'assets/css/tpw-admin-ui.css';
                wp_enqueue_style(
                    'tpw-admin-ui',
                    $tpw_ui_url,
                    [],
                    file_exists( $tpw_ui_path ) ? filemtime( $tpw_ui_path ) : null
                );
            }
            wp_enqueue_script(
                'tpw-member-form-validation',
                plugins_url('../assets/js/member-form-validation.js', __FILE__),
                ['jquery'],
                filemtime(plugin_dir_path(__FILE__) . '../assets/js/member-form-validation.js'),
                true
            );

            wp_enqueue_style(
                'tpw-member-admin-style',
                plugins_url('../assets/css/member-admin.css', __FILE__),
                [],
                filemtime(plugin_dir_path(__FILE__) . '../assets/css/member-admin.css')
            );

            // Centralized TPW admin tabs CSS (FlexiGolf-like tabs)
            wp_enqueue_style(
                'tpw-admin-tabs',
                TPW_CORE_URL . 'assets/css/tpw-admin-tabs.css',
                [],
                file_exists( TPW_CORE_PATH . 'assets/css/tpw-admin-tabs.css' ) ? filemtime( TPW_CORE_PATH . 'assets/css/tpw-admin-tabs.css' ) : null
            );

            // Enqueue link-user JS for list view
            if ($action === 'list') {
                wp_enqueue_script(
                    'tpw-member-link-user',
                    plugins_url('../assets/js/member-link-user.js', __FILE__),
                    ['jquery'],
                    filemtime(plugin_dir_path(__FILE__) . '../assets/js/member-link-user.js'),
                    true
                );
                wp_localize_script(
                    'tpw-member-link-user',
                    'TPW_MEMBER_LINK',
                    [
                        'nonce'   => wp_create_nonce('tpw_member_create_nonce'),
                        'ajaxUrl' => admin_url('admin-ajax.php')
                    ]
                );

                // Directory script for member view and admin list interactions
                wp_enqueue_script(
                    'tpw-member-directory',
                    plugins_url('../assets/js/member-directory.js', __FILE__),
                    ['jquery'],
                    filemtime(plugin_dir_path(__FILE__) . '../assets/js/member-directory.js'),
                    true
                );
                $current = wp_get_current_user();
                // Derive sender's member details if available for display-only fields in email modal
                $sender_member_name = '';
                $sender_member_email = '';
                if ( $current && $current->ID ) {
                    $ctrl_for_sender = new TPW_Member_Controller();
                    $sender_member = $ctrl_for_sender->get_member_by_user_id( (int) $current->ID );
                    if ( $sender_member ) {
                        $sender_member_name = trim( (string) ($sender_member->first_name ?? '') . ' ' . (string) ($sender_member->surname ?? '') );
                        $sender_member_email = (string) ($sender_member->email ?? '');
                    }
                }
                wp_localize_script(
                    'tpw-member-directory',
                    'TPW_MEMBER_DIR',
                    [
                        'nonce'            => wp_create_nonce('tpw_member_create_nonce'),
                        'ajaxUrl'          => admin_url('admin-ajax.php'),
                        'currentUserName'  => $current ? $current->display_name : '',
                        'currentUserEmail' => $current ? $current->user_email : '',
                        'senderMemberName' => $sender_member_name,
                        'senderMemberEmail'=> $sender_member_email,
                    ]
                );

                // Datepicker for Advanced Search modal (date_range)
                wp_enqueue_script('jquery-ui-datepicker');
                wp_enqueue_style('jquery-ui-theme', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', [], '1.13.2');
                $initAdv = "jQuery(function($){ $('.tpw-adv-date').datepicker({ dateFormat: 'dd/mm/yy', changeMonth:true, changeYear:true, yearRange:'1900:2100' }); });";
                wp_add_inline_script('jquery-ui-datepicker', $initAdv);
            }

            // Datepicker for add/edit forms
            if (in_array($action, ['add','edit_form'], true)) {
                wp_enqueue_script('jquery-ui-datepicker');
                wp_enqueue_style('jquery-ui-theme', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', [], '1.13.2');
                // Inline init script with site-configured format
                $php_date = tpw_core_get_date_format();
                $jq_date  = tpw_core_php_date_to_jqueryui( $php_date );
                $init = "jQuery(function($){ $('.tpw-date').datepicker({ dateFormat: '" . esc_js( $jq_date ) . "', changeMonth:true, changeYear:true, yearRange:'1900:2100' }); });";
                wp_add_inline_script('jquery-ui-datepicker', $init);

                // Initialize shared Core postcode binder for Members add/edit forms
                // This script depends on 'tpw-core-postcode' which is enqueued via modules/postcodes/enqueue.php
                wp_register_script(
                    'tpw-members-postcode-init',
                    plugins_url('js/members-postcode-init.js', __FILE__),
                    ['tpw-core-postcode'],
                    filemtime( plugin_dir_path(__FILE__) . 'js/members-postcode-init.js' ),
                    true
                );
                wp_enqueue_script('tpw-members-postcode-init');
            }
        }
    }
});

// Init AJAX endpoints in all contexts where admin may load the page
add_action('init', function(){
    if ( is_user_logged_in() ) {
        TPW_Member_Ajax::init();
    }
});

// Stream CSV export for current filters when requested
add_action('template_redirect', function(){
    // Must be on the manage members page (has the shortcode) and requesting export
    if ( ! is_page() ) return;
    global $post;
    if ( ! $post || ! has_shortcode( $post->post_content, 'tpw_manage_members' ) ) return;

    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    if ( $action !== 'export_csv' ) return;

    // Restrict to admins only
    if ( ! TPW_Member_Access::is_admin_current() ) {
        wp_die( 'Access denied.', 403 );
    }

    // Optional nonce check if present
    if ( isset($_GET['_wpnonce']) && ! wp_verify_nonce( $_GET['_wpnonce'], 'tpw_export_members' ) ) {
        wp_die( 'Invalid export request.', 400 );
    }

    // Build filters from the current query (ignore pagination to export all matching rows)
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

    $controller = new TPW_Member_Controller();
    $args = [];
    if ( $search !== '' ) { $args['search'] = $search; }
    if ( $status !== '' ) { $args['status'] = $status; }

    // Support Advanced Search has_value flags in export
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
    if (!empty($has_value_selected)) { $args['has_value'] = $has_value_selected; }

    // Advanced filters for export
    $searchable_opt = get_option('tpw_member_searchable_fields', []);
    if (!is_array($searchable_opt)) { $searchable_opt = []; }
    global $wpdb; $members_table = $wpdb->prefix . 'tpw_members';
    $tpw_cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $members_table, 0 );
    $adv_text = $adv_select = $adv_date_range = [];
    $adv_checkbox = [];
    foreach ($searchable_opt as $ekey => $econf) {
        $stype = isset($econf['search_type']) ? $econf['search_type'] : 'text';
        $col = sanitize_key($ekey);
        if (!in_array($col, $tpw_cols, true)) continue;
        if ($stype === 'text' && isset($_GET['adv_txt_'.$col]) && $_GET['adv_txt_'.$col] !== '') {
            $adv_text[$col] = sanitize_text_field($_GET['adv_txt_'.$col]);
        } elseif ($stype === 'select' && isset($_GET['adv_sel_'.$col]) && $_GET['adv_sel_'.$col] !== '') {
            $adv_select[$col] = sanitize_text_field($_GET['adv_sel_'.$col]);
        } elseif ($stype === 'date_range') {
            $from = isset($_GET['adv_from_'.$col]) ? sanitize_text_field($_GET['adv_from_'.$col]) : '';
            $to   = isset($_GET['adv_to_'.$col]) ? sanitize_text_field($_GET['adv_to_'.$col]) : '';
            $norm = function($d){
                if (!$d) return '';
                if (strpos($d,'/') !== false) {
                    $parts = explode('/', $d);
                    if (count($parts) === 3) { return sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]); }
                }
                return $d;
            };
            $fromN = $norm($from); $toN = $norm($to);
            if ($fromN !== '' || $toN !== '') { $adv_date_range[$col] = ['from'=>$fromN,'to'=>$toN]; }
        } elseif ($stype === 'checkbox') {
            if (isset($_GET[$col]) && $_GET[$col] === '1') { $adv_checkbox[] = $col; }
        }
    }
    if (!empty($adv_text)) { $args['adv_text'] = $adv_text; }
    if (!empty($adv_select)) { $args['adv_select'] = $adv_select; }
    if (!empty($adv_date_range)) { $args['adv_date_range'] = $adv_date_range; }
    if (!empty($adv_checkbox)) { $args['adv_checkbox'] = $adv_checkbox; }

    $members = $controller->get_members( $args ); // no pagination -> all matching

    // Determine all columns of the members table in defined order
    global $wpdb;
    $table = $wpdb->prefix . 'tpw_members';
    $columns = [];
    $describe = $wpdb->get_results( "DESCRIBE {$table}" );
    if ( $describe && is_array($describe) ) {
        foreach ( $describe as $col ) {
            if ( isset($col->Field) ) {
                $columns[] = $col->Field;
            }
        }
    }
    // Fallback to keys from the first row if DESCRIBE fails
    if ( empty($columns) && ! empty($members) ) {
        $first = (array) $members[0];
        $columns = array_keys( $first );
    }

    // Determine enabled field-managed columns (core/custom) to exclude disabled core fields from export
    $enabled = TPW_Member_Field_Loader::get_all_enabled_fields();
    $enabled_keys = array_map( function($f){ return $f['key']; }, $enabled );
    $core_fields = array_keys( TPW_Member_Field_Loader::get_core_fields() );

    // Build final export columns: include system columns, and only enabled core fields
    $export_columns = [];
    foreach ( (array) $columns as $col ) {
        if ( in_array( $col, $core_fields, true ) ) {
            if ( in_array( $col, $enabled_keys, true ) ) {
                $export_columns[] = $col;
            } else {
                // Skip disabled core field
            }
        } else {
            // System/housekeeping columns (e.g., id, user_id, created_at) are not field-managed -> include
            $export_columns[] = $col;
        }
    }

    // Start CSV output
    nocache_headers();
    // Use site-configured formats for filename (avoid spaces and slashes)
    $settings = get_option( 'flexievent_settings', [] );
    $date_format = $settings['date_format'] ?? 'd-m-Y';
    $time_format = $settings['time_format'] ?? 'H:i';
    $stamp = date_i18n( strtr($date_format, ['/' => '-', ' ' => '-']) . '_' . strtr($time_format, [':' => '-', ' ' => '-']), current_time('timestamp') );
    $filename = 'members-export-' . $stamp . '.csv';
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );

    // Emit UTF-8 BOM for Excel compatibility
    echo "\xEF\xBB\xBF";

    $out = fopen( 'php://output', 'w' );
    // Header row: filtered columns
    fputcsv( $out, $export_columns );
    foreach ( (array) $members as $m ) {
        $row = [];
        foreach ( $export_columns as $c ) {
            $row[] = isset($m->$c) && $m->$c !== null ? $m->$c : '';
        }
        fputcsv( $out, $row );
    }
    fclose( $out );
    exit;
});

// Register shortcode to manage members (admin use only)
add_shortcode('tpw_manage_members', function() {
    $is_admin  = TPW_Member_Access::is_admin_current();
    $is_member = TPW_Member_Access::is_member_current();
    if ( ! $is_admin && ! $is_member ) {
        return '<div class="tpw-error">Access Denied</div>';
    }

    $inherit_global = function_exists('tpw_core_inherit_global_frontend_enabled') ? tpw_core_inherit_global_frontend_enabled() : false;
    ob_start();
    if ( ! $inherit_global ) { echo '<div class="tpw-admin-ui tpw-admin-wrapper">'; }

    // Flash message renderer (simple GET param)
    if ( isset($_GET['msg']) ) {
        $msg = sanitize_text_field( $_GET['msg'] );
        $text = '';
        if ( $msg === 'photo_deleted' ) {
            $text = 'Member photo deleted.';
        } elseif ( $msg === 'photo_replaced' ) {
            $text = 'Member photo updated.';
        }
        if ( $text !== '' ) {
            echo '<div class="notice notice-success" style="margin:10px 0;"><p>' . esc_html($text) . '</p></div>';
        }
    }

    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

    // Members (non-admin) only see the list/directory
    if ( ! $is_admin ) {
        $action = 'list';
    }

    if ($action === 'import_csv') {
        require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-csv-importer.php';
    }

    switch ($action) {
        case 'add':
            if ($is_admin) include TPW_CORE_PATH . 'modules/members/templates/admin/add.php';
            break;

        case 'edit_form':
            include TPW_CORE_PATH . 'modules/members/templates/admin/edit.php';
            break;

        case 'settings':
            if ($is_admin) include TPW_CORE_PATH . 'modules/members/settings/member-settings.php';
            break;

        case 'field_settings':
            if ($is_admin) include TPW_CORE_PATH . 'modules/members/settings/member-fields-settings.php';
            break;

        case 'member-field-visibility':
            if ($is_admin) include TPW_CORE_PATH . 'modules/members/settings/member-field-visibility.php';
            break;

        case 'import_csv':
            if ($is_admin) include TPW_CORE_PATH . 'modules/members/templates/admin/import-csv.php';
            break;

        default:
            // Pass role flags to the list template via globals
            $GLOBALS['tpw_members_is_admin'] = $is_admin;
            include TPW_CORE_PATH . 'modules/members/templates/admin/list.php';
            break;
    }

    if ( ! $inherit_global ) { echo '</div>'; }

    return ob_get_clean();
});