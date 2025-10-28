# Gallery

## Overview
The Gallery module renders image collections from registered sources. It is optional and can be disabled via a filter.

## Key Screens / Shortcodes
- Shortcode: typically provided by dependent plugins; core exposes filters for sources and display.

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

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
