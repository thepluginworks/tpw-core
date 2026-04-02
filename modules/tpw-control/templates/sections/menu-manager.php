<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Admin guard (section visibility is admin, but double check)
if ( ! class_exists('TPW_Control_UI') || ! TPW_Control_UI::user_has_access( [ 'logged_in' => true, 'flags_any' => ['is_admin'] ] ) ) {
    echo '<p>' . esc_html__( 'You do not have permission to manage menus.', 'tpw-core' ) . '</p>';
    return;
}

// Helpers
$base_url = TPW_Control_UI::menu_url('menu-manager');
$nonce_action = 'tpw_control_menu_manager';
if ( ! class_exists( 'TPW_Member_Field_Loader' ) ) {
    $member_field_loader = TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-field-loader.php';
    if ( file_exists( $member_field_loader ) ) {
        require_once $member_field_loader;
    }
}
$show_match_manager_visibility = method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active();

// Safe redirect helper to avoid blank pages when headers are already sent
$tpw_mm_redirect = function( $url ) {
    if ( ! headers_sent() ) {
        wp_safe_redirect( $url );
        exit;
    }
    // Fallback to client-side redirect
    $safe = esc_url( $url );
    echo '<script>window.location.href = ' . wp_json_encode( $url ) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safe . '"></noscript>';
    echo '<p><a href="' . $safe . '">Continue</a></p>';
    exit;
};

// Handle POST actions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_wpnonce']) && wp_verify_nonce( $_POST['_wpnonce'], $nonce_action ) ) {
    $action = sanitize_key( $_POST['tpw_menu_action'] ?? '' );
    $redirect_menu_id = isset($_POST['menu_id']) ? (int) $_POST['menu_id'] : ( isset($_GET['menu_id']) ? (int) $_GET['menu_id'] : 0 );
    switch ( $action ) {
        case 'save_structure':
            $menu_id = (int) ($_POST['menu_id'] ?? 0);
            $tree_json = isset($_POST['mm_tree']) ? wp_unslash( (string) $_POST['mm_tree'] ) : '';
            if ( $menu_id && $tree_json ) {
                $tree = json_decode( $tree_json, true );
                if ( is_array( $tree ) ) {
                    $position = 1;
                    $update_node = function( $node, $parent_id ) use ( &$position, &$update_node ) {
                        $id = isset($node['id']) ? (int) $node['id'] : 0;
                        if ( $id > 0 ) {
                            // Update only order and parent meta to avoid clobbering fields
                            wp_update_post( [ 'ID' => $id, 'menu_order' => (int) $position ] );
                            update_post_meta( $id, '_menu_item_menu_item_parent', (int) $parent_id );
                            $position++;
                        }
                        if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
                            foreach ( $node['children'] as $child ) {
                                $update_node( $child, $id );
                            }
                        }
                    };
                    foreach ( $tree as $top ) { $update_node( $top, 0 ); }
                    // Clear caches so the change is visible immediately
                    if ( function_exists( 'clean_nav_menu_cache' ) ) { clean_nav_menu_cache( $menu_id ); }
                }
            }
            break;
        case 'create_menu':
            $name = sanitize_text_field( $_POST['menu_name'] ?? '' );
            if ( $name !== '' ) {
                $created = wp_create_nav_menu( $name );
                if ( ! is_wp_error( $created ) && $created ) $redirect_menu_id = (int) $created;
            }
            break;
        case 'add_section':
            $menu_id = (int) ($_POST['menu_id'] ?? 0);
            $section = sanitize_key( $_POST['section_key'] ?? '' );
            $label = sanitize_text_field( $_POST['label'] ?? '' );
            $vis = [
                'is_admin' => ! empty($_POST['visibility_is_admin']),
                'is_committee' => ! empty($_POST['visibility_is_committee']),
                'is_match_manager' => ! empty($_POST['visibility_is_match_manager']),
                'is_noticeboard_admin' => ! empty($_POST['visibility_is_noticeboard_admin']),
                'is_gallery_admin' => ! empty($_POST['visibility_is_gallery_admin']),
                'status' => isset($_POST['visibility_status']) && is_array($_POST['visibility_status']) ? array_map('sanitize_text_field', $_POST['visibility_status']) : [],
            ];
            $requires_login = ! empty( $_POST['requires_login'] );
            $url = TPW_Control_UI::menu_url( $section );
            if ( $menu_id && $section ) {
                $item_id = wp_update_nav_menu_item( $menu_id, 0, [
                    'menu-item-title' => $label !== '' ? $label : ucfirst( str_replace( '-', ' ', $section ) ),
                    'menu-item-url'   => $url,
                    'menu-item-status'=> 'publish',
                    'menu-item-type'  => 'custom',
                ] );
                if ( $item_id && ! is_wp_error( $item_id ) ) {
                    update_post_meta( $item_id, '_tpw_visibility_json', wp_json_encode( $vis ) );
                    update_post_meta( $item_id, '_tpw_requires_login', $requires_login ? 1 : 0 );
                }
            }
            break;
        case 'add_upload_page':
            $menu_id = (int) ($_POST['menu_id'] ?? 0);
            $page_id = (int) ($_POST['upload_page_id'] ?? 0);
            $label = sanitize_text_field( $_POST['label'] ?? '' );
            $vis = [
                'is_admin' => ! empty($_POST['visibility_is_admin']),
                'is_committee' => ! empty($_POST['visibility_is_committee']),
                'is_match_manager' => ! empty($_POST['visibility_is_match_manager']),
                'is_noticeboard_admin' => ! empty($_POST['visibility_is_noticeboard_admin']),
                'is_gallery_admin' => ! empty($_POST['visibility_is_gallery_admin']),
                'status' => isset($_POST['visibility_status']) && is_array($_POST['visibility_status']) ? array_map('sanitize_text_field', $_POST['visibility_status']) : [],
            ];
            $requires_login = ! empty( $_POST['requires_login'] );
            if ( $menu_id && $page_id ) {
                $url = '';
                $title = $label;
                if ( class_exists('TPW_Control_Upload_Pages') ) {
                    $p = TPW_Control_Upload_Pages::get_page_by_id( $page_id );
                    if ( $p ) {
                        // Prefer linked WP Page permalink
                        $wpid = isset($p->wp_page_id) ? (int)$p->wp_page_id : 0;
                        if ( $wpid && get_post( $wpid ) ) {
                            $url = get_permalink( $wpid );
                        }
                        if ( $title === '' ) $title = $p->title;
                    }
                }
                if ( $url === '' ) {
                    // Fallback to control route if no linked page exists
                    $url = add_query_arg( [ 'id' => $page_id ], TPW_Control_UI::menu_url( 'upload-pages' ) );
                }
                if ( $title === '' ) $title = 'Upload Page';
                $item_id = wp_update_nav_menu_item( $menu_id, 0, [
                    'menu-item-title' => $title,
                    'menu-item-url'   => $url,
                    'menu-item-status'=> 'publish',
                    'menu-item-type'  => 'custom',
                ] );
                if ( $item_id && ! is_wp_error( $item_id ) ) {
                    update_post_meta( $item_id, '_tpw_visibility_json', wp_json_encode( $vis ) );
                    update_post_meta( $item_id, '_tpw_requires_login', $requires_login ? 1 : 0 );
                }
            }
            break;
        case 'add_custom':
            $menu_id = (int) ($_POST['menu_id'] ?? 0);
            $label = sanitize_text_field( $_POST['label'] ?? '' );
            $url   = esc_url_raw( $_POST['url'] ?? '' );
            $vis = [
                'is_admin' => ! empty($_POST['visibility_is_admin']),
                'is_committee' => ! empty($_POST['visibility_is_committee']),
                'is_match_manager' => ! empty($_POST['visibility_is_match_manager']),
                'is_noticeboard_admin' => ! empty($_POST['visibility_is_noticeboard_admin']),
                'is_gallery_admin' => ! empty($_POST['visibility_is_gallery_admin']),
                'status' => isset($_POST['visibility_status']) && is_array($_POST['visibility_status']) ? array_map('sanitize_text_field', $_POST['visibility_status']) : [],
            ];
            $requires_login = ! empty( $_POST['requires_login'] );
            if ( $menu_id && $label !== '' && $url !== '' ) {
                $item_id = wp_update_nav_menu_item( $menu_id, 0, [
                    'menu-item-title' => $label,
                    'menu-item-url'   => $url,
                    'menu-item-status'=> 'publish',
                    'menu-item-type'  => 'custom',
                ] );
                if ( $item_id && ! is_wp_error( $item_id ) ) {
                    update_post_meta( $item_id, '_tpw_visibility_json', wp_json_encode( $vis ) );
                    update_post_meta( $item_id, '_tpw_requires_login', $requires_login ? 1 : 0 );
                }
            }
            break;
        case 'add_tpw_page':
            $menu_id = (int) ($_POST['menu_id'] ?? 0);
            $slug = sanitize_key( $_POST['tpw_page_slug'] ?? '' );
            $label = sanitize_text_field( $_POST['label'] ?? '' );
            $vis = [
                'is_admin' => ! empty($_POST['visibility_is_admin']),
                'is_committee' => ! empty($_POST['visibility_is_committee']),
                'is_match_manager' => ! empty($_POST['visibility_is_match_manager']),
                'is_noticeboard_admin' => ! empty($_POST['visibility_is_noticeboard_admin']),
                'is_gallery_admin' => ! empty($_POST['visibility_is_gallery_admin']),
                'status' => isset($_POST['visibility_status']) && is_array($_POST['visibility_status']) ? array_map('sanitize_text_field', $_POST['visibility_status']) : [],
            ];
            $requires_login = ! empty( $_POST['requires_login'] );
            if ( $menu_id && $slug !== '' && class_exists('TPW_Core_System_Pages') ) {
                // Prevent duplicates in this menu
                $existing = $menu_id ? wp_get_nav_menu_items( $menu_id, [ 'update_post_term_cache' => false ] ) : [];
                $dup = false;
                foreach ( (array) $existing as $it ) {
                    $ps = get_post_meta( $it->ID, '_tpw_page_slug', true );
                    if ( is_string($ps) && sanitize_key($ps) === $slug ) { $dup = true; break; }
                }
                if ( ! $dup ) {
                    $url = TPW_Core_System_Pages::get_permalink( $slug );
                    $title = $label;
                    if ( $title === '' ) {
                        $rows = TPW_Core_System_Pages::get_all();
                        foreach ( $rows as $r ) { if ( $r->slug === $slug ) { $title = $r->title; break; } }
                        if ( $title === '' ) $title = ucfirst( str_replace('-', ' ', $slug ) );
                    }
                    if ( $url ) {
                        $item_id = wp_update_nav_menu_item( $menu_id, 0, [
                            'menu-item-title' => $title,
                            'menu-item-url'   => $url,
                            'menu-item-status'=> 'publish',
                            'menu-item-type'  => 'custom',
                        ] );
                        if ( $item_id && ! is_wp_error( $item_id ) ) {
                            update_post_meta( $item_id, '_tpw_page_slug', $slug );
                            update_post_meta( $item_id, '_tpw_visibility_json', wp_json_encode( $vis ) );
                            update_post_meta( $item_id, '_tpw_requires_login', $requires_login ? 1 : 0 );
                        }
                    }
                }
            }
            break;
        case 'update_item':
            $menu_id = (int) ($_POST['menu_id'] ?? 0);
            $item_id = (int) ($_POST['item_id'] ?? 0);
            $label = sanitize_text_field( $_POST['label'] ?? '' );
            $url   = esc_url_raw( $_POST['url'] ?? '' );
            $existing_vis = [];
            if ( $item_id > 0 ) {
                $raw_existing_vis = get_post_meta( $item_id, '_tpw_visibility_json', true );
                if ( is_string( $raw_existing_vis ) && $raw_existing_vis !== '' ) {
                    $decoded_existing_vis = json_decode( $raw_existing_vis, true );
                    if ( is_array( $decoded_existing_vis ) ) {
                        $existing_vis = $decoded_existing_vis;
                    }
                }
            }
            $vis = [
                'is_admin' => ! empty($_POST['visibility_is_admin']),
                'is_committee' => ! empty($_POST['visibility_is_committee']),
                'is_match_manager' => $show_match_manager_visibility ? ! empty($_POST['visibility_is_match_manager']) : ! empty($existing_vis['is_match_manager']),
                'is_noticeboard_admin' => ! empty($_POST['visibility_is_noticeboard_admin']),
                'is_gallery_admin' => ! empty($_POST['visibility_is_gallery_admin']),
                'status' => isset($_POST['visibility_status']) && is_array($_POST['visibility_status']) ? array_map('sanitize_text_field', $_POST['visibility_status']) : [],
            ];
            $requires_login = ! empty( $_POST['requires_login'] );
            if ( $menu_id && $item_id ) {
                $existing_item = wp_setup_nav_menu_item( get_post( $item_id ) );
                if ( $existing_item && ! is_wp_error( $existing_item ) ) {
                    $args = [
                        'menu-item-title'      => $label,
                        'menu-item-status'     => 'publish',
                        'menu-item-position'   => (int) $existing_item->menu_order,
                        'menu-item-parent-id'  => (int) $existing_item->menu_item_parent,
                        'menu-item-type'       => $existing_item->type,
                        'menu-item-object-id'  => (int) $existing_item->object_id,
                        'menu-item-object'     => $existing_item->object,
                    ];

                    if ( 'custom' === $existing_item->type ) {
                        $args['menu-item-url'] = $url !== '' ? $url : (string) $existing_item->url;
                    }

                    wp_update_nav_menu_item( $menu_id, $item_id, $args );
                }
                update_post_meta( $item_id, '_tpw_visibility_json', wp_json_encode( $vis ) );
                update_post_meta( $item_id, '_tpw_requires_login', $requires_login ? 1 : 0 );
            }
            break;
        case 'delete_item':
            $item_id = (int) ($_POST['item_id'] ?? 0);
            if ( $item_id ) wp_delete_post( $item_id, true );
            break;
        case 'repair_menu_items':
            $menu_id = (int) ($_POST['menu_id'] ?? 0);
            if ( $menu_id ) {
                $items_to_fix = wp_get_nav_menu_items( $menu_id, [ 'update_post_term_cache' => false ] );
                if ( is_array( $items_to_fix ) ) {
                    foreach ( $items_to_fix as $mi ) {
                        $id = (int) $mi->ID;
                        $need_update = false;
                        $new_title = (string) $mi->title;
                        $new_url   = (string) $mi->url;
                        // Try to restore from TPW meta first
                        if ( $new_url === '' ) {
                            $slug = get_post_meta( $id, '_tpw_page_slug', true );
                            if ( $slug && class_exists('TPW_Core_System_Pages') ) {
                                $u = TPW_Core_System_Pages::get_permalink( sanitize_key( $slug ) );
                                if ( $u ) { $new_url = $u; $need_update = true; }
                            }
                        }
                        if ( $new_title === '' ) {
                            if ( $slug && class_exists('TPW_Core_System_Pages') ) {
                                $rows = TPW_Core_System_Pages::get_all();
                                foreach ( (array) $rows as $r ) { if ( $r->slug === $slug ) { $new_title = (string) $r->title; break; } }
                            }
                            if ( $new_title === '' && $new_url !== '' ) {
                                // Derive a sensible label from URL
                                $parts = wp_parse_url( $new_url );
                                if ( $parts ) {
                                    $seg = '';
                                    if ( ! empty( $parts['path'] ) ) {
                                        $p = trim( (string) $parts['path'], '/' );
                                        $bits = $p !== '' ? explode( '/', $p ) : [];
                                        $seg = end( $bits ) ?: '';
                                    }
                                    $new_title = $seg !== '' ? ucwords( str_replace( ['-','_'], ' ', $seg ) ) : ( $parts['host'] ?? 'Link' );
                                } else {
                                    $new_title = 'Link';
                                }
                            }
                            if ( $new_title !== '' ) $need_update = true;
                        }
                        if ( $need_update ) {
                            // Update fields directly to avoid API side-effects
                            if ( $new_title !== '' ) { wp_update_post( [ 'ID' => $id, 'post_title' => $new_title ] ); }
                            if ( $new_url   !== '' ) { update_post_meta( $id, '_menu_item_url', esc_url_raw( $new_url ) ); }
                        }
                    }
                }
            }
            break;
    }
    // Redirect to avoid resubmission (build robust URL with add_query_arg)
    $redir = $base_url;
    if ( $redirect_menu_id ) {
        $redir = add_query_arg( 'menu_id', (int)$redirect_menu_id, $base_url );
    }
    $tpw_mm_redirect( $redir );
}

// Fetch menus and selected state
$menus = wp_get_nav_menus();
$selected_menu_id = isset($_GET['menu_id']) ? (int) $_GET['menu_id'] : ( ( ! empty($menus) ) ? (int)$menus[0]->term_id : 0 );
$items = $selected_menu_id ? wp_get_nav_menu_items( $selected_menu_id, [ 'update_post_term_cache' => false ] ) : [];
$sections = TPW_Control::get_sections();
$upload_pages = class_exists('TPW_Control_Upload_Pages') ? TPW_Control_Upload_Pages::get_pages() : [];
$tpw_pages = [];
if ( class_exists('TPW_Core_System_Pages') ) {
    $all_pages = TPW_Core_System_Pages::get_all();
    foreach ( (array) $all_pages as $row ) {
        $pid = isset( $row->wp_page_id ) ? (int) $row->wp_page_id : 0;
        if ( $pid > 0 ) {
            $p = get_post( $pid );
            if ( $p && 'page' === $p->post_type && 'trash' !== $p->post_status ) {
                $tpw_pages[] = (object) [ 'slug' => (string) $row->slug, 'title' => (string) $row->title, 'pid' => $pid ];
            }
        }
    }
}
$statuses_list = apply_filters( 'tpw_members/known_statuses', [ 'Active','Honorary','Life Member','Junior','Student' ] );

// Small helper
$vis_check = function( $vis, $key ){ return ! empty( $vis[$key] ); };
$parse_vis = function( $json ){
    $arr = is_string($json) ? json_decode($json, true) : ( is_array($json) ? $json : [] );
    return is_array($arr) ? $arr : [];
};

echo '<div class="tpw-upl-wrap">';
echo '<div class="tpw-upl-head"><h2>Menu Manager</h2></div>';

// Scoped styles for cards and modals (kept minimal and specific)
echo '<style>
/* Layout rows */
.tpw-mm-row{display:flex;gap:12px;align-items:stretch;flex-wrap:wrap;margin:0 0 14px}
.tpw-mm-card{position:relative;flex:1 1 320px;min-width:300px;background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:18px 16px}
.tpw-mm-card__title{position:absolute;top:-10px;left:12px;background:#fff;padding:0 8px;font-weight:600;color:#1d2327}
.tpw-mm-card__body{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.tpw-mm-card__body .tpw-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.tpw-mm-card__body .tpw-form label{display:flex;align-items:center;gap:6px;margin:0}
.tpw-mm-card__body .tpw-form input[type="text"]{height:32px}
.tpw-mm-card__body .tpw-form select{height:auto;min-height:32px;line-height:1.4;padding:2px 6px}
.tpw-mm-card__body .tpw-form input[type="text"].wide{width:320px;max-width:100%}
.tpw-mm-card .wide{min-width:280px}
.tpw-mm-card--create .tpw-form{flex-wrap:nowrap}
.tpw-mm-card--create .tpw-form input[type="text"].wide{flex:1 1 auto;min-width:180px;width:auto}

/* Top row spacing and OR separator */
.tpw-mm-row--top{margin-bottom:24px}
.tpw-mm-or{align-self:center;color:#666;font-weight:600;padding:0 6px}

/* Three-up grid for adders */
.tpw-mm-grid-3{display:flex;gap:12px;flex-wrap:wrap}
.tpw-mm-grid-3 .tpw-mm-card{flex:1 1 320px}

/* Modal */
.tpw-mm-modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:100000}
.tpw-mm-modal[hidden]{display:none}
.tpw-mm-modal__dialog{background:#fff;width:min(720px,94vw);max-height:82vh;overflow:auto;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
.tpw-mm-modal__header,.tpw-mm-modal__body,.tpw-mm-modal__footer{padding:12px 16px}
.tpw-mm-modal__header{border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between}
.tpw-mm-modal__footer{border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end}
.tpw-mm-vis-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;align-items:start}
.tpw-mm-vis-col{display:flex;flex-direction:column;gap:6px}
.tpw-mm-vis-summary{color:#646970;font-size:12px}
</style>';

// Menu selector and create (two cards inline)
echo '<div class="tpw-mm-row tpw-mm-row--top">';
// Load Menu card
echo '<div class="tpw-mm-card">';
echo '<div class="tpw-mm-card__title">Load Menu</div>';
echo '<div class="tpw-mm-card__body">';
echo '<form class="tpw-form" method="get" action=""><input type="hidden" name="action" value="menu-manager" />';
echo '<label>Menu: <select name="menu_id" onchange="this.form.submit()">';
foreach ( $menus as $m ) {
    echo '<option value="' . (int)$m->term_id . '"' . selected( $selected_menu_id, (int)$m->term_id, false ) . '>' . esc_html( $m->name ) . '</option>';
}
echo '</select></label> ';
echo '<button class="tpw-btn tpw-btn-secondary" type="submit">Load</button>';
echo '</form>';
echo '</div>';
echo '</div>';

// OR separator between the cards
echo '<div class="tpw-mm-or">OR</div>';

// Create New Menu card
echo '<div class="tpw-mm-card tpw-mm-card--create">';
echo '<div class="tpw-mm-card__title">Create New Menu</div>';
echo '<div class="tpw-mm-card__body">';
echo '<form class="tpw-form" method="post" action=""><input type="hidden" name="tpw_menu_action" value="create_menu" />';
wp_nonce_field( $nonce_action );
echo '<input type="text" class="wide" name="menu_name" placeholder="New menu name" /> ';
echo '<button class="tpw-btn tpw-btn-primary" type="submit">Create Menu</button>';
echo '</form>';
echo '</div>';
echo '</div>';
echo '</div>';

if ( $selected_menu_id ) {
    // Compact launcher: horizontal button row
    echo '<style>';
    echo '.tpw-mm-actions{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0 16px}';
    echo '</style>';
    echo '<div class="tpw-mm-actions">';
    echo '  <button type="button" class="tpw-btn tpw-btn-secondary" data-mm-open="#tpw-mm-add-section">+ Add TPW Section</button>';
    echo '  <button type="button" class="tpw-btn tpw-btn-secondary" data-mm-open="#tpw-mm-add-tpw-page">+ Add TPW Page</button>';
    echo '  <button type="button" class="tpw-btn tpw-btn-secondary" data-mm-open="#tpw-mm-add-upload">+ Add Upload Page</button>';
    echo '  <button type="button" class="tpw-btn tpw-btn-secondary" data-mm-open="#tpw-mm-add-custom">+ Add Custom Link</button>';
    echo '</div>';

    // Modal: Add TPW Control Section
    echo '<div id="tpw-mm-add-section" class="tpw-mm-modal" hidden><div class="tpw-mm-modal__dialog">';
    echo '  <div class="tpw-mm-modal__header"><strong>Add TPW Section</strong></div>';
    echo '  <div class="tpw-mm-modal__body">';
    echo '    <form class="tpw-form" method="post">'; wp_nonce_field( $nonce_action );
    echo '      <input type="hidden" name="tpw_menu_action" value="add_section" />';
    echo '      <input type="hidden" name="menu_id" value="' . (int)$selected_menu_id . '" />';
    echo '      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">';
    echo '        <label>Section: <select name="section_key">';
    foreach ( $sections as $s ) { echo '<option value="' . esc_attr( $s['key'] ) . '">' . esc_html( $s['label'] ) . '</option>'; }
    echo '        </select></label>';
    echo '        <label>Label: <input type="text" name="label" placeholder="(optional)" /></label>';
    echo '      </div>';
    echo '      <div class="tpw-mm-vis-inline" style="margin-top:8px">';
    echo '        <label style="margin:0"><input type="checkbox" name="requires_login" /> Requires login</label>';
    echo '        <div class="tpw-mm-vis-collapsible">';
    echo '          <div class="tpw-mm-vis-toggle"><span class="tpw-mm-vis-summary" data-mm-summary>No visibility restrictions</span> <button type="button" class="tpw-btn tpw-btn-outline" data-mm-toggle>Set visibility restrictions</button></div>';
    echo '          <div class="tpw-mm-vis-panel" hidden>';
    echo '            <div class="tpw-mm-vis-grid">';
    echo '              <div class="tpw-mm-vis-col">';
    echo '                <label><input type="checkbox" name="visibility_is_admin" /> Admins</label>';
    echo '                <label><input type="checkbox" name="visibility_is_committee" /> Committee</label>';
    if ( $show_match_manager_visibility ) echo '                <label><input type="checkbox" name="visibility_is_match_manager" /> Match Managers</label>';
    echo '                <label><input type="checkbox" name="visibility_is_noticeboard_admin" /> Noticeboard Admins</label>';
    echo '                <label><input type="checkbox" name="visibility_is_gallery_admin" /> Gallery Admins</label>';
    echo '              </div>';
    echo '              <div class="tpw-mm-vis-col">';
    echo '                <label><strong>Member Status</strong></label>';
    echo '                <select name="visibility_status[]" multiple size="6" style="width:100%">';
    foreach ( $statuses_list as $st ) echo '<option value="' . esc_attr($st) . '">' . esc_html($st) . '</option>';
    echo '                </select>';
    echo '                <span class="description">Hold Cmd/Ctrl to select multiple</span>';
    echo '              </div>';
    echo '            </div>';
    echo '          </div>';
    echo '        </div>';
    echo '      </div>';
    echo '    </form>';
    echo '  </div>';
    echo '  <div class="tpw-mm-modal__footer">';
    echo '    <button type="button" class="tpw-btn tpw-btn-light tpw-mm-close">Cancel</button>';
    echo '    <button type="button" class="tpw-btn tpw-btn-primary" data-mm-submit="#tpw-mm-add-section">Add Section Link</button>';
    echo '  </div>';
    echo '</div></div>';

    // Modal: Add TPW Page
    echo '<div id="tpw-mm-add-tpw-page" class="tpw-mm-modal" hidden><div class="tpw-mm-modal__dialog">';
    echo '  <div class="tpw-mm-modal__header"><strong>Add TPW Page</strong></div>';
    echo '  <div class="tpw-mm-modal__body">';
    echo '    <form class="tpw-form" method="post">'; wp_nonce_field( $nonce_action );
    echo '      <input type="hidden" name="tpw_menu_action" value="add_tpw_page" />';
    echo '      <input type="hidden" name="menu_id" value="' . (int)$selected_menu_id . '" />';
    echo '      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">';
    echo '        <label>Page: <select name="tpw_page_slug">';
    foreach ( $tpw_pages as $p ) echo '<option value="' . esc_attr($p->slug) . '">' . esc_html($p->title) . '</option>';
    echo '        </select></label>';
    echo '        <label>Label: <input type="text" name="label" placeholder="(optional)" /></label>';
    echo '      </div>';
    echo '      <div class="tpw-mm-vis-inline" style="margin-top:8px">';
    echo '        <label style="margin:0"><input type="checkbox" name="requires_login" /> Requires login</label>';
    echo '        <div class="tpw-mm-vis-collapsible">';
    echo '          <div class="tpw-mm-vis-toggle"><span class="tpw-mm-vis-summary" data-mm-summary>No visibility restrictions</span> <button type="button" class="tpw-btn tpw-btn-outline" data-mm-toggle>Set visibility restrictions</button></div>';
    echo '          <div class="tpw-mm-vis-panel" hidden>';
    echo '            <div class="tpw-mm-vis-grid">';
    echo '              <div class="tpw-mm-vis-col">';
    echo '                <label><input type="checkbox" name="visibility_is_admin" /> Admins</label>';
    echo '                <label><input type="checkbox" name="visibility_is_committee" /> Committee</label>';
    if ( $show_match_manager_visibility ) echo '                <label><input type="checkbox" name="visibility_is_match_manager" /> Match Managers</label>';
    echo '                <label><input type="checkbox" name="visibility_is_noticeboard_admin" /> Noticeboard Admins</label>';
    echo '                <label><input type="checkbox" name="visibility_is_gallery_admin" /> Gallery Admins</label>';
    echo '              </div>';
    echo '              <div class="tpw-mm-vis-col">';
    echo '                <label><strong>Member Status</strong></label>';
    echo '                <select name="visibility_status[]" multiple size="6" style="width:100%">';
    foreach ( $statuses_list as $st ) echo '<option value="' . esc_attr($st) . '">' . esc_html($st) . '</option>';
    echo '                </select>';
    echo '                <span class="description">Hold Cmd/Ctrl to select multiple</span>';
    echo '              </div>';
    echo '            </div>';
    echo '          </div>';
    echo '        </div>';
    echo '      </div>';
    echo '    </form>';
    echo '  </div>';
    echo '  <div class="tpw-mm-modal__footer">';
    echo '    <button type="button" class="tpw-btn tpw-btn-light tpw-mm-close">Cancel</button>';
    echo '    <button type="button" class="tpw-btn tpw-btn-primary" data-mm-submit="#tpw-mm-add-tpw-page">Add Page Link</button>';
    echo '  </div>';
    echo '</div></div>';

    // Modal: Add Upload Page
    echo '<div id="tpw-mm-add-upload" class="tpw-mm-modal" hidden><div class="tpw-mm-modal__dialog">';
    echo '  <div class="tpw-mm-modal__header"><strong>Add Upload Page</strong></div>';
    echo '  <div class="tpw-mm-modal__body">';
    echo '    <form class="tpw-form" method="post">'; wp_nonce_field( $nonce_action );
    echo '      <input type="hidden" name="tpw_menu_action" value="add_upload_page" />';
    echo '      <input type="hidden" name="menu_id" value="' . (int)$selected_menu_id . '" />';
    echo '      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">';
    echo '        <label>Upload Page: <select name="upload_page_id">';
    foreach ( $upload_pages as $p ) echo '<option value="' . (int)$p->id . '">' . esc_html( $p->title ) . '</option>';
    echo '        </select></label>';
    echo '        <label>Label: <input type="text" name="label" placeholder="(optional)" /></label>';
    echo '      </div>';
    echo '      <div class="tpw-mm-vis-inline" style="margin-top:8px">';
    echo '        <label style="margin:0"><input type="checkbox" name="requires_login" /> Requires login</label>';
    echo '        <div class="tpw-mm-vis-collapsible">';
    echo '          <div class="tpw-mm-vis-toggle"><span class="tpw-mm-vis-summary" data-mm-summary>No visibility restrictions</span> <button type="button" class="tpw-btn tpw-btn-outline" data-mm-toggle>Set visibility restrictions</button></div>';
    echo '          <div class="tpw-mm-vis-panel" hidden>';
    echo '            <div class="tpw-mm-vis-grid">';
    echo '              <div class="tpw-mm-vis-col">';
    echo '                <label><input type="checkbox" name="visibility_is_admin" /> Admins</label>';
    echo '                <label><input type="checkbox" name="visibility_is_committee" /> Committee</label>';
    if ( $show_match_manager_visibility ) echo '                <label><input type="checkbox" name="visibility_is_match_manager" /> Match Managers</label>';
    echo '                <label><input type="checkbox" name="visibility_is_noticeboard_admin" /> Noticeboard Admins</label>';
    echo '                <label><input type="checkbox" name="visibility_is_gallery_admin" /> Gallery Admins</label>';
    echo '              </div>';
    echo '              <div class="tpw-mm-vis-col">';
    echo '                <label><strong>Member Status</strong></label>';
    echo '                <select name="visibility_status[]" multiple size="6" style="width:100%">';
    foreach ( $statuses_list as $st ) echo '<option value="' . esc_attr($st) . '">' . esc_html($st) . '</option>';
    echo '                </select>';
    echo '                <span class="description">Hold Cmd/Ctrl to select multiple</span>';
    echo '              </div>';
    echo '            </div>';
    echo '          </div>';
    echo '        </div>';
    echo '      </div>';
    echo '    </form>';
    echo '  </div>';
    echo '  <div class="tpw-mm-modal__footer">';
    echo '    <button type="button" class="tpw-btn tpw-btn-light tpw-mm-close">Cancel</button>';
    echo '    <button type="button" class="tpw-btn tpw-btn-primary" data-mm-submit="#tpw-mm-add-upload">Add Upload Page Link</button>';
    echo '  </div>';
    echo '</div></div>';

    // Modal: Add Custom Link
    echo '<div id="tpw-mm-add-custom" class="tpw-mm-modal" hidden><div class="tpw-mm-modal__dialog">';
    echo '  <div class="tpw-mm-modal__header"><strong>Add Custom Link</strong></div>';
    echo '  <div class="tpw-mm-modal__body">';
    echo '    <form class="tpw-form" method="post">'; wp_nonce_field( $nonce_action );
    echo '      <input type="hidden" name="tpw_menu_action" value="add_custom" />';
    echo '      <input type="hidden" name="menu_id" value="' . (int)$selected_menu_id . '" />';
    echo '      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">';
    echo '        <label>Label: <input type="text" name="label" required /></label> ';
    echo '        <label>URL: <input type="url" name="url" placeholder="https://" required style="min-width:320px"/></label>';
    echo '      </div>';
    echo '      <div class="tpw-mm-vis-inline" style="margin-top:8px">';
    echo '        <label style="margin:0"><input type="checkbox" name="requires_login" /> Requires login</label>';
    echo '        <div class="tpw-mm-vis-collapsible">';
    echo '          <div class="tpw-mm-vis-toggle"><span class="tpw-mm-vis-summary" data-mm-summary>No visibility restrictions</span> <button type="button" class="tpw-btn tpw-btn-outline" data-mm-toggle>Set visibility restrictions</button></div>';
    echo '          <div class="tpw-mm-vis-panel" hidden>';
    echo '            <div class="tpw-mm-vis-grid">';
    echo '              <div class="tpw-mm-vis-col">';
    echo '                <label><input type="checkbox" name="visibility_is_admin" /> Admins</label>';
    echo '                <label><input type="checkbox" name="visibility_is_committee" /> Committee</label>';
    if ( $show_match_manager_visibility ) echo '                <label><input type="checkbox" name="visibility_is_match_manager" /> Match Managers</label>';
    echo '                <label><input type="checkbox" name="visibility_is_noticeboard_admin" /> Noticeboard Admins</label>';
    echo '                <label><input type="checkbox" name="visibility_is_gallery_admin" /> Gallery Admins</label>';
    echo '              </div>';
    echo '              <div class="tpw-mm-vis-col">';
    echo '                <label><strong>Member Status</strong></label>';
    echo '                <select name="visibility_status[]" multiple size="6" style="width:100%">';
    foreach ( $statuses_list as $st ) echo '<option value="' . esc_attr($st) . '">' . esc_html($st) . '</option>';
    echo '                </select>';
    echo '                <span class="description">Hold Cmd/Ctrl to select multiple</span>';
    echo '              </div>';
    echo '            </div>';
    echo '          </div>';
    echo '        </div>';
    echo '      </div>';
    echo '    </form>';
    echo '  </div>';
    echo '  <div class="tpw-mm-modal__footer">';
    echo '    <button type="button" class="tpw-btn tpw-btn-light tpw-mm-close">Cancel</button>';
    echo '    <button type="button" class="tpw-btn tpw-btn-primary" data-mm-submit="#tpw-mm-add-custom">Add Link</button>';
    echo '  </div>';
    echo '</div></div>';

        // Items tree (nested)
        echo '<h3>Current Items</h3>';
        if ( empty( $items ) ) {
            echo '<p>No items in this menu yet.</p>';
        } else {
            // Build tree from flat items
            $by_parent = [];
            foreach ( $items as $it ) {
                $pid = isset($it->menu_item_parent) ? (int) $it->menu_item_parent : 0;
                if ( ! isset( $by_parent[$pid] ) ) $by_parent[$pid] = [];
                $by_parent[$pid][] = $it;
            }
            // Sort each siblings group by menu_order
            foreach ( $by_parent as &$group ) {
                usort( $group, function($a,$b){ return (int)$a->menu_order <=> (int)$b->menu_order; } );
            }
            unset($group);

            $render_ul = function( $parent_id ) use ( &$render_ul, $by_parent, $parse_vis, $statuses_list, $selected_menu_id, $nonce_action, $show_match_manager_visibility ) {
                $html = '<ul class="tpw-mm-list" ' . ( $parent_id === 0 ? 'id="tpw-mm-root"' : '' ) . '>';
                $siblings = $by_parent[$parent_id] ?? [];
                foreach ( $siblings as $it ) {
                    $vis = $parse_vis( get_post_meta( $it->ID, '_tpw_visibility_json', true ) );
                    $req = (bool) get_post_meta( $it->ID, '_tpw_requires_login', true );
                    $row_id = (int) $it->ID;
                    $bits = [];
                    foreach ( ['is_admin'=>'Admin','is_committee'=>'Committee','is_match_manager'=>'Match','is_noticeboard_admin'=>'Noticeboard','is_gallery_admin'=>'Gallery'] as $k=>$lbl ) if ( ! empty($vis[$k]) ) $bits[] = $lbl;
                    if ( ! empty($vis['status']) ) $bits[] = 'Status(' . implode(', ', array_map('esc_html',$vis['status'])) . ')';
                    $html .= '<li class="tpw-mm-item" data-id="' . $row_id . '">';
                    $html .= '<div class="tpw-mm-item__row">';
                    $html .= '<span class="dashicons dashicons-move tpw-mm-handle"></span> ';
                    $html .= '<span class="tpw-mm-item__title">' . esc_html( $it->title ) . '</span> ';
                    $html .= '<a class="tpw-mm-item__url" href="' . esc_url( $it->url ) . '" target="_blank" rel="noopener">' . esc_html( $it->url ) . '</a> ';
                    $html .= '<span class="tpw-mm-item__meta">' . ( $req ? 'Login required' : 'Public/Logged-in' ) . '</span> ';
                    $html .= '<span class="tpw-mm-item__vis">' . esc_html( implode(' • ', $bits ) ) . '</span> ';
                    $html .= '<span class="tpw-mm-item__actions">';
                    $html .= '<button type="button" class="tpw-btn tpw-btn-secondary" data-mm-open="#mm-edit-item-' . $row_id . '">Edit</button> ';
                    $html .= '<form class="tpw-form" method="post" style="display:inline-block;margin-left:6px" onsubmit="return confirm(\'Remove this item?\');">';
                    ob_start(); wp_nonce_field( $nonce_action ); $nonce_html = ob_get_clean();
                    $html .= $nonce_html;
                    $html .= '<input type="hidden" name="tpw_menu_action" value="delete_item" />';
                    $html .= '<input type="hidden" name="item_id" value="' . (int)$it->ID . '" />';
                    $html .= '<button class="tpw-btn tpw-btn-danger" type="submit">Delete</button>';
                    $html .= '</form>';
                    $html .= '</span>';
                    $html .= '</div>';
                    // Edit modal (same structure as before)
                    $html .= '<div id="mm-edit-item-' . $row_id . '" class="tpw-mm-modal" hidden>';
                    $html .= '  <div class="tpw-mm-modal__dialog">';
                    $html .= '    <div class="tpw-mm-modal__header"><strong>Edit Menu Item</strong><button type="button" class="button-link tpw-mm-close">Close</button></div>';
                    $html .= '    <div class="tpw-mm-modal__body">';
                    $html .= '      <form class="tpw-form" method="post">';
                    ob_start(); wp_nonce_field( $nonce_action ); $nonce2 = ob_get_clean();
                    $html .= $nonce2;
                    $html .= '        <input type="hidden" name="tpw_menu_action" value="update_item" />';
                    $html .= '        <input type="hidden" name="menu_id" value="' . (int)$selected_menu_id . '" />';
                    $html .= '        <input type="hidden" name="item_id" value="' . $row_id . '" />';
                    $html .= '        <div style="display:flex;flex-direction:column;gap:10px">';
                    $html .= '          <label>Label <input type="text" name="label" value="' . esc_attr( $it->title ) . '" /></label>';
                    $html .= '          <label>URL <input type="url" name="url" value="' . esc_attr( $it->url ) . '" /></label>';
                    $html .= '          <label><input type="checkbox" name="requires_login" ' . checked( $req, true, false ) . ' /> Requires login</label>';
                    $html .= '          <div style="margin-top:4px">';
                    $html .= '            <div style="margin-bottom:6px"><strong>Visibility</strong></div>';
                    $html .= '            <div style="display:flex;gap:12px;flex-wrap:wrap">';
                    $html .= '              <label><input type="checkbox" name="visibility_is_admin" ' . checked( !empty($vis['is_admin']), true, false ) . ' /> Admins</label>';
                    $html .= '              <label><input type="checkbox" name="visibility_is_committee" ' . checked( !empty($vis['is_committee']), true, false ) . ' /> Committee</label>';
                    if ( $show_match_manager_visibility ) {
                        $html .= '              <label><input type="checkbox" name="visibility_is_match_manager" ' . checked( !empty($vis['is_match_manager']), true, false ) . ' /> Match Managers</label>';
                    }
                    $html .= '              <label><input type="checkbox" name="visibility_is_noticeboard_admin" ' . checked( !empty($vis['is_noticeboard_admin']), true, false ) . ' /> Noticeboard Admins</label>';
                    $html .= '              <label><input type="checkbox" name="visibility_is_gallery_admin" ' . checked( !empty($vis['is_gallery_admin']), true, false ) . ' /> Gallery Admins</label>';
                    $html .= '            </div>';
                    $sel = isset($vis['status']) && is_array($vis['status']) ? $vis['status'] : [];
                    $html .= '            <div style="margin-top:8px">';
                    $html .= '              <label>Status</label>';
                    $html .= '              <select name="visibility_status[]" multiple size="6" style="width:100%">';
                    foreach ( $statuses_list as $st ) { $html .= '<option value="' . esc_attr($st) . '"' . selected( in_array( $st, $sel, true ), true, false ) . '>' . esc_html($st) . '</option>'; }
                    $html .= '              </select>';
                    $html .= '            </div>';
                    $html .= '          </div>';
                    $html .= '        </div>';
                    $html .= '      </form>';
                    $html .= '    </div>';
                    $html .= '    <div class="tpw-mm-modal__footer">';
                    $html .= '      <button type="button" class="tpw-btn tpw-btn-primary" data-mm-submit="#mm-edit-item-' . $row_id . '">Save</button>';
                    $html .= '      <button type="button" class="tpw-btn tpw-btn-light tpw-mm-close">Cancel</button>';
                    $html .= '    </div>';
                    $html .= '  </div>';
                    $html .= '</div>';

                    // Children UL
                    $html .= $render_ul( (int)$it->ID );
                    $html .= '</li>';
                }
                $html .= '</ul>';
                return $html;
            };

            // Styles for nested list
            echo '<style>
            .tpw-mm-tree{margin:10px 0}
            .tpw-mm-list{list-style:none;margin:0;padding-left:18px}
            .tpw-mm-item{margin:4px 0;padding:4px;border:1px solid #e0e0e0;border-radius:6px;background:#fff}
            .tpw-mm-item__row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
            .tpw-mm-handle{cursor:move;color:#777}
            .tpw-mm-item__title{font-weight:600}
            .tpw-mm-item__url{color:#2271b1;text-decoration:none;max-width:40ch;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .tpw-mm-item__meta{color:#646970;font-size:12px}
            .tpw-mm-item__vis{color:#646970;font-size:12px}
            .tpw-mm-savebar{display:flex;justify-content:flex-end;margin-top:10px}
            </style>';

            echo '<div class="tpw-mm-tree">' . $render_ul(0) . '</div>';

            // Save structure form
            echo '<form id="tpw-mm-save-structure" method="post" style="margin-top:8px">';
            wp_nonce_field( $nonce_action );
            echo '<input type="hidden" name="tpw_menu_action" value="save_structure" />';
            echo '<input type="hidden" name="menu_id" value="' . (int)$selected_menu_id . '" />';
            echo '<input type="hidden" name="mm_tree" id="tpw-mm-tree-json" value="" />';
            echo '<div class="tpw-mm-savebar" style="display:flex;gap:8px;justify-content:flex-end">';
            echo '  <button type="submit" class="tpw-btn tpw-btn-primary">Save Order</button>';
            echo '</div>';
            echo '</form>';

            // Add a small repair button (separate form to avoid accidental trigger)
            echo '<form id="tpw-mm-repair" method="post" style="margin-top:6px;text-align:right">';
            wp_nonce_field( $nonce_action );
            echo '<input type="hidden" name="tpw_menu_action" value="repair_menu_items" />';
            echo '<input type="hidden" name="menu_id" value="' . (int)$selected_menu_id . '" />';
            echo '<button type="submit" class="tpw-btn tpw-btn-light" onclick="return confirm(\'Attempt to repair blank labels/URLs in this menu?\')">Attempt Repair Blank Items</button>';
            echo '</form>';
        }
} else {
    echo '<div class="tpw-empty-state">';
    echo '<p>No menus yet. Create your first menu to start adding items.</p>';
    echo '</div>';
}

echo '</div>';

// Modal behaviour and visibility summary updater + Sortable nested tree
echo '<script>
(function(){
    // Load Sortable.js if not already present (lightweight guard)
    function ensureSortable(cb){
        if (window.Sortable) { cb(); return; }
        var s = document.createElement("script");
        s.src = "https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js";
        s.onload = cb; document.head.appendChild(s);
    }
    function summarize(form){
        const aEl = form.querySelector("input[name=visibility_is_admin]");
        const cEl = form.querySelector("input[name=visibility_is_committee]");
        const mEl = form.querySelector("input[name=visibility_is_match_manager]");
        const nEl = form.querySelector("input[name=visibility_is_noticeboard_admin]");
        const gEl = form.querySelector("input[name=visibility_is_gallery_admin]");
        const admins = aEl && aEl.checked;
        const commit = cEl && cEl.checked;
        const matchm = mEl && mEl.checked;
        const nb = nEl && nEl.checked;
        const gallery = gEl && gEl.checked;
        const statusSel = form.querySelector("select[name=\"visibility_status[]\"]");
        const statuses = statusSel ? Array.from(statusSel.selectedOptions).map(function(o){return o.value;}) : [];
        const bits = [];
        if (admins) bits.push("Admin");
        if (commit) bits.push("Committee");
        if (matchm) bits.push("Match");
        if (nb) bits.push("Noticeboard");
        if (gallery) bits.push("Gallery");
        if (statuses.length) bits.push("Status("+statuses.join(", ")+")");
        return bits.length ? bits.join(" • ") : "No visibility restrictions";
    }
    document.querySelectorAll("[data-mm-open]").forEach(btn=>{
        const sel = btn.getAttribute("data-mm-open");
        const modal = document.querySelector(sel);
        if(!modal) return;
        const parentForm = btn.closest("form");
        const summaryEl = parentForm ? parentForm.querySelector("[data-mm-summary]") : null;
        function close(){ modal.setAttribute("hidden","hidden"); }
        function open(){ modal.removeAttribute("hidden"); }
        btn.addEventListener("click", open);
        modal.querySelectorAll(".tpw-mm-close").forEach(b=>b.addEventListener("click", close));
        modal.addEventListener("click", (e)=>{ if(e.target===modal) close(); });
        const apply = modal.querySelector(".tpw-mm-apply");
        if (apply) apply.addEventListener("click", ()=>{ if(summaryEl){ summaryEl.textContent = summarize(parentForm ? parentForm : modal); } close(); });
    });
    // Inline visibility collapsible
    document.querySelectorAll("[data-mm-toggle]").forEach(function(btn){
        btn.addEventListener("click", function(){
            var panel = btn.closest(".tpw-mm-vis-collapsible").querySelector(".tpw-mm-vis-panel");
            if (!panel) return;
            var isHidden = panel.hasAttribute("hidden");
            if (isHidden) panel.removeAttribute("hidden"); else panel.setAttribute("hidden","hidden");
        });
    });
    // Update summary text when visibility controls change
    document.querySelectorAll(".tpw-mm-modal form").forEach(function(f){
        f.addEventListener("change", function(e){
            var summary = f.querySelector("[data-mm-summary]");
            if (summary) summary.textContent = summarize(f);
        });
    });
        // Save buttons inside modals: submit the first form inside the dialog
            document.querySelectorAll("[data-mm-submit]").forEach(btn=>{
                btn.addEventListener("click", function(){
                    const modalSel = btn.getAttribute("data-mm-submit");
                const modal = document.querySelector(modalSel);
                if (!modal) return;
                const form = modal.querySelector("form");
                if (form) form.submit();
            });
        });
    // Nested Sortable init and indent/unindent gestures
    function initNested(){
        var root = document.getElementById("tpw-mm-root");
        if (!root || !window.Sortable) return;
    var lists = document.querySelectorAll(".tpw-mm-list");
        lists.forEach(function(ul){
            new Sortable(ul, {
                group: "tpw-mm",
                animation: 150,
                handle: ".tpw-mm-handle",
                fallbackOnBody: true,
                swapThreshold: 0.65
            });
        });

        var startX = 0; var dragged = null;
        document.addEventListener("dragstart", function(e){
            var h = e.target && e.target.matches && e.target.matches(".tpw-mm-handle") ? e.target : null;
            if (h) { startX = (e.clientX || 0); dragged = h.closest(".tpw-mm-item"); }
        }, true);
        document.addEventListener("dragend", function(e){
            if (!dragged) return; var endX = (e.clientX || startX); var dx = endX - startX; var li = dragged; dragged = null;
            if (Math.abs(dx) < 24) return; // small moves ignored
            if (dx > 0) {
                // indent: move into previous sibling if exists
                var prev = li.previousElementSibling;
                if (prev) {
                    var child = prev.querySelector(":scope > .tpw-mm-list");
                    if (!child) { child = document.createElement("ul"); child.className = "tpw-mm-list"; prev.appendChild(child); new Sortable(child, {group:"tpw-mm", animation:150, handle:".tpw-mm-handle", fallbackOnBody:true, swapThreshold:0.65}); }
                    child.appendChild(li);
                }
            } else {
                // unindent: move after parent li if parent exists
                var parentLi = li.parentElement && li.parentElement.closest(".tpw-mm-item");
                if (parentLi) {
                    var hostUl = parentLi.parentElement; // UL of parent level
                    hostUl.insertBefore(li, parentLi.nextElementSibling);
                }
            }
        }, true);

        // Serialize tree on submit
        var form = document.getElementById("tpw-mm-save-structure");
        if (form) {
            form.addEventListener("submit", function(){
                var tree = [];
                function walk(ul){
                    var out = [];
                    ul.querySelectorAll(":scope > .tpw-mm-item").forEach(function(li){
                        var id = parseInt(li.getAttribute("data-id"),10) || 0;
                        var childUl = li.querySelector(":scope > .tpw-mm-list");
                        out.push({ id: id, children: childUl ? walk(childUl) : [] });
                    });
                    return out;
                }
                tree = walk(document.getElementById("tpw-mm-root"));
                var hidden = document.getElementById("tpw-mm-tree-json");
                if (hidden) hidden.value = JSON.stringify(tree);
            });
        }
    }

    ensureSortable(function(){ initNested(); });
})();
</script>';
