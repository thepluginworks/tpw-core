# TPW Core Branding CSS Reference

This document explains where TPW Core's branding and typography CSS Custom Properties ("tokens") are defined, how they are injected into pages, and how to extend them (e.g. adding semantic notice colors such as `--tpw-color-success`).

---

## Overview

TPW Core exposes a set of CSS variables that unify button styling, UI theme typography, and headings across admin and (future) frontend UIs. These are generated dynamically from saved WordPress options rather than a dedicated class file (the old `class-tpw-core-branding.php` no longer exists).

All generation and output now live inside `includes/tpw-core-settings.php` via helper functions and anonymous hooks on `admin_head` and `wp_head`.

Cross-link: For a usage-oriented overview (including the new semantic notice colour tokens) see `docs/help/tpw-branding.md` → section "Semantic notice colours".

---

## Source of Branding Variables

Function: `tpw_core_build_branding_css( $only_if_not_empty = false )`

Location: `includes/tpw-core-settings.php` (around the block starting near the Email Template save handler). It:

1. Reads the option `tpw_core_branding` (array of saved values).
2. Maps option keys to CSS variable names.
3. Optionally appends UI Theme typography tokens from `tpw_ui_theme_settings`.
4. Returns a single CSS string of the form:
	```css
	:root{
	  --tpw-btn-primary: #0b6cad;
	  ...
	}
	```
5. Skips empty values when `$only_if_not_empty` is `true`, allowing static CSS fallbacks defined in `assets/css/tpw-buttons.css` and other stylesheets.

### Option Keys → CSS Variables Map

| Option Key        | CSS Variable              | Purpose |
|-------------------|---------------------------|---------|
| btn_primary       | --tpw-btn-primary         | Primary button background |
| btn_secondary     | --tpw-btn-secondary       | Secondary button background |
| btn_danger        | --tpw-btn-danger          | Destructive actions |
| btn_light         | --tpw-btn-light           | Light (neutral) button background |
| btn_dark          | --tpw-btn-dark            | Dark button background |
| btn_text_light    | --tpw-btn-text-light      | Text color for dark/primary buttons |
| btn_text_dark     | --tpw-btn-text-dark       | Text color on light buttons |
| action_edit       | --tpw-action-edit         | Accent color for edit action badges/buttons |
| btn_radius        | --tpw-btn-radius          | Radius for `.tpw-btn` |
| btn_padding       | --tpw-btn-padding         | Horizontal/vertical padding |
| btn_font_size     | --tpw-btn-font-size       | Font size |
| btn_font_family   | --tpw-btn-font-family     | Font family override for buttons |
| btn_font_weight   | --tpw-btn-font-weight     | Font weight |
| btn_height        | --tpw-btn-height          | Explicit height (optional) |

Appended (if set in UI Theme tab):

| UI Theme Key      | CSS Variable        | Scope Impact |
|-------------------|---------------------|--------------|
| font_family       | --tpw-font-family   | Global font (inherited by buttons when variable is used) |
| font_weight       | --tpw-font-weight   | Base weight for UI text |
| text_transform    | --tpw-text-transform| Text transform hints |
| letter_spacing    | --tpw-letter-spacing| Tracking/spacing |
| text_shadow       | --tpw-text-shadow   | Shadow style |

---

## Injection Mechanisms

Two places output the branding variables:

1. Global admin & frontend head:
	```php
	add_action( 'admin_head', function(){
		 $css = tpw_core_build_branding_css( true );
		 if ( $css ) { echo '<style id="tpw-core-branding-vars">' . $css . '</style>'; }
	});
	add_action( 'wp_head', function(){
		 $css = tpw_core_build_branding_css( true );
		 if ( $css ) { echo '<style id="tpw-core-branding-vars">' . $css . '</style>'; }
	});
	```

2. Branding tab live preview:
	Inside `tpw_core_render_branding_tab()` the file `assets/css/tpw-buttons.css` is enqueued with handle `tpw-buttons`, then `wp_add_inline_style( 'tpw-buttons', $inline )` attaches the generated `:root{...}` block for immediate preview.

Fallback colors & dimensions are hard-coded in `assets/css/tpw-buttons.css`; empty option values therefore maintain consistent UI via CSS fallbacks.

---

## Heading Typography Variables (Related)

Function: `tpw_core_build_heading_css( $only_if_not_empty = true )`

Generates:
`--tpw-h1-size`, `--tpw-h1-weight`, `--tpw-h1-color`, ... through `--tpw-h6-*`, plus `--tpw-heading-font`.

These are injected similarly using `<style id="tpw-core-heading-vars">` via `admin_head` and `wp_head`. Scope output includes `.tpw-admin-ui`, `.tpw-frontend-ui`, and `:root` so headings render correctly both inside and outside scoped containers.

---

## Extending: Adding Semantic Notice Colors

Target new variables:
```
--tpw-color-success
--tpw-color-info
--tpw-color-warning
--tpw-color-error
```

Steps (no code changes yet):

1. Add defaults in `$defaults` inside `tpw_core_render_branding_tab()` (e.g. success `#198754`, info `#0d6efd`, warning `#ffc107`, error `#dc3545`).
2. Add keys to the `$map` array in `tpw_core_build_branding_css()` mapping to the CSS variable names above.
3. Update the save whitelist loop (search for the `foreach ( ['btn_primary','btn_secondary', ...` near the saving logic) to include the new keys so they persist.
4. (Optional) Update documentation (`docs/help/tpw-branding.md`) to reference the semantic colors and encourage consistent component usage.
5. In notice CSS, consume variables with fallbacks:
	```css
	.tpw-notice-success { --tpw-notice-base: var(--tpw-color-success, #198754); }
	.tpw-notice-success { background: color-mix(in srgb, var(--tpw-notice-base) 12%, white); border-left: 4px solid var(--tpw-notice-base); color: var(--tpw-notice-base); }
	```

Design Guidance:
* Keep variable naming consistent (`--tpw-color-*`) for non-button palette tokens.
* Use `color-mix()` for subtle backgrounds instead of opaque blocks.
* Provide accessible contrast; test against white and light gray backgrounds.

See also: `docs/help/tpw-branding.md` for the live defaults and admin override description.

### Scoped Notice Styling

To make semantic notice colours work consistently in both admin screens and public-facing UIs, TPW Core styles notices under both `.tpw-admin-ui` and `.tpw-frontend-ui` scopes using the same variables. For example:

```css
.tpw-admin-ui :where(.tpw-notice-warning),
.tpw-frontend-ui :where(.tpw-notice-warning) {
	color: var(--tpw-color-warning);
}
```

This ensures consistent appearance between admin and public-facing RSVP pages.

### Frontend: Universal field validation

TPW Core provides a universal style for invalid required fields on TPW-styled forms, scoped to `.tpw-frontend-ui` and using the semantic warning token. These rules live in `assets/css/tpw-admin-ui.css`:

```css
/* Universal field validation warning */
.tpw-frontend-ui :where(.tpw-field--invalid) :is(input, select, textarea) {
	border-color: var(--tpw-color-warning);
	outline: 0;
	box-shadow: 0 0 0 2px color-mix(in srgb, var(--tpw-color-warning) 25%, transparent);
}

.tpw-frontend-ui :where(.tpw-field--invalid) .tpw-field-hint {
	color: var(--tpw-color-warning);
}
```

No markup or JavaScript changes are required—any field wrapper with `.tpw-field--invalid` will apply the styling automatically.

---

## Behavior & Edge Cases

| Scenario | Result |
|----------|--------|
| Option array missing | Function treats as empty; returns empty string (no `<style>` output). |
| Empty value saved | Skipped when `$only_if_not_empty = true`; fallback in static CSS applies. |
| Invalid (non-array) option | Cast to empty array, safely ignored. |
| Frontend usage before admin visit | `wp_head` hook still injects variables; no dependency on admin first. |

---

## Quick Reference: Retrieve & Use

```php
$val = get_option( 'tpw_core_branding', [] ); // Array of raw values
// To build CSS manually (rare):
$css = function_exists('tpw_core_build_branding_css') ? tpw_core_build_branding_css(true) : '';
```

In CSS:
```css
button.primary { background: var(--tpw-btn-primary); color: var(--tpw-btn-text-light); }
.alert-success { color: var(--tpw-color-success, #198754); }
```

---

## Diff Points When Implementing Semantic Colors (Checklist)

* [ ] Add defaults (`$defaults`) in `tpw_core_render_branding_tab()`.
* [ ] Extend `$map` in `tpw_core_build_branding_css()`.
* [ ] Extend save/reset whitelist arrays.
* [ ] Add form inputs to the Branding tab table for each new color.
* [ ] Update docs (`tpw-branding.md`, this file) with examples.
* [ ] Add CSS usage in notices / components.

---

## Related Files

* `includes/tpw-core-settings.php` – Generation & injection logic.
* `assets/css/tpw-buttons.css` – Baseline button system & default fallbacks.
* `assets/css/tpw-admin-ui.css` – Admin UI styling that consumes typography and heading tokens.
* `docs/help/tpw-branding.md` – User-facing explanation of branding tab (should be cross-referenced here after extensions).

---

## Notes

* Sanitization: Values are currently output verbatim; if adding more complex token types (e.g. gradients) consider stricter validation.
* Performance: Inline CSS is tiny; no concatenation step needed. Multiple tabs reuse the same `<style>` block.
* Scope: Using `:root` keeps variables available universally; heading variables additionally target `.tpw-admin-ui` & `.tpw-frontend-ui` for future sandboxing.

---

End of reference.

