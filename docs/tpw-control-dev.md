# TPW Control (Front-end Admin Hub) — Developer Notes

TPW Control centralises front‑end admin tools behind a single shortcode and routed sub‑pages.

- Shortcode: `[tpw-control]`
- Route format: `/tpw-control/?action=` where `action` matches a registered section key.
- Default page (no `action`): Dashboard.

Conventions
- Front‑end only for now; architected for optional future wp‑admin UI.
- Permissions leverage Members module statuses and flags (`is_admin`, `is_committee`, `is_match_manager`, `is_noticeboard_admin`).
- Sections can be added by other plugins via filter and action hooks.

Auto-create the Control Page
- TPW plugins should auto-create a WordPress Page titled `TPW Control` with content `[tpw-control]` on activation, if one does not exist. This provides a stable front‑end entry point for society admins.

Registering Sections
Use the `tpw_control/sections` filter to register or modify sections. Each section is an associative array with:

```
[
  'key'        => 'my-section',         // unique id (also used in ?action=)
  'label'      => 'My Section',         // sidebar label
  'capability' => '__tpw_control_is_admin__', // see capability markers below
  'callback'   => [ $class, 'render' ], // callable to render content
  'position'   => 30,                   // sort order in sidebar
  'icon'       => 'dashicons-admin-generic' // optional Dashicons class or URL
]
```

Capability markers (strings) understood by TPW Control:
- `__tpw_control_is_member__` — current user is a valid member per `TPW_Member_Access::is_member_current()`
- `__tpw_control_is_admin__` — current user is an admin per `TPW_Member_Access::is_admin_current()`
- `__tpw_control_is_committee_or_admin__` — member with committee flag or admin

Alternatively, set `capability` to:
- a callable `(array $section) => bool`,
- a WordPress capability string (e.g. `manage_options`),
- `true` (any logged‑in user), or
- `false` (public).

Router and Hooks
- All rendering goes through `TPW_Control_Router` which reads `?action=` and dispatches.
- Hooks:
  - `tpw_control/register_sections` — fire early to let plugins prepare section definitions.
  - `tpw_control/sections` (filter) — add/modify sections.
  - `tpw_control/sidebar_after_menu` — append content below the menu list.
  - `tpw_control/render_upload_pages` — output Upload Pages UI into the Upload Pages section.
  - `tpw_control/upload_pages_shortcode` (filter) — return a shortcode string to be rendered inside the Upload Pages section.
  - `tpw_control/render_menu_manager` — output the front‑end WP Menu Manager UI.

Templates
- Layout: `modules/tpw-control/templates/layout.php` (sidebar + content).
- Dashboard: `modules/tpw-control/templates/dashboard.php`.
- Sections: `modules/tpw-control/templates/sections/*.php`.

Assets
- CSS: `modules/tpw-control/assets/css/tpw-control.css`
- JS: `modules/tpw-control/assets/js/tpw-control.js`

Examples
Add a custom FlexiGolf section:

```php
add_filter( 'tpw_control/sections', function( array $sections ){
    $sections['flexigolf'] = [
        'key'        => 'flexigolf',
        'label'      => 'FlexiGolf',
        'capability' => '__tpw_control_is_member__',
        'callback'   => function(){
            echo '<h3>FlexiGolf</h3>';
            do_action('flexigolf/render_control_panel');
        },
        'position'   => 50,
        'icon'       => 'dashicons-flag',
    ];
    return $sections;
});
```
