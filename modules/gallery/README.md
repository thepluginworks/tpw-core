# TPW Core – Gallery Module

A modular, hybrid gallery system for TPW Core. New uploads can be stored in `uploads/tpw-galleries/{slug}/` while also creating WordPress Media Library attachments; attaching existing images keeps them in WordPress’ standard uploads structure. Provides admin UI, public shortcodes, and an integration API so other TPW plugins can register gallery sources or attach galleries to domain objects (e.g., events).

- Status: stable
- Version: 0.6.0
- Text domain: `tpw-core`

## Features
- Hybrid storage: filesystem + Media Library attachments
- Versioned DB schema via `dbDelta()`
- Admin UI inside TPW System Page (shortcode: `[tpw_gallery_admin]`)
- Public shortcodes: gallery grid and categories
- AJAX with nonces and capability checks
- Extensibility hooks and source registration API

## Database Schema
Tables are created on activation and versioned under the option `tpw_gallery_db_version`:
- `{$wpdb->prefix}tpw_galleries` – galleries meta (id, slug, title, description, category_id, created_at)
- `{$wpdb->prefix}tpw_gallery_images` – images per gallery (id, gallery_id, attachment_id, file_path, sort_order, created_at)
- `{$wpdb->prefix}tpw_gallery_categories` – taxonomy-like categories (id, slug, title)

See `modules/gallery/gallery-db.php` for column definitions.

## Public PHP API
All functions live in `modules/gallery/gallery-functions.php`.

- tpw_gallery_create( array $args ): int|WP_Error
- tpw_gallery_get( int $id ): array|null
- tpw_gallery_update( int $id, array $args ): bool|WP_Error
- tpw_gallery_delete( int $id ): bool|WP_Error
- tpw_gallery_add_image( int $gallery_id, array $file ): array|WP_Error
- tpw_gallery_delete_image( int $image_id ): bool|WP_Error
- tpw_gallery_reorder_images( int $gallery_id, array $image_ids ): bool|WP_Error
- tpw_gallery_list_by_category( int $category_id ): array
- tpw_gallery_all_with_counts(): array
- tpw_gallery_get_categories(): array
- tpw_gallery_add_category( array $args ): int|WP_Error
- tpw_gallery_delete_category( int $category_id ): bool|WP_Error

### Upload helper
- tpw_gallery_upload_image( int $gallery_id, array $file ): array|WP_Error
  - Creates directories under `uploads/tpw-galleries/{slug}`
  - Uses `wp_handle_upload` then `wp_insert_attachment`
  - Generates image sizes and stores DB row in `tpw_gallery_images`

## Shortcodes
Use these shortcodes anywhere shortcodes are supported (Shortcode block, classic editor, widgets, etc.).

### [tpw_gallery]
Renders one or more responsive gallery grids.

Attributes:
- id: (int) Render a specific gallery by ID.
- category: (string) Category slug; when set (and id not provided), renders all galleries in that category.
- columns: (int) 1..6. Hint for initial column density on wide screens. Default: 3. Layout remains responsive.
- show_categories: ("0"|"1") When "1", shows a categories toolbar above the grid.

Examples:

```text
[tpw_gallery id="123" columns="3"]
[tpw_gallery category="events" columns="4" show_categories="1"]
```

### [tpw_gallery_categories]
Renders a list of all gallery categories as links.

```text
[tpw_gallery_categories]
```

Notes:
- Output is cached using `wp_cache_get/set` keyed by shortcode args.
- Assets are only enqueued when the shortcode renders.
- Clicking a thumbnail opens a built-in lightbox with captions and prev/next navigation.

## Admin UI
System Page: `gallery-admin` contains the shortcode `[tpw_gallery_admin]` which renders:
- Galleries list with image counts
- Create/edit form
- Categories management

AJAX endpoints (all capability and nonce protected):
- wp_ajax_tpw_gallery_delete
- wp_ajax_tpw_gallery_add_attachments
- wp_ajax_tpw_gallery_add_category
- wp_ajax_tpw_gallery_delete_category

Assets are module-scoped under `modules/gallery/assets/`.

### Uploads and Storage (Hybrid)
- Add from Media Library: links existing attachments to the gallery. Files stay under `wp-content/uploads/YYYY/MM/` and are not moved.
- Upload to this Gallery: uploads new files into `wp-content/uploads/tpw-galleries/{slug}/` and registers them as Media Library attachments.

### Removal vs Deletion
- Remove (default): unlinks the image from the gallery (row removed from `tpw_gallery_images`, attachment remains in the Media Library).
- Delete permanently: removes the attachment from the Media Library and deletes the file from disk.

## Integration API (Sources)
Allow other TPW plugins to contribute gallery renderers or sources.

Register a source:

```php
/**
 * In your plugin bootstrap.
 */
add_action('init', function(){
    if ( function_exists('tpw_register_gallery_source') ) {
        tpw_register_gallery_source('my-source', [
            'label'    => 'My Source',
            'priority' => 20,
            // Callback receives ($gallery, $args) and returns HTML string
            'render'   => 'my_source_render_gallery',
        ]);
    }
});
```

List sources:
```php
$sources = tpw_gallery_get_sources();
```

Render via a specific source (internal):
```php
$html = tpw_gallery_render_source('my-source', $gallery, $args);
```

### Relation helpers
- tpw_gallery_attach_to_event( int $event_id, int $gallery_id ): bool
- tpw_gallery_get_for_event( int $event_id ): array|null

## Hooks
- Actions:
  - tpw_gallery_source_registered( array $source )
- Filters:
  - tpw_gallery_sources( array $sources )
  - tpw_gallery_display( string $html, array $atts, array $gallery )
  - Dynamic source filter: `tpw_gallery_source_{slug}` for source-specific params

## WP-CLI
If WP-CLI is present, the module exposes:
- wp tpw gallery list-sources – prints registered sources as JSON

## Versioning
- 0.6.0 – Initial public release: hybrid upload, CRUD, admin UI, public shortcodes, integration API, CLI

## Notes
- Capability defaults to `manage_options` for admin UI during initial release; consider scoping to TPW roles in production.
- All SQL uses `$wpdb->prepare` where applicable; schema created via `dbDelta()`.
- All strings use text domain `tpw-core`.
