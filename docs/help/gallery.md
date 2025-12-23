# Gallery

## Overview
The Gallery module renders image collections from registered sources. It is optional and can be disabled via a filter.

## Key Screens / Shortcodes


- Public shortcode: `[tpw_gallery id="123" view="grid|list|story" columns="3" show_categories="0|1" per_page="0" paginate="0|1"]`
	- `id`: Gallery ID to render.
	- `view`: Choose `grid`, `list`, or the new `story` inline carousel (one image at a time, no autoplay).
	- `columns`: Grid density hint (ignored by `list`/`story`).
	- `show_categories`: When `1`, shows the categories toolbar above the gallery.
	- `per_page`: Optional performance safeguard for `grid`/`list` only. Limits how many tiles are rendered per page. `0` disables (default).
	- `paginate`: When `1`, enables pagination for `grid`/`list`. If `per_page` is not set, a conservative default (60) is used.

## Elementor: TPW Gallery widget

If Elementor is installed and active, TPW Core provides an Elementor widget named **TPW Gallery**.

- **Gallery**: Type-to-search dropdown to select a gallery by title. The displayed label is `Title (Category)` when a category exists.
- **View**:
  - **Grid**: Responsive grid of thumbnails (uses the same output as the `view="grid"` shortcode).
  - **List**: Vertical list of images with captions (uses the same output as `view="list"`).
  - **Story**: Inline carousel (one image at a time) with prev/next, keyboard arrows, and swipe (uses the same output as `view="story"`).
- **Columns**: Only shown when View = Grid; acts as the same grid density hint as the shortcode `columns` attribute.
- **Paginate** (Grid/List only): Toggle pagination.
- **Per page** (when Paginate is enabled): Limits how many thumbnails render at once.

Notes:

- Pagination uses a per-gallery query arg like `?tpw_gallery_page_123=2` so multiple galleries on the same page can paginate independently.

### Help Page
- Route: `/gallery-help/`
- Shortcode: `[tpw_gallery_help]`
- Purpose: Explains gallery management features (reordering, captions, focal points, categories) for end users.

## Hooks
- tpw_gallery_enabled (filter) — Toggle gallery feature.
- tpw_gallery_sources (filter) — Filter the list of registered sources.
- tpw_gallery_source_{slug} (filter) — Transform output for a specific source.
- tpw_gallery_source_registered (action) — Fires after a source is registered.

## Extending
- Register a source and use the per‑source filter to render your output. Observe contexts passed in the filter args to vary markup.

## References
- Developer Guide → ../developer-guide.md
- File: modules/gallery/gallery-functions.php

### Story / Carousel View
- Inline, on-page carousel (not a lightbox).
- One image at a time, navigable via Previous/Next, keyboard arrows, and touch swipes.
- Honors existing `sort_order`; caption is shown prominently with the image.
- Usage example: `[tpw_gallery id="123" view="story"]`

### Large galleries
- For very large galleries in `grid`/`list`, prefer `per_page` or `paginate` to avoid rendering hundreds of thumbnails at once.

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
