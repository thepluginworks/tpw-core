<?php
/**
 * TPW Core – Gallery Public Functions (Phase 3)
 *
 * Purpose:
 * - Provide programmatic CRUD APIs for galleries, images, and categories.
 * - No UI, no shortcodes; callable by other TPW plugins only.
 *
 * Next phases:
 * - Phase 4: Admin UI forms
 * - Phase 5: Shortcodes for display
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'tpw_gallery_manage_capability' ) ) {
    /**
     * Capability required to manage TPW Galleries (admin UI + privileged AJAX).
     *
     * Filter: tpw_gallery_manage_capability
     * Default: manage_options
     */
    function tpw_gallery_manage_capability(): string {
        $cap = (string) apply_filters( 'tpw_gallery_manage_capability', 'manage_options' );
        return $cap !== '' ? $cap : 'manage_options';
    }
}

if ( ! function_exists( 'tpw_gallery_module_enabled' ) ) {
    function tpw_gallery_module_enabled(): bool {
        return (bool) apply_filters( 'tpw_gallery_enabled', true );
    }
}

if ( ! function_exists( 'tpw_gallery_get_info' ) ) {
    function tpw_gallery_get_info(): array {
        if ( function_exists( 'tpw_get_registered_modules' ) ) {
            $all = tpw_get_registered_modules();
            return isset( $all['gallery'] ) ? (array) $all['gallery'] : [];
        }
        return [];
    }
}

/**
 * Create a new gallery.
 *
 * $args keys: title (required), slug (optional), description (optional), category_id (optional)
 * Returns: array{gallery_id:int, title:string, slug:string, description:string, category_id:int, created_by:int, created_at:string}
 */
function tpw_gallery_create( array $args ) {
    global $wpdb;
    $title = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
    if ( '' === $title ) return new WP_Error( 'tpw_gallery_invalid', 'Title is required.' );

    $slug  = isset( $args['slug'] ) && $args['slug'] !== '' ? sanitize_title( $args['slug'] ) : sanitize_title( $title );
    $desc  = isset( $args['description'] ) ? (string) $args['description'] : '';
    $catid = isset( $args['category_id'] ) ? max( 0, (int) $args['category_id'] ) : 0;
    $user  = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
    $now   = current_time( 'mysql' );

    $tbl = $wpdb->prefix . 'tpw_galleries';

    // Ensure slug is unique; add suffix if needed
    $base = $slug;
    $i = 1;
    while ( $wpdb->get_var( $wpdb->prepare( "SELECT gallery_id FROM {$tbl} WHERE slug = %s LIMIT 1", $slug ) ) ) {
        $slug = $base . '-' . $i;
        $i++;
        if ( $i > 50 ) break; // avoid infinite loop
    }

    $ok = $wpdb->insert( $tbl, [
        'title'       => $title,
        'slug'        => $slug,
        'description' => $desc,
        'category_id' => $catid,
        'created_by'  => $user,
        'created_at'  => $now,
    ], [ '%s','%s','%s','%d','%d','%s' ] );

    if ( false === $ok ) return new WP_Error( 'tpw_gallery_insert_failed', 'Failed to create gallery.' );

    $id = (int) $wpdb->insert_id;

    return [
        'gallery_id'  => $id,
        'title'       => $title,
        'slug'        => $slug,
        'description' => $desc,
        'category_id' => $catid,
        'created_by'  => $user,
        'created_at'  => $now,
    ];
}

/**
 * Fetch a gallery with images and category meta.
 */
function tpw_gallery_get( int $gallery_id ) {
    global $wpdb;
    $tbl_galleries = $wpdb->prefix . 'tpw_galleries';
    $tbl_images    = $wpdb->prefix . 'tpw_gallery_images';
    $tbl_cats      = $wpdb->prefix . 'tpw_gallery_categories';

    $g = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_galleries} WHERE gallery_id = %d", $gallery_id ), ARRAY_A );
    if ( ! $g ) return null;

    $images = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tbl_images} WHERE gallery_id = %d ORDER BY sort_order ASC, image_id ASC", $gallery_id ), ARRAY_A );

    $cat = null;
    if ( (int) $g['category_id'] > 0 ) {
        $cat = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_cats} WHERE category_id = %d", (int) $g['category_id'] ), ARRAY_A );
    }

    // Enrich images with URLs and caption when they have attachments
    foreach ( $images as &$img ) {
        $aid = (int) ( $img['attachment_id'] ?? 0 );
        if ( $aid > 0 ) {
            $src_full  = wp_get_attachment_image_src( $aid, 'full' );
            $src_thumb = wp_get_attachment_image_src( $aid, 'thumbnail' );
            $img['url']       = is_array( $src_full )  ? (string) $src_full[0]  : '';
            $img['thumb_url'] = is_array( $src_thumb ) ? (string) $src_thumb[0] : $img['url'];
            // Prefer gallery DB caption when available, else fall back to attachment caption, then title
            $db_caption = isset( $img['caption'] ) ? (string) $img['caption'] : '';
            if ( '' !== trim( $db_caption ) ) {
                $img['caption'] = $db_caption;
            } else {
                $att_caption = (string) get_post_field( 'post_excerpt', $aid );
                $img['caption'] = '' !== trim( $att_caption ) ? $att_caption : (string) get_the_title( $aid );
            }
            // Normalize focus values as floats (0..100 percent for CSS convenience can be computed at render time)
            $img['focus_x'] = isset($img['focus_x']) && $img['focus_x'] !== null ? (float) $img['focus_x'] : null;
            $img['focus_y'] = isset($img['focus_y']) && $img['focus_y'] !== null ? (float) $img['focus_y'] : null;
        } else {
            $img['url']       = '';
            $img['thumb_url'] = '';
            $img['caption']   = '';
            $img['focus_x']   = null;
            $img['focus_y']   = null;
        }
    }

    return [
        'gallery'    => $g,
        'images'     => $images,
        'category'   => $cat,
    ];
}

/**
 * Update image focal point (focus_x, focus_y) stored as ratios 0..1
 * @since 0.8.0
 */
function tpw_gallery_update_image_focus( int $image_id, $focus_x, $focus_y ) {
    global $wpdb;
    $image_id = (int) $image_id; if ( $image_id <= 0 ) return new WP_Error( 'tpw_gallery_invalid', 'Invalid image.' );
    $fx = is_null($focus_x) ? null : max(0.0, min(1.0, (float) $focus_x));
    $fy = is_null($focus_y) ? null : max(0.0, min(1.0, (float) $focus_y));
    $tbl = $wpdb->prefix . 'tpw_gallery_images';
    $data = [ 'focus_x' => $fx, 'focus_y' => $fy ];
    $fmt  = [ '%f', '%f' ];
    // Use NULL when not provided
    if ($fx === null) { unset($data['focus_x']); }
    if ($fy === null) { unset($data['focus_y']); }
    $ok = $wpdb->update( $tbl, $data, [ 'image_id' => $image_id ], $fmt, [ '%d' ] );
    if ( false === $ok ) return new WP_Error( 'tpw_gallery_update_failed', 'Failed to update focus point.' );
    return true;
}

/**
 * Add image to a gallery using the hybrid upload engine.
 */
function tpw_gallery_add_image( int $gallery_id, array $file ) {
    if ( ! function_exists( 'tpw_gallery_upload_image' ) ) {
        return new WP_Error( 'tpw_gallery_upload_missing', 'Upload engine not available.' );
    }
    return tpw_gallery_upload_image( $gallery_id, $file );
}

/**
 * List galleries by category (0 = uncategorised).
 */
function tpw_gallery_list_by_category( int $category_id ): array {
    global $wpdb;
    $tbl = $wpdb->prefix . 'tpw_galleries';
    $cid = max( 0, $category_id );
    if ( $cid === 0 ) {
        return (array) $wpdb->get_results( "SELECT * FROM {$tbl} WHERE category_id = 0 ORDER BY created_at DESC", ARRAY_A );
    }
    return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE category_id = %d ORDER BY created_at DESC", $cid ), ARRAY_A );
}

/**
 * Delete a gallery: removes DB rows, attachments (with _tpw_gallery_id meta), and the gallery folder.
 */
function tpw_gallery_delete( int $gallery_id ) {
    global $wpdb;
    $gallery_id = (int) $gallery_id;
    if ( $gallery_id <= 0 ) return new WP_Error( 'tpw_gallery_invalid', 'Invalid gallery.' );

    $tbl_galleries = $wpdb->prefix . 'tpw_galleries';
    $tbl_images    = $wpdb->prefix . 'tpw_gallery_images';

    // Fetch images
    $imgs = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tbl_images} WHERE gallery_id = %d", $gallery_id ), ARRAY_A );

    // Best-effort delete attachments
    foreach ( $imgs as $img ) {
        $aid = (int) ( $img['attachment_id'] ?? 0 );
        if ( $aid > 0 && get_post( $aid ) ) {
            // Force delete (skip trash) to clean files
            wp_delete_attachment( $aid, true );
        }
    }

    // Remove DB rows
    $wpdb->delete( $tbl_images, [ 'gallery_id' => $gallery_id ], [ '%d' ] );

    // Compute and remove gallery folder (best-effort)
    $slug = (string) $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$tbl_galleries} WHERE gallery_id = %d", $gallery_id ) );
    if ( $slug !== '' ) {
        $uploads = wp_upload_dir();
        if ( empty( $uploads['error'] ) ) {
            $dir = trailingslashit( $uploads['basedir'] ) . 'tpw-galleries/' . sanitize_title( $slug ) . '/';
            if ( is_dir( $dir ) ) {
                // Recursive delete with WP_Filesystem when available; fallback to rmdir unlink
                global $wp_filesystem;
                if ( ! $wp_filesystem ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                if ( $wp_filesystem && $wp_filesystem->exists( $dir ) ) {
                    $wp_filesystem->rmdir( $dir, true );
                } else {
                    // Fallback: naive recursive
                    $it = @scandir( $dir );
                    if ( is_array( $it ) ) {
                        foreach ( $it as $f ) {
                            if ( $f === '.' || $f === '..' ) continue;
                            $path = $dir . $f;
                            if ( is_dir( $path ) ) {
                                // one level deep
                                @rmdir( $path );
                            } else {
                                @unlink( $path );
                            }
                        }
                    }
                    @rmdir( $dir );
                }
            }
        }
    }

    // Finally remove the gallery row
    $wpdb->delete( $tbl_galleries, [ 'gallery_id' => $gallery_id ], [ '%d' ] );

    return true;
}

/**
 * Get category list.
 */
function tpw_gallery_get_categories(): array {
    global $wpdb;
    $tbl = $wpdb->prefix . 'tpw_gallery_categories';
    return (array) $wpdb->get_results( "SELECT * FROM {$tbl} ORDER BY sort_order ASC, name ASC", ARRAY_A );
}

/**
 * Add a new category.
 */
function tpw_gallery_add_category( string $name, string $slug = '', string $description = '', int $sort_order = 0 ) {
    global $wpdb;
    $name = trim( $name );
    if ( $name === '' ) return new WP_Error( 'tpw_category_invalid', 'Name is required.' );
    $slug = $slug !== '' ? sanitize_title( $slug ) : sanitize_title( $name );
    $tbl  = $wpdb->prefix . 'tpw_gallery_categories';

    // Ensure unique slug
    $base = $slug; $i = 1;
    while ( $wpdb->get_var( $wpdb->prepare( "SELECT category_id FROM {$tbl} WHERE slug = %s LIMIT 1", $slug ) ) ) {
        $slug = $base . '-' . $i;
        $i++; if ( $i > 50 ) break;
    }

    $ok = $wpdb->insert( $tbl, [
        'name'        => $name,
        'slug'        => $slug,
        'description' => $description,
        'sort_order'  => max( 0, $sort_order ),
    ], [ '%s','%s','%s','%d' ] );

    if ( false === $ok ) return new WP_Error( 'tpw_category_insert_failed', 'Failed to create category.' );

    return [
        'category_id' => (int) $wpdb->insert_id,
        'name'        => $name,
        'slug'        => $slug,
        'description' => $description,
        'sort_order'  => max( 0, $sort_order ),
    ];
}

/**
 * Delete a category. Does not delete galleries; callers should reassign or keep category_id=0.
 */
function tpw_gallery_delete_category( int $category_id ) {
    global $wpdb;
    $category_id = (int) $category_id;
    if ( $category_id <= 0 ) return new WP_Error( 'tpw_category_invalid', 'Invalid category.' );

    $tbl_cats = $wpdb->prefix . 'tpw_gallery_categories';
    $wpdb->delete( $tbl_cats, [ 'category_id' => $category_id ], [ '%d' ] );

    return true;
}

// Phase 3 TODOs (design notes only; no runtime hooks)
// - Admin UI forms (Phase 4): add, edit, delete galleries; upload images; manage categories.
// - Shortcodes (Phase 5): public rendering of galleries and lightbox.

/**
 * Update a gallery's fields.
 * @since 0.4.0 Gallery UI
 */
function tpw_gallery_update( int $gallery_id, array $args ) {
    global $wpdb;
    $gallery_id = (int) $gallery_id;
    if ( $gallery_id <= 0 ) return new WP_Error( 'tpw_gallery_invalid', 'Invalid gallery.' );

    $data = [];
    $fmt  = [];
    if ( isset( $args['title'] ) ) { $data['title'] = (string) $args['title']; $fmt[] = '%s'; }
    if ( isset( $args['description'] ) ) { $data['description'] = (string) $args['description']; $fmt[] = '%s'; }
    if ( array_key_exists( 'category_id', $args ) ) { $data['category_id'] = max(0, (int) $args['category_id']); $fmt[] = '%d'; }

    if ( empty( $data ) ) return true; // nothing to do

    $tbl = $wpdb->prefix . 'tpw_galleries';
    $ok = $wpdb->update( $tbl, $data, [ 'gallery_id' => $gallery_id ], $fmt, [ '%d' ] );
    if ( false === $ok ) return new WP_Error( 'tpw_gallery_update_failed', 'Failed to update gallery.' );
    return true;
}

/**
 * Register an external Gallery source/renderer.
 *
 * Usage example (from another plugin):
 *
 * tpw_register_gallery_source([
 *   'slug'     => 'flexigallery-pro',
 *   'title'    => 'FlexiGallery Pro Layouts',
 *   'callback' => 'my_render_callback', // function ( $context ) { ... }
 *   'priority' => 10,
 *   'context'  => 'frontend',
 * ]);
 *
 * Other plugins may also hook into the dynamic filter:
 * apply_filters( 'tpw_gallery_source_{slug}', $output, $context );
 *
 * @param array $args { slug, title, callback, priority, context }
 * @return string The registered slug.
 * @since 0.6.0
 */
function tpw_register_gallery_source( array $args ) {
    if ( ! isset( $GLOBALS['tpw_gallery_sources'] ) || ! is_array( $GLOBALS['tpw_gallery_sources'] ) ) {
        $GLOBALS['tpw_gallery_sources'] = [];
    }
    $defaults = [
        'slug'     => '',
        'title'    => '',
        'callback' => null, // callable|null
        'priority' => 10,
        'context'  => 'frontend', // or 'admin'
    ];
    $args = wp_parse_args( $args, $defaults );
    $slug = sanitize_key( (string) $args['slug'] );
    if ( '' === $slug ) {
        return '';
    }

    $args['title']    = is_string( $args['title'] ) ? $args['title'] : '';
    $args['priority'] = (int) $args['priority'];
    $args['context']  = in_array( $args['context'], [ 'frontend', 'admin' ], true ) ? $args['context'] : 'frontend';

    $GLOBALS['tpw_gallery_sources'][ $slug ] = $args;

    /**
     * Fires when a gallery source is registered.
     *
     * @param array $args Registered args for the source.
     */
    do_action( 'tpw_gallery_source_registered', $args );

    return $slug;
}

/**
 * Return registered gallery sources.
 *
 * @return array<string,array> Map of slug => args
 * @since 0.6.0
 */
function tpw_gallery_get_sources(): array {
    $registered = isset( $GLOBALS['tpw_gallery_sources'] ) && is_array( $GLOBALS['tpw_gallery_sources'] )
        ? $GLOBALS['tpw_gallery_sources']
        : [];
    // Optional: sort by priority asc
    uasort( $registered, function( $a, $b ){
        return (int)($a['priority'] ?? 10) <=> (int)($b['priority'] ?? 10);
    } );
    /**
     * Filters the set of registered gallery sources before returning.
     *
     * @param array $registered Current request's registered sources.
     */
    return apply_filters( 'tpw_gallery_sources', $registered );
}

/**
 * Render a gallery source by slug using its callback, then dynamic filter.
 *
 * @param string $slug
 * @param array  $context Arbitrary context (e.g., gallery_id, layout args)
 * @return string HTML output (may be empty)
 * @since 0.6.0
 */
function tpw_gallery_render_source( string $slug, array $context = [] ): string {
    $slug = sanitize_key( $slug );
    if ( '' === $slug ) return '';

    $output = '';
    // Allow a direct callback if a plugin called tpw_register_gallery_source earlier in the request.
    // Since we don't keep a persistent registry here, we primarily rely on the dynamic filter below.
    $output = apply_filters( 'tpw_gallery_source_' . $slug, $output, $context );
    return (string) $output;
}

/**
 * Attach a gallery to a FlexiEvent (or any post) by storing a meta reference.
 *
 * @param int $event_id   Post ID of the event.
 * @param int $gallery_id Gallery ID to attach.
 * @return bool|WP_Error
 * @since 0.6.0
 */
function tpw_gallery_attach_to_event( int $event_id, int $gallery_id ) {
    if ( $event_id <= 0 || $gallery_id <= 0 ) return new WP_Error( 'tpw_gallery_invalid', 'Invalid IDs.' );
    if ( ! get_post( $event_id ) ) return new WP_Error( 'tpw_gallery_invalid_post', 'Event not found.' );
    // Single gallery per event for now; overwrite existing.
    return (bool) update_post_meta( $event_id, '_tpw_gallery_id', (int) $gallery_id );
}

/**
 * Get the attached gallery for an event post.
 *
 * @param int $event_id Post ID
 * @return int Gallery ID or 0 when none
 * @since 0.6.0
 */
function tpw_gallery_get_for_event( int $event_id ): int {
    if ( $event_id <= 0 ) return 0;
    $gid = get_post_meta( $event_id, '_tpw_gallery_id', true );
    return (int) $gid;
}

/**
 * Add an existing Media Library attachment to a gallery.
 * @since 0.4.0 Gallery UI
 */
function tpw_gallery_add_attachment( int $gallery_id, int $attachment_id, string $caption = '' ) {
    global $wpdb;
    $gallery_id = (int) $gallery_id;
    $attachment_id = (int) $attachment_id;
    if ( $gallery_id <= 0 || $attachment_id <= 0 ) return new WP_Error( 'tpw_gallery_invalid', 'Invalid gallery or attachment.' );

    $tbl = $wpdb->prefix . 'tpw_gallery_images';
    $next = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$tbl} WHERE gallery_id = %d", $gallery_id ) );

    add_post_meta( $attachment_id, '_tpw_gallery_id', $gallery_id, true );
    $ok = $wpdb->insert( $tbl, [
        'gallery_id'    => $gallery_id,
        'attachment_id' => $attachment_id,
        'caption'       => $caption,
        'sort_order'    => max(0, $next),
    ], [ '%d','%d','%s','%d' ] );
    if ( false === $ok ) return new WP_Error( 'tpw_gallery_image_insert_failed', 'Failed to add image.' );

    return (int) $wpdb->insert_id;
}

/**
 * Remove a single image from a gallery (unlink only; keeps the attachment in Media Library).
 * @since 0.4.0 Gallery UI
 * @since 0.6.1 Behavior changed to non-destructive (unlink only).
 */
function tpw_gallery_delete_image( int $image_id ) {
    global $wpdb;
    $image_id = (int) $image_id;
    if ( $image_id <= 0 ) return new WP_Error( 'tpw_gallery_invalid', 'Invalid image.' );

    $tbl = $wpdb->prefix . 'tpw_gallery_images';
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE image_id = %d", $image_id ), ARRAY_A );
    if ( ! $row ) return true; // already gone
    $aid = (int) ( $row['attachment_id'] ?? 0 );
    if ( $aid > 0 ) {
        // Remove gallery reference meta if present
        delete_post_meta( $aid, '_tpw_gallery_id', (int) ($row['gallery_id'] ?? 0) );
    }
    $wpdb->delete( $tbl, [ 'image_id' => $image_id ], [ '%d' ] );
    return true;
}

/**
 * Permanently delete a gallery image: removes attachment file from Media Library and DB row.
 * @since 0.6.1
 */
function tpw_gallery_delete_image_permanently( int $image_id ) {
    global $wpdb;
    $image_id = (int) $image_id;
    if ( $image_id <= 0 ) return new WP_Error( 'tpw_gallery_invalid', 'Invalid image.' );

    $tbl = $wpdb->prefix . 'tpw_gallery_images';
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE image_id = %d", $image_id ), ARRAY_A );
    if ( ! $row ) return true;
    $aid = (int) ( $row['attachment_id'] ?? 0 );
    if ( $aid > 0 && get_post( $aid ) ) {
        wp_delete_attachment( $aid, true );
    }
    $wpdb->delete( $tbl, [ 'image_id' => $image_id ], [ '%d' ] );
    return true;
}

/**
 * Reorder images in a gallery based on an ordered list of image IDs.
 * @since 0.4.0 Gallery UI
 */
function tpw_gallery_reorder_images( int $gallery_id, array $ordered_image_ids ) {
    global $wpdb;
    $gallery_id = (int) $gallery_id;
    if ( $gallery_id <= 0 ) return new WP_Error( 'tpw_gallery_invalid', 'Invalid gallery.' );
    $tbl = $wpdb->prefix . 'tpw_gallery_images';
    $order = 0;
    foreach ( $ordered_image_ids as $id ) {
        $iid = (int) $id;
        if ( $iid <= 0 ) continue;
        $wpdb->update( $tbl, [ 'sort_order' => $order ], [ 'image_id' => $iid, 'gallery_id' => $gallery_id ], [ '%d' ], [ '%d','%d' ] );
        $order++;
    }
    return true;
}

/**
 * Update an image caption in both gallery DB row and Media Library attachment.
 * Returns the normalized caption saved.
 * @since 0.6.2
 */
function tpw_gallery_update_image_caption( int $image_id, string $caption ) {
    global $wpdb;
    $image_id = (int) $image_id;
    if ( $image_id <= 0 ) return new WP_Error( 'tpw_gallery_invalid', 'Invalid image.' );
    $tbl = $wpdb->prefix . 'tpw_gallery_images';
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE image_id = %d", $image_id ), ARRAY_A );
    if ( ! $row ) return new WP_Error( 'tpw_gallery_not_found', 'Image not found.' );

    $normalized = wp_kses_post( $caption );
    // Update DB caption
    $ok = $wpdb->update( $tbl, [ 'caption' => $normalized ], [ 'image_id' => $image_id ], [ '%s' ], [ '%d' ] );
    if ( false === $ok ) return new WP_Error( 'tpw_gallery_update_failed', 'Failed to update caption.' );

    // Also update Media Library attachment caption (post_excerpt)
    $aid = (int) ( $row['attachment_id'] ?? 0 );
    if ( $aid > 0 && get_post( $aid ) ) {
        wp_update_post( [ 'ID' => $aid, 'post_excerpt' => $normalized ] );
    }

    return (string) $normalized;
}

/**
 * Return all galleries with image counts for list view.
 * @since 0.4.0 Gallery UI
 */
function tpw_gallery_all_with_counts(): array {
    global $wpdb;
    $g = $wpdb->prefix . 'tpw_galleries';
    $i = $wpdb->prefix . 'tpw_gallery_images';
    $c = $wpdb->prefix . 'tpw_gallery_categories';
    $sql = "SELECT g.*, COALESCE(cnt.c,0) AS image_count, COALESCE(cat.name,'') AS category_name,
                   (
                       SELECT gi.attachment_id
                       FROM {$i} gi
                       WHERE gi.gallery_id = g.gallery_id
                       ORDER BY gi.sort_order ASC, gi.image_id ASC
                       LIMIT 1
                   ) AS cover_attachment_id
            FROM {$g} g
            LEFT JOIN (SELECT gallery_id, COUNT(*) c FROM {$i} GROUP BY gallery_id) cnt ON cnt.gallery_id = g.gallery_id
            LEFT JOIN {$c} cat ON cat.category_id = g.category_id
            ORDER BY g.created_at DESC";
    return (array) $wpdb->get_results( $sql, ARRAY_A );
}
