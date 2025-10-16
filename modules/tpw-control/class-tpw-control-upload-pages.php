<?php
// AJAX handler for inline file delete
add_action('wp_ajax_tpw_control_delete_file', function() {
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Not logged in.'], 403);
    $fid = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    $pid = isset($_POST['page_id']) ? (int)$_POST['page_id'] : 0;
    $nonce = $_POST['_wpnonce'] ?? '';
    if (!$fid || !$pid || !$nonce || !wp_verify_nonce($nonce, 'tpw_control_upload_pages')) wp_send_json_error(['message'=>'Invalid request.'], 400);
    // Permission: must be able to edit the page (committee or admin)
    if ( ! class_exists('TPW_Control_UI') ) {
        $ui = __DIR__ . '/class-tpw-control-ui.php';
        if ( file_exists( $ui ) ) require_once $ui;
    }
    if ( ! class_exists('TPW_Control_UI') ) {
        wp_send_json_error(['message'=>'No permission.'], 403);
    }
    // Explicit check: allow if admin OR committee
    if ( ! ( TPW_Control_UI::is_admin() || TPW_Control_UI::is_committee() ) ) {
        wp_send_json_error(['message'=>'No permission.'], 403);
    }
    // Confirm file link belongs to page
    global $wpdb;
    $links = $wpdb->prefix . 'tpw_upload_pages_files';
    $row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$links} WHERE id=%d AND page_id=%d", $fid, $pid));
    // If already missing or page mismatch, consider it success (idempotent delete)
    if (!$row) wp_send_json_success();
    // Delete link (and physical file if no more links)
    $ok = TPW_Control_Upload_Pages::delete_file($fid);
    if ($ok) wp_send_json_success();
    // If primary delete path reported failure, double-check if the record is already gone
    $still_there = (bool) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(1) FROM {$links} WHERE id=%d", $fid) );
    if ( ! $still_there ) {
        wp_send_json_success();
    }
    $err = $wpdb->last_error ? (' DB: ' . $wpdb->last_error) : '';
    wp_send_json_error(['message'=>'Delete failed.' . $err], 500);
});

// Provide a consistent JSON response for unauthenticated requests too (will return Not logged in.)
add_action('wp_ajax_nopriv_tpw_control_delete_file', function(){
    wp_send_json_error(['message' => 'Not logged in.'], 403);
});

// AJAX: Return the current Trash panel HTML for a given Upload Page
add_action('wp_ajax_tpw_control_get_trash', function(){
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Not logged in.'], 403);
    $page_id = isset($_POST['page_id']) ? (int) $_POST['page_id'] : 0;
    $nonce   = $_POST['_wpnonce'] ?? '';
    if (!$page_id || !$nonce || !wp_verify_nonce($nonce, 'tpw_control_upload_pages')) wp_send_json_error(['message' => 'Invalid request.'], 400);

    // Permission: allow admins and committee
    if ( ! class_exists('TPW_Control_UI') ) {
        $ui = __DIR__ . '/class-tpw-control-ui.php';
        if ( file_exists( $ui ) ) require_once $ui;
    }
    if ( ! class_exists('TPW_Control_UI') || ! ( TPW_Control_UI::is_admin() || TPW_Control_UI::is_committee() ) ) {
        wp_send_json_error(['message' => 'No permission.'], 403);
    }

    global $wpdb; 
    $links_table = $wpdb->prefix . 'tpw_upload_pages_files';
    $files_table = $wpdb->prefix . 'tpw_files';
    $trashed = $wpdb->get_results( $wpdb->prepare( "SELECT l.*, f.file_type, f.file_url, f.thumbnail_url FROM {$links_table} l JOIN {$files_table} f ON f.file_id=l.file_id WHERE l.page_id=%d AND l.is_deleted=1 ORDER BY l.deleted_at DESC", (int)$page_id ) );

    if ( empty( $trashed ) ) {
        wp_send_json_success([ 'html' => '' ]);
    }

    ob_start();
    ?>
    <h4 style="margin:0 0 8px">Trash</h4>
    <form method="post">
        <?php wp_nonce_field( 'tpw_control_upload_pages' ); ?>
        <input type="hidden" name="tpw_control_upload_pages_action" value="bulk_files" />
        <input type="hidden" name="upload_page_id" value="<?php echo (int)$page_id; ?>" />
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
    <?php
    $html = ob_get_clean();
    wp_send_json_success([ 'html' => $html ]);
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

// AJAX handler for sorting categories (update sort_order)
add_action('wp_ajax_tpw_control_sort_categories', function(){
    if ( ! is_user_logged_in() ) wp_send_json_error(['message'=>'Not logged in'], 403);
    $nonce = $_POST['_wpnonce'] ?? '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'tpw_control_upload_pages' ) ) wp_send_json_error(['message'=>'Bad nonce'], 400);
    if ( ! class_exists('TPW_Control_UI') || ! TPW_Control_UI::user_has_access( [ 'logged_in' => true, 'flags_any' => ['is_committee','is_admin'] ] ) ) wp_send_json_error(['message'=>'No permission'], 403);
    $page_id = isset($_POST['page_id']) ? (int) $_POST['page_id'] : 0;
    $order = isset($_POST['order']) && is_array($_POST['order']) ? array_map('intval', $_POST['order']) : [];
    if ( ! $page_id || empty($order) ) wp_send_json_error(['message'=>'Invalid data'], 400);
    TPW_Control_Upload_Pages::reorder_categories( $page_id, $order );
    wp_send_json_success();
});
add_action('wp_ajax_nopriv_tpw_control_sort_categories', function(){
    wp_send_json_error(['message' => 'Not logged in'], 403);
});

// AJAX: Download CSV template for bulk import
add_action('wp_ajax_tpw_control_download_import_template', function(){
    if ( ! is_user_logged_in() ) {
        status_header( 403 ); echo 'Not logged in.'; exit;
    }
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'tpw_control_upload_pages' ) ) {
        status_header( 400 ); echo 'Bad nonce.'; exit;
    }
    if ( ! class_exists('TPW_Control_UI') ) {
        $ui = __DIR__ . '/class-tpw-control-ui.php';
        if ( file_exists( $ui ) ) require_once $ui;
    }
    if ( ! class_exists('TPW_Control_UI') || ! TPW_Control_UI::user_has_access( [ 'logged_in' => true, 'flags_any' => ['is_committee','is_admin'] ] ) ) {
        status_header( 403 ); echo 'No permission.'; exit;
    }
    // Send CSV headers and a single header row
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=manifest-template.csv' );
    echo "filename,label,year,category\r\n";
    exit;
});
add_action('wp_ajax_nopriv_tpw_control_download_import_template', function(){
    status_header( 403 ); echo 'Not logged in.'; exit;
});

// AJAX handler for adding a category inline (returns refreshed categories)
add_action('wp_ajax_tpw_control_add_category', function(){
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Not logged in'], 403);
    $nonce = $_POST['_wpnonce'] ?? '';
    if (!$nonce || !wp_verify_nonce($nonce, 'tpw_control_upload_pages')) wp_send_json_error(['message' => 'Bad nonce'], 400);
    // Ensure UI helper is available
    if ( ! class_exists('TPW_Control_UI') ) {
        $ui = __DIR__ . '/class-tpw-control-ui.php';
        if ( file_exists( $ui ) ) require_once $ui;
    }
    // Permission: allow WP admins, or committee users
    $allowed = false;
    if ( function_exists('current_user_can') && current_user_can('manage_options') ) {
        $allowed = true;
    } elseif ( class_exists('TPW_Control_UI') && TPW_Control_UI::user_has_access( [ 'logged_in' => true, 'flags_any' => ['is_committee'] ] ) ) {
        $allowed = true;
    }
    if ( ! $allowed ) {
        wp_send_json_error(['message' => 'No permission'], 403);
    }
    $page_id = isset($_POST['upload_page_id']) ? (int) $_POST['upload_page_id'] : 0;
    $name = isset($_POST['category_name']) ? (string) $_POST['category_name'] : '';
    if (!$page_id || $name === '') wp_send_json_error(['message' => 'Invalid data'], 400);
    $new_id = TPW_Control_Upload_Pages::add_category($page_id, $name);
    if (!$new_id) wp_send_json_error(['message' => 'Could not create category'], 500);
    $rows = TPW_Control_Upload_Pages::get_categories($page_id);
    $cats = [];
    foreach ( (array)$rows as $r ) {
        $cats[] = [ 'id' => (int)$r->category_id, 'name' => (string)$r->category_name ];
    }
    wp_send_json_success([ 'new_id' => $new_id, 'categories' => $cats ]);
});
add_action('wp_ajax_nopriv_tpw_control_add_category', function(){
    wp_send_json_error(['message' => 'Not logged in'], 403);
});

// nopriv variant for sort (not allowed, but respond with a clear JSON error instead of "0")
add_action('wp_ajax_nopriv_tpw_control_sort_files', function(){
    wp_send_json_error(['message' => 'Not logged in'], 403);
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
        $files_old = $wpdb->prefix . 'tpw_upload_files';
        $cats  = $wpdb->prefix . 'tpw_upload_categories';
        $files = $wpdb->prefix . 'tpw_files';
        $links = $wpdb->prefix . 'tpw_upload_pages_files';

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

        // Categories table
        $sql_cats = "CREATE TABLE {$cats} (
            category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            upload_page_id INT UNSIGNED NOT NULL,
            category_name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (category_id),
            KEY page_sort (upload_page_id, sort_order),
            UNIQUE KEY page_slug (upload_page_id, slug)
        ) {$charset};";
        dbDelta( $sql_cats );

        // New central files table
        $sql_files = "CREATE TABLE {$files} (
            file_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            file_path TEXT NOT NULL,
            file_url TEXT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            thumbnail_url TEXT NULL,
            checksum CHAR(64) NULL,
            uploaded_by INT NULL,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (file_id)
        ) {$charset};";
        dbDelta( $sql_files );

        // Linking table between upload pages and files
        $sql_links = "CREATE TABLE {$links} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_id INT UNSIGNED NOT NULL,
            file_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NULL,
            label VARCHAR(255) DEFAULT '',
            year INT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at DATETIME NULL,
            deleted_by INT NULL,
            PRIMARY KEY (id),
            KEY page_sort (page_id, sort_order),
            KEY page_cat (page_id, category_id),
            KEY page_year (page_id, year),
            KEY file_ref (file_id),
            KEY page_deleted (page_id, is_deleted)
        ) {$charset};";
        dbDelta( $sql_links );

        // Migration: if legacy table exists, move data into new tables (run once)
        $legacy_cols = $wpdb->get_results( $wpdb->prepare( "SHOW TABLES LIKE %s", $files_old ) );
        $did_migrate = get_option( 'tpw_control_files_migrated_to_registry', '0' );
        if ( ! empty( $legacy_cols ) && (string)$did_migrate !== '1' ) {
            // Copy unique files to registry
            $legacy_rows = $wpdb->get_results( "SELECT * FROM {$files_old}" );
            if ( is_array($legacy_rows) ) {
                foreach ( $legacy_rows as $r ) {
                    // Upsert into registry based on file_path
                    $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT file_id FROM {$files} WHERE file_path=%s", $r->file_path ) );
                    if ( ! $exists ) {
                        $wpdb->insert( $files, [
                            'file_path' => $r->file_path,
                            'file_url' => $r->file_url,
                            'file_type' => $r->file_type,
                            'thumbnail_url' => $r->thumbnail_url,
                            'checksum' => null,
                            'uploaded_by' => isset($r->uploaded_by) ? $r->uploaded_by : null,
                            'notes' => isset($r->notes) ? $r->notes : null,
                        ], [ '%s','%s','%s','%s','%s','%d','%s' ] );
                        $exists = (int) $wpdb->insert_id;
                    }
                    // Create link row if an identical link doesn't already exist
                    $conds = [];
                    $conds[] = $wpdb->prepare( 'page_id=%d', (int) $r->page_id );
                    $conds[] = $wpdb->prepare( 'file_id=%d', (int) $exists );
                    $conds[] = $wpdb->prepare( 'label=%s', (string) $r->label );
                    if ( isset($r->year) && $r->year !== null && $r->year !== '' ) {
                        $conds[] = $wpdb->prepare( 'year=%d', (int) $r->year );
                    } else {
                        $conds[] = 'year IS NULL';
                    }
                    if ( isset($r->category_id) && $r->category_id !== null && $r->category_id !== '' ) {
                        $conds[] = $wpdb->prepare( 'category_id=%d', (int) $r->category_id );
                    } else {
                        $conds[] = 'category_id IS NULL';
                    }
                    $sql_check = 'SELECT COUNT(1) FROM ' . $links . ' WHERE ' . implode( ' AND ', $conds );
                    $exists_link = (int) $wpdb->get_var( $sql_check );
                    if ( ! $exists_link ) {
                        $wpdb->insert( $links, [
                            'page_id' => (int) $r->page_id,
                            'file_id' => (int) $exists,
                            'category_id' => isset($r->category_id) ? (int) $r->category_id : null,
                            'label' => (string) $r->label,
                            'year' => isset($r->year) ? (int) $r->year : null,
                            'sort_order' => isset($r->sort_order) ? (int) $r->sort_order : 0,
                            'is_deleted' => 0,
                        ], [ '%d','%d','%d','%s','%d','%d','%d' ] );
                    }
                }
            }
            // Mark migration complete to avoid re-running
            update_option( 'tpw_control_files_migrated_to_registry', '1', false );
            // Optionally keep legacy table for compatibility if other code references it, but our code stops using it.
        }

        // Safety nets: ensure new columns exist
        $cols_files = $wpdb->get_results( "DESCRIBE {$files}" );
        $fields_files = array_map( function($r){ return isset($r->Field) ? $r->Field : ( $r['Field'] ?? '' ); }, (array)$cols_files );
        if ( ! in_array( 'checksum', $fields_files, true ) ) {
            $wpdb->query( "ALTER TABLE {$files} ADD COLUMN checksum CHAR(64) NULL AFTER thumbnail_url" );
        }
        // Ensure checksum index exists for fast dedupe
        $idx_checksum = $wpdb->get_results( $wpdb->prepare("SHOW INDEX FROM {$files} WHERE Key_name=%s", 'checksum_idx') );
        if ( empty($idx_checksum) ) { $wpdb->query( "ALTER TABLE {$files} ADD INDEX checksum_idx (checksum)" ); }
        $cols_links = $wpdb->get_results( "DESCRIBE {$links}" );
        $fields_links = array_map( function($r){ return isset($r->Field) ? $r->Field : ( $r['Field'] ?? '' ); }, (array)$cols_links );
        if ( ! in_array( 'is_deleted', $fields_links, true ) ) {
            $wpdb->query( "ALTER TABLE {$links} ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order" );
        }
        if ( ! in_array( 'deleted_at', $fields_links, true ) ) {
            $wpdb->query( "ALTER TABLE {$links} ADD COLUMN deleted_at DATETIME NULL AFTER is_deleted" );
        }
        if ( ! in_array( 'deleted_by', $fields_links, true ) ) {
            $wpdb->query( "ALTER TABLE {$links} ADD COLUMN deleted_by INT NULL AFTER deleted_at" );
        }
        // Ensure performance indexes on link table
        $existing_indexes = $wpdb->get_results( "SHOW INDEX FROM {$links}" );
        $has_page_deleted = false; $has_file_deleted = false; $has_cat_deleted = false;
        foreach ( (array)$existing_indexes as $ix ) {
            $name = isset($ix->Key_name) ? $ix->Key_name : ( $ix['Key_name'] ?? '' );
            if ( $name === 'page_deleted' ) $has_page_deleted = true;
            if ( $name === 'file_deleted' ) $has_file_deleted = true;
            if ( $name === 'cat_deleted' ) $has_cat_deleted = true;
        }
        if ( ! $has_page_deleted ) { $wpdb->query( "ALTER TABLE {$links} ADD INDEX page_deleted (page_id, is_deleted)" ); }
        if ( ! $has_file_deleted ) { $wpdb->query( "ALTER TABLE {$links} ADD INDEX file_deleted (file_id, is_deleted)" ); }
        if ( ! $has_cat_deleted ) { $wpdb->query( "ALTER TABLE {$links} ADD INDEX cat_deleted (category_id, is_deleted)" ); }

        // Schedule checksum backfill if needed
        self::schedule_checksum_backfill();
    }

    /** Schedule a WP-Cron event to backfill missing checksums in small batches */
    public static function schedule_checksum_backfill() {
        if ( ! function_exists('wp_next_scheduled') || ! function_exists('wp_schedule_event') ) return;
        // Preferred new hook name per guidance
        $new_hook = 'tpw_control_backfill_checksums';
        // Legacy hook name kept for backward compatibility
        $old_hook = 'tpw_control_checksum_backfill';

        $has_new = (bool) wp_next_scheduled( $new_hook );
        $has_old = (bool) wp_next_scheduled( $old_hook );

        // Only schedule if neither event exists to avoid double-running
        if ( ! $has_new && ! $has_old ) {
            wp_schedule_event( time() + 300, 'hourly', $new_hook );
        }

        // Register callbacks for both hook names so either will work
        add_action( $new_hook, [ __CLASS__, 'run_checksum_backfill' ] );
        add_action( $old_hook, [ __CLASS__, 'run_checksum_backfill' ] );
    }

    /**
     * Alias required by guidance: run_checksum_backfill() should perform the checksum backfill.
     * Internally delegates to cron_backfill_checksums() which processes a small batch.
     */
    public static function run_checksum_backfill() {
        // Process a batch of missing checksums; batch size can be filtered if needed
        self::cron_backfill_checksums( 100 );
    }

    /** Process a batch of files missing checksum */
    public static function cron_backfill_checksums( $limit = 100 ) {
        global $wpdb; $files = $wpdb->prefix . 'tpw_files';
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT file_id, file_path FROM {$files} WHERE checksum IS NULL LIMIT %d", (int) $limit ) );
        foreach ( (array)$rows as $r ) {
            $cs = ( file_exists( $r->file_path ) && is_readable( $r->file_path ) && filesize( $r->file_path ) > 0 ) ? @hash_file( 'sha256', $r->file_path ) : '';
            if ( $cs ) { $wpdb->update( $files, [ 'checksum' => $cs ], [ 'file_id' => (int) $r->file_id ], [ '%s' ], [ '%d' ] ); }
        }
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
        $new_id = $ok ? (int)$wpdb->insert_id : 0;
        if ( $new_id ) {
            // Ensure default "General" category exists
            self::ensure_default_category( $new_id );
        }
        return $new_id;
    }

    // ---- Categories CRUD ----
    public static function get_categories( $page_id ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_categories';
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE upload_page_id=%d ORDER BY sort_order ASC, category_name ASC", (int)$page_id ) );
    }
    public static function get_categories_map( $page_id ) {
        $rows = self::get_categories( $page_id );
        $map = [];
        foreach ( (array)$rows as $r ) { $map[(int)$r->category_id] = [ 'name' => (string)$r->category_name, 'slug' => (string)$r->slug ]; }
        return $map;
    }
    protected static function unique_category_slug( $page_id, $name, $proposed = '' ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_categories';
        $base = sanitize_title( $proposed !== '' ? $proposed : $name );
        if ( $base === '' ) $base = 'category';
        $slug = $base; $i = 2;
        while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$table} WHERE upload_page_id=%d AND slug=%s", (int)$page_id, $slug ) ) > 0 ) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
    public static function add_category( $page_id, $name, $slug = '' ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_categories';
        $page_id = (int) $page_id; if ( $page_id <= 0 ) return 0;
        $name = sanitize_text_field( $name ); if ( $name === '' ) return 0;
        $slug = self::unique_category_slug( $page_id, $name, $slug );
        $max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sort_order) FROM {$table} WHERE upload_page_id=%d", $page_id ) );
        $ok = $wpdb->insert( $table, [
            'upload_page_id' => $page_id,
            'category_name' => $name,
            'slug' => $slug,
            'sort_order' => $max + 1,
        ], [ '%d','%s','%s','%d' ] );
        return $ok ? (int)$wpdb->insert_id : 0;
    }
    public static function update_category( $category_id, $fields ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_categories';
        $data = [];$fmts=[];
        if ( isset($fields['category_name']) ) { $data['category_name'] = sanitize_text_field( $fields['category_name'] ); $fmts[]='%s'; }
        if ( isset($fields['slug']) ) { $data['slug'] = sanitize_title( $fields['slug'] ); $fmts[]='%s'; }
        if ( isset($fields['sort_order']) ) { $data['sort_order'] = (int)$fields['sort_order']; $fmts[]='%d'; }
        if ( empty($data) ) return false;
        return false !== $wpdb->update( $table, $data, [ 'category_id' => (int)$category_id ], $fmts, [ '%d' ] );
    }
    public static function delete_category( $page_id, $category_id ) {
        global $wpdb; $cats = $wpdb->prefix . 'tpw_upload_categories'; $files = $wpdb->prefix . 'tpw_upload_files';
        $page_id = (int)$page_id; $category_id = (int)$category_id; if ( $page_id <= 0 || $category_id <= 0 ) return false;
        // Reassign files: prefer General if exists, else NULL
        $general_id = self::ensure_default_category( $page_id );
        if ( $general_id && $general_id !== $category_id ) {
            $wpdb->update( $files, [ 'category_id' => $general_id ], [ 'page_id' => $page_id, 'category_id' => $category_id ], [ '%d' ], [ '%d','%d' ] );
        } else {
            $wpdb->update( $files, [ 'category_id' => null ], [ 'page_id' => $page_id, 'category_id' => $category_id ], [ '%d' ], [ '%d','%d' ] );
        }
        return (bool) $wpdb->delete( $cats, [ 'upload_page_id' => $page_id, 'category_id' => $category_id ], [ '%d','%d' ] );
    }
    public static function reorder_categories( $page_id, array $ordered_ids ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_categories';
        $order = 0; foreach ( $ordered_ids as $cid ) { $wpdb->update( $table, [ 'sort_order' => $order++ ], [ 'category_id' => (int)$cid, 'upload_page_id' => (int)$page_id ], [ '%d' ], [ '%d','%d' ] ); }
        return true;
    }
    public static function ensure_default_category( $page_id ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_categories';
        $page_id = (int)$page_id; if ( $page_id <= 0 ) return 0;
        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$table} WHERE upload_page_id=%d", $page_id ) );
        if ( $count === 0 ) { return self::add_category( $page_id, 'General', 'general' ); }
        $id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT category_id FROM {$table} WHERE upload_page_id=%d AND slug=%s", $page_id, 'general' ) );
        if ( $id ) return $id;
        $id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT category_id FROM {$table} WHERE upload_page_id=%d ORDER BY sort_order ASC, category_name ASC LIMIT 1", $page_id ) );
        return $id ?: 0;
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
        $links = $wpdb->prefix . 'tpw_upload_pages_files';
        $files = $wpdb->prefix . 'tpw_files';
        // Trash linked WP Page if present
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT wp_page_id FROM {$table} WHERE id=%d", (int)$id ) );
        if ( $row && ! empty( $row->wp_page_id ) ) {
            $pid = (int) $row->wp_page_id;
            if ( get_post( $pid ) ) {
                wp_trash_post( $pid );
            }
        }
        // Collect referenced file_ids for this page before removing links
        $file_ids = $wpdb->get_col( $wpdb->prepare( "SELECT file_id FROM {$links} WHERE page_id=%d", (int)$id ) );
        // Remove all links for this page
        $wpdb->delete( $links, [ 'page_id' => (int)$id ], [ '%d' ] );
        // For each file, if no other links reference it, remove from disk and delete from registry
        if ( is_array( $file_ids ) ) {
            foreach ( $file_ids as $fid ) {
                $fid = (int) $fid; if ( $fid <= 0 ) continue;
                $refs = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$links} WHERE file_id=%d", $fid ) );
                if ( $refs === 0 ) {
                    $f = $wpdb->get_row( $wpdb->prepare( "SELECT file_path, thumbnail_url FROM {$files} WHERE file_id=%d", $fid ) );
                    if ( $f ) {
                        if ( ! empty( $f->file_path ) && file_exists( $f->file_path ) ) { @unlink( $f->file_path ); }
                        if ( ! empty( $f->thumbnail_url ) ) {
                            $upload = wp_upload_dir();
                            $thumb_rel = str_replace( $upload['baseurl'], '', $f->thumbnail_url );
                            $thumb_abs = $upload['basedir'] . $thumb_rel;
                            if ( file_exists( $thumb_abs ) ) { @unlink( $thumb_abs ); }
                        }
                        $wpdb->delete( $files, [ 'file_id' => $fid ], [ '%d' ] );
                    }
                }
            }
        }
        return (bool) $wpdb->delete( $table, [ 'id' => (int)$id ], [ '%d' ] );
    }

    // ---- Files CRUD ----
    public static function get_files( $page_id ) {
        global $wpdb; $files = $wpdb->prefix . 'tpw_files'; $links = $wpdb->prefix . 'tpw_upload_pages_files';
        // Allow external providers to fully override file retrieval (e.g., pagination/search engines)
        $override = apply_filters( 'tpw_control_upload_pages_get_files_override', null, (int) $page_id );
        if ( is_array( $override ) ) {
            return $override;
        }
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT l.id, l.page_id, l.category_id, l.label, l.year, l.sort_order,
                    f.file_id, f.file_path, f.file_url, f.file_type, f.thumbnail_url, f.uploaded_by, f.notes, f.created_at
             FROM {$links} l JOIN {$files} f ON f.file_id = l.file_id
             WHERE l.page_id=%d AND l.is_deleted=0
             ORDER BY l.year DESC, l.sort_order ASC, l.id ASC",
            (int)$page_id
        ) );
        // Post-filter to adjust/augment rows if needed
        return apply_filters( 'tpw_control_upload_pages_files', $rows, (int) $page_id );
    }
    public static function add_file_record( $page_id, $file_path, $file_url, $file_type, $label = '', $year = null, $sort_order = 0, $thumbnail_url = null, $category_id = null, $uploaded_by = null, $notes = null ) {
        global $wpdb; $files = $wpdb->prefix . 'tpw_files'; $links = $wpdb->prefix . 'tpw_upload_pages_files';
        // Upsert registry entry by checksum (preferred) or file_path to allow reuse
        $checksum = null;
        if ( file_exists( $file_path ) && is_readable( $file_path ) && filesize($file_path) > 0 ) {
            $checksum = @hash_file( 'sha256', $file_path ) ?: null;
        }
        if ( $checksum ) {
            $fid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT file_id FROM {$files} WHERE checksum=%s", $checksum ) );
        } else {
            $fid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT file_id FROM {$files} WHERE file_path=%s", $file_path ) );
        }
        if ( ! $fid ) {
            $wpdb->insert( $files, [
                'file_path' => $file_path,
                'file_url' => $file_url,
                'file_type' => sanitize_text_field( $file_type ),
                'thumbnail_url' => $thumbnail_url,
                'checksum' => $checksum,
                'uploaded_by' => $uploaded_by !== null ? (int) $uploaded_by : null,
                'notes' => is_string( $notes ) ? sanitize_textarea_field( $notes ) : ( $notes === null ? null : sanitize_text_field( (string) $notes ) ),
            ], [ '%s','%s','%s','%s','%s','%d','%s' ] );
            $fid = (int) $wpdb->insert_id;
        }
        // Before inserting a link, ensure an identical active link doesn't already exist
        $conds = [];
        $conds[] = $wpdb->prepare( 'page_id=%d', (int) $page_id );
        $conds[] = $wpdb->prepare( 'file_id=%d', (int) $fid );
        $conds[] = $wpdb->prepare( 'label=%s', sanitize_text_field( $label ) );
        $conds[] = 'is_deleted=0';
        if ( $year !== null && $year !== '' ) { $conds[] = $wpdb->prepare( 'year=%d', (int) $year ); } else { $conds[] = 'year IS NULL'; }
        if ( $category_id ) { $conds[] = $wpdb->prepare( 'category_id=%d', (int) $category_id ); } else { $conds[] = 'category_id IS NULL'; }
        $exists_link = (int) $wpdb->get_var( 'SELECT COUNT(1) FROM ' . $links . ' WHERE ' . implode( ' AND ', $conds ) );
        if ( $exists_link ) {
            // Return the existing link id when possible to avoid duplicates
            $sel_sql = 'SELECT id FROM ' . $links . ' WHERE ' . implode( ' AND ', $conds ) . ' ORDER BY id DESC LIMIT 1';
            $existing_id = (int) $wpdb->get_var( $sel_sql );
            return $existing_id ?: 0;
        }
        // Create link row for this page
        $wpdb->insert( $links, [
            'page_id' => (int) $page_id,
            'file_id' => (int) $fid,
            'category_id' => $category_id ? (int) $category_id : null,
            'label' => sanitize_text_field( $label ),
            'year' => $year !== null ? (int) $year : null,
            'sort_order' => (int) $sort_order,
            'is_deleted' => 0,
        ], [ '%d','%d','%d','%s','%d','%d','%d' ] );
        return (int) $wpdb->insert_id; // return link id
    }
    /** Quickly create a link to an existing registry file */
    protected static function link_existing_file( $page_id, $file_id, $label = '', $year = null, $category_id = null, $sort_order = 0 ) {
        global $wpdb; $links = $wpdb->prefix . 'tpw_upload_pages_files';
        $wpdb->insert( $links, [
            'page_id' => (int)$page_id,
            'file_id' => (int)$file_id,
            'category_id' => $category_id ? (int)$category_id : null,
            'label' => sanitize_text_field( $label ),
            'year' => $year !== null ? (int)$year : null,
            'sort_order' => (int)$sort_order,
            'is_deleted' => 0,
        ], [ '%d','%d','%d','%s','%d','%d','%d' ] );
        return (int) $wpdb->insert_id;
    }
    public static function update_file( $id, $fields ) {
        global $wpdb; $links = $wpdb->prefix . 'tpw_upload_pages_files';
        $data = [];$fmts=[];
        foreach ( ['label','year','sort_order','category_id'] as $k ) {
            if ( array_key_exists( $k, $fields ) ) {
                if ( $k === 'label' ) { $data[$k] = sanitize_text_field( $fields[$k] ); $fmts[]='%s'; }
                else { $data[$k] = $fields[$k] !== null ? (int)$fields[$k] : null; $fmts[]='%d'; }
            }
        }
        if ( empty( $data ) ) return false;
        $ok = false !== $wpdb->update( $links, $data, [ 'id' => (int)$id ], $fmts, [ '%d' ] );
        if ( $ok ) {
            do_action( 'tpw_control_upload_pages_file_updated', (int) $id, $data );
        }
        return $ok;
    }
    public static function delete_file( $id ) {
        global $wpdb; $links = $wpdb->prefix . 'tpw_upload_pages_files'; $files = $wpdb->prefix . 'tpw_files';
        $id = (int) $id;
        if ( $id <= 0 ) return false;
        // Soft delete the link (trash)
        $wpdb->update( $links, [ 'is_deleted' => 1, 'deleted_at' => current_time('mysql'), 'deleted_by' => function_exists('get_current_user_id') ? (int) get_current_user_id() : null ], [ 'id' => $id ], [ '%d','%s','%d' ], [ '%d' ] );
        do_action( 'tpw_control_upload_pages_file_deleted', (int) $id );
        return true;
    }
    /** Permanently remove a link and cleanup the underlying file if unreferenced */
    protected static function purge_link( $id ) {
        global $wpdb; $links = $wpdb->prefix . 'tpw_upload_pages_files'; $files = $wpdb->prefix . 'tpw_files';
        $id = (int)$id; if ( $id <= 0 ) return false;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT file_id FROM {$links} WHERE id=%d", $id ) );
        if ( ! $row ) return true;
        $file_id = (int) $row->file_id;
        $wpdb->delete( $links, [ 'id' => $id ], [ '%d' ] );
        $refcount = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$links} WHERE file_id=%d AND is_deleted=0", $file_id ) );
        if ( $refcount === 0 ) {
            $f = $wpdb->get_row( $wpdb->prepare( "SELECT file_path, thumbnail_url FROM {$files} WHERE file_id=%d", $file_id ) );
            if ( $f ) {
                if ( ! empty( $f->file_path ) && file_exists( $f->file_path ) ) { @unlink( $f->file_path ); }
                if ( ! empty( $f->thumbnail_url ) ) {
                    $upload = wp_upload_dir();
                    $thumb_rel = str_replace( $upload['baseurl'], '', $f->thumbnail_url );
                    $thumb_abs = $upload['basedir'] . $thumb_rel;
                    if ( file_exists( $thumb_abs ) ) { @unlink( $thumb_abs ); }
                }
                $wpdb->delete( $files, [ 'file_id' => $file_id ], [ '%d' ] );
            }
        }
        return true;
    }
    /** Restore a trashed link */
    protected static function restore_link( $id ) {
        global $wpdb; $links = $wpdb->prefix . 'tpw_upload_pages_files'; $cats = $wpdb->prefix . 'tpw_upload_categories';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, page_id, category_id FROM {$links} WHERE id=%d", (int)$id ) );
        if ( ! $row ) return false;
        $cat_ok = true;
        if ( ! empty( $row->category_id ) ) {
            $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$cats} WHERE category_id=%d", (int) $row->category_id ) );
            $cat_ok = $exists > 0;
        }
        $new_cat = null;
        if ( ! $cat_ok ) { $new_cat = self::ensure_default_category( (int) $row->page_id ); }
        return false !== $wpdb->update( $links, [ 'is_deleted' => 0, 'deleted_at' => null, 'deleted_by' => null ] + ( $new_cat ? [ 'category_id' => (int) $new_cat ] : [] ), [ 'id' => (int)$id ], [ '%d','%s','%d','%d' ], [ '%d' ] );
    }
    public static function reorder_files( $page_id, array $ordered_ids ) {
        global $wpdb; $table = $wpdb->prefix . 'tpw_upload_pages_files';
        $order = 0;
        foreach ( $ordered_ids as $fid ) {
            $wpdb->update( $table, [ 'sort_order' => $order++ ], [ 'id' => (int)$fid, 'page_id' => (int)$page_id ], [ '%d' ], [ '%d','%d' ] );
        }
        do_action( 'tpw_control_upload_pages_files_reordered', (int) $page_id, array_map( 'intval', $ordered_ids ) );
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
                // Safety: do not delete if files still attached
                $has_files = ! empty( self::get_files( $page_id ) );
                if ( $has_files ) {
                    // Bounce back with an error flag
                    self::redirect_section([ 'err' => 'has_files' ]);
                    break;
                }
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
                $cats   = isset($_POST['file_category']) && is_array($_POST['file_category']) ? $_POST['file_category'] : [];
                $delete = isset($_POST['delete_ids']) && is_array($_POST['delete_ids']) ? array_map('intval', $_POST['delete_ids']) : [];
                foreach ( $labels as $fid => $label ) {
                    if ( in_array( (int)$fid, $delete, true ) ) continue;
                    $cid = isset($cats[$fid]) && $cats[$fid] !== '' ? (int)$cats[$fid] : null;
                    $rawYear = isset($years[$fid]) ? (string) $years[$fid] : '';
                    $yearVal = ($rawYear === '') ? null : (int) $rawYear;
                    self::update_file( (int)$fid, [
                        'label' => $label,
                        'year'  => $yearVal,
                        'sort_order' => isset($orders[$fid]) ? (int)$orders[$fid] : 0,
                        'category_id' => $cid,
                    ] );
                }
                foreach ( $delete as $fid ) {
                    self::delete_file( (int)$fid );
                }
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id ]);
                break;
            }
            case 'bulk_files': {
                $page_id = (int) ($_POST['upload_page_id'] ?? 0);
                $action2 = isset($_POST['bulk_action']) ? sanitize_key( $_POST['bulk_action'] ) : '';
                $ids = isset($_POST['selected_links']) && is_array($_POST['selected_links']) ? array_map('intval', $_POST['selected_links']) : [];
                $report = [ 'action' => $action2, 'ok' => 0, 'fail' => 0, 'items' => [], 'message' => '' ];
                if ( $page_id && ! empty($ids) ) {
                    // Capability check: must be admin or committee
                    if ( ! class_exists('TPW_Control_UI') || ! TPW_Control_UI::user_has_access( [ 'logged_in' => true, 'flags_any' => ['is_committee','is_admin'] ] ) ) {
                        $report['items'][] = [ 'id' => 0, 'status' => 'error', 'message' => 'No permission' ];
                    } else if ( $action2 === 'assign' ) {
                        $target_page = (int) ($_POST['target_page_id'] ?? 0);
                        if ( $target_page > 0 && self::get_page_by_id( $target_page ) ) {
                            // Optional overrides
                            $assign_year = isset($_POST['assign_year']) && $_POST['assign_year'] !== '' ? (int) $_POST['assign_year'] : null;
                            $assign_cat_mode = isset($_POST['assign_category_mode']) ? sanitize_key( $_POST['assign_category_mode'] ) : 'inherit';
                            global $wpdb; $links = $wpdb->prefix . 'tpw_upload_pages_files';
                            foreach ( $ids as $lid ) {
                                $row = $wpdb->get_row( $wpdb->prepare( "SELECT file_id, label, year, category_id FROM {$links} WHERE id=%d", $lid ) );
                                if ( ! $row ) { $report['fail']++; $report['items'][] = [ 'id' => $lid, 'status' => 'error', 'message' => 'Link not found' ]; continue; }
                                $use_year = $assign_year !== null ? $assign_year : ( isset($row->year) ? (int) $row->year : null );
                                $use_cat = ( isset($row->category_id) ? (int) $row->category_id : null );
                                if ( $assign_cat_mode === 'none' ) { $use_cat = null; }
                                elseif ( $assign_cat_mode === 'general' ) { $use_cat = self::ensure_default_category( $target_page ); }
                                $new_id = self::link_existing_file( $target_page, (int) $row->file_id, (string) $row->label, $use_year, $use_cat, 0 );
                                if ( $new_id ) { $report['ok']++; $report['items'][] = [ 'id' => $lid, 'status' => 'ok' ]; }
                                else { $report['fail']++; $report['items'][] = [ 'id' => $lid, 'status' => 'error', 'message' => 'Insert failed' ]; }
                            }
                        } else {
                            $report['items'][] = [ 'id' => 0, 'status' => 'error', 'message' => 'Target page missing' ];
                        }
                    } elseif ( $action2 === 'unlink' ) {
                        foreach ( $ids as $lid ) {
                            $ok = self::delete_file( (int)$lid );
                            if ($ok) { $report['ok']++; $report['items'][] = [ 'id' => $lid, 'status' => 'ok', 'message' => 'Moved to Trash' ]; }
                            else { $report['fail']++; $report['items'][] = [ 'id' => $lid, 'status' => 'error' ]; }
                        }
                        $report['message'] = sprintf( _n( 'Moved %d file to Trash', 'Moved %d files to Trash', (int) $report['ok'], 'tpw-core' ), (int) $report['ok'] );
                    } elseif ( $action2 === 'restore' ) {
                        foreach ( $ids as $lid ) { $ok = self::restore_link( (int)$lid ); if ($ok) { $report['ok']++; $report['items'][] = [ 'id' => $lid, 'status' => 'ok' ]; } else { $report['fail']++; $report['items'][] = [ 'id' => $lid, 'status' => 'error' ]; } }
                    } elseif ( $action2 === 'delete_permanent' ) {
                        foreach ( $ids as $lid ) { $ok = self::purge_link( (int)$lid ); if ($ok) { $report['ok']++; $report['items'][] = [ 'id' => $lid, 'status' => 'ok' ]; } else { $report['fail']++; $report['items'][] = [ 'id' => $lid, 'status' => 'error' ]; } }
                        $report['message'] = sprintf( _n( 'Deleted %d file permanently', 'Deleted %d files permanently', (int) $report['ok'], 'tpw-core' ), (int) $report['ok'] );
                    }
                }
                // Store report transient and log
                $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
                if ( $user_id > 0 && $page_id > 0 ) {
                    set_transient( 'tpw_upl_bulk_report_' . $user_id . '_' . $page_id, $report, 10 * MINUTE_IN_SECONDS );
                }
                do_action( 'tpw_control_upload_pages_bulk_action', $report, $page_id );
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('[TPW Control][upload-pages] Bulk action ' . $action2 . ' by user ' . $user_id . ': ok=' . $report['ok'] . ' fail=' . $report['fail'] );
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id ]);
                break;
            }
            case 'bulk_import': {
                $page_id = (int) ( $_POST['upload_page_id'] ?? 0 );
                $report = self::handle_bulk_import( $page_id, $_FILES['bulk_zip'] ?? null );
                // Store a short-lived transient keyed by user + page for display
                $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
                if ( $user_id > 0 && $page_id > 0 ) {
                    set_transient( 'tpw_upl_import_report_' . $user_id . '_' . $page_id, $report, 10 * MINUTE_IN_SECONDS );
                }
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id, 'import' => 1 ]);
                break;
            }
            case 'add_category': {
                $page_id = (int) ($_POST['upload_page_id'] ?? 0);
                $name = $_POST['category_name'] ?? '';
                if ( $page_id && $name !== '' ) self::add_category( $page_id, $name );
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id, 'tab' => 'categories' ]);
                break;
            }
            case 'update_categories': {
                $page_id = (int) ($_POST['upload_page_id'] ?? 0);
                $names = isset($_POST['cat_name']) && is_array($_POST['cat_name']) ? $_POST['cat_name'] : [];
                $slugs = isset($_POST['cat_slug']) && is_array($_POST['cat_slug']) ? $_POST['cat_slug'] : [];
                $orders = isset($_POST['cat_order']) && is_array($_POST['cat_order']) ? $_POST['cat_order'] : [];
                $delete = isset($_POST['cat_delete']) && is_array($_POST['cat_delete']) ? array_map('intval', $_POST['cat_delete']) : [];
                foreach ( $names as $cid => $nm ) {
                    if ( in_array( (int)$cid, $delete, true ) ) continue;
                    $data = [ 'category_name' => $nm ];
                    if ( isset($slugs[$cid]) && $slugs[$cid] !== '' ) $data['slug'] = $slugs[$cid];
                    if ( isset($orders[$cid]) ) $data['sort_order'] = (int)$orders[$cid];
                    self::update_category( (int)$cid, $data );
                }
                foreach ( $delete as $cid ) {
                    self::delete_category( $page_id, (int)$cid );
                }
                self::redirect_section([ 'sub' => 'edit', 'upload_page_id' => $page_id, 'tab' => 'categories' ]);
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

    /**
     * Handle CSV + ZIP bulk import for a specific Upload Page.
     * Expected manifest file inside the ZIP: manifest.csv
     * Columns (case-insensitive): filename,label,year,category
     * Returns a report array with keys: success_count, error_count, warnings[], errors[], added_ids[], created_categories
     */
    protected static function handle_bulk_import( $page_id, $zip_upload ) {
        $page_id = (int) $page_id;
        $report = [
            'success_count' => 0,
            'error_count' => 0,
            'warnings' => [],
            'errors' => [],
            'added_ids' => [],
            'created_categories' => 0,
        ];
        if ( $page_id <= 0 ) { $report['errors'][] = 'Missing Upload Page ID.'; $report['error_count']++; return $report; }
        if ( ! class_exists('ZipArchive') ) { $report['errors'][] = 'ZIP extension not available on this server.'; $report['error_count']++; return $report; }
        if ( ! is_array( $zip_upload ) || (int) ($zip_upload['error'] ?? 0) !== UPLOAD_ERR_OK ) {
            $report['errors'][] = 'No ZIP file received or upload failed.'; $report['error_count']++; return $report; }
        $orig_name = sanitize_file_name( (string) ( $zip_upload['name'] ?? '' ) );
        $tmp_name  = (string) ( $zip_upload['tmp_name'] ?? '' );
        if ( strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) ) !== 'zip' ) {
            $report['errors'][] = 'Uploaded file is not a ZIP archive.'; $report['error_count']++; return $report; }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $tmp_name ) ) {
            $report['errors'][] = 'Could not open the ZIP archive.'; $report['error_count']++; return $report; }

        // Find manifest.csv (case-insensitive match on basename)
        $manifestIndex = -1; $entries = [];
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i );
            if ( $name === false ) continue;
            $entries[] = $name;
            if ( strtolower( basename( $name ) ) === 'manifest.csv' ) {
                $manifestIndex = $i;
            }
        }
        if ( $manifestIndex < 0 ) {
            $report['errors'][] = 'manifest.csv not found in the ZIP.'; $report['error_count']++; $zip->close(); return $report; }

        // Read manifest contents
        $stream = $zip->getStream( $zip->getNameIndex( $manifestIndex ) );
        if ( ! $stream ) { $report['errors'][] = 'Could not read manifest.csv from ZIP.'; $report['error_count']++; $zip->close(); return $report; }
        $manifestContent = stream_get_contents( $stream ); fclose( $stream );
        // Normalize encoding and strip BOM so header detection works reliably
        if ( is_string( $manifestContent ) && $manifestContent !== '' ) {
            // UTF-16 BOM handling: convert to UTF-8 if detected
            $head2 = substr( $manifestContent, 0, 2 );
            if ( $head2 === "\xFF\xFE" || $head2 === "\xFE\xFF" ) {
                if ( function_exists( 'mb_convert_encoding' ) ) {
                    $manifestContent = @mb_convert_encoding( $manifestContent, 'UTF-8', 'UTF-16' );
                }
            }
            // UTF-8 BOM
            if ( substr( $manifestContent, 0, 3 ) === "\xEF\xBB\xBF" ) {
                $manifestContent = substr( $manifestContent, 3 );
            }
        }
        if ( ! is_string( $manifestContent ) ) { $report['errors'][] = 'Empty manifest.csv.'; $report['error_count']++; $zip->close(); return $report; }

        // Parse CSV
        $rows = [];
        $tmpCsv = tmpfile();
        if ( $tmpCsv ) {
            fwrite( $tmpCsv, $manifestContent );
            fseek( $tmpCsv, 0 );
            $meta = stream_get_meta_data( $tmpCsv );
            $fp = fopen( $meta['uri'], 'r' );
            if ( $fp ) {
                $headers = [];
                if ( ( $h = fgetcsv( $fp ) ) !== false ) {
                    foreach ( $h as $col ) {
                        $col = (string) $col;
                        // Remove any lingering UTF-8 BOM from the first header cell
                        if ( empty( $headers ) && strpos( $col, "\xEF\xBB\xBF" ) === 0 ) {
                            $col = substr( $col, 3 );
                        }
                        $headers[] = strtolower( trim( $col ) );
                    }
                }
                // Expected headers
                // Map indices
                $idx = [ 'filename' => -1, 'label' => -1, 'year' => -1, 'category' => -1 ];
                foreach ( $headers as $i => $col ) {
                    if ( isset( $idx[ $col ] ) ) $idx[ $col ] = $i;
                }
                if ( $idx['filename'] < 0 ) {
                    $report['errors'][] = 'manifest.csv must include a "filename" column.'; $report['error_count']++; fclose($fp); fclose($tmpCsv); $zip->close(); return $report;
                }
                while ( ( $r = fgetcsv( $fp ) ) !== false ) {
                    if ( is_array( $r ) && count( array_filter( $r, function($v){ return trim((string)$v) !== ''; }) ) === 0 ) continue; // skip blank
                    $filename = isset($r[ $idx['filename'] ]) ? trim( (string) $r[ $idx['filename'] ] ) : '';
                    if ( $filename === '' ) continue;
                    $rows[] = [
                        'filename' => $filename,
                        'label'    => ($idx['label']    >= 0 && isset($r[$idx['label']]))    ? trim( (string) $r[$idx['label']] )    : '',
                        'year'     => ($idx['year']     >= 0 && isset($r[$idx['year']]))     ? trim( (string) $r[$idx['year']] )     : '',
                        'category' => ($idx['category'] >= 0 && isset($r[$idx['category']])) ? trim( (string) $r[$idx['category']] ) : '',
                    ];
                }
                fclose( $fp );
            }
            fclose( $tmpCsv );
        }
        if ( empty( $rows ) ) { $report['errors'][] = 'manifest.csv contains no importable rows.'; $report['error_count']++; $zip->close(); return $report; }

        // Build quick lookup of ZIP files by basename (case-insensitive)
        $zipIndexByBase = [];
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i ); if ( $name === false ) continue;
            $base = strtolower( basename( $name ) );
            if ( $base === 'manifest.csv' ) continue;
            // Keep first occurrence only
            if ( ! isset( $zipIndexByBase[ $base ] ) ) { $zipIndexByBase[ $base ] = $i; }
        }

        // Prepare storage paths
        $upload = wp_upload_dir();
        $subdir = 'tpw-upload-pages/' . date('Y') . '/' . date('m');
        $target_dir = trailingslashit( $upload['basedir'] ) . $subdir;
        $target_url = trailingslashit( $upload['baseurl'] ) . $subdir;
        if ( ! wp_mkdir_p( $target_dir ) ) { $report['errors'][] = 'Could not create upload directory.'; $report['error_count']++; $zip->close(); return $report; }
        self::ensure_protected_upload_base();

        $settings = (array) get_option( 'tpw_control_settings', [] );
        $max_mb = isset($settings['max_upload_mb']) ? (int)$settings['max_upload_mb'] : 10; if ( $max_mb < 1 ) $max_mb = 1; if ( $max_mb > 50 ) $max_mb = 50;
        $max_bytes = $max_mb * 1024 * 1024;
        $allowed = self::allowed_types();
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Cache categories map (name/slug insensitive) for this page to avoid repeated queries
        $cats_rows = self::get_categories( $page_id );
        $cats_by_slug = []; $cats_by_name_lc = [];
        foreach ( (array)$cats_rows as $c ) { $cats_by_slug[ (string) $c->slug ] = (int) $c->category_id; $cats_by_name_lc[ strtolower( (string) $c->category_name ) ] = (int) $c->category_id; }

        foreach ( $rows as $line => $row ) {
            $requested = $row['filename'];
            $base_req  = strtolower( basename( $requested ) );
            if ( $base_req === 'manifest.csv' ) { $report['warnings'][] = 'Row ' . ( $line + 2 ) . ': filename cannot be manifest.csv; skipped.'; continue; }
            if ( ! isset( $zipIndexByBase[ $base_req ] ) ) { $report['errors'][] = 'Row ' . ( $line + 2 ) . ': file not found in ZIP: ' . $requested; $report['error_count']++; continue; }
            $idx = $zipIndexByBase[ $base_req ];
            $stat = $zip->statIndex( $idx );
            if ( isset( $stat['size'] ) && (int)$stat['size'] > $max_bytes ) {
                $report['errors'][] = 'Row ' . ( $line + 2 ) . ': file too large (>' . $max_mb . 'MB): ' . $requested; $report['error_count']++; continue;
            }
            // Extract to temp file
            $tmpDir = trailingslashit( sys_get_temp_dir() ) . 'tpw-bulk-' . uniqid();
            if ( ! wp_mkdir_p( $tmpDir ) ) { $report['errors'][] = 'Row ' . ( $line + 2 ) . ': cannot create temp dir.'; $report['error_count']++; continue; }
            $tmpDest = $tmpDir . '/' . basename( $requested );
            $s = $zip->getStream( $zip->getNameIndex( $idx ) );
            if ( ! $s ) { $report['errors'][] = 'Row ' . ( $line + 2 ) . ': cannot read entry from ZIP: ' . $requested; $report['error_count']++; @rmdir( $tmpDir ); continue; }
            $outfp = fopen( $tmpDest, 'w' );
            if ( ! $outfp ) { $report['errors'][] = 'Row ' . ( $line + 2 ) . ': cannot write temp file.'; $report['error_count']++; fclose($s); @rmdir($tmpDir); continue; }
            stream_copy_to_stream( $s, $outfp ); fclose( $s ); fclose( $outfp );

            // Validate type
            $ext = strtolower( pathinfo( $tmpDest, PATHINFO_EXTENSION ) );
            $mime = wp_check_filetype_and_ext( $tmpDest, basename( $tmpDest ) ); $mime_type = $mime['type'] ?: '';
            if ( ! self::is_allowed_type( $ext, $mime_type, $allowed ) ) { $report['errors'][] = 'Row ' . ( $line + 2 ) . ': unsupported file type: ' . $requested; $report['error_count']++; @unlink($tmpDest); @rmdir($tmpDir); continue; }

            // Build unique destination name in target dir
            $base = pathinfo( basename( $tmpDest ), PATHINFO_FILENAME );
            $dest_name = $base . '.' . $ext;
            $dest_path = trailingslashit( $target_dir ) . $dest_name;
            $suffix = 1;
            while ( file_exists( $dest_path ) ) { $dest_name = $base . '-' . $suffix++ . '.' . $ext; $dest_path = trailingslashit( $target_dir ) . $dest_name; }

            // Move to final location
            if ( ! @rename( $tmpDest, $dest_path ) ) { $report['errors'][] = 'Row ' . ( $line + 2 ) . ': failed to move file to uploads.'; $report['error_count']++; @unlink($tmpDest); @rmdir($tmpDir); continue; }
            @chmod( $dest_path, 0644 );
            @rmdir( $tmpDir );

            $public_url = trailingslashit( $target_url ) . rawurlencode( $dest_name );
            $thumb_url = null;

            // Image processing
            if ( in_array( $ext, ['jpg','jpeg','png'], true ) ) {
                $editor = wp_get_image_editor( $dest_path );
                if ( ! is_wp_error( $editor ) ) {
                    $editor->resize( 1600, null, false );
                    if ( in_array( $ext, ['jpg','jpeg'], true ) && method_exists( $editor, 'set_quality' ) ) { $editor->set_quality( 80 ); }
                    $editor->save( $dest_path );
                    $editor2 = wp_get_image_editor( $dest_path );
                    if ( ! is_wp_error( $editor2 ) ) {
                        $editor2->resize( 300, null, false );
                        $thumb_name = $base . '-thumb.' . $ext;
                        $thumb_path = trailingslashit( $target_dir ) . $thumb_name;
                        $saved = $editor2->save( $thumb_path );
                        if ( ! is_wp_error( $saved ) ) { $thumb_url = trailingslashit( $target_url ) . rawurlencode( $thumb_name ); }
                    }
                }
            }

            // Category resolution/creation
            $cid = null; $cat_name = (string) $row['category'];
            if ( $cat_name !== '' ) {
                $slug = sanitize_title( $cat_name );
                if ( isset( $cats_by_slug[ $slug ] ) ) { $cid = (int) $cats_by_slug[ $slug ]; }
                elseif ( isset( $cats_by_name_lc[ strtolower( $cat_name ) ] ) ) { $cid = (int) $cats_by_name_lc[ strtolower( $cat_name ) ]; }
                else {
                    $new_id = self::add_category( $page_id, $cat_name );
                    if ( $new_id ) { $cid = (int) $new_id; $cats_by_slug[ self::unique_category_slug( $page_id, $cat_name, $slug ) ] = $cid; $cats_by_name_lc[ strtolower( $cat_name ) ] = $cid; $report['created_categories']++; }
                }
            }

            // Label and year
            $label = $row['label'] !== '' ? sanitize_text_field( $row['label'] ) : sanitize_file_name( $base );
            $year  = ( $row['year'] !== '' && preg_match('/^\d{4}$/', $row['year']) ) ? (int) $row['year'] : null;

            // Insert file record
            $uploaded_by = function_exists('get_current_user_id') ? (int) get_current_user_id() : null;
            $id = self::add_file_record( $page_id, $dest_path, $public_url, ( $mime_type ?: ( $allowed[$ext] ?? 'application/octet-stream' ) ), $label, $year, 0, $thumb_url, $cid, $uploaded_by, null );
            if ( $id ) { $report['success_count']++; $report['added_ids'][] = (int) $id; }
            else { $report['errors'][] = 'Row ' . ( $line + 2 ) . ': database insert failed for ' . $requested; $report['error_count']++; }
        }

        // Report unreferenced files in ZIP (not in manifest)
        $referenced = array_map( function($r){ return strtolower( basename( $r['filename'] ) ); }, $rows );
        $referenced = array_unique( $referenced );
        $unref = [];
        foreach ( $zipIndexByBase as $base => $i ) { if ( $base !== 'manifest.csv' && ! in_array( $base, $referenced, true ) ) $unref[] = $base; }
        if ( ! empty( $unref ) ) { $report['warnings'][] = 'Files in ZIP not referenced by manifest: ' . implode( ', ', array_slice( $unref, 0, 10 ) ) . ( count($unref) > 10 ? ' …' : '' ); }

        $zip->close();
        return $report;
    }

    protected static function handle_file_uploads( $page_id ) {
        $out = [ 'uploaded' => [], 'errors' => [] ];
        do_action( 'tpw_control_upload_pages_before_handle_uploads', (int) $page_id );
        // Allow an override handler to process uploads (e.g., bulk import pipeline). Must return ['uploaded'=>[], 'errors'=>[]]
        $override = apply_filters( 'tpw_control_upload_pages_handle_uploads', null, (int) $page_id );
        if ( is_array( $override ) && array_key_exists( 'uploaded', $override ) && array_key_exists( 'errors', $override ) ) {
            return $override;
        }
        if ( empty($_FILES['upload_files']) || empty($_FILES['upload_files']['name']) ) return $out;
        $names = $_FILES['upload_files']['name'];
        $types = $_FILES['upload_files']['type'];
        $tmpns = $_FILES['upload_files']['tmp_name'];
        $errs  = $_FILES['upload_files']['error'];
        $sizes = $_FILES['upload_files']['size'];
    $year  = isset($_POST['upload_year']) && $_POST['upload_year'] !== '' ? (int) $_POST['upload_year'] : null;
    $bulk_label = isset($_POST['upload_label']) ? sanitize_text_field( wp_unslash( $_POST['upload_label'] ) ) : '';
    $category_id = isset($_POST['upload_category_id']) && $_POST['upload_category_id'] !== '' ? (int) $_POST['upload_category_id'] : null;

        // Prepare storage paths
        $upload = wp_upload_dir();
        $subdir = 'tpw-upload-pages/' . date('Y') . '/' . date('m');
        $target_dir = trailingslashit( $upload['basedir'] ) . $subdir;
        $target_url = trailingslashit( $upload['baseurl'] ) . $subdir;
        if ( ! wp_mkdir_p( $target_dir ) ) {
            $out['errors'][] = 'Could not create upload directory.';
            return $out;
        }

        // Ensure base protection .htaccess exists to block direct web access
        self::ensure_protected_upload_base();

        // Settings: max size (MB)
        $settings = (array) get_option( 'tpw_control_settings', [] );
        $max_mb = isset($settings['max_upload_mb']) ? (int)$settings['max_upload_mb'] : 10;
        if ( $max_mb < 1 ) $max_mb = 1; if ( $max_mb > 50 ) $max_mb = 50;
        $max_bytes = $max_mb * 1024 * 1024;

        $allowed = self::allowed_types();

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploaded_by = function_exists('get_current_user_id') ? (int) get_current_user_id() : null;
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
            $id = self::add_file_record( $page_id, $dest_path, $public_url, $mime_type, $label, $year, 0, $thumb_url, $category_id, $uploaded_by, null );
            do_action( 'tpw_control_upload_pages_file_added', (int) $id, [
                'page_id' => (int) $page_id,
                'file_path' => $dest_path,
                'file_url' => $public_url,
                'file_type' => $mime_type,
                'label' => $label,
                'year' => $year,
                'thumbnail_url' => $thumb_url,
                'category_id' => $category_id,
                'uploaded_by' => $uploaded_by,
            ] );
            $out['uploaded'][] = $id;
        }
        do_action( 'tpw_control_upload_pages_after_handle_uploads', $out, (int) $page_id );
        return $out;
    }

    /**
     * Create a .htaccess file inside wp-content/uploads/tpw-upload-pages/ to deny direct access.
     */
    protected static function ensure_protected_upload_base() {
        $upload = wp_upload_dir();
        $base = trailingslashit( $upload['basedir'] ) . 'tpw-upload-pages';
        if ( ! file_exists( $base ) ) {
            wp_mkdir_p( $base );
        }
        if ( is_dir( $base ) ) {
            $ht = $base . '/.htaccess';
            if ( ! file_exists( $ht ) ) {
                $rules = "# Auto-generated by TPW Core to protect Upload Pages\n" .
                    "# Deny direct web access to files. Files are served via modules/tpw-control/serve.php with permission checks.\n" .
                    "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
                    "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n";
                // If writing fails, silently continue (best-effort)
                @file_put_contents( $ht, $rules );
            }
            // Also add index.html to prevent directory listing
            $idx = $base . '/index.html';
            if ( ! file_exists( $idx ) ) {
                @file_put_contents( $idx, "" );
            }
        }
    }

    /** Build a signed, time-limited URL to serve a file (or its thumbnail) via the secure handler. */
    public static function build_served_url( $file_id, $variant = 'file', $ttl = 600, $download = false ) {
        // $file_id now represents the link id (primary key in tpw_upload_pages_files)
        $file_id = (int) $file_id; if ( $file_id <= 0 ) return '';
        $ttl = (int) apply_filters( 'tpw_control_upload_pages_signed_url_ttl', (int) $ttl, (string) $variant, (int) $file_id );
        $exp = time() + max(60, (int)$ttl);
        $sig = self::make_signature( $file_id, $variant, $exp );
        $base = defined('TPW_CORE_URL') ? TPW_CORE_URL . 'modules/tpw-control/serve.php' : plugins_url( 'modules/tpw-control/serve.php', dirname( __FILE__, 2 ) );
        $args = [ 'f' => $file_id, 'v' => $variant, 'exp' => $exp, 'sig' => $sig ];
        if ( $download ) $args['dl'] = '1';
        return add_query_arg( $args, $base );
    }

    protected static function secret_key() {
        $salt = '';
        foreach ( ['AUTH_SALT','LOGGED_IN_SALT','SECURE_AUTH_SALT','NONCE_SALT','AUTH_KEY','SECURE_AUTH_KEY'] as $c ) {
            if ( defined( $c ) ) { $salt .= constant( $c ); }
        }
        if ( $salt === '' ) $salt = wp_salt( 'auth' );
        return hash( 'sha256', $salt );
    }

    protected static function make_signature( $file_id, $variant, $exp ) {
        $data = ((int)$file_id) . '|' . (string)$variant . '|' . ((int)$exp);
        return hash_hmac( 'sha256', $data, self::secret_key() );
    }

    protected static function allowed_types() {
        $types = [
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
        // Allow sites to extend supported types (e.g., CSV) without modifying core
        return apply_filters( 'tpw_control_upload_pages_allowed_types', $types );
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
        // Determine extension based on original stored URL when possible; fall back to MIME
        $ext = strtolower( pathinfo( parse_url( (string)$file_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
        if ( $ext === '' || $ext === 'php' ) {
            $map = [
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'video/mp4' => 'mp4',
            ];
            if ( isset( $map[ $file_type ] ) ) $ext = $map[ $file_type ];
        }
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

            // Ensure the shared TPW Control JS is available on public pages using [tpw_upload_page]
            $js_file = TPW_CORE_PATH . 'modules/tpw-control/assets/js/tpw-control.js';
            $js_url  = TPW_CORE_URL . 'modules/tpw-control/assets/js/tpw-control.js';
            $js_ver  = file_exists( $js_file ) ? filemtime( $js_file ) : '1.0.0';
            // jQuery UI Sortable is harmless if present; our script checks before using it
            wp_enqueue_script( 'jquery' );
            if ( ! wp_script_is( 'jquery-ui-sortable', 'enqueued' ) ) wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_script( 'tpw-control', $js_url, [ 'jquery','jquery-ui-sortable' ], $js_ver, true );
            wp_localize_script( 'tpw-control', 'TPW_CONTROL', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'tpw_control' ),
            ] );
            // Hook for pagination/search scripts to enqueue their assets
            do_action( 'tpw_control_upload_pages_enqueue_public_assets' );
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
        do_action( 'tpw_control_upload_pages_before_render', $page );
        if ( class_exists( 'TPW_Control_UI' ) && ! TPW_Control_UI::user_has_access( self::convert_flat_visibility( $vis ) ) ) {
            return '<div class="tpw-upload-page-error">You do not have permission to view this Upload Page.</div>';
        }
    $files = self::get_files( (int)$page->id );
    $files_all = $files; // Preserve original set for independent UI calculations
    $categories = self::get_categories_map( (int)$page->id );
    $layout = isset($page->layout) ? self::sanitize_layout( $page->layout ) : 'table';

        self::enqueue_public_styles();
        ob_start();
    echo '<div class="tpw-upload-page tpw-upload-' . esc_attr( $layout ) . '" data-page="' . (int) $page->id . '" data-year="' . esc_attr( $selected_year ) . '">';
        // Admin-only Edit button to jump to the Upload Pages editor for this page
        if ( class_exists('TPW_Control_UI') && method_exists('TPW_Control_UI','is_admin') && TPW_Control_UI::is_admin() ) {
            // Try to locate the TPW Control hub page (contains [tpw-control])
            $hub_url = apply_filters( 'tpw_control/main_page_url', '' );
            if ( ! $hub_url ) {
                global $wpdb; $posts = $wpdb->posts;
                $like = '%[tpw-control%';
                $pid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$posts} WHERE post_type='page' AND post_status='publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1", $like ) );
                if ( $pid ) { $hub_url = get_permalink( $pid ); }
            }
            // Fallback to current page if not found (still functional if the current is the hub)
            if ( ! $hub_url ) { $hub_url = get_permalink(); }
            // Build edit URL and ask the editor to open the Add File modal immediately
            $edit_url  = add_query_arg( [ TPW_Control::ACTION_QUERY_VAR => 'upload-pages', 'sub' => 'edit', 'upload_page_id' => (int) $page->id, 'open' => 'add_file' ], $hub_url );
            echo '<div class="tpw-admin-tools" style="margin:8px 0 12px">'
                . '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit Upload Page', 'tpw-core' ) . '</a>'
                . '</div>';
        }
        if ( ! empty( $page->description ) ) {
            $desc = (string) $page->description;
            // Rewrite any editor-uploaded image URLs to signed, time-limited handler URLs
            $desc = self::rewrite_description_images( $desc, (int) $page->id );
            echo apply_filters( 'the_content', $desc );
        }
        if ( empty( $files ) ) {
            echo '<p class="tpw-empty">' . esc_html__( 'No files uploaded yet.', 'tpw-core' ) . '</p>';
            echo '</div>';
            return ob_get_clean();
        }
        // Optional category grouping/filter via query var ?tpw_cat=slug (avoid WP core 'cat' conflict)
        $selected_cat = isset($_GET['tpw_cat']) ? sanitize_title($_GET['tpw_cat']) : '';
        if ( $selected_cat === '' && isset($_GET['cat']) ) { $selected_cat = sanitize_title( $_GET['cat'] ); }
        if ( $selected_cat !== '' ) {
            // Filter files by category slug
            $cat_id = 0; foreach ( $categories as $cid => $meta ) { if ( $meta['slug'] === $selected_cat ) { $cat_id = (int)$cid; break; } }
            if ( $cat_id ) { $files = array_values( array_filter( (array)$files, function($f) use ($cat_id){ return (int)$f->category_id === $cat_id; } ) ); }
        }

    // Optional year filter: legacy support via URL only on initial load; otherwise controlled client-side (data-year)
        $selected_year_raw = isset($_GET['tpw_year']) ? sanitize_text_field( wp_unslash( $_GET['tpw_year'] ) ) : ( isset($_GET['year']) ? sanitize_text_field( wp_unslash( $_GET['year'] ) ) : '' );
        $selected_year = '';
        if ( $selected_year_raw !== '' ) {
            if ( strtolower($selected_year_raw) === 'none' ) $selected_year = 'none';
            else if ( preg_match('/^\d{4}$/', $selected_year_raw) ) $selected_year = (string) (int) $selected_year_raw;
        }
        // Default to current year if no explicit selection and there are files for the current year in the category-filtered set
        $files_after_cat = $files;
        $year_param_present = array_key_exists('tpw_year', $_GET) || array_key_exists('year', $_GET);
        if ( $selected_year === '' && ! $year_param_present ) {
            $currY = (int) current_time('Y');
            $has_curr = false;
            foreach ( (array) $files_after_cat as $f ) { if ( (int) ($f->year ?? 0) === $currY ) { $has_curr = true; break; } }
            if ( $has_curr ) { $selected_year = (string) $currY; }
        }
        if ( $selected_year !== '' ) {
            if ( $selected_year === 'none' ) {
                $files = array_values( array_filter( (array)$files, function($f){ $y = isset($f->year) ? (int)$f->year : 0; return $y === 0; } ) );
            } else {
                $ywant = (int) $selected_year;
                $files = array_values( array_filter( (array)$files, function($f) use ($ywant){ return (int)($f->year ?? 0) === $ywant; } ) );
            }
        }

        // Build a category list for UI that only includes categories with files (after the year filter, ignoring category selection)
        $files_for_cat_ui = $files_all;
        if ( $selected_year !== '' ) {
            if ( $selected_year === 'none' ) {
                $files_for_cat_ui = array_values( array_filter( (array)$files_for_cat_ui, function($f){ $y = isset($f->year) ? (int)$f->year : 0; return $y === 0; } ) );
            } else {
                $ywant = (int) $selected_year;
                $files_for_cat_ui = array_values( array_filter( (array)$files_for_cat_ui, function($f) use ($ywant){ return (int)($f->year ?? 0) === $ywant; } ) );
            }
        }
        $cat_ids_with_files = [];
        foreach ( (array)$files_for_cat_ui as $ff ) {
            $cid_ff = (int) ($ff->category_id ?? 0);
            if ( $cid_ff > 0 ) $cat_ids_with_files[$cid_ff] = true;
        }
        // Resolve currently selected category id (if any) to keep it visible even when empty after year filter
        $selected_cat_id = 0; if ( $selected_cat !== '' ) { foreach ( $categories as $cid => $meta ) { if ( $meta['slug'] === $selected_cat ) { $selected_cat_id = (int)$cid; break; } } }
        $categories_for_ui = [];
        foreach ( $categories as $cid => $meta ) {
            if ( isset($cat_ids_with_files[(int)$cid]) || ( $selected_cat_id && (int)$cid === $selected_cat_id ) ) {
                $categories_for_ui[$cid] = $meta;
            }
        }

        // Group by category then by year
        $by_cat = [];
        foreach ( (array)$files as $f ) {
            $cid = (int)($f->category_id ?? 0);
            $by_cat[ $cid ][] = $f;
        }

        // Category and Year filter UI
    $has_categories = ! empty( $categories_for_ui );
        // Derive years present in the category-filtered set (before applying the year filter) to decide if the Year filter is useful
        $year_set = [];
        $has_none_year = false;
        foreach ( (array)$files_after_cat as $f ) {
            $y = isset($f->year) ? (int)$f->year : 0;
            if ( $y > 0 ) $year_set[$y] = true; else $has_none_year = true;
        }
        $years = array_keys( $year_set ); rsort( $years, SORT_NUMERIC );
        $year_option_count = count($years) + ( $has_none_year ? 1 : 0 );

        if ( $has_categories || $year_option_count >= 2 ) {
            // Allow injection before the filter bar (e.g., search input) without altering default markup
            $before_filter_bar = apply_filters( 'tpw_control_upload_pages_before_filter_bar', '', $page );
            if ( is_string( $before_filter_bar ) && $before_filter_bar !== '' ) echo $before_filter_bar;
            echo '<div class="tpw-upload-filter" style="margin:10px 0;display:flex;gap:12px;align-items:center;flex-wrap:wrap">';
            if ( $has_categories ) {
                echo '<div class="tpw-filter tpw-filter-cat">';
                echo '<strong>' . esc_html__('Category:', 'tpw-core') . '</strong> ';
                $base = remove_query_arg( [ 'tpw_cat', 'cat' ] );
                $all_active = ($selected_cat === '');
                $all_url = esc_url( $base );
                echo '<a class="tpw-btn ' . ( $all_active ? 'tpw-btn-primary' : 'tpw-btn-secondary' ) . ' tpw-cat-filter" data-page="' . (int)$page->id . '" data-cat="" href="' . $all_url . '">' . esc_html__('All', 'tpw-core') . '</a> ';
                foreach ( $categories_for_ui as $cid => $meta ) {
                    $url = add_query_arg( 'tpw_cat', $meta['slug'], $base );
                    $active = $selected_cat === $meta['slug'];
                    echo '<a class="tpw-btn ' . ( $active ? 'tpw-btn-primary' : 'tpw-btn-secondary' ) . ' tpw-cat-filter" data-page="' . (int)$page->id . '" data-cat="' . esc_attr( $meta['slug'] ) . '" style="margin-left:6px" href="' . esc_url( $url ) . '">' . esc_html( $meta['name'] ) . '</a>';
                }
                echo '</div>';
            }

            if ( $year_option_count >= 2 ) {
                // Year filter as a dropdown; instance-local state and AJAX (no URL changes)
                echo '<div class="tpw-filter tpw-filter-year" style="display:flex;align-items:center;gap:6px">';
                echo '<label><strong>' . esc_html__('Year:', 'tpw-core') . '</strong> ';
                echo '<select name="tpw_year" data-page="' . (int) $page->id . '">';
                $sel = ( $selected_year === '' ) ? ' selected' : '';
                echo '<option value=""' . $sel . '>' . esc_html__( 'All', 'tpw-core' ) . '</option>';
                foreach ( $years as $y ) {
                    $ys = (string) (int) $y; $sel = ( $selected_year === $ys ) ? ' selected' : '';
                    echo '<option value="' . esc_attr( $ys ) . '"' . $sel . '>' . esc_html( $ys ) . '</option>';
                }
                if ( $has_none_year ) {
                    $sel = ( $selected_year === 'none' ) ? ' selected' : '';
                    echo '<option value="none"' . $sel . '>' . esc_html__( 'Uncategorised', 'tpw-core' ) . '</option>';
                }
                echo '</select></label>';
                echo '</div>';
            }
            echo '</div>';
            // Allow injection after the filter bar
            $after_filter_bar = apply_filters( 'tpw_control_upload_pages_after_filter_bar', '', $page );
            if ( is_string( $after_filter_bar ) && $after_filter_bar !== '' ) echo $after_filter_bar;
        }

        echo '<div class="tpw-upload-list" id="tpw-upload-list-' . (int)$page->id . '" data-page="' . (int)$page->id . '">';
        // Placeholder for future pagination controls above the list
        $before_list = apply_filters( 'tpw_control_upload_pages_before_list', '', $page, $layout );
        if ( is_string( $before_list ) && $before_list !== '' ) echo $before_list;
        echo self::render_file_list_inner( $layout, $by_cat, $categories );
        // Placeholder for future pagination controls below the list
        $after_list = apply_filters( 'tpw_control_upload_pages_after_list', '', $page, $layout );
        if ( is_string( $after_list ) && $after_list !== '' ) echo $after_list;
        echo '</div>';
        echo '</div>';
        do_action( 'tpw_control_upload_pages_after_render', $page );
        return ob_get_clean();
    }

    protected static function render_file_list_inner( $layout, $by_cat, $categories ) {
        ob_start();
        $idx = 0; // sequential index across all previewable items for Lightbox navigation
        if ( $layout === 'table' ) {
            foreach ( $by_cat as $cid => $group_cat ) {
                $cat_label = isset($categories[$cid]) ? $categories[$cid]['name'] : '';
                // Group by year within category
                $by_year = [];
                foreach ( $group_cat as $f ) { $y = $f->year ?? 0; $by_year[ $y ][] = $f; }
                krsort( $by_year );
                foreach ( $by_year as $year => $group ) {
                    // Section header: Category - Year as h2 (omit year if not set)
                    $parts = [];
                    if ( $cat_label !== '' ) $parts[] = (string) $cat_label;
                    if ( (int)$year > 0 ) $parts[] = (string) $year;
                    $title = trim( implode( ' - ', $parts ) );
                    if ( $title === '' ) { $title = esc_html__( 'Documents', 'tpw-core' ); }
                    echo '<h2>' . esc_html( $title ) . '</h2>';

                    // Div-based table using tpw-ui.css classes; single File column
                    echo '<div class="table-container">';
                    foreach ( $group as $f ) {
                        $url = self::build_served_url( (int)$f->id, 'file', 900, false );
                        $label = $f->label !== '' ? $f->label : basename( parse_url($f->file_url, PHP_URL_PATH) );
                        $icon = self::get_file_icon_url( $f->file_url, $f->file_type );
                        $a = '<a class="tpw-upl-preview" data-index="' . (int)$idx . '" data-type="' . esc_attr( $f->file_type ) . '" data-label="' . esc_attr( $label ) . '" href="' . esc_url( $url ) . '">';
                        $link = $a . esc_html( $label ) . '</a>';
                        $thumb = $icon ? ($a . '<img src="' . esc_url( $icon ) . '" alt="" class="tpw-file-icon" />' . '</a> ') : '';
                        echo '<div class="table-row">';
                        echo '<div class="table-cell">' . $thumb . $link . '</div>';
                        echo '</div>';
                        $idx++;
                    }
                    echo '</div>';
                }
            }
        } elseif ( $layout === 'list' ) {
            foreach ( $by_cat as $cid => $group_cat ) {
                $cat_label = isset($categories[$cid]) ? $categories[$cid]['name'] : '';
                // Group by year within category
                $by_year = [];
                foreach ( $group_cat as $f ) { $y = $f->year ?? 0; $by_year[ $y ][] = $f; }
                krsort( $by_year );
                foreach ( $by_year as $year => $group ) {
                    // Section header: Category - Year as h2
                    $parts = [];
                    if ( $cat_label !== '' ) $parts[] = (string) $cat_label;
                    if ( (int)$year > 0 ) $parts[] = (string) $year;
                    $title = trim( implode( ' - ', $parts ) );
                    if ( $title === '' ) { $title = esc_html__( 'Documents', 'tpw-core' ); }
                    echo '<h2>' . esc_html( $title ) . '</h2>';

                    echo '<ul class="tpw-upload-list">';
                    foreach ( $group as $f ) {
                        $url = self::build_served_url( (int)$f->id, 'file', 900, false );
                        $label = $f->label !== '' ? $f->label : basename( parse_url($f->file_url, PHP_URL_PATH) );
                        $icon = self::get_file_icon_url( $f->file_url, $f->file_type );
                        $a = '<a class="tpw-upl-preview" data-index="' . (int)$idx . '" data-type="' . esc_attr( $f->file_type ) . '" data-label="' . esc_attr( $label ) . '" href="' . esc_url( $url ) . '">';
                        $link = $a . esc_html( $label ) . '</a>';
                        $thumb = $icon ? ($a . '<img src="' . esc_url( $icon ) . '" alt="" class="tpw-file-icon" />' . '</a> ') : '';
                        echo '<li>' . $thumb . '<span class="tpw-file-label">' . $link . '</span></li>';
                        $idx++;
                    }
                    echo '</ul>';
                }
            }
        } else { // cards
            echo '<div class="tpw-upload-cards">';
            foreach ( $by_cat as $cid => $group_cat ) {
                $cat_label = isset($categories[$cid]) ? $categories[$cid]['name'] : '';
                // Group by year within category
                $by_year = [];
                foreach ( $group_cat as $f ) { $y = $f->year ?? 0; $by_year[ $y ][] = $f; }
                krsort( $by_year );
                foreach ( $by_year as $year => $group ) {
                    // Section header: Category - Year as h2
                    $parts = [];
                    if ( $cat_label !== '' ) $parts[] = (string) $cat_label;
                    if ( (int)$year > 0 ) $parts[] = (string) $year;
                    $title = trim( implode( ' - ', $parts ) );
                    if ( $title === '' ) { $title = esc_html__( 'Documents', 'tpw-core' ); }
                    echo '<h2>' . esc_html( $title ) . '</h2>';
                    foreach ( $group as $f ) {
                        $url = self::build_served_url( (int)$f->id, 'file', 900, false );
                        $label = $f->label !== '' ? $f->label : basename( parse_url($f->file_url, PHP_URL_PATH) );
                        $icon = self::get_file_icon_url( $f->file_url, $f->file_type );
                        echo '<div class="tpw-card">';
                        if ( $icon ) echo '<div class="tpw-card-icon"><img class="tpw-file-icon-large" src="' . esc_url( $icon ) . '" alt="" /></div>';
                        $a = '<a class="tpw-upl-preview" data-index="' . (int)$idx . '" data-type="' . esc_attr( $f->file_type ) . '" data-label="' . esc_attr( $label ) . '" href="' . esc_url( $url ) . '">';
                        $link = $a . esc_html( $label ) . '</a>';
                        echo '<h4 class="tpw-card-title">' . $link . '</h4>';
                        echo '<div class="tpw-card-actions"><a class="tpw-btn tpw-btn-primary" href="' . esc_url( add_query_arg( 'dl', 1, $url ) ) . '">' . esc_html__( 'Download', 'tpw-core' ) . '</a></div>';
                        echo '</div>';
                        $idx++;
                    }
                }
            }
            echo '</div>';
        }
        return ob_get_clean();
    }

    public static function ajax_filter_files() {
        $page_id = isset($_POST['page_id']) ? (int) $_POST['page_id'] : ( isset($_POST['upload_page_id']) ? (int) $_POST['upload_page_id'] : 0 );
        $cat_slug = isset($_POST['cat']) ? sanitize_title( wp_unslash( $_POST['cat'] ) ) : '';
    $sel_year_raw = isset($_POST['tpw_year']) ? sanitize_text_field( wp_unslash( $_POST['tpw_year'] ) ) : '';
        $q = isset($_POST['q']) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        $sort = isset($_POST['sort']) ? sanitize_text_field( wp_unslash( $_POST['sort'] ) ) : '';
        $pg = isset($_POST['pg']) ? max(1, (int) $_POST['pg']) : 0;
        $per_page = isset($_POST['per_page']) ? max(1, min(100, (int) $_POST['per_page'])) : 0;
        $page = self::get_page_by_id( $page_id );
        if ( ! $page ) { wp_send_json_error([ 'message' => 'Not found' ], 404); }
        if ( ! class_exists( 'TPW_Control_UI' ) ) {
            $ui = __DIR__ . '/class-tpw-control-ui.php';
            if ( file_exists( $ui ) ) require_once $ui;
        }
        $vis = json_decode( (string)$page->visibility, true );
        if ( class_exists( 'TPW_Control_UI' ) && ! TPW_Control_UI::user_has_access( self::convert_flat_visibility( $vis ) ) ) {
            wp_send_json_error([ 'message' => 'No permission' ], 403);
        }
        $layout = isset($page->layout) ? self::sanitize_layout( $page->layout ) : 'table';
        // If advanced params provided, use advanced retrieval. Otherwise keep legacy get_files()
        if ( $q !== '' || $sort !== '' || $pg > 0 || $per_page > 0 ) {
            $filters = [ 'q' => $q, 'cat_slug' => $cat_slug, 'year' => $sel_year_raw, 'sort' => $sort, 'pg' => $pg, 'per_page' => $per_page ];
            $adv = self::get_files_advanced( (int) $page->id, $filters );
            $files = $adv['rows'];
        } else {
            $files = self::get_files( (int)$page->id );
        }
        $categories = self::get_categories_map( (int)$page->id );
        $selected_year = '';
        if ( $sel_year_raw !== '' ) {
            if ( strtolower($sel_year_raw) === 'none' ) $selected_year = 'none';
            else if ( preg_match('/^\d{4}$/', $sel_year_raw) ) $selected_year = (string) (int) $sel_year_raw;
        }
        if ( $cat_slug !== '' && ( $q === '' && $sort === '' && $pg === 0 && $per_page === 0 ) ) {
            $cat_id = 0; foreach ( $categories as $cid => $meta ) { if ( $meta['slug'] === $cat_slug ) { $cat_id = (int)$cid; break; } }
            if ( $cat_id ) { $files = array_values( array_filter( (array)$files, function($f) use ($cat_id){ return (int)$f->category_id === $cat_id; } ) ); }
        }
        if ( $selected_year !== '' && ( $q === '' && $sort === '' && $pg === 0 && $per_page === 0 ) ) {
            if ( $selected_year === 'none' ) {
                $files = array_values( array_filter( (array)$files, function($f){ $y = isset($f->year) ? (int)$f->year : 0; return $y === 0; } ) );
            } else {
                $ywant = (int) $selected_year;
                $files = array_values( array_filter( (array)$files, function($f) use ($ywant){ return (int)($f->year ?? 0) === $ywant; } ) );
            }
        }
        $by_cat = [];
        foreach ( (array)$files as $f ) { $cid = (int)($f->category_id ?? 0); $by_cat[$cid][] = $f; }
        // Allow custom HTML renderer to replace the inner list (for async pagination/search)
        $html_override = apply_filters( 'tpw_control_upload_pages_ajax_list_html', null, $layout, $files, $categories, $page );
        $html = is_string( $html_override ) ? $html_override : self::render_file_list_inner( $layout, $by_cat, $categories );
        $meta = null;
        if ( isset($adv) && is_array($adv) ) {
            $meta = [ 'total' => $adv['total'], 'pg' => $adv['pg'], 'per_page' => $adv['per_page'] ];
        }
        wp_send_json_success([ 'html' => $html, 'meta' => $meta ]);
    }

    /**
     * Advanced retrieval supporting search, sort, category/year filtering, and pagination.
     * Filters: [q, cat_slug, year, sort, pg, per_page]
     * Return: [rows => array, total => int, pg => int, per_page => int]
     */
    protected static function get_files_advanced( $page_id, $filters ) {
        global $wpdb; $files = $wpdb->prefix . 'tpw_files'; $cats = $wpdb->prefix . 'tpw_upload_categories'; $links = $wpdb->prefix . 'tpw_upload_pages_files';
        $page_id = (int) $page_id;
        $q = isset($filters['q']) ? (string) $filters['q'] : '';
        $cat_slug = isset($filters['cat_slug']) ? sanitize_title( (string) $filters['cat_slug'] ) : '';
        $yearRaw = isset($filters['year']) ? (string) $filters['year'] : '';
        $sort = isset($filters['sort']) ? strtolower( (string) $filters['sort'] ) : '';
        $pg = isset($filters['pg']) ? max(1, (int) $filters['pg']) : 1;
        $per_page = isset($filters['per_page']) ? max(1, min(100, (int) $filters['per_page'])) : 20;

    $where = [ $wpdb->prepare( "l.page_id=%d", $page_id ), 'l.is_deleted=0' ];
        $joins = "JOIN {$files} f ON f.file_id = l.file_id";
        if ( $cat_slug !== '' ) {
            $joins .= " LEFT JOIN {$cats} c ON c.category_id = l.category_id";
            $where[] = $wpdb->prepare( "c.slug=%s", $cat_slug );
        }
        if ( $yearRaw !== '' ) {
            if ( strtolower($yearRaw) === 'none' ) {
                $where[] = "(l.year IS NULL OR l.year=0)";
            } elseif ( preg_match('/^\d{4}$/', $yearRaw) ) {
                $where[] = $wpdb->prepare( "l.year=%d", (int) $yearRaw );
            }
        }
        if ( $q !== '' ) {
            $like = '%' . $wpdb->esc_like( $q ) . '%';
            $where[] = $wpdb->prepare( "(l.label LIKE %s OR f.notes LIKE %s)", $like, $like );
        }

        $order = 'l.year DESC, l.sort_order ASC, l.id ASC';
        switch ( $sort ) {
            case 'alpha': $order = 'l.label ASC'; break;
            case 'newest': $order = 'f.created_at DESC'; break;
            case 'oldest': $order = 'f.created_at ASC'; break;
            case 'year_asc': $order = 'l.year ASC, l.sort_order ASC, l.id ASC'; break;
            case 'year_desc': $order = 'l.year DESC, l.sort_order ASC, l.id ASC'; break;
            case 'manual': $order = 'l.sort_order ASC, l.id ASC'; break;
        }

        $whereSql = implode(' AND ', $where);
        $offset = ($pg - 1) * $per_page;
        $total = (int) $wpdb->get_var( "SELECT COUNT(1) FROM {$links} l {$joins} WHERE {$whereSql}" );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT l.id, l.page_id, l.category_id, l.label, l.year, l.sort_order,
                                                             f.file_id, f.file_path, f.file_url, f.file_type, f.thumbnail_url, f.uploaded_by, f.notes, f.created_at
                                                      FROM {$links} l {$joins}
                                                      WHERE {$whereSql}
                                                      ORDER BY {$order} LIMIT %d OFFSET %d", $per_page, $offset ) );
        // Allow filter override as with get_files
        $rows = apply_filters( 'tpw_control_upload_pages_files', $rows, (int) $page_id );
        return [ 'rows' => $rows, 'total' => $total, 'pg' => $pg, 'per_page' => $per_page ];
    }

    /**
     * Rewrite <img> src and srcset URLs that point to /tpw-upload-pages/editor/ to signed handler URLs.
     */
    protected static function rewrite_description_images( $html, $page_id ) {
        if ( ! is_string( $html ) || $html === '' ) return $html;
        $upload = wp_upload_dir();
        $uploads_path = (string) parse_url( $upload['baseurl'], PHP_URL_PATH );
        if ( $uploads_path === '' ) $uploads_path = '/wp-content/uploads';
        $editor_prefix = trailingslashit( $uploads_path ) . 'tpw-upload-pages/editor/';

        // Helper to rewrite a single URL
        $rewrite_url = function( $url ) use ( $upload, $editor_prefix, $page_id ) {
            if ( ! is_string( $url ) || $url === '' ) return $url;
            $decoded = html_entity_decode( $url );
            $parts = @parse_url( $decoded );
            if ( ! is_array( $parts ) || empty( $parts['path'] ) ) return $url;
            $path = $parts['path'];
            // If the path begins with the uploads path prefix for editor images, build a served URL
            if ( 0 === strpos( $path, $editor_prefix ) ) {
                $rel = substr( $path, strlen( $uploads_path ) ); // relative from /uploads
                return self::build_editor_served_url( (int)$page_id, $rel, 900 );
            }
            return $url;
        };

        // Rewrite src attributes
        $html = preg_replace_callback( '/\s(src)=(\"|\')([^\"\']+)(\2)/i', function( $m ) use ( $rewrite_url ) {
            $attr = $m[1]; $q = $m[2]; $val = $m[3];
            $new = $rewrite_url( $val );
            return ' ' . $attr . '=' . $q . $new . $q;
        }, $html );

        // Rewrite srcset attributes (multiple URLs with descriptors)
        $html = preg_replace_callback( '/\s(srcset)=(\"|\')([^\"\']+)(\2)/i', function( $m ) use ( $rewrite_url ) {
            $attr = $m[1]; $q = $m[2]; $val = $m[3];
            $items = array_map( 'trim', explode( ',', $val ) );
            foreach ( $items as &$it ) {
                if ( $it === '' ) continue;
                // Each item: URL [<w|x>]
                $parts = preg_split( '/\s+/', $it, 2 );
                $url = $parts[0];
                $desc = isset( $parts[1] ) ? ' ' . trim( $parts[1] ) : '';
                $url_new = $rewrite_url( $url );
                $it = $url_new . $desc;
            }
            $new = implode( ', ', $items );
            return ' ' . $attr . '=' . $q . $new . $q;
        }, $html );

        return $html;
    }

    /** Build a signed URL for serving an editor-uploaded asset by relative path under uploads. */
    public static function build_editor_served_url( $page_id, $relative_path, $ttl = 900 ) {
        $page_id = (int) $page_id; if ( $page_id <= 0 ) return '';
        $relative_path = '/' . ltrim( (string) $relative_path, '/' );
        $ttl = (int) apply_filters( 'tpw_control_upload_pages_signed_url_ttl', (int) $ttl, 'editor', (int) $page_id );
        $exp = time() + max(60, (int)$ttl);
        $variant = 'editor';
        $sig = self::make_signature_editor( $page_id, $relative_path, $exp );
        $base = defined('TPW_CORE_URL') ? TPW_CORE_URL . 'modules/tpw-control/serve.php' : plugins_url( 'modules/tpw-control/serve.php', dirname( __FILE__, 2 ) );
        $args = [ 'pid' => $page_id, 'p' => rawurlencode( $relative_path ), 'v' => $variant, 'exp' => $exp, 'sig' => $sig ];
        return add_query_arg( $args, $base );
    }

    protected static function make_signature_editor( $page_id, $relative_path, $exp ) {
        $data = 'editor|' . ((int)$page_id) . '|' . (string)$relative_path . '|' . ((int)$exp);
        return hash_hmac( 'sha256', $data, self::secret_key() );
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
    // Ensure checksum backfill cron is scheduled and callback is registered
    if ( class_exists( 'TPW_Control_Upload_Pages' ) ) {
        TPW_Control_Upload_Pages::schedule_checksum_backfill();
    }
});

// Front-end AJAX: filter files by category (and optional year) for a given Upload Page
add_action('wp_ajax_tpw_control_filter_files', function(){ TPW_Control_Upload_Pages::ajax_filter_files(); });
add_action('wp_ajax_nopriv_tpw_control_filter_files', function(){ TPW_Control_Upload_Pages::ajax_filter_files(); });
