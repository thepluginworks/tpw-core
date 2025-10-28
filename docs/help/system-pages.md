# System Pages

## Overview
System Pages is a registry for front‑end WordPress Pages required by TPW (e.g., My Profile, TPW Control). It ensures pages exist and provides helper lookups.

## Key Screens / Shortcodes
- Typical pages:
  - My Profile — [tpw_member_profile]
  - TPW Control — [tpw-control]

## Hooks
- tpw/system_pages/defaults (filter) — Modify default registry rows.
- tpw_system_page_url (filter) — Override URL for a registered slug.

## Extending
- Register your page and ensure it exists:
  - TPW_Core_System_Pages::register_page( 'slug', [ 'title' => 'Title', 'shortcode' => '[shortcode]', 'plugin' => 'my-addon', 'required' => 1 ] );
  - TPW_Core_System_Pages::ensure_page( 'slug' );

## References
- Developer Guide → ../developer-guide.md
- Class: includes/class-tpw-core-system-pages.php

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
