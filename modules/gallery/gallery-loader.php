<?php
/**
 * TPW Core – Gallery Module Loader (Phase 1 Scaffold)
 *
 * Registers the Gallery module in the internal registry. No front-end or admin UI yet.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Public helpers (reserved for later phases)
require_once __DIR__ . '/gallery-functions.php';
// DB install/upgrade class (no-op until activation hook is wired in a later phase)
require_once __DIR__ . '/gallery-db.php';
// Hybrid upload engine and internal API helpers (Phase 3 implementation)
require_once __DIR__ . '/gallery-upload.php';

// Phase 4 – Admin UI wiring (front-end admin only, no public shortcodes)
add_action( 'plugins_loaded', function(){
    // Register a front-end admin system page at /gallery-admin/ using System Pages API
    if ( class_exists( 'TPW_Core_System_Pages' ) ) {
        TPW_Core_System_Pages::register_page( 'gallery-admin', [
            'title'     => __( 'Gallery Admin', 'tpw-core' ),
            'shortcode' => '[tpw_gallery_admin]',
            'plugin'    => 'tpw-core',
            'required'  => 0,
        ] );
        // Register a front-end help page at /gallery-help/
        TPW_Core_System_Pages::register_page( 'gallery-help', [
            'title'     => __( 'Gallery Help', 'tpw-core' ),
            'shortcode' => '[tpw_gallery_help]',
            'plugin'    => 'tpw-core',
            'required'  => 0,
        ] );
        // Ensure the page actually exists so /gallery-admin/ resolves even if not marked as required
        try {
            TPW_Core_System_Pages::ensure_page( 'gallery-admin' );
            TPW_Core_System_Pages::ensure_page( 'gallery-help' );
        } catch ( \Throwable $e ) {
            // Silently ignore; admins can still create a page with [tpw_gallery_admin]
        }
    }
}, 20 );

// Shortcode renders the admin wrapper plus list/categories
add_shortcode( 'tpw_gallery_admin', function( $atts ){
    if ( ! is_user_logged_in() || ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) {
        return '<div class="tpw-notice tpw-notice--error">' . esc_html__( 'You do not have permission to access the Gallery admin.', 'tpw-core' ) . '</div>';
    }
    ob_start();
    // Enqueue admin assets (module-scoped)
    $base_url = trailingslashit( TPW_CORE_URL ) . 'modules/gallery/';
    // Global TPW buttons on front-end admin page
    wp_enqueue_style( 'tpw-buttons', trailingslashit( TPW_CORE_URL ) . 'assets/css/tpw-buttons.css', [], '0.6.0' );
    // Shared UI kit (table-container, table-row, table-cell, .button, etc.)
    $ui_file = trailingslashit( TPW_CORE_PATH ) . 'assets/css/tpw-ui.css';
    $ui_url  = trailingslashit( TPW_CORE_URL ) . 'assets/css/tpw-ui.css';
    $ui_ver  = file_exists( $ui_file ) ? (string) filemtime( $ui_file ) : '0.1.0';
    if ( ! wp_style_is( 'tpw-ui', 'enqueued' ) ) wp_enqueue_style( 'tpw-ui', $ui_url, [], $ui_ver );
    // Ensure Media Library is available on front-end system page
    if ( function_exists( 'wp_enqueue_media' ) ) {
        wp_enqueue_media();
    }
    wp_enqueue_style( 'tpw-gallery-admin', $base_url . 'assets/gallery.css', [], '0.6.11' );
    // Lightbox assets used for previewing thumbnails inside the modal
    wp_enqueue_style( 'tpw-gallery-lightbox', $base_url . 'assets/lightbox.css', [], '0.6.0' );
    // Enable drag-and-drop sorting support
    wp_enqueue_script( 'jquery-ui-sortable' );
    wp_enqueue_script( 'tpw-gallery-admin', $base_url . 'assets/gallery.js', [ 'jquery', 'jquery-ui-sortable', 'media-editor', 'wp-util' ], '0.6.10', true );
    wp_enqueue_script( 'tpw-gallery-lightbox', $base_url . 'assets/lightbox.js', [ 'jquery' ], '0.6.0', true );
    $editor_id = isset($_GET['gallery_id']) ? (int) $_GET['gallery_id'] : 0;
    wp_localize_script( 'tpw-gallery-admin', 'tpwGallery', [
        'nonce' => wp_create_nonce( 'tpw_gallery' ),
        'i18nConfirmDelete' => __( 'Delete this gallery?', 'tpw-core' ),
        'i18nAddTitle' => __( 'Add Gallery', 'tpw-core' ),
        'i18nEditTitle' => __( 'Edit Gallery', 'tpw-core' ),
        'adminUrl' => home_url( '/gallery-admin/' ),
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'isAdmin' => true,
        'isEditorPage' => $editor_id > 0,
        'editorGalleryId' => $editor_id,
        'i18nRemoveConfirm' => __( 'Remove this image from the gallery?', 'tpw-core' ),
        'i18nRemove' => __( 'Remove', 'tpw-core' ),
        'i18nNoImages' => __( 'No images in this gallery yet.', 'tpw-core' ),
        'i18nUploadToGallery' => __( 'Upload to this Gallery', 'tpw-core' ),
        'i18nAddFromLibrary' => __( 'Add Images (Media Library)', 'tpw-core' ),
        'i18nDeletePerm' => __( 'Delete this image permanently from the Media Library? This cannot be undone.', 'tpw-core' ),
        'i18nDeletePermShort' => __( 'Delete', 'tpw-core' ),
        'i18nEditCaption' => __( 'Edit caption', 'tpw-core' ),
        'i18nSave' => __( 'Save', 'tpw-core' ),
        'i18nCancel' => __( 'Cancel', 'tpw-core' ),
        'i18nSaved' => __( 'Saved', 'tpw-core' ),
        'i18nAddCategory' => __( 'Add Category', 'tpw-core' ),
        'i18nCategoryNamePH' => __( 'Category name', 'tpw-core' ),
        'i18nDeleteCategoryConfirm' => __( 'Delete this category?', 'tpw-core' ),
        'i18nUploading' => __( 'Uploading...', 'tpw-core' ),
        'i18nUploadFailed' => __( 'Upload failed', 'tpw-core' ),
        'i18nAutoSavedBeforeUpload' => __( 'Gallery saved automatically before upload.', 'tpw-core' ),
        'i18nFocusHelp' => __( 'Click or drag to set the focal point.', 'tpw-core' ),
    ] );

    // Default list view with embedded categories panel
    if ( $editor_id > 0 && function_exists('tpw_gallery_get') ) {
        $gallery = tpw_gallery_get( $editor_id );
        include __DIR__ . '/templates/editor.php';
    } else {
        include __DIR__ . '/templates/list.php';
    }
    return ob_get_clean();
} );

// Shortcode: gallery help page
add_shortcode( 'tpw_gallery_help', function( $atts ){
    if ( ! is_user_logged_in() || ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) {
        return '<div class="tpw-notice tpw-notice--error">' . esc_html__( 'You do not have permission to access the Gallery help.', 'tpw-core' ) . '</div>';
    }
    // Enqueue base admin UI styles for consistent look
    $base_url = trailingslashit( TPW_CORE_URL ) . 'modules/gallery/';
    wp_enqueue_style( 'tpw-buttons', trailingslashit( TPW_CORE_URL ) . 'assets/css/tpw-buttons.css', [], '0.6.0' );
    $ui_file = trailingslashit( TPW_CORE_PATH ) . 'assets/css/tpw-ui.css';
    $ui_url  = trailingslashit( TPW_CORE_URL ) . 'assets/css/tpw-ui.css';
    $ui_ver  = file_exists( $ui_file ) ? (string) filemtime( $ui_file ) : '0.1.0';
    if ( ! wp_style_is( 'tpw-ui', 'enqueued' ) ) wp_enqueue_style( 'tpw-ui', $ui_url, [], $ui_ver );
    // Scope gallery admin styles for layout consistency
    wp_enqueue_style( 'tpw-gallery-admin', $base_url . 'assets/gallery.css', [], '0.6.11' );

    ob_start();
    include __DIR__ . '/templates/help.php';
    return ob_get_clean();
} );

// AJAX: delete gallery
add_action( 'wp_ajax_tpw_gallery_delete', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $res = tpw_gallery_delete( $id );
    if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
    wp_send_json_success( true );
} );

// AJAX: create gallery
add_action( 'wp_ajax_tpw_gallery_create', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $title = isset($_POST['title']) ? sanitize_text_field( wp_unslash($_POST['title']) ) : '';
    $description = isset($_POST['description']) ? wp_kses_post( wp_unslash($_POST['description']) ) : '';
    $category_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
    if ( ! function_exists('tpw_gallery_create') ) wp_send_json_error( 'API missing' );
    $res = tpw_gallery_create([
        'title' => $title,
        'description' => $description,
        'category_id' => max(0,$category_id),
    ]);
    if ( is_wp_error($res) ) wp_send_json_error( $res->get_error_message() );
    wp_send_json_success( $res );
} );

// AJAX: update gallery
add_action( 'wp_ajax_tpw_gallery_update', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $gallery_id = isset($_POST['gallery_id']) ? (int) $_POST['gallery_id'] : 0;
    $title = isset($_POST['title']) ? sanitize_text_field( wp_unslash($_POST['title']) ) : null;
    $description = isset($_POST['description']) ? wp_kses_post( wp_unslash($_POST['description']) ) : null;
    $category_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : null;
    if ( ! function_exists('tpw_gallery_update') ) wp_send_json_error( 'API missing' );
    $payload = [];
    if ( null !== $title ) $payload['title'] = $title;
    if ( null !== $description ) $payload['description'] = $description;
    if ( null !== $category_id ) $payload['category_id'] = max(0, $category_id);
    $res = tpw_gallery_update( $gallery_id, $payload );
    if ( is_wp_error($res) ) wp_send_json_error( $res->get_error_message() );
    wp_send_json_success( true );
} );

// AJAX: get gallery (for edit prefill)
add_action( 'wp_ajax_tpw_gallery_get', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ( ! $id ) wp_send_json_error( 'Invalid ID' );
    if ( ! function_exists('tpw_gallery_get') ) wp_send_json_error( 'API missing' );
    $g = tpw_gallery_get( $id );
    if ( ! $g ) wp_send_json_error( 'Not found' );
    wp_send_json_success( $g );
} );

// AJAX: upload a file directly to this gallery (stores in uploads/tpw-galleries/{slug})
add_action( 'wp_ajax_tpw_gallery_upload_image', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $gid = isset($_POST['gallery_id']) ? (int) $_POST['gallery_id'] : 0;
    if ( $gid <= 0 ) wp_send_json_error( 'Invalid gallery ID' );
    if ( empty($_FILES['file']) || ! is_array($_FILES['file']) ) wp_send_json_error( 'No file' );
    if ( ! function_exists('tpw_gallery_upload_image') ) wp_send_json_error( 'API missing' );
    $res = tpw_gallery_upload_image( $gid, $_FILES['file'] );
    if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
    wp_send_json_success( $res );
} );

// AJAX: add selected media library attachments to a gallery
add_action( 'wp_ajax_tpw_gallery_add_attachments', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $gid = isset($_POST['gallery_id']) ? (int) $_POST['gallery_id'] : 0;
    $ids = isset($_POST['ids']) ? explode( ',', (string) $_POST['ids'] ) : [];
    foreach ( $ids as $aid ) {
        $aid = (int) $aid; if ( $aid > 0 ) tpw_gallery_add_attachment( $gid, $aid );
    }
    wp_send_json_success( true );
} );

// AJAX: add category
add_action( 'wp_ajax_tpw_gallery_add_category', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $name = isset($_POST['name']) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
    $res = tpw_gallery_add_category( $name );
    if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
    wp_send_json_success( $res );
} );

// AJAX: delete category
add_action( 'wp_ajax_tpw_gallery_delete_category', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $res = tpw_gallery_delete_category( $id );
    if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
    wp_send_json_success( true );
} );

// AJAX: delete a single image (remove from gallery)
add_action( 'wp_ajax_tpw_gallery_delete_image', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
    if ( $image_id <= 0 ) wp_send_json_error( 'Invalid image ID' );
    if ( ! function_exists('tpw_gallery_delete_image') ) wp_send_json_error( 'API missing' );
    $res = tpw_gallery_delete_image( $image_id );
    if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
    wp_send_json_success( true );
} );

// AJAX: permanently delete image (attachment + row)
add_action( 'wp_ajax_tpw_gallery_delete_image_permanently', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
    if ( $image_id <= 0 ) wp_send_json_error( 'Invalid image ID' );
    if ( ! function_exists('tpw_gallery_delete_image_permanently') ) wp_send_json_error( 'API missing' );
    $res = tpw_gallery_delete_image_permanently( $image_id );
    if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
    wp_send_json_success( true );
} );

// AJAX: update caption for an image (updates DB row and Media Library caption)
add_action( 'wp_ajax_tpw_gallery_update_caption', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
    $caption  = isset($_POST['caption']) ? wp_unslash( (string) $_POST['caption'] ) : '';
    if ( $image_id <= 0 ) wp_send_json_error( 'Invalid image ID' );
    if ( ! function_exists('tpw_gallery_update_image_caption') ) wp_send_json_error( 'API missing' );
    $res = tpw_gallery_update_image_caption( $image_id, $caption );
    if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
    wp_send_json_success( [ 'caption' => (string) $res ] );
} );

// AJAX: update image focal point (focus_x, focus_y ratios 0..1)
add_action( 'wp_ajax_tpw_gallery_update_image_focus', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $image_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;
    $fx = isset($_POST['focus_x']) ? (float) $_POST['focus_x'] : null;
    $fy = isset($_POST['focus_y']) ? (float) $_POST['focus_y'] : null;
    if ( $image_id <= 0 ) wp_send_json_error( 'Invalid image ID' );
    if ( ! function_exists('tpw_gallery_update_image_focus') ) wp_send_json_error( 'API missing' );
    $res = tpw_gallery_update_image_focus( $image_id, $fx, $fy );
    if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
    wp_send_json_success( true );
} );

// AJAX: reorder images within a gallery (saves to sort_order)
add_action( 'wp_ajax_tpw_gallery_reorder_images', function(){
    if ( ! current_user_can( function_exists('tpw_gallery_manage_capability') ? tpw_gallery_manage_capability() : 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ) );
    check_ajax_referer( 'tpw_gallery' );
    $gallery_id = isset($_POST['gallery_id']) ? (int) $_POST['gallery_id'] : 0;
    $order_raw  = isset($_POST['order']) ? (string) $_POST['order'] : '';
    if ( $gallery_id <= 0 ) wp_send_json_error( 'Invalid gallery ID' );
    $ids = array_filter( array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $order_raw ) ) ) ) );
    if ( empty( $ids ) ) wp_send_json_error( 'Invalid order' );
    if ( ! function_exists( 'tpw_gallery_reorder_images' ) ) wp_send_json_error( 'API missing' );
    $res = tpw_gallery_reorder_images( $gallery_id, $ids );
    if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
    wp_send_json_success( true );
} );

add_action( 'plugins_loaded', function() {
    if ( function_exists( 'tpw_register_module' ) ) {
        tpw_register_module( 'gallery', [
            'title'        => 'Gallery',
            'version'      => '0.6.0',
                'status'       => 'stable',
            'plugin'       => 'tpw-core',
                'has_ui'       => true,
            'capabilities' => [ /* future: manage_galleries, edit_galleries */ ],
            'description'  => 'Hybrid (filesystem + Media Library) image galleries for clubs and societies. Admin UI, public shortcodes, and integration API.',
        ] );
    }
}, 12 );

// Phase 5 – Public display layer
require_once __DIR__ . '/gallery-display.php';

// Optional: WP-CLI integration
if ( defined( 'WP_CLI' ) && WP_CLI && file_exists( __DIR__ . '/cli.php' ) ) {
    require_once __DIR__ . '/cli.php';
}
