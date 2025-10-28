# TPW Branding: Use across other plugins

This guide shows how to consume the TPW Core Branding and UI Theme settings from your own plugins or wp-admin pages. It covers which assets to enqueue, what wrapper/classes to use, and the CSS variables the Branding tab emits.

Applies to: wp-admin and front-end pages.

---

## What the Branding tab configures

The Branding tab at: Settings → TPW Core → Branding (`/wp-admin/options-general.php?page=tpw-core-settings&tab=branding`) lets admins set:

- Button system tokens used by `.tpw-btn` variants
  - `--tpw-btn-primary`, `--tpw-btn-secondary`, `--tpw-btn-danger`, `--tpw-btn-light`, `--tpw-btn-dark`
  - Text colors: `--tpw-btn-text-light`, `--tpw-btn-text-dark`
  - Shape/size: `--tpw-btn-radius`, `--tpw-btn-padding`, `--tpw-btn-font-size`, `--tpw-btn-height`
  - Typography: `--tpw-btn-font-family`, `--tpw-btn-font-weight`
  - Action color: `--tpw-action-edit`
- UI Theme tokens for admin-like pages
  - Font family/weight and text transform/letter spacing/shadow
  - Accent color and default button bg/text used in scoped UIs
- Heading tokens for h1–h6 (optional overrides)

These settings are output automatically as CSS Custom Properties in two places:
- Admin layouts: in `<head>` via a `<style id="tpw-core-branding-vars">` and `<style id="tpw-core-heading-vars">`
- Front-end: same style tags in the site `<head>`

That means other plugins only need to enqueue the relevant CSS and use the classes below to benefit from saved settings.

---

## Enqueue: which stylesheets to use

Use these handles/paths exposed by TPW Core.

1) Global TPW button system (admin + front-end)
- Handle: `tpw-buttons`
- File: `assets/css/tpw-buttons.css`
- Purpose: provides `.tpw-btn` base + variants and consumes Branding tokens
- Example (admin or front-end):
```php
$base = defined('TPW_CORE_URL') ? TPW_CORE_URL : plugin_dir_url( __FILE__ ) . '../tpw-core/';
wp_enqueue_style( 'tpw-buttons', trailingslashit( $base ) . 'assets/css/tpw-buttons.css', [], null );
```

2) Scoped admin UI (wp-admin pages you render)
- Handle: `tpw-admin-ui`
- File: `assets/css/tpw-admin-ui.css`
- Purpose: resets and styles for TPW admin-like layouts under `.tpw-admin-ui`
- Example:
```php
$base = TPW_CORE_URL; // when TPW Core is active
wp_enqueue_style( 'tpw-admin-ui', trailingslashit( $base ) . 'assets/css/tpw-admin-ui.css', [], null );
```

3) Optional general admin styling helpers (used by Core pages)
- Handle: `tpw-core-admin-css`
- File: `assets/css/admin-style.css`
- Purpose: small admin complements (buttons, forms) when body has `tpw-origin`/`tpw-fe-embed`
- Typical use: only if you also add those admin body classes (see below). For most addon pages inside `.tpw-admin-ui`, stick to #1 and #2.

4) Optional admin tabs
- File: `assets/css/tpw-admin-tabs.css` (no core handle registered globally)
- Purpose: the minimal `.tpw-tabs` pattern; often unnecessary if you use WP’s `nav-tab` UI. If you need it, enqueue manually from your plugin.

Note: On many TPW admin screens, TPW Core already enqueues #2 automatically. If you control a different plugin’s settings page, enqueue `tpw-admin-ui` yourself.

---

## Wrappers and classes to apply

- For wp-admin pages you build: wrap your page content in `.tpw-admin-ui` to get the scoped UI theme + resets.
```php
echo '<div class="tpw-admin-ui" style="' . esc_attr( function_exists('tpw_core_build_ui_theme_style_attr') ? tpw_core_build_ui_theme_style_attr() : '' ) . '">';
echo '  <div class="wrap">';
// ... your markup in here ...
echo '  </div>';
echo '</div>';
```
  - The inline `style` injects the current UI Theme tokens, ensuring they’re present even if your page loads very early.

- For buttons in either admin or front-end, use the TPW button classes:
  - Base: `tpw-btn`
  - Variants: `tpw-btn-primary`, `tpw-btn-secondary`, `tpw-btn-danger`, `tpw-btn-light`, `tpw-btn-dark`, `tpw-btn-outline`, `tpw-btn-edit`
  - Sizes: `tpw-btn-sm` or `.small`
  - Block: `tpw-btn-block`
  - Example:
```html
<a class="tpw-btn tpw-btn-primary" href="#">Save changes</a>
<button type="submit" class="tpw-btn tpw-btn-secondary">Cancel</button>
```
 
### Tokens the Branding tab controls

- Optional admin body classes (wp-admin only): if your screen should inherit Core’s admin tweaks, add body classes `tpw-origin tpw-fe-embed`. Core does this automatically on its own pages via `admin_body_class` filter, but external plugins can mimic it when appropriate.

- Developer Guide → ../developer-guide.md
- Admin helpers: includes/admin-functions.php
- Buttons CSS: assets/css/tpw-buttons.css
- Admin UI CSS: assets/css/tpw-admin-ui.css
---

## Consuming tokens in your own CSS

The Branding tab emits variables to `:root`, so your CSS can use them directly:
```css
.my-custom-chip { border-radius: var(--tpw-btn-radius); }
.my-primary-bg { background: var(--tpw-btn-primary); color: var(--tpw-btn-text-light); }
.my-accent { color: var(--tpw-accent-color); }
/* Heading tokens (if set) */
.my-panel h2 { font-size: var(--tpw-h2-size, 1.5rem); font-weight: var(--tpw-h2-weight, 600); }
```
When you wrap in `.tpw-admin-ui`, additional scoped tokens exist for inputs and buttons (see `assets/css/tpw-admin-ui.css`).

---

## Helper functions you can call

Available in `includes/admin-functions.php` and `includes/tpw-core-settings.php`:

- `tpw_core_build_ui_theme_style_attr(): string`
  - Returns a `style` attribute value containing the current UI Theme tokens for `.tpw-admin-ui`.
  - Use it on your wrapper as shown above.

- `tpw_core_output_header( $title, $notice_message = '', $args = [] )`
  - Renders a consistent header block used by TPW admin pages. Accepts `icon_url` and `logo_url` via `$args`.
  - Place it inside your `.tpw-admin-ui .wrap` before your content.

- `tpw_core_build_branding_css( $only_if_not_empty = true ): string`
  - Returns a CSS string of `:root{...}` variables from Branding + UI Theme. Core already inserts this into both `admin_head` and `wp_head`. Call it yourself only for custom injection scenarios.

- `tpw_core_build_heading_css( $only_if_not_empty = true ): string`
  - Returns h1–h6 tokens. Also injected automatically by Core into heads.

- Front-end detection/auto enqueue
  - Core enqueues `tpw-buttons` automatically on selected front-end routes/shortcodes. If your plugin needs TPW buttons on the front-end elsewhere, explicitly enqueue `tpw-buttons`.

---

## Minimal recipes

1) Admin settings page (in another plugin) with TPW look-and-feel
```php
add_action( 'admin_menu', function(){
    add_submenu_page(
        'options-general.php',
        'My Addon',
        'My Addon',
        'manage_options',
        'my-addon-settings',
        function(){
            // Enqueue scoped UI
            if ( defined('TPW_CORE_URL') ) {
                wp_enqueue_style( 'tpw-admin-ui', TPW_CORE_URL . 'assets/css/tpw-admin-ui.css', [], null );
            }
            echo '<div class="tpw-admin-ui" style="' . esc_attr( function_exists('tpw_core_build_ui_theme_style_attr') ? tpw_core_build_ui_theme_style_attr() : '' ) . '">';
            echo '<div class="wrap">';
            if ( function_exists('tpw_core_output_header') ) tpw_core_output_header('My Addon');
            echo '<p><a class="tpw-btn tpw-btn-primary" href="#">Do thing</a></p>';
            echo '</div></div>';
        }
    );
});
```

2) Front-end shortcode that uses TPW buttons
```php
add_action( 'wp_enqueue_scripts', function(){
    if ( defined('TPW_CORE_URL') ) {
        wp_enqueue_style( 'tpw-buttons', TPW_CORE_URL . 'assets/css/tpw-buttons.css', [], null );
    }
});

add_shortcode( 'my_action_link', function(){
    return '<a class="tpw-btn tpw-btn-primary" href="#">Action</a>';
});
```

---

## Notes and conventions

- You do not need to re-emit tokens: Core prints them in heads on both admin and front-end. You only need to load the CSS that uses them.
- If Elementor or themes override typography, the `.tpw-admin-ui` scope neutralizes most globals using `@layer` + `all: revert-layer`. Keep your admin markup inside that wrapper.
- For WordPress native button elements (`<button>`, `<input type=submit>`), also add `tpw-btn` + variant classes to get consistent styling.
- The `tpw-admin-tabs.css` utilities are optional; prefer WP’s `nav-tab` when in wp-admin.

If you need more examples, check usages in:
- `modules/payments/views/payment-settings-page.php` for `.tpw-admin-ui`
- `assets/css/tpw-buttons.css` for available button variants

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
