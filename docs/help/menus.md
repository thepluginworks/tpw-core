# Menus (Event Menus)

## Overview
Event Menus let you define menus (courses and choices) and attach them to events. A front‑end modal displays the menu on event pages.

## Key Screens / Shortcodes
- Front‑end render: automatically after event content; shortcode fallback [tpw_menu_modal event_id="123"].
- Admin tables (created on activate): tpw_menus, tpw_menu_courses, tpw_menu_choices, tpw_event_menu_relationship.

## Hooks
- tpw_core/menu_modal_button_label (filter) — Change button label.
- tpw_core/menu_modal_title (filter) — Change modal title.
- tpw_core/menu_modal_price_html (filter) — Customize price HTML.

## Extending
- Use TPW_Menus::get_menu_payload( $event_id ) to reuse payload server‑side.
- Theme override: create template at theme/tpw-core/menus/menu-modal.php to customize markup.

## References
- Developer Guide → ../developer-guide.md
- Renderer: modules/menus/class-tpw-menus.php
- Template: modules/menus/templates/menu-modal.php

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
