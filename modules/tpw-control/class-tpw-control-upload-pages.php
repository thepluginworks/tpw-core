<?php
// AJAX handler for inline file delete
add_action('wp_ajax_tpw_control_delete_file', function() {
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Not logged in.'], 403);
    $fid = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    $pid = isset($_POST['page_id']) ? (int)$_POST['page_id'] : 0;
    $nonce = $_POST['_wpnonce'] ?? '';
    if (!$fid || !$pid || !$nonce || !wp_verify_nonce($nonce, 'tpw_control_upload_pages')) wp_send_json_error(['message'=>'Invalid request.'], 400);
    // Permission: must be able to edit the page
    if (!class_exists('TPW_Control_UI') || !TPW_Control_UI::user_has_access(['logged_in'=>true,'flags_any'=>['is_committee','is_admin']])) wp_send_json_error(['message'=>'No permission.'], 403);
    // Confirm file belongs to page
    global $wpdb;
    $table = $wpdb->prefix . 'tpw_upload_files';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND page_id=%d", $fid, $pid));
    if (!$row) wp_send_json_error(['message'=>'File not found.'], 404);
    // Remove files from disk (main + thumbnail) when present
    $upload_files_table = $wpdb->prefix . 'tpw_upload_files';
    $frow = $wpdb->get_row($wpdb->prepare("SELECT file_path, thumbnail_url FROM $upload_files_table WHERE id=%d", $fid));
    if ( $frow && ! empty($frow->file_path) && file_exists( $frow->file_path ) ) { @unlink( $frow->file_path ); }
    if ( $frow && ! empty($frow->thumbnail_url ) ) {
        $upload = wp_upload_dir();
        $thumb_rel = str_replace( $upload['baseurl'], '', $frow->thumbnail_url );
        $thumb_abs = $upload['basedir'] . $thumb_rel;
        if ( file_exists( $thumb_abs ) ) { @unlink( $thumb_abs ); }
    }
    // Delete file record
    $ok = TPW_Control_Upload_Pages::delete_file($fid);
    if ($ok) wp_send_json_success();
    wp_send_json_error(['message'=>'Delete failed.'], 500);
});

// AJAX handler for sorting files (update sort_order)
add_action('wp_ajax_tpw_control_sort_files', function(){
    if ( ! is_user_logged_in() ) wp_send_json_error(['message'=>'Not logged in'], 403);
    $nonce = $_POST['_wpnonce'] ?? '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'tpw_control_upload_pages' ) ) wp_send_json_error(['message'=>'Bad nonce'], 400);
    if ( ! class_exists('TPW_Control_UI') || ! TPW_Control_UI::user_has_access( [ 'logged_in' => true, 'flags_any' => ['is_committee','is_admin'] ] ) ) wp_send_json_error(['message'=>'No permission'], 403);
    $page_id = isset($_POST['page_id']) ? (int) $_POST['page_id'] : 0;
    $order = isset($_POST['order']) && is_array($_POST['order']) ? array_map('intval', $_POST['order']) : [];
    if ( ! $page_id || empty($order) ) wp_send_json_error(['message'=>'Invalid data'], 400);
    TPW_Control_Upload_Pages::reorder_files( $page_id, $order );
    wp_send_json_success();
});

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Upload Pages section built into TPW Control.
 * - DB: tpw_upload_pages, tpw_upload_files
 * - CRUD for pages and files
 * - Visibility JSON stored per-page using Core keys: is_admin, is_committee, is_match_manager, is_noticeboard_admin, status
 * - Exposes render() suitable for future shortcode usage
 */
class TPW_Control_Upload_Pages {
    /**
     * When rendering the editor on the Upload Pages edit screen, we want media uploaded via
     * the Add Media button to go into a dedicated folder under uploads (tpw-upload-pages/editor/).
     * We'll set a flag and hook upload_dir during render and clear it afterwards (best-effort).
     */
    protected static $using_custom_editor_upload_dir = false;

    protected static function enable_custom_editor_upload_dir() {
        if ( self::$using_custom_editor_upload_dir ) return;
        self::$using_custom_editor_upload_dir = true;
        add_filter( 'upload_dir', [ __CLASS__, 'filter_upload_dir_for_editor' ], 50 );
    }

    protected static function disable_custom_editor_upload_dir() {
        if ( ! self::$using_custom_editor_upload_dir ) return;
        remove_filter( 'upload_dir', [ __CLASS__, 'filter_upload_dir_for_editor' ], 50 );
        self::$using_custom_editor_upload_dir = false;
    }

    public static function filter_upload_dir_for_editor( $uploads ) {
        // Only adjust when our context flag is present on the upload request
        if ( empty( $_REQUEST['tpw_upl_editor'] ) ) return $uploads;
        // Route to tpw-upload-pages/editor/YYYY/MM
        $time = current_time( 'mysql' );
        $y = mysql2date( 'Y', $time );
        $m = mysql2date( 'm', $time );
        $subdir = "/tpw-upload-pages/editor/$y/$m";
        $uploads['subdir'] = $subdir;
        $uploads['path']   = $uploads['basedir'] . $subdir;
        $uploads['url']    = $uploads['baseurl'] . $subdir;
        // Ensure directory exists
        if ( ! wp_mkdir_p( $uploads['path'] ) ) {
            // Fallback to default if creation fails
            return $uploads;
        }
        return $uploads;
    }
    public static function ensure_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $pages = $wpdb->prefix . 'tpw_upload_pages';
        $files = $wpdb->prefix . 'tpw_upload_files';

        // Recreate pages table if missing wp_page_id or layout (test site directive)
        $pages_describe = $wpdb->get_results( "DESCRIBE {$pages}" );
        $drop_pages = false;
        if ( empty( $pages_describe ) ) {
            $drop_pages = true;
        } else {
            $cols = array_map( function($r){ return isset($r->Field) ? $r->Field : ( $r['Field'] ?? '' ); }, (array)$pages_describe );
            if ( ! in_array( 'wp_page_id', $cols, true ) || ! in_array( 'layout', $cols, true ) ) {
                // On test sites we are allowed to drop and recreate to introduce new column
                $drop_pages = true;
            }
        }
        if ( $drop_pages ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$pages}" );
        }
        $sql_pages = "CREATE TABLE {$pages} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(150) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description MEDIUMTEXT NULL,
            visibility LONGTEXT NULL,
            wp_page_id INT NULL,
            layout VARCHAR(20) NOT NULL DEFAULT 'table',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug_unique (slug),
            KEY wp_page_id (wp_page_id)
        ) {$charset};";
        dbDelta( $sql_pages );

        // Detect legacy schema and drop if needed (to remove attachment_id and introduce new columns)
        $describe = $wpdb->get_results( "DESCRIBE {$files}" );
        $need_recreate = false;
        if ( empty($describe) ) {
            $need_recreate = true;
        } else {
            $cols = array_map( function($r){ return isset($r->Field) ? $r->Field : ( $r['Field'] ?? '' ); }, (array)$describe );
            if ( in_array('attachment_id', $cols, true) || ! in_array('file_path', $cols, true) ) {
                $need_recreate = true;
            }
        }
        if ( $need_recreate ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$files}" );
        }
        $sql_files = "CREATE TABLE {$files} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_id INT UNSIGNED NOT NULL,
            file_path TEXT NOT NULL,
            file_url TEXT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            label VARCHAR(255) DEFAULT '',
            year INT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            thumbnail_url TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_sort (page_id, sort_order),
            KEY page_year (page_id, year)
        ) {$charset};";
        dbDelta( $sql_files );
    }

    // ---- Pages CRUD ----
    public static function get_pages() {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_pages';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY title ASC" );
    }
    public static function get_page_by_id( $id ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_pages';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", (int)$id ) );
    }
    public static function get_page_by_slug( $slug ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_pages';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug=%s", sanitize_title( $slug ) ) );
    }
    protected static function make_wp_page_content( $slug ) {
        $slug = sanitize_title( $slug );
        return '[tpw_upload_page slug="' . $slug . '"]';
    }

    protected static function create_linked_wp_page( $title, $slug ) {
        $args = [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => wp_strip_all_tags( (string)$title ),
            'post_name'    => sanitize_title( $slug ),
            'post_content' => self::make_wp_page_content( $slug ),
        ];
        $post_id = wp_insert_post( $args, true );
        if ( is_wp_error( $post_id ) ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('[TPW Control][upload-pages] Failed to create WP Page: ' . $post_id->get_error_message());
            return 0;
        }
        // Tag page so other features can recognise it
        add_post_meta( (int)$post_id, '_tpw_page_type', 'upload_page', true );
        return (int) $post_id;
    }

    /**
     * Find published WP pages whose content contains our shortcode for a given slug.
     * Returns an array of WP_Post objects (id, post_title minimal guaranteed).
     */
    public static function find_wp_pages_for_slug( $slug ) {
        global $wpdb; $slug = sanitize_title( (string) $slug );
        if ( $slug === '' ) return [];
        // Match more loosely to allow extra attributes/whitespace in shortcode
        $like = '%tpw_upload_page%slug=\"' . $wpdb->esc_like( $slug ) . '\"%';
        $posts_table = $wpdb->posts;
        $sql = $wpdb->prepare(
            "SELECT ID, post_title FROM {$posts_table} WHERE post_type='page' AND post_status='publish' AND post_content LIKE %s ORDER BY post_title ASC",
            $like
        );
        $rows = $wpdb->get_results( $sql );
        return is_array($rows) ? $rows : [];
    }

    public static function create_page( $data ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_pages';
        $slug = sanitize_title( $data['slug'] ?? '' );
        if ( $slug === '' ) $slug = sanitize_title( $data['title'] ?? '' );
        $vis  = self::normalize_visibility_for_store( $data['visibility'] ?? [] );
        $layout = self::sanitize_layout( $data['layout'] ?? 'table' );
        // Create linked WP Page first
        $wp_page_id = self::create_linked_wp_page( ( $data['title'] ?? '' ), $slug );
        $ok = $wpdb->insert( $table, [
            'slug' => $slug,
            'title' => sanitize_text_field( $data['title'] ?? '' ),
            'description' => wp_kses_post( $data['description'] ?? '' ),
            'visibility' => wp_json_encode( $vis ),
            'wp_page_id' => $wp_page_id ?: null,
            'layout' => $layout,
        ], [ '%s','%s','%s','%s','%d','%s' ] );
        return $ok ? (int)$wpdb->insert_id : 0;
    }
    public static function update_page( $id, $data ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_pages';
        $fields = [];$fmts=[];
        if ( isset( $data['slug'] ) ) { $fields['slug'] = sanitize_title( $data['slug'] ); $fmts[]='%s'; }
        if ( isset( $data['title'] ) ) { $fields['title'] = sanitize_text_field( $data['title'] ); $fmts[]='%s'; }
        if ( isset( $data['description'] ) ) { $fields['description'] = wp_kses_post( $data['description'] ); $fmts[]='%s'; }
        if ( isset( $data['visibility'] ) ) { $fields['visibility'] = wp_json_encode( self::normalize_visibility_for_store( $data['visibility'] ) ); $fmts[]='%s'; }
        if ( isset( $data['layout'] ) ) { $fields['layout'] = self::sanitize_layout( $data['layout'] ); $fmts[]='%s'; }
        if ( empty( $fields ) ) return false;
        $ok = $wpdb->update( $table, $fields, [ 'id' => (int)$id ], $fmts, [ '%d' ] );

        // Maintain linked WP Page title/content if exists
        $row = self::get_page_by_id( (int)$id );
        if ( $row && ! empty( $row->wp_page_id ) ) {
            $post = get_post( (int) $row->wp_page_id );
            if ( $post && $post->post_type === 'page' ) {
                $new_title = isset($data['title']) ? (string)$data['title'] : $post->post_title;
                $new_slug  = isset($data['slug']) ? sanitize_title( $data['slug'] ) : $row->slug;
                $content   = self::make_wp_page_content( $new_slug );
                wp_update_post( [ 'ID' => (int)$row->wp_page_id, 'post_title' => $new_title, 'post_content' => $content ] );
                // Ensure meta exists
                if ( ! get_post_meta( (int)$row->wp_page_id, '_tpw_page_type', true ) ) {
                    add_post_meta( (int)$row->wp_page_id, '_tpw_page_type', 'upload_page', true );
                }
            }
        }
        return $ok !== false;
    }
    public static function delete_page( $id ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_pages';
        $files = $wpdb->prefix . 'tpw_upload_files';
        // Trash linked WP Page if present
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT wp_page_id FROM {$table} WHERE id=%d", (int)$id ) );
        if ( $row && ! empty( $row->wp_page_id ) ) {
            $pid = (int) $row->wp_page_id;
            if ( get_post( $pid ) ) {
                wp_trash_post( $pid );
            }
        }
        $wpdb->delete( $files, [ 'page_id' => (int)$id ], [ '%d' ] );
        return (bool) $wpdb->delete( $table, [ 'id' => (int)$id ], [ '%d' ] );
    }

    // ---- Files CRUD ----
    public static function get_files( $page_id ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_files';
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE page_id=%d ORDER BY year DESC, sort_order ASC, id ASC", (int)$page_id ) );
    }
    public static function add_file_record( $page_id, $file_path, $file_url, $file_type, $label = '', $year = null, $sort_order = 0, $thumbnail_url = null ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_files';
        $wpdb->insert( $table, [
            'page_id' => (int)$page_id,
            'file_path' => $file_path,
            'file_url' => $file_url,
            'file_type' => sanitize_text_field( $file_type ),
            'label' => sanitize_text_field( $label ),
            'year' => $year !== null ? (int)$year : null,
            'sort_order' => (int)$sort_order,
            'thumbnail_url' => $thumbnail_url,
        ], [ '%d','%s','%s','%s','%s','%d','%d','%s' ] );
        return (int)$wpdb->insert_id;
    }
    public static function update_file( $id, $fields ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_files';
        $data = [];$fmts=[];
        foreach ( ['label','year','sort_order'] as $k ) {
            if ( array_key_exists( $k, $fields ) ) {
                if ( $k === 'label' ) { $data[$k] = sanitize_text_field( $fields[$k] ); $fmts[]='%s'; }
                else { $data[$k] = (int) $fields[$k]; $fmts[]='%d'; }
            }
        }
        if ( empty( $data ) ) return false;
        return false !== $wpdb->update( $table, $data, [ 'id' => (int)$id ], $fmts, [ '%d' ] );
    }
    public static function delete_file( $id ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_files';
        return (bool) $wpdb->delete( $table, [ 'id' => (int)$id ], [ '%d' ] );
    }
    public static function reorder_files( $page_id, array $ordered_ids ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_files';
        $order = 0;
        foreach ( $ordered_ids as $fid ) {
            $wpdb->update( $table, [ 'sort_order' => $order++ ], [ 'id' => (int)$fid, 'page_id' => (int)$page_id ], [ '%d' ], [ '%d','%d' ] );
        }
        return true;
    }

    // ---- Form Handling ----
    public static function handle_post() {
        if ( empty($_POST['tpw_control_upload_pages_action']) ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce( $_POST['_wpnonce'], 'tpw_control_upload_pages' ) ) return;

    // Only allow if user can see the section (committee or admin)
    if ( ! class_exists('TPW_Control_UI') || ! TPW_Control_UI::user_has_access( [ 'logged_in' => true, 'flags_any' => ['is_committee','is_admin'] ] ) ) return;

        $action = sanitize_key( $_POST['tpw_control_upload_pages_action'] );

        switch ( $action ) {
            case 'create_page': {
                $vis = self::read_visibility_from_request();
                $id = self::create_page([
                    'title' => $_POST['title'] ?? '',
                    'slug'  => $_POST['slug'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'visibility' => $vis,
                    // layout not shown on create modal, default in create_page()
                ]);
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('[TPW Control][upload-pages] Created page id=' . (int)$id);
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $id ]);
                break;
            }
            case 'create_linked_wp_page': {
                $page_id = (int) ($_POST['upload_page_id'] ?? 0);
                $row = self::get_page_by_id( $page_id );
                if ( $row ) {
                    $new_id = self::create_linked_wp_page( $row->title, $row->slug );
                    if ( $new_id ) {
                        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_pages';
                        $wpdb->update( $table, [ 'wp_page_id' => (int)$new_id ], [ 'id' => (int)$page_id ], [ '%d' ], [ '%d' ] );
                    }
                }
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id ]);
                break;
            }
            case 'link_existing_wp_page': {
                $page_id = (int) ($_POST['upload_page_id'] ?? 0);
                $wp_id   = (int) ($_POST['selected_wp_page_id'] ?? 0);
                if ( $page_id && $wp_id && get_post( $wp_id ) ) {
                    // Save link and tag meta
                    global $wpdb; $table = $wpdb->prefix . 'tpw_upload_pages';
                    $wpdb->update( $table, [ 'wp_page_id' => $wp_id ], [ 'id' => $page_id ], [ '%d' ], [ '%d' ] );
                    if ( ! get_post_meta( $wp_id, '_tpw_page_type', true ) ) add_post_meta( $wp_id, '_tpw_page_type', 'upload_page', true );
                }
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id ]);
                break;
            }
            case 'update_page': {
                $page_id = (int) ($_POST['upload_page_id'] ?? 0);
                $vis = self::read_visibility_from_request();
                self::update_page( $page_id, [
                    'title' => $_POST['title'] ?? '',
                    'slug'  => $_POST['slug'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'visibility' => $vis,
                    'layout' => isset($_POST['layout']) ? sanitize_text_field( wp_unslash( $_POST['layout'] ) ) : null,
                ]);
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('[TPW Control][upload-pages] Updated page id=' . (int)$page_id);
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id ]);
                break;
            }
            case 'recreate_wp_page': {
                $page_id = (int) ($_POST['upload_page_id'] ?? 0);
                $row = self::get_page_by_id( $page_id );
                if ( $row ) {
                    $new_id = self::create_linked_wp_page( $row->title, $row->slug );
                    if ( $new_id ) {
                        // Save new link
                        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_pages';
                        $wpdb->update( $table, [ 'wp_page_id' => (int)$new_id ], [ 'id' => (int)$page_id ], [ '%d' ], [ '%d' ] );
                    }
                }
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id ]);
                break;
            }
            case 'delete_page': {
                $page_id = (int) ($_POST['upload_page_id'] ?? 0);
                self::delete_page( $page_id );
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('[TPW Control][upload-pages] Deleted page id=' . (int)$page_id);
                self::redirect_section();
                break;
            }
            case 'add_files': {
                $page_id = (int) ($_POST['upload_page_id'] ?? 0);
                $ajax = ! empty($_POST['tpw_ajax']);
                $result = self::handle_file_uploads( $page_id );
                if ( $ajax ) {
                    if ( ! empty($result['errors']) ) {
                        wp_send_json_error(['message' => implode("\n", $result['errors'])]);
                    }
                    wp_send_json_success(['uploaded' => $result['uploaded']]);
                }
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id ]);
                break;
            }
            case 'update_files': {
                $page_id = (int) ($_POST['upload_page_id'] ?? 0);
                $labels = isset($_POST['file_label']) && is_array($_POST['file_label']) ? $_POST['file_label'] : [];
                $years  = isset($_POST['file_year']) && is_array($_POST['file_year']) ? $_POST['file_year'] : [];
                $orders = isset($_POST['file_order']) && is_array($_POST['file_order']) ? $_POST['file_order'] : [];
                $delete = isset($_POST['delete_ids']) && is_array($_POST['delete_ids']) ? array_map('intval', $_POST['delete_ids']) : [];
                foreach ( $labels as $fid => $label ) {
                    if ( in_array( (int)$fid, $delete, true ) ) continue;
                    self::update_file( (int)$fid, [
                        'label' => $label,
                        'year'  => isset($years[$fid]) ? (int)$years[$fid] : null,
                        'sort_order' => isset($orders[$fid]) ? (int)$orders[$fid] : 0,
                    ] );
                }
                foreach ( $delete as $fid ) {
                    self::delete_file( (int)$fid );
                }
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id ]);
                break;
            }
            case 'delete_file': {
                $page_id = (int) ($_POST['page_id'] ?? 0);
                $fid = (int) ($_POST['file_id'] ?? 0);
                self::delete_file( $fid );
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id ]);
                break;
            }
        }
    }

    protected static function handle_file_uploads( $page_id ) {
        $out = [ 'uploaded' => [], 'errors' => [] ];
        if ( empty($_FILES['upload_files']) || empty($_FILES['upload_files']['name']) ) return $out;
        $names = $_FILES['upload_files']['name'];
        $types = $_FILES['upload_files']['type'];
        $tmpns = $_FILES['upload_files']['tmp_name'];
        $errs  = $_FILES['upload_files']['error'];
        $sizes = $_FILES['upload_files']['size'];
        $year  = isset($_POST['upload_year']) && $_POST['upload_year'] !== '' ? (int) $_POST['upload_year'] : null;
        $bulk_label = isset($_POST['upload_label']) ? sanitize_text_field( wp_unslash( $_POST['upload_label'] ) ) : '';

        // Prepare storage paths
        $upload = wp_upload_dir();
        $subdir = 'tpw-upload-pages/' . date('Y') . '/' . date('m');
        $target_dir = trailingslashit( $upload['basedir'] ) . $subdir;
        $target_url = trailingslashit( $upload['baseurl'] ) . $subdir;
        if ( ! wp_mkdir_p( $target_dir ) ) {
            $out['errors'][] = 'Could not create upload directory.';
            return $out;
        }

        // Settings: max size (MB)
        $settings = (array) get_option( 'tpw_control_settings', [] );
        $max_mb = isset($settings['max_upload_mb']) ? (int)$settings['max_upload_mb'] : 10;
        if ( $max_mb < 1 ) $max_mb = 1; if ( $max_mb > 50 ) $max_mb = 50;
        $max_bytes = $max_mb * 1024 * 1024;

        $allowed = self::allowed_types();

        require_once ABSPATH . 'wp-admin/includes/image.php';

        foreach ( (array)$names as $i => $n ) {
            if ( (int)$errs[$i] !== UPLOAD_ERR_OK ) continue;
            $orig_name = sanitize_file_name( $n );
            $tmp_path  = $tmpns[$i];
            $size      = (int) $sizes[$i];
            $type_hint = $types[$i];

            // Block PHP/executables by extension explicitly
            $ext = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );
            $blocked = ['php','php3','php4','php5','phtml','phar','cgi','pl','exe','sh','bat','js','jsp','asp'];
            if ( in_array( $ext, $blocked, true ) ) {
                $out['errors'][] = 'This file type is not supported. Allowed types: PDF, Word, Excel, JPG, PNG, MP4.';
                continue;
            }

            // Validate size
            if ( $size > $max_bytes ) {
                $out['errors'][] = 'This file is too large. Please upload a file under ' . $max_mb . ' MB.';
                continue;
            }

            // Validate type by extension and mime
            $mime = wp_check_filetype_and_ext( $tmp_path, $orig_name );
            $mime_type = $mime['type'] ?: $type_hint;
            if ( ! self::is_allowed_type( $ext, $mime_type, $allowed ) ) {
                $out['errors'][] = 'This file type is not supported. Allowed types: PDF, Word, Excel, JPG, PNG, MP4.';
                continue;
            }

            // Ensure unique file name in target dir
            $base = pathinfo( $orig_name, PATHINFO_FILENAME );
            $dest_name = $base . '.' . $ext;
            $dest_path = trailingslashit( $target_dir ) . $dest_name;
            $suffix = 1;
            while ( file_exists( $dest_path ) ) {
                $dest_name = $base . '-' . $suffix++ . '.' . $ext;
                $dest_path = trailingslashit( $target_dir ) . $dest_name;
            }

            // Move uploaded file securely
            if ( ! @move_uploaded_file( $tmp_path, $dest_path ) ) {
                $out['errors'][] = 'Failed to move the uploaded file.';
                continue;
            }
            @chmod( $dest_path, 0644 );

            $public_url = trailingslashit( $target_url ) . rawurlencode( $dest_name );
            $thumb_url = null;

            // Image processing (jpg/png)
            if ( in_array( $ext, ['jpg','jpeg','png'], true ) ) {
                $editor = wp_get_image_editor( $dest_path );
                if ( ! is_wp_error( $editor ) ) {
                    // Resize to max 1600px width
                    $editor->resize( 1600, null, false );
                    if ( in_array( $ext, ['jpg','jpeg'], true ) ) {
                        if ( method_exists( $editor, 'set_quality' ) ) $editor->set_quality( 80 );
                    }
                    $editor->save( $dest_path );

                    // Thumbnail 300px width
                    $editor2 = wp_get_image_editor( $dest_path );
                    if ( ! is_wp_error( $editor2 ) ) {
                        $editor2->resize( 300, null, false );
                        $thumb_name = $base . '-thumb.' . $ext;
                        $thumb_path = trailingslashit( $target_dir ) . $thumb_name;
                        $saved = $editor2->save( $thumb_path );
                        if ( ! is_wp_error( $saved ) ) {
                            $thumb_url = trailingslashit( $target_url ) . rawurlencode( $thumb_name );
                        }
                    }
                }
            }

            $label = $bulk_label !== '' ? $bulk_label : sanitize_file_name( $base );
            $id = self::add_file_record( $page_id, $dest_path, $public_url, $mime_type, $label, $year, 0, $thumb_url );
            $out['uploaded'][] = $id;
        }
        return $out;
    }

    protected static function allowed_types() {
        return [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'mp4'  => 'video/mp4',
        ];
    }
    protected static function is_allowed_type( $ext, $mime_type, $allowed ) {
        $ext = strtolower($ext);
        if ( isset($allowed[$ext]) ) return true;
        // Allow when mime matches any allowed (some browsers send generic)
        return in_array( $mime_type, array_values($allowed), true );
    }

    protected static function normalize_visibility_for_store( $visibility ) {
        // Support both flat keys and advanced model; store flat for this feature
        if ( is_string( $visibility ) ) { $decoded = json_decode( $visibility, true ); if ( json_last_error() === JSON_ERROR_NONE ) $visibility = $decoded; }
        $out = [
            'is_admin' => ! empty( $visibility['is_admin'] ),
            'is_committee' => ! empty( $visibility['is_committee'] ),
            'is_match_manager' => ! empty( $visibility['is_match_manager'] ),
            'is_noticeboard_admin' => ! empty( $visibility['is_noticeboard_admin'] ),
            'status' => [],
        ];
        if ( ! empty( $visibility['status'] ) && is_array( $visibility['status'] ) ) {
            $out['status'] = array_values( array_unique( array_filter( array_map( 'strval', $visibility['status'] ) ) ) );
        }
        // Default admin-only if nothing specified
        if ( ! $out['is_admin'] && ! $out['is_committee'] && ! $out['is_match_manager'] && ! $out['is_noticeboard_admin'] && empty( $out['status'] ) ) {
            $out['is_admin'] = true;
        }
        return $out;
    }

    protected static function read_visibility_from_request() {
        $statuses = isset($_POST['visibility_status']) && is_array($_POST['visibility_status']) ? array_map('sanitize_text_field', $_POST['visibility_status']) : [];
        return [
            'is_admin' => ! empty($_POST['visibility_is_admin']),
            'is_committee' => ! empty($_POST['visibility_is_committee']),
            'is_match_manager' => ! empty($_POST['visibility_is_match_manager']),
            'is_noticeboard_admin' => ! empty($_POST['visibility_is_noticeboard_admin']),
            'status' => $statuses,
        ];
    }

    protected static function redirect_section( $args = [] ) {
        // Prefer an explicit page URL from the form, then fall back to current menu URL, then referer, then home
        $posted_url = isset($_POST['_tpw_control_page_url']) ? esc_url_raw( wp_unslash( $_POST['_tpw_control_page_url'] ) ) : '';
        $url = $posted_url ?: TPW_Control_UI::menu_url('upload-pages');
        if ( empty( $url ) ) {
            $ref = wp_get_referer();
            $url = $ref ? $ref : home_url( '/' );
        }
        foreach ( $args as $k => $v ) { $url = add_query_arg( $k, $v, $url ); }
        // If possible, send a proper redirect header
        if ( ! headers_sent() ) {
            wp_safe_redirect( $url );
            exit;
        }
        // Fallback: clean any buffers and force client-side redirect
        while ( ob_get_level() > 0 ) { @ob_end_clean(); }
        $safe = esc_url( $url );
        echo '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $safe . '">'
            . '<script>window.location.replace(' . wp_json_encode( $url ) . ');</script>'
            . '</head><body><a href="' . $safe . '">Continue</a></body></html>';
        exit;
    }

    protected static function sanitize_layout( $layout ) {
        $layout = is_string( $layout ) ? strtolower( trim( $layout ) ) : 'table';
        $allowed = ['table','list','cards'];
        return in_array( $layout, $allowed, true ) ? $layout : 'table';
    }

    protected static function get_file_icon_url( $file_url, $file_type ) {
        $ext = strtolower( pathinfo( parse_url( (string)$file_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
        $img_base = defined('TPW_CORE_URL') ? TPW_CORE_URL . 'assets/images/' : '';
        $icon = '';
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
        return $icon;
    }

    protected static function enqueue_public_styles() {
        $handle = 'tpw-upload-pages';
        // Default to module CSS path
        if ( defined('TPW_CORE_URL') && defined('TPW_CORE_PATH') ) {
            // Enqueue shared TPW styles so buttons/typography match branding
            $btn_file = TPW_CORE_PATH . 'assets/css/tpw-buttons.css';
            $btn_url  = TPW_CORE_URL . 'assets/css/tpw-buttons.css';
            $ui_file  = TPW_CORE_PATH . 'assets/css/tpw-ui.css';
            $ui_url   = TPW_CORE_URL . 'assets/css/tpw-ui.css';
            $btn_ver  = file_exists( $btn_file ) ? filemtime( $btn_file ) : '1.0.0';
            $ui_ver   = file_exists( $ui_file ) ? filemtime( $ui_file ) : '1.0.0';
            if ( ! wp_style_is( 'tpw-buttons', 'enqueued' ) ) wp_enqueue_style( 'tpw-buttons', $btn_url, [], $btn_ver );
            if ( ! wp_style_is( 'tpw-ui', 'enqueued' ) ) wp_enqueue_style( 'tpw-ui', $ui_url, [], $ui_ver );
            $css_file = TPW_CORE_PATH . 'modules/tpw-control/assets/css/tpw-upload-pages.css';
            $css_url  = TPW_CORE_URL . 'modules/tpw-control/assets/css/tpw-upload-pages.css';
            $ver = file_exists( $css_file ) ? filemtime( $css_file ) : '1.0.0';
            wp_enqueue_style( $handle, $css_url, [], $ver );
        }
    }

    // Public render API to support future [tpw_upload_page] shortcode usage
    public static function render_page_public( $slug ) {
        $page = self::get_page_by_slug( $slug );
        if ( ! $page ) return '';
        // Access check
        if ( ! class_exists( 'TPW_Control_UI' ) ) {
            $ui = __DIR__ . '/class-tpw-control-ui.php';
            if ( file_exists( $ui ) ) require_once $ui;
        }
        $vis = json_decode( (string)$page->visibility, true );
        if ( class_exists( 'TPW_Control_UI' ) && ! TPW_Control_UI::user_has_access( self::convert_flat_visibility( $vis ) ) ) {
            return '<div class="tpw-upload-page-error">You do not have permission to view this Upload Page.</div>';
        }
        $files = self::get_files( (int)$page->id );
        $layout = isset($page->layout) ? self::sanitize_layout( $page->layout ) : 'table';

        self::enqueue_public_styles();
        ob_start();
        echo '<div class="tpw-upload-page tpw-upload-' . esc_attr( $layout ) . '">';
        echo '<h3>' . esc_html( $page->title ) . '</h3>';
        if ( ! empty( $page->description ) ) {
            echo apply_filters( 'the_content', (string) $page->description );
        }
        if ( empty( $files ) ) {
            echo '<p class="tpw-empty">' . esc_html__( 'No files uploaded yet.', 'tpw-core' ) . '</p>';
            echo '</div>';
            return ob_get_clean();
        }
        $by_year = [];
        foreach ( $files as $f ) { $y = $f->year ?? 0; $by_year[ $y ][] = $f; }
        krsort( $by_year );

        if ( $layout === 'table' ) {
            foreach ( $by_year as $year => $group ) {
                if ( $year ) echo '<h4>' . esc_html( (string)$year ) . '</h4>';
                echo '<table class="tpw-table tpw-upload-table"><thead><tr><th>' . esc_html__( 'File', 'tpw-core' ) . '</th><th>' . esc_html__( 'Year', 'tpw-core' ) . '</th><th>' . esc_html__( 'Download', 'tpw-core' ) . '</th></tr></thead><tbody>';
                foreach ( $group as $f ) {
                    $url = $f->file_url; $label = $f->label !== '' ? $f->label : basename( parse_url($url, PHP_URL_PATH) );
                    $icon = self::get_file_icon_url( $url, $f->file_type );
                    echo '<tr>';
                    echo '<td>' . ( $icon ? '<img src="' . esc_url( $icon ) . '" alt="" class="tpw-file-icon" /> ' : '' ) . esc_html( $label ) . '</td>';
                    echo '<td>' . esc_html( (string) ( $f->year ?: '' ) ) . '</td>';
                    echo '<td><a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Download', 'tpw-core' ) . '</a></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
        } elseif ( $layout === 'list' ) {
            foreach ( $by_year as $year => $group ) {
                if ( $year ) echo '<h4>' . esc_html( (string)$year ) . '</h4>';
                echo '<ul class="tpw-upload-list">';
                foreach ( $group as $f ) {
                    $url = $f->file_url; $label = $f->label !== '' ? $f->label : basename( parse_url($url, PHP_URL_PATH) );
                    $icon = self::get_file_icon_url( $url, $f->file_type );
                    $year_out = $f->year ? '<span class="tpw-year">(' . (int)$f->year . ')</span>' : '';
                    echo '<li>' . ( $icon ? '<img src="' . esc_url( $icon ) . '" alt="" class="tpw-file-icon" /> ' : '' ) . '<span class="tpw-file-label">' . esc_html( $label ) . '</span> ' . $year_out . ' <a class="tpw-btn tpw-btn-light" style="margin-left:8px" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Download', 'tpw-core' ) . '</a></li>';
                }
                echo '</ul>';
            }
        } else { // cards
            echo '<div class="tpw-upload-cards">';
            foreach ( $by_year as $year => $group ) {
                if ( $year ) echo '<h4 class="tpw-year-heading">' . esc_html( (string)$year ) . '</h4>';
                foreach ( $group as $f ) {
                    $url = $f->file_url; $label = $f->label !== '' ? $f->label : basename( parse_url($url, PHP_URL_PATH) );
                    $icon = self::get_file_icon_url( $url, $f->file_type );
                    echo '<div class="tpw-card">';
                    if ( $icon ) echo '<div class="tpw-card-icon"><img class="tpw-file-icon-large" src="' . esc_url( $icon ) . '" alt="" /></div>';
                    echo '<h4 class="tpw-card-title">' . esc_html( $label ) . '</h4>';
                    if ( $f->year ) echo '<p class="tpw-card-meta">' . esc_html( (string) $f->year ) . '</p>';
                    echo '<div class="tpw-card-actions"><a class="tpw-btn tpw-btn-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Download', 'tpw-core' ) . '</a></div>';
                    echo '</div>';
                }
            }
            echo '</div>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    protected static function convert_flat_visibility( $vis ) {
        if ( ! is_array( $vis ) ) return [];
        $flags = [];
        foreach ( ['is_admin','is_committee','is_match_manager','is_noticeboard_admin'] as $k ) {
            if ( ! empty( $vis[$k] ) ) $flags[] = $k;
        }
        $out = [];
        // Important: Do not include 'is_admin' in flag constraints for public access.
        // Admins are already granted unconditional access in TPW_Control_UI::user_has_access().
        // Including it here would require users to be admins in addition to any status rules,
        // unintentionally blocking members who should be allowed via statuses.
        $flags = array_values( array_filter( $flags, function( $f ) { return $f !== 'is_admin'; } ) );
        if ( ! empty( $flags ) ) $out['flags_any'] = $flags;
        if ( ! empty( $vis['status'] ) && is_array( $vis['status'] ) ) $out['allowed_statuses'] = $vis['status'];
        if ( empty( $out ) ) $out['flags_any'] = ['is_admin'];
        return $out;
    }

    public static function render() {
        self::handle_post();
        $template = __DIR__ . '/templates/sections/upload-pages.php';
        if ( file_exists( $template ) ) { include $template; return; }
        echo '<p>' . esc_html__( 'Upload Pages module not available.', 'tpw-core' ) . '</p>';
    }
}

// Register our upload_dir filter globally at a low priority; it only activates when tpw_upl_editor flag is present.
add_filter( 'upload_dir', [ 'TPW_Control_Upload_Pages', 'filter_upload_dir_for_editor' ], 50 );

// Register public shortcode to render a single Upload Page by slug
add_action( 'init', function(){
    add_shortcode( 'tpw_upload_page', function( $atts ) {
        $atts = shortcode_atts( [ 'slug' => '' ], $atts, 'tpw_upload_page' );
        $slug = sanitize_title( $atts['slug'] );
        if ( ! class_exists( 'TPW_Control_Upload_Pages' ) ) return '<div class="tpw-upload-page-error">Upload Page module not available.</div>';
        if ( $slug === '' ) return '<div class="tpw-upload-page-error">Upload Page not found: missing slug.</div>';
        $out = TPW_Control_Upload_Pages::render_page_public( $slug );
        if ( $out === '' ) {
            return '<div class="tpw-upload-page-error">Upload Page not found for slug: ' . esc_html( $slug ) . '</div>';
        }
        return $out;
    } );
});
