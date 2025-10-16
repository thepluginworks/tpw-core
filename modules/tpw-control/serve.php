<?php
/**
 * Secure file serving endpoint for TPW Control Upload Pages.
 * Usage: /wp-content/plugins/tpw-core/modules/tpw-control/serve.php?f=<id>&v=<file|thumb>&exp=<ts>&sig=<hmac>&dl=0|1
 */

// Bootstrap WordPress
if (!defined('ABSPATH')) {
    // Try to locate wp-load.php relative to this file
    $root = dirname(__FILE__, 5);
    $candidate = $root . '/wp-load.php';
    if (file_exists($candidate)) {
        require_once $candidate;
    } else {
        // Fallback: walk up until we find wp-load.php
        $dir = __DIR__;
        $found = false;
        for ($i = 0; $i < 8; $i++) {
            $dir = dirname($dir);
            if (file_exists($dir . '/wp-load.php')) { require_once $dir . '/wp-load.php'; $found = true; break; }
        }
        if (!$found) {
            header('HTTP/1.1 500 Internal Server Error');
            echo 'WP bootstrap failed';
            exit;
        }
    }
}

// Minimal safety
if (!function_exists('wp_get_current_user')) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'WP not loaded';
    exit;
}

// Load classes if not already
if (!class_exists('TPW_Control_UI') && defined('TPW_CORE_PATH')) {
    $ui = TPW_CORE_PATH . 'modules/tpw-control/class-tpw-control-ui.php';
    if (file_exists($ui)) require_once $ui;
}
if (!class_exists('TPW_Control_Upload_Pages') && defined('TPW_CORE_PATH')) {
    $up = TPW_CORE_PATH . 'modules/tpw-control/class-tpw-control-upload-pages.php';
    if (file_exists($up)) require_once $up;
}

// Helper: signature utils (mirror of methods in TPW_Control_Upload_Pages)
function tpw_upl_secret_key() {
    $salt = '';
    foreach (['AUTH_SALT','LOGGED_IN_SALT','SECURE_AUTH_SALT','NONCE_SALT','AUTH_KEY','SECURE_AUTH_KEY'] as $c) {
        if (defined($c)) $salt .= constant($c);
    }
    if ($salt === '') $salt = wp_salt('auth');
    return hash('sha256', $salt);
}
function tpw_upl_make_sig($file_id, $variant, $exp) {
    $data = ((int)$file_id) . '|' . (string)$variant . '|' . ((int)$exp);
    return hash_hmac('sha256', $data, tpw_upl_secret_key());
}

// Params
$file_id = isset($_GET['f']) ? (int) $_GET['f'] : 0;
$page_id = isset($_GET['pid']) ? (int) $_GET['pid'] : 0; // for editor assets
$variant = isset($_GET['v']) ? sanitize_key($_GET['v']) : 'file';
$exp     = isset($_GET['exp']) ? (int) $_GET['exp'] : 0;
$sig     = isset($_GET['sig']) ? sanitize_text_field(wp_unslash($_GET['sig'])) : '';
$download = isset($_GET['dl']) && (string)$_GET['dl'] === '1';
$rel_p   = isset($_GET['p']) ? wp_unslash($_GET['p']) : '';

// Validate basic params
if ((($variant === 'file' || $variant === 'thumb') && $file_id <= 0) || ($variant === 'editor' && ($page_id <= 0 || $rel_p === '')) || $exp <= 0 || $sig === '') {
    status_header(400);
    echo 'Bad request';
    exit;
}
if ($exp < time() - 30) { // small clock skew tolerance
    status_header(403);
    echo 'Link expired';
    exit;
}
if ($variant === 'editor') {
    // Verify editor signature
    $data = 'editor|' . ((int)$page_id) . '|' . (string)$rel_p . '|' . ((int)$exp);
    $expected = hash_hmac('sha256', $data, tpw_upl_secret_key());
    if (!hash_equals($expected, $sig)) {
        status_header(403);
        echo 'Invalid token';
        exit;
    }
} else {
    if (!hash_equals(tpw_upl_make_sig($file_id, $variant, $exp), $sig)) {
        status_header(403);
        echo 'Invalid token';
        exit;
    }
}

global $wpdb;
$files_table = $wpdb->prefix . 'tpw_files';
$links_table = $wpdb->prefix . 'tpw_upload_pages_files';
$pages_table = $wpdb->prefix . 'tpw_upload_pages';
// Load visibility depending on variant
if ($variant === 'editor') {
    $row = $wpdb->get_row($wpdb->prepare("SELECT id as page_id, visibility FROM {$pages_table} WHERE id=%d", $page_id));
    if (!$row) {
        status_header(404);
        echo 'Not found';
        exit;
    }
} else {
    // Here f is from registry and l is the link id passed in 'f'
    $row = $wpdb->get_row($wpdb->prepare("SELECT l.id AS link_id, l.page_id, l.label, l.year, l.category_id, l.sort_order, l.is_deleted,
                             f.file_id, f.file_path, f.file_url, f.file_type, f.thumbnail_url, f.uploaded_by, f.notes, f.created_at, f.checksum,
                             p.visibility
                         FROM {$links_table} l
                         JOIN {$files_table} f ON f.file_id = l.file_id
                         JOIN {$pages_table} p ON p.id = l.page_id
                         WHERE l.id=%d", $file_id));
    if (!$row) {
        status_header(404);
        echo 'Not found';
        exit;
    }
}

// Reject trashed links explicitly
if (!empty($row->is_deleted)) {
    status_header(404);
    echo 'Not found';
    exit;
}

// Convert stored flat visibility into TPW_Control_UI shape
$vis = is_string($row->visibility) ? json_decode((string)$row->visibility, true) : (array) $row->visibility;
$visibility = [];
if (is_array($vis)) {
    $flags = [];
    foreach (['is_committee','is_match_manager','is_noticeboard_admin'] as $k) { if (!empty($vis[$k])) $flags[] = $k; }
    if (!empty($flags)) $visibility['flags_any'] = $flags;
    if (!empty($vis['status']) && is_array($vis['status'])) $visibility['allowed_statuses'] = $vis['status'];
}
if (empty($visibility)) {
    // Default to admin-only if not specified
    $visibility['flags_any'] = ['is_admin'];
}

// Permission check
if (!class_exists('TPW_Control_UI') || !TPW_Control_UI::user_has_access($visibility)) {
    status_header(403);
    echo 'Forbidden';
    exit;
}

// Map to actual file on disk
if ($variant === 'editor') {
    $upload = wp_upload_dir();
    $rel = '/' . ltrim((string)$rel_p, '/');
    // Resolve to disk path
    $path = $upload['basedir'] . $rel;
    // Safety: ensure it stays within uploads and points to the editor subdir
    $uploads_real = realpath($upload['basedir']);
    $path_real = realpath($path);
    if (!$uploads_real || !$path_real || 0 !== strpos($path_real, $uploads_real)) {
        status_header(400);
        echo 'Bad path';
        exit;
    }
    $editor_dir = $upload['basedir'] . '/tpw-upload-pages/editor/';
    if (0 !== strpos($path_real, realpath($editor_dir))) {
        status_header(403);
        echo 'Forbidden';
        exit;
    }
} else {
    $path = $row->file_path;
    if ($variant === 'thumb' && !empty($row->thumbnail_url)) {
        $upload = wp_upload_dir();
        $thumb_rel = str_replace($upload['baseurl'], '', $row->thumbnail_url);
        $thumb_abs = $upload['basedir'] . $thumb_rel;
        if (file_exists($thumb_abs)) $path = $thumb_abs;
    }
}

if (empty($path) || !file_exists($path)) {
    status_header(404);
    echo 'File missing';
    exit;
}

// On-demand checksum backfill (best-effort)
if (empty($row->checksum) && function_exists('hash_file')) {
    $cs = @hash_file('sha256', $path);
    if ($cs) {
        $wpdb->update($files_table, [ 'checksum' => $cs ], [ 'file_id' => (int) $row->file_id ], [ '%s' ], [ '%d' ] );
    }
}

// Send headers
if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
nocache_headers();
$mime = 'application/octet-stream';
if ($variant === 'editor') {
    $ft = wp_check_filetype($path);
    if (!empty($ft['type'])) $mime = $ft['type'];
} else {
    $mime = !empty($row->file_type) ? $row->file_type : wp_check_filetype($path)['type'];
}
if (!$mime) $mime = 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
$basename = basename($path);
if ($download) {
    header('Content-Disposition: attachment; filename="' . rawurldecode($basename) . '"');
} else {
    // Inline by default for images/pdf/video
    header('Content-Disposition: inline; filename="' . rawurldecode($basename) . '"');
}

// Read the file
$fp = @fopen($path, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 8192);
        @flush();
    }
    fclose($fp);
    exit;
}
// Fallback
readfile($path);
exit;
