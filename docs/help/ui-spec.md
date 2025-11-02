# TPW Admin UI Specification

This document defines the structural and component conventions for TPW admin pages. It complements the Branding guide and avoids repeating colour, font, or token values. For all token definitions and theming details, see Branding and tokens → [tpw-branding.md](tpw-branding.md).

---

## Overview

- Purpose: Provide a consistent admin layout and component structure across TPW plugins.
- Scope: Structure, semantics, and responsive behaviour. Styling tokens and variables live in [tpw-branding.md](tpw-branding.md).
- Applicability: wp-admin pages you render, and admin-like screens embedded in front-end contexts.

---

## Admin Page Structure

Canonical wrapper: `.tpw-admin-ui`

- Wrap all TPW-styled admin pages with `.tpw-admin-ui` to get the scoped resets and theme application.
- Inside structure (recommended skeleton):

```html
<div class="tpw-admin-ui">
  <header class="tpw-admin-header">
    <h1 class="tpw-section-heading">Page Title</h1>
    <div class="tpw-admin-actions">
      <a class="tpw-btn tpw-btn-primary" href="#">Add New</a>
    </div>
  </header>
  <div class="tpw-admin-content">
    <!-- content here -->
  </div>
</div>
```

Responsive behaviour and spacing expectations:
- Header layout: title left, actions right, collapsing to a stacked layout on small screens with actions below the title.
- Content area: use natural flow; prefer CSS `gap` on flex/grid containers for spacing rather than large margins.
- Vertical rhythm: keep section spacing consistent; avoid mixing arbitrary pixel values with CSS variable-driven space (see admin CSS for defaults). Use container padding rather than per-child padding when possible.
- Typography and colours are provided by the theme scope in `.tpw-admin-ui`; do not hard-code them here.

---

## Tables and Lists

Use a flexible container pattern for list and table-like layouts.

Core conventions:
- `.tpw-table-container` — outer wrapper that manages scrolling and responsive behaviour
- `.table-row` — a single row or item
- `.table-cell` — a cell/column inside a row

Behaviour:
- Grid-based alignment: rows may be implemented with CSS Grid for precise column sizing; cells align with grid template areas or fractions (e.g., `grid-template-columns`).
- Hover states: rows should have a subtle hover background; avoid changing text colour significantly.
- Responsive stacking: on narrow viewports, rows stack cells vertically with labels where helpful.

Example (list view):

```html
<div class="tpw-table-container">
  <div class="table-row" role="row">
    <div class="table-cell" role="cell"><strong>Member</strong><br/>Alex Smith</div>
    <div class="table-cell" role="cell">alex@example.com</div>
    <div class="table-cell" role="cell">Active</div>
    <div class="table-cell" role="cell">
      <a class="tpw-btn tpw-btn-secondary" href="#">Edit</a>
    </div>
  </div>
  <div class="table-row" role="row">
    <div class="table-cell" role="cell"><strong>Member</strong><br/>Jamie Lee</div>
    <div class="table-cell" role="cell">jamie@example.com</div>
    <div class="table-cell" role="cell">Pending</div>
    <div class="table-cell" role="cell">
      <a class="tpw-btn tpw-btn-secondary" href="#">Review</a>
    </div>
  </div>
</div>
```

Accessibility:
- Use appropriate roles (`row`, `cell`) when you’re not using native `<table>` markup.
- Ensure focus states are visible and keyboard interaction is supported for any row-level actions.

---

## Forms and Panels

Conventions:
- `.tpw-form-group` — wrap label + control + help text
- `.tpw-input` — text-like inputs (applies to `<input type="text|email|number|...">`)
- `.tpw-select` — select dropdowns
- `.tpw-textarea` — multi-line input
- `.tpw-form-help` — brief helper text under a control

Two-column settings screens:
- Use a responsive two-column grid for desktop (content and sidebar panels) that collapses to one column on small screens.
- Panel blocks (cards) should have a clear header and content area; keep consistent padding and spacing.

Example:

```html
<div class="tpw-admin-ui">
  <div class="tpw-admin-content tpw-grid-2">
    <section class="tpw-panel">
      <h2 class="tpw-section-heading">General Settings</h2>
      <div class="tpw-form-group">
        <label for="site_name">Site Name</label>
        <input class="tpw-input" id="site_name" name="site_name" type="text" />
        <p class="tpw-form-help">Shown in emails and headers.</p>
      </div>
      <div class="tpw-form-group">
        <label for="default_role">Default Role</label>
        <select class="tpw-select" id="default_role" name="default_role">
          <option value="none">None</option>
          <option value="member">Member</option>
        </select>
      </div>
      <p>
        <button type="submit" class="tpw-btn tpw-btn-primary">Save changes</button>
      </p>
    </section>

    <aside class="tpw-panel tpw-panel-aside">
      <h3 class="tpw-section-heading">Tips</h3>
      <p>Use clear labels and help text for each setting.</p>
    </aside>
  </div>
</div>
```

For additional real-world examples, refer to the Members and FlexiGolf admin templates in this repository.

---

## Buttons and Actions

- Use the TPW button system for all actions: `.tpw-btn` as the base, with variants like `.tpw-btn-primary`, `.tpw-btn-secondary`, etc.
- Keep button labels short. Group related actions in the header’s `.tpw-admin-actions` or at the end of relevant panels.
- For the full list of variants, sizing, and semantics, see Branding and tokens → [tpw-branding.md](tpw-branding.md).

---

## Tabs and Navigation (Optional)

- When a page requires multiple sections, prefer WordPress `nav-tab` styles for standard WP pages.
- If you need TPW’s minimal tab utilities, you can use `assets/css/tpw-admin-tabs.css`. Apply them sparingly and only when they improve clarity.

---

## Asset Enqueuing

Reference handles and usage are documented in [tpw-branding.md](tpw-branding.md). In summary:

- `tpw-admin-ui.css` — Scoped admin layout and resets (handle: `tpw-admin-ui`)
- `tpw-buttons.css` — Global button system (handle: `tpw-buttons`)

Example for dependent plugins:

```php
if ( defined('TPW_CORE_URL') ) {
    // Scoped UI for admin-like pages
    wp_enqueue_style( 'tpw-admin-ui', TPW_CORE_URL . 'assets/css/tpw-admin-ui.css', [], null );
    // Button system (admin or front-end where needed)
    wp_enqueue_style( 'tpw-buttons', TPW_CORE_URL . 'assets/css/tpw-buttons.css', [], null );
}
```

---

## Implementation Checklist

- Wrap the page with `.tpw-admin-ui`.
- Use `.tpw-table-container` for listings with `.table-row` and `.table-cell`.
- Use TPW Core button classes for actions.
- Enqueue TPW Core assets (`tpw-admin-ui` and `tpw-buttons`).
- Verify responsive layout, including stacked header and list/table behaviour.

---

## Related documentation

- Branding and tokens → [tpw-branding.md](tpw-branding.md)
- Developer guidance → [../developer-guide.md](../developer-guide.md)
