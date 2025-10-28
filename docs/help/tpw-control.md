# TPW Control

## Overview
TPW Control is a front‑end admin hub that aggregates society tools (e.g., Upload Pages, Menu Manager) behind a single shortcode and routed sections.

## Key Screens / Shortcodes
- Shortcode: [tpw-control]
- Route: /tpw-control/?action=section-key (e.g., upload-pages, menu-manager)

## Hooks
- tpw_control_register_sections (filter) — Register/modify sections (preferred).
- tpw_control/sections (filter) — Back‑compat filter for sections.
- tpw_control_can_manage (filter) — Gate access to the Control hub.
- tpw_control/sidebar_after_menu (action) — Append sidebar content.
- tpw_control_render_section_{slug} (action) — Render external sections.

## Extending
- Register a section via the filter; optionally render via the dynamic action if no callback is provided in the section.
- Respect the visibility model (admins always pass; members per flags/status).

## References
- Admin guide → ./admin-guide-tpw-control.md
- Developer guide → ./developer-guide-tpw-control.md
- Core Developer Guide → ../developer-guide.md

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
