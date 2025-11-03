# TPW Payments UI — My Payments Hub

This guide defines the layout, styling, and behaviour standards for the My Payments Hub in TPW Core. It aligns with Core Branding and Admin UI patterns so add‑ons feel consistent across pages.

Applies to: wp‑admin pages and front‑end pages wrapped with the TPW admin UI scope.

---

## Enqueue and scope

Use the same Core styles that power other admin‑like UIs.

1) Scoped admin UI (layout, typography, inputs)
- Handle: `tpw-admin-ui`
- File: `assets/css/tpw-admin-ui.css`
- Scope wrapper: `.tpw-admin-ui`

2) TPW button system
- Handle: `tpw-buttons`
- File: `assets/css/tpw-buttons.css`
- Classes: `.tpw-btn` + variants (primary, secondary, danger, light, dark)

Optional general tokens (front-end helpers)
- File: `assets/css/tpw-ui.css`
- Exposes color tokens like `--tpw-primary`, `--tpw-accent`, etc.

Example (wp‑admin page or front‑end template you control):

```php
if ( defined('TPW_CORE_URL') ) {
    wp_enqueue_style( 'tpw-admin-ui', TPW_CORE_URL . 'assets/css/tpw-admin-ui.css', [], null );
    wp_enqueue_style( 'tpw-buttons',  TPW_CORE_URL . 'assets/css/tpw-buttons.css',  [], null );
}
echo '<div class="tpw-admin-ui" style="' . esc_attr( function_exists('tpw_core_build_ui_theme_style_attr') ? tpw_core_build_ui_theme_style_attr() : '' ) . '">';
// ... your Payments Hub markup ...
echo '</div>';
```

---

## Layout structure

Payments Hub uses a two‑pane structure: a left sidebar for sections and a right content area for the active view.

Required classes
- `.tpw-admin-wrapper` — optional wrapper when embedding inside other Core modules; inherits Core admin helpers.
- `.tpw-sidebar` — left navigation column; contains the section menu.
- `.tpw-menu` — vertical list of links/tabs within the sidebar.
- `.tpw-content` — main content area; scroll host for long tables/forms.
- `.tpw-table-container` — responsive table wrapper for lists and logs.
- `.tpw-card` — lightweight card surface for summaries/metrics.

Minimal skeleton

```html
<div class="tpw-admin-ui tpw-admin-wrapper">
  <div class="tpw-layout">
    <aside class="tpw-sidebar" aria-label="Payments navigation">
      <nav>
        <ul class="tpw-menu">
          <li><a class="is-active" aria-current="page" href="#">Overview</a></li>
          <li><a href="#">Transactions</a></li>
          <li><a href="#">Payment Methods</a></li>
          <li><a href="#">Settings</a></li>
        </ul>
      </nav>
    </aside>
    <main class="tpw-content" role="region" aria-label="Payments content">
      <div class="tpw-card">
        <h2>Overview</h2>
        <p>Key balances and recent activity.</p>
      </div>

      <div class="tpw-table-container" role="region" aria-label="Recent transactions">
        <!-- render listing table or row layout here -->
      </div>
    </main>
  </div>
</div>
```

Notes
- `.tpw-layout` is an internal container; any simple CSS grid/flex is acceptable as long as the class names above are present.
- Use `.tpw-card` for dashboard tiles and summary panels; they are styled by `tpw-admin-ui.css`.

---

## Sidebar behaviour

Active states
- Add `is-active` and `aria-current="page"` to the current menu link.
- Keep labels short; truncate with CSS at ~24ch in narrow viewports.

Responsive collapse
- At ≤ 980px, collapse the sidebar to an icon+tooltip rail or a hidden panel.
- Provide a visible toggle button (e.g., “Menu”) before the main content:

```html
<button class="tpw-btn tpw-btn-secondary tpw-sidebar-toggle" aria-expanded="false" aria-controls="tpw-sidebar">
  Menu
</button>
<aside id="tpw-sidebar" class="tpw-sidebar is-collapsed" hidden>
  <!-- nav -->
</aside>
```

- Toggle rules:
  - When opened, remove `hidden`, remove `.is-collapsed`, and set `aria-expanded="true"`; focus the first active link.
  - When closed, add `hidden`, add `.is-collapsed`, and set `aria-expanded="false"`; return focus to the toggle.
  - Close on Escape and on outside click.

Keyboard
- Sidebar links must be tabbable in DOM order.
- Maintain focus outlines; do not suppress `:focus-visible`.

---

## CSS class references

Apply these classes within the `.tpw-admin-ui` scope so you inherit Core tokens and resets.

- `.tpw-admin-wrapper` — enables Core admin helpers for buttons, tables, notices.
- `.tpw-sidebar` — left column; use a fixed width (e.g., 240px) with sticky positioning.
- `.tpw-menu` — unstyled list inside the sidebar; add `.is-active` to the current `<a>`.
- `.tpw-content` — right column; flexible width; contains cards, forms, tables.
- `.tpw-table-container` — wraps large tables with overflow auto and sticky header support.
- `.tpw-card` — white surface with border radius and spacing for dashboards/settings blocks.

Example table markup

```html
<div class="tpw-table-container">
  <table class="widefat fixed striped">
    <thead>
      <tr>
        <th>Date</th>
        <th>Reference</th>
        <th>Payer</th>
        <th>Amount</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>2025‑11‑01</td>
        <td>INV‑10045</td>
        <td>Jane Doe</td>
        <td>£25.00</td>
        <td><span class="tpw-status tpw-status--success">Paid</span></td>
      </tr>
    </tbody>
  </table>
</div>
```

---

## Core tokens and theming

Use Core CSS Custom Properties for colors, typography, radii, and component styling. The Branding and UI Theme tabs emit tokens into the page head automatically.

Common tokens
- Colors (front‑end helpers): `--tpw-primary`, `--tpw-accent`, `--tpw-bg-light`, `--tpw-text-dark`, `--tpw-border` (from `assets/css/tpw-ui.css`).
- Buttons: `--tpw-btn-primary`, `--tpw-btn-secondary`, `--tpw-btn-danger`, `--tpw-btn-light`, `--tpw-btn-dark`, `--tpw-btn-text-light`, `--tpw-btn-text-dark`, `--tpw-btn-radius`, `--tpw-btn-padding`, `--tpw-btn-font-size`, `--tpw-btn-height` (from `assets/css/tpw-buttons.css`).
- Admin UI scope: `--tpw-font-family`, `--tpw-font-weight`, `--tpw-accent-color`, `--tpw-input-*`, heading `--tpw-h1..h6-*` (from `assets/css/tpw-admin-ui.css`).

Examples

```css
/* Sidebar current item */
.tpw-admin-ui .tpw-sidebar .tpw-menu a.is-active {
  background: color-mix(in oklab, var(--tpw-primary, var(--tpw-accent-color, #2271b1)) 12%, white);
  color: var(--tpw-text-dark, #222);
  border-left: 3px solid var(--tpw-primary, var(--tpw-accent-color, #2271b1));
}

/* Status chips in tables */
.tpw-status--success { color: #0f5132; background: color-mix(in oklab, var(--tpw-success, #28a745) 12%, white); }
.tpw-status--warning { color: #664d03; background: color-mix(in oklab, var(--tpw-warning, #f59e0b) 16%, white); }
.tpw-status--danger  { color: #842029; background: color-mix(in oklab, var(--tpw-danger,  #dc3545) 12%, white); }

/* Cards */
.tpw-admin-ui .tpw-card { border-radius: var(--tpw-btn-radius); }
```

Notes
- You don’t need to re‑emit tokens; Core injects them into both admin and front‑end heads. Use the wrapper `.tpw-admin-ui` to scope resets and fonts.
- Prefer `.tpw-btn` classes for actions; they already consume branding tokens.

---

## Accessibility guidance

- Landmarks: Use `<aside>` for the sidebar and `<main>` for content; add `aria-label` where helpful.
- Active state: Add `aria-current="page"` on the current menu link.
- Keyboard: Ensure the sidebar toggle is focusable, closes on Escape, and returns focus to the invoking button.
- Contrast: Meet WCAG AA contrast. The default tokens do; verify if you override colors.
- Notices: Use `.notice` blocks (in the scope) for errors/success where possible.
- Tables: Provide column headers (`<th scope="col">`), and use visually hidden labels for icon‑only controls.

---

## Responsive behaviour

Breakpoints
- ≥ 1200px: Two‑pane layout with fixed sidebar (≈ 240–280px) and fluid content.
- 981–1199px: Slightly narrower sidebar; reduce card grid columns.
- ≤ 980px: Sidebar collapses into a toggleable drawer; content becomes single column.

Patterns
- Use `.tpw-table-container` with overflow auto for wide tables; avoid horizontal scroll on the body.
- Convert action toolbars to wrap on small screens and prefer icon + label buttons where space is tight.

---

## Developer notes — registering Payments Hub sub‑tabs

Plugins can add sources/sections to the Payments Hub via a filter that collects registered sources. Use the preferred hook:

- Filter: `tpw_core_register_payment_sources`
- Since: 1.1.x
- Purpose: Register additional Payments Hub sources (sub‑tabs) with labels, visibility, and render callbacks.

Shape

```php
add_filter( 'tpw_core_register_payment_sources', function( array $sources ) {
    $sources['payouts'] = [
        'key'        => 'payouts',              // unique slug
        'label'      => 'Payouts',              // sidebar label
        'position'   => 40,                     // sort order
        'capability' => 'manage_options',       // WP cap or callable (bool)
        'icon'       => 'dashicons-money-alt',  // optional
        'callback'   => [ My_Plugin_Payouts::class, 'render' ], // callable to render
    ];
    return $sources;
});
```

Rendering
- If you provide `callback`, it will be called to render the content inside `.tpw-content`.
- If you omit `callback`, the router should fire `do_action( 'tpw_payments_render_source_' . $slug, $section )` — keep your renderer hooked to that action. This mirrors other Core hubs.

Active URL
- Build links using your hub base (admin or front‑end) and append `?tab=<slug>` or `&source=<slug>` per your router. Keep the `<a>` in the sidebar and mark the current one with `.is-active` and `aria-current="page"`.

Back‑compat
- If you already use a legacy filter (e.g., `tpw_core/payment_sources`), keep it in place; the new filter should run first and then merge legacy entries.

---

## Minimal recipes

1) Add a custom “Payouts” tab to the Payments Hub and render a card + table

```php
add_filter( 'tpw_core_register_payment_sources', function( $sources ) {
    $sources['payouts'] = [
        'key'      => 'payouts',
        'label'    => 'Payouts',
        'position' => 40,
        'callback' => function(){
            echo '<div class="tpw-card"><h2>Payouts</h2><p>Recent transfers to bank.</p></div>';
            echo '<div class="tpw-table-container">';
            echo '<table class="widefat fixed striped"><thead><tr>';
            echo '<th>Date</th><th>Reference</th><th>Amount</th><th>Status</th>';
            echo '</tr></thead><tbody>';
            echo '<tr><td>2025‑10‑31</td><td>PO‑0098</td><td>£125.00</td><td><span class="tpw-status tpw-status--success">Completed</span></td></tr>';
            echo '</tbody></table></div>';
        },
    ];
    return $sources;
});
```

2) Use Core buttons and tokens in actions

```html
<div class="tpw-actions">
  <a class="tpw-btn tpw-btn-primary" href="#">Export CSV</a>
  <button type="button" class="tpw-btn tpw-btn-light">Refresh</button>
</div>
```

---

## Notes and conventions

- Keep your markup inside `.tpw-admin-ui` so Core can apply typography and component tokens.
- Use `.tpw-card` for panels; use `.tpw-table-container` for lists.
- Keep side effects (enqueue, nonces, saving) in your PHP and keep templates free of business logic.
- For front‑end embeds, prefer adding an outer `.tpw-frontend-ui` class as needed; the admin scope works in both contexts.
- Avoid redefining colors; lean on `--tpw-*` tokens from Branding and UI Theme. If you add success/warning/danger, expose them as CSS vars and use `color-mix()` to keep soft backgrounds.

See also: Branding → `docs/help/tpw-branding.md`, Admin UI CSS → `assets/css/tpw-admin-ui.css`, Buttons → `assets/css/tpw-buttons.css`.
