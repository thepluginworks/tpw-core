# TPW Control — Upload Pages Hooks (Phase 1)

Internal reference for new actions/filters introduced to evolve Upload Pages into a full archive system without changing current UI or behavior.

- Module: `modules/tpw-control/` (class `TPW_Control_Upload_Pages`)
- Scope: Front-end shortcode render, AJAX filtering, uploads, CRUD, and URL signing
- Back-compat: All hooks are optional; defaults preserve existing behavior

## Filters

### tpw_control_upload_pages_get_files_override
- Purpose: Fully override file retrieval (e.g., pagination/search backends)
- Signature: `apply_filters( 'tpw_control_upload_pages_get_files_override', $override, int $page_id )`
- Return: `array|null` of file row objects; return `null` to fall back to default SQL

### tpw_control_upload_pages_files
- Purpose: Adjust/augment rows after default retrieval
- Signature: `apply_filters( 'tpw_control_upload_pages_files', array $rows, int $page_id )`
- Return: Modified array of rows

### tpw_control_upload_pages_ajax_list_html
- Purpose: Replace the inner HTML list returned by AJAX filter endpoint
- Signature: `apply_filters( 'tpw_control_upload_pages_ajax_list_html', $html_or_null, string $layout, array $files, array $categories, object $page )`
- Return: `string|null` — return string to override; return `null` to use default renderer

### tpw_control_upload_pages_before_filter_bar / tpw_control_upload_pages_after_filter_bar
- Purpose: Inject custom markup before/after the filter bar (e.g., search input)
- Signature: `apply_filters( 'tpw_control_upload_pages_before_filter_bar', '', object $page )`
- Signature: `apply_filters( 'tpw_control_upload_pages_after_filter_bar', '', object $page )`
- Return: `string` HTML to echo (empty by default)

### tpw_control_upload_pages_before_list / tpw_control_upload_pages_after_list
- Purpose: Inject markup before/after the files list (e.g., pagination controls)
- Signature: `apply_filters( 'tpw_control_upload_pages_before_list', '', object $page, string $layout )`
- Signature: `apply_filters( 'tpw_control_upload_pages_after_list', '', object $page, string $layout )`
- Return: `string` HTML to echo (empty by default)

### tpw_control_upload_pages_signed_url_ttl
- Purpose: Adjust TTL for signed URLs (files and editor assets)
- Signature: `apply_filters( 'tpw_control_upload_pages_signed_url_ttl', int $ttl, string $variant, int $id )`
- Notes: `$variant` is `file` or `editor`; `$id` is file_id for `file`, or page_id for `editor`
- Return: `int` seconds (minimum enforced: 60)

### tpw_control_upload_pages_allowed_types
- Purpose: Extend/modify allowed upload file types
- Signature: `apply_filters( 'tpw_control_upload_pages_allowed_types', array $types )`
- Default: `pdf, doc, docx, xls, xlsx, jpg/jpeg, png, mp4`
- Return: Associative array: `ext => mime`

## Actions

### tpw_control_upload_pages_before_render / tpw_control_upload_pages_after_render
- Purpose: Observe lifecycle around public render for an Upload Page
- Signature: `do_action( 'tpw_control_upload_pages_before_render', object $page )`
- Signature: `do_action( 'tpw_control_upload_pages_after_render', object $page )`

### tpw_control_upload_pages_enqueue_public_assets
- Purpose: Enqueue additional front-end assets alongside Upload Pages assets
- Signature: `do_action( 'tpw_control_upload_pages_enqueue_public_assets' )`

### tpw_control_upload_pages_before_handle_uploads / tpw_control_upload_pages_after_handle_uploads
- Purpose: Lifecycle around handling uploads (front-end admin UI)
- Signature: `do_action( 'tpw_control_upload_pages_before_handle_uploads', int $page_id )`
- Signature: `do_action( 'tpw_control_upload_pages_after_handle_uploads', array $result, int $page_id )`
- Notes: `$result` matches `['uploaded' => int[], 'errors' => string[]]`

### tpw_control_upload_pages_file_added
- Purpose: React to a newly added file
- Signature: `do_action( 'tpw_control_upload_pages_file_added', int $file_id, array $meta )`
- `$meta`: `page_id, file_path, file_url, file_type, label, year, thumbnail_url, category_id`

### tpw_control_upload_pages_file_updated
- Purpose: React to an updated file (label/year/order/category)
- Signature: `do_action( 'tpw_control_upload_pages_file_updated', int $file_id, array $fields )`

### tpw_control_upload_pages_file_deleted
- Purpose: React to a deleted file record
- Signature: `do_action( 'tpw_control_upload_pages_file_deleted', int $file_id )`

### tpw_control_upload_pages_files_reordered
- Purpose: React to a batch reorder within a page
- Signature: `do_action( 'tpw_control_upload_pages_files_reordered', int $page_id, int[] $ordered_ids )`

## Override upload handling (optional)

You can take over the entire upload pipeline (e.g., to run an import) and return the standard result structure.

- Filter: `tpw_control_upload_pages_handle_uploads`
- Signature: `apply_filters( 'tpw_control_upload_pages_handle_uploads', $override, int $page_id )`
- Return: `['uploaded' => int[], 'errors' => string[]]` or `null` to use default handler

## Notes

- All hooks are namespaced with `tpw_control_upload_pages_*` to avoid collisions.
- Added in Phase 1; intended for pagination/search/import in later phases.
- No changes to existing markup/behavior when hooks are unused.
