# Gallery

## Overview
The Gallery module renders image collections from registered sources. It is optional and can be disabled via a filter.

## Key Screens / Shortcodes
- Public shortcode: `[tpw_gallery id="123" view="grid|list|story" columns="3" show_categories="0|1"]`
	- `id`: Gallery ID to render.
	- `view`: Choose `grid`, `list`, or the new `story` inline carousel (one image at a time, no autoplay).
	- `columns`: Grid density hint (ignored by `list`/`story`).
	- `show_categories`: When `1`, shows the categories toolbar above the gallery.

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

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
