<?php

require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-controller.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-meta.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-form-handler.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-roles.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-field-loader.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-ajax.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-tpw-member-access.php';

add_action('wp_enqueue_scripts', function () {
    if ( function_exists('tpw_members_module_enabled') && ! tpw_members_module_enabled() ) {
        return;
    }
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
    if ( function_exists('tpw_members_module_enabled') && ! tpw_members_module_enabled() ) {
        return;
    }
    if ( is_user_logged_in() ) {
        TPW_Member_Ajax::init();
    }
});

// Stream CSV export for current filters when requested
add_action('template_redirect', function(){
    if ( function_exists('tpw_members_module_enabled') && ! tpw_members_module_enabled() ) {
        return;
    }
    // Must be on the manage members page (has the shortcode) and requesting export
    if ( ! is_page() ) return;
    global $post;
    if ( ! $post || ! has_shortcode( $post->post_content, 'tpw_manage_members' ) ) return;

    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    if ( $action !== 'export_csv' ) return;

    // Restrict to managers per setting: WP admins always; committee if enabled
    $manage_setting = get_option('tpw_members_manage_access', 'admins_only');
    $wp_admin = current_user_can('manage_options');
    $is_committee = false;
    if ( is_user_logged_in() ) {
        $member_obj = TPW_Member_Access::get_member_by_user_id( get_current_user_id() );
        $is_committee = $member_obj && ! empty($member_obj->is_committee) && (int)$member_obj->is_committee === 1;
    }
    $can_manage = $wp_admin || ( $manage_setting === 'admins_committee' && $is_committee );
    if ( ! $can_manage ) {
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

    // Build export field list from 'Download' selections (option), ordered by field sort_order
    global $wpdb;
    $download_selected = get_option( 'tpw_member_field_download', [] );
    if ( ! is_array( $download_selected ) ) { $download_selected = []; }

    // Determine all columns of the members table to separate core vs meta
    $table = $wpdb->prefix . 'tpw_members';
    $members_columns = [];
    $describe = $wpdb->get_results( "DESCRIBE {$table}" );
    if ( $describe && is_array($describe) ) {
        foreach ( $describe as $col ) {
            if ( isset($col->Field) ) { $members_columns[] = $col->Field; }
        }
    }
    if ( empty($members_columns) && ! empty($members) ) {
        $first = (array) $members[0];
        $members_columns = array_keys( $first );
    }

    // If nothing selected, export an empty CSV with header only
    $export_columns = [];
    $meta_columns = [];
    if ( ! empty( $download_selected ) ) {
        // Fetch sort order for selected keys for consistent ordering
        $fs_table = $wpdb->prefix . 'tpw_field_settings';
        // Prepare placeholders
        $placeholders = implode( ',', array_fill( 0, count( $download_selected ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT field_key, sort_order, custom_label FROM {$fs_table} WHERE field_key IN ($placeholders) ORDER BY sort_order ASC", ...array_map('sanitize_key', $download_selected) ) );
        $ordered = [];
        if ( $rows ) {
            foreach ( $rows as $r ) { $ordered[] = $r->field_key; }
            // Include any selected keys missing in settings table (fallback at end)
            foreach ( $download_selected as $k ) { if ( ! in_array( $k, $ordered, true ) ) { $ordered[] = $k; } }
        } else {
            $ordered = array_map( 'sanitize_key', $download_selected );
        }

        // Build a map of labels from settings table
        $label_map = [];
        if ( $rows ) {
            foreach ( $rows as $r ) {
                $fk = (string) $r->field_key;
                $cl = isset($r->custom_label) ? (string) $r->custom_label : '';
                if ( $cl !== '' ) { $label_map[$fk] = $cl; }
            }
        }

        foreach ( $ordered as $key ) {
            if ( in_array( $key, $members_columns, true ) ) {
                $export_columns[] = $key; // core/member table column
            } else {
                $meta_columns[] = $key;   // custom meta column
            }
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
    // Header row: use Custom Label where available; fallback to core field label or prettified key
    $header_labels = [];
    $core_fields_def = method_exists('TPW_Member_Field_Loader','get_core_fields') ? TPW_Member_Field_Loader::get_core_fields() : [];
    $label_for = function($key) use ($label_map, $core_fields_def) {
        $k = (string) $key;
        if ( isset($label_map[$k]) && $label_map[$k] !== '' ) { return $label_map[$k]; }
        if ( isset($core_fields_def[$k]) ) {
            $def = $core_fields_def[$k];
            if ( is_array($def) && isset($def['label']) && $def['label'] !== '' ) { return (string) $def['label']; }
            if ( is_string($def) && $def !== '' ) { return $def; }
        }
        return ucwords( str_replace('_',' ', $k) );
    };
    foreach ( $export_columns as $c ) { $header_labels[] = $label_for($c); }
    foreach ( $meta_columns as $c )   { $header_labels[] = $label_for($c); }
    fputcsv( $out, $header_labels );

    // Preload selected meta values for all members in one query
    $meta_map = [];
    if ( ! empty( $meta_columns ) && ! empty( $members ) ) {
        $member_ids = array_map( function( $m ) { return (int) $m->id; }, (array) $members );
        $member_ids = array_values( array_unique( array_filter( $member_ids ) ) );
        if ( ! empty( $member_ids ) ) {
            $meta_table = $wpdb->prefix . 'tpw_member_meta';
            $id_placeholders = implode( ',', array_fill( 0, count( $member_ids ), '%d' ) );
            $key_placeholders = implode( ',', array_fill( 0, count( $meta_columns ), '%s' ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sql = "SELECT member_id, meta_key, meta_value FROM {$meta_table} WHERE member_id IN ($id_placeholders) AND meta_key IN ($key_placeholders)";
            $params = array_merge( $member_ids, array_map( 'sanitize_key', $meta_columns ) );
            $results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
            if ( $results ) {
                foreach ( $results as $r ) {
                    $mid = (int) $r['member_id'];
                    $mk  = (string) $r['meta_key'];
                    $mv  = (string) $r['meta_value'];
                    if ( ! isset( $meta_map[ $mid ] ) ) { $meta_map[ $mid ] = []; }
                    $meta_map[ $mid ][ $mk ] = $mv;
                }
            }
        }
    }

    foreach ( (array) $members as $m ) {
        $row = [];
        // Core/member table columns
        foreach ( $export_columns as $c ) {
            $row[] = isset($m->$c) && $m->$c !== null ? $m->$c : '';
        }
        // Meta columns
        $mid = isset($m->id) ? (int) $m->id : 0;
        foreach ( $meta_columns as $mc ) {
            $row[] = ( $mid && isset($meta_map[$mid]) && array_key_exists($mc, $meta_map[$mid]) ) ? $meta_map[$mid][$mc] : '';
        }
        fputcsv( $out, $row );
    }
    fclose( $out );
    exit;
});

// Register shortcode to manage members (admin use only)
add_shortcode('tpw_manage_members', function() {
    // Only render when Members module is enabled
    if ( function_exists('tpw_members_module_enabled') && ! tpw_members_module_enabled() ) {
        return '';
    }

    // Determine management access per setting
    $manage_setting = get_option('tpw_members_manage_access', 'admins_only');
    $wp_admin = current_user_can('manage_options');
    $is_admin  = TPW_Member_Access::is_admin_current(); // keeps WP admin override unless filtered off

    // Committee flag (from linked member row)
    $is_committee = false; $member_obj = null;
    if ( function_exists('wp_get_current_user') && is_user_logged_in() ) {
        $member_obj = TPW_Member_Access::get_member_by_user_id( get_current_user_id() );
        $is_committee = $member_obj && ! empty($member_obj->is_committee) && (int)$member_obj->is_committee === 1;
    }

    // Management access:
    // - Always allow WordPress admins (manage_options)
    // - If setting allows, also allow committee members
    $can_manage = $wp_admin || ( $manage_setting === 'admins_committee' && $is_committee );

    // Directory eligibility: Active/Honorary/Life Member statuses
    $can_view_directory = false;
    if ( $member_obj ) {
        $status_norm = strtolower( trim( (string) ($member_obj->status ?? '') ) );
        $allowed = array_map('strtolower', TPW_Member_Access::get_allowed_statuses());
        $can_view_directory = in_array( $status_norm, $allowed, true );
    }

    $inherit_global = function_exists('tpw_core_inherit_global_frontend_enabled') ? tpw_core_inherit_global_frontend_enabled() : false;
    ob_start();
    if ( ! $inherit_global ) { echo '<div class="tpw-admin-ui tpw-admin-wrapper">'; }

    // Flash message renderer (simple GET param)
    if ( isset($_GET['msg']) ) {
        $msg = sanitize_text_field( $_GET['msg'] );
        $text = '';
        if ( $msg === 'photo_deleted' ) {
            $text = esc_html__( 'Member photo deleted.', 'tpw-core' );
        } elseif ( $msg === 'photo_replaced' ) {
            $text = esc_html__( 'Member photo updated.', 'tpw-core' );
        }
        if ( $text !== '' ) {
            echo '<div class="notice notice-success" style="margin:10px 0;"><p>' . esc_html($text) . '</p></div>';
        }
    }

    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

    // If the user cannot manage and also isn't eligible to view, block entirely
    if ( ! $can_manage && ! $can_view_directory ) {
        return '<div class="tpw-error">' . esc_html__( 'Access Denied', 'tpw-core' ) . '</div>';
    }

    // Allow importer include when admin only
    if ($action === 'import_csv') {
        require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-csv-importer.php';
    }

    // Normalize action based on capabilities
    if ( ! $can_manage ) {
        // Members (directory-only): force list view (read-only)
        $action = 'list';
    }

    // Lightweight no-cache safeguard for admin‑like front-end views served by /manage-members/
    // Prevents cached pages from serving stale nonces which cause silent save failures.
    // Applies only when the current user can manage members and is viewing an admin-type action.
    if ( $can_manage && in_array( $action, [ 'settings', 'field_settings', 'member-field-visibility', 'add', 'edit_form', 'import_csv' ], true ) ) {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        if ( function_exists( 'nocache_headers' ) ) {
            nocache_headers();
        }
    }

    switch ($action) {
        case 'add':
            if ($can_manage) include TPW_CORE_PATH . 'modules/members/templates/admin/add.php';
            break;

        case 'edit_form':
            if ($can_manage) include TPW_CORE_PATH . 'modules/members/templates/admin/edit.php';
            break;

        case 'settings':
            if ($can_manage) include TPW_CORE_PATH . 'modules/members/settings/member-settings.php';
            break;

        case 'field_settings':
            if ($can_manage) include TPW_CORE_PATH . 'modules/members/settings/member-fields-settings.php';
            break;

        case 'member-field-visibility':
            if ($can_manage) include TPW_CORE_PATH . 'modules/members/settings/member-field-visibility.php';
            break;

        case 'import_csv':
            if ($can_manage) include TPW_CORE_PATH . 'modules/members/templates/admin/import-csv.php';
            break;

        default:
            // Pass capability flags to the list template via globals
            $GLOBALS['tpw_members_is_admin'] = $can_manage; // treat can_manage as admin for UI controls
            // Also pass directory group for field visibility checks
            if ( $can_manage ) {
                $GLOBALS['tpw_members_vis_group'] = 'admin';
            } else {
                // Non-managers: prefer committee group when applicable, else member
                $GLOBALS['tpw_members_vis_group'] = $is_committee ? 'committee' : 'member';
            }
            include TPW_CORE_PATH . 'modules/members/templates/admin/list.php';
            break;
    }

    if ( ! $inherit_global ) { echo '</div>'; }

    return ob_get_clean();
});