# TPW Profile Badge Shortcode

The `tpw_profile_badge` shortcode renders a compact circular badge that links members to their profile, or guests to the front‑end login page. It is designed for use in site headers, page‑builder templates (Elementor, Divi, WPBakery, Gutenberg), and any widget or content area that accepts shortcodes.

## What It Does
When inserted as `[tpw_profile_badge]`, the shortcode outputs a 36px circular badge:

- Logged out users see a Login badge linking to the TPW Core System Page `member-login`.
- Logged in users see their member photo (if enabled & uploaded), else WordPress avatar, else initials derived from their display name or login.
- The badge links to the System Page `my-profile`.
- Nothing is rendered inside wp‑admin (suppressed to avoid layout noise in editors).

## Inserting the Shortcode
Basic usage:

```
[tpw_profile_badge]
```

Place it in any:
- Elementor Shortcode widget
- WPBakery text block
- Divi Code or Text module
- Gutenberg Shortcode block
- Classic editor content area

## Avatar → WP Avatar → Initials Fallback Logic
1. If Member Photos are enabled (`Use Photos of Members` setting) and the member has a stored `member_photo`, that image is shown.
2. Else, if `get_avatar_url()` returns a WordPress avatar for the user, that image is shown.
3. Else, the plugin generates up to two initials from the user’s display name (or login) and shows them on a colored background.

## Link Behaviour
- Logged in: links to the System Page key `my-profile`.
- Logged out: links to the System Page key `member-login`.
- Resolution uses the central System Pages registry (`TPW_Core_System_Pages::get()`), so overrides and custom page assignments are respected.

## Using in Elementor Header Templates
1. Edit your Header template.
2. Add a “Shortcode” widget where the badge should appear.
3. Enter `[tpw_profile_badge]`.
4. Save and preview while logged out and logged in to confirm both states.

No theme menu “location” is required; the badge renders independently from the member menu swap logic.

## Styling Classes
The output structure:
```html
<div class="tpw-profile-badge">
  <a href="/my-profile/" class="tpw-profile-badge__link">
    <img class="tpw-profile-avatar" src="…" alt="" />
    <!-- OR -->
    <span class="tpw-profile-initials">AB</span>
  </a>
</div>
```

Core CSS defines:
- `.tpw-profile-badge` – outer wrapper
- `.tpw-profile-badge__link` – interactive circular area (36px)
- `.tpw-profile-avatar` – image style (cover & rounded)
- `.tpw-profile-initials` – initials fallback circle

### Safe Overrides
Add overrides in your child theme or custom CSS file, e.g.:
```css
.tpw-profile-badge__link { background: var(--tpw-accent); }
.tpw-profile-initials { font-size: 14px; }
```
Avoid targeting generic selectors (e.g. `img` or `a`) to prevent conflicts. The badge does not reuse the legacy menu avatar classes (`.tpw-nav-avatar`, `.tpw-nav-initials`).

## Difference vs Menu-Based Profile Injection
| Aspect | Menu Injection | Profile Badge Shortcode |
|--------|----------------|-------------------------|
| Trigger | `wp_nav_menu` filter by location | Direct shortcode rendering |
| Placement | Only where theme/widget calls a menu | Anywhere shortcodes allowed |
| Ordering | Controlled by WP Menu editor | Controlled by page layout | 
| Dependencies | Theme menu location, swap logic | None (standalone) |
| Editing | Move via menu items | Move via page builder drag/drop |

## Caching & Visibility Notes
- Badge output is user‑specific but very small; full page caching solutions should be configured to bypass or vary cache for logged‑in users (standard WP practice). The shortcode itself sets no cache headers.
- Logged out state is static and safe to cache.
- The underlying profile and login pages are already protected by TPW Core’s system page and cache‑control logic.

## Related Documentation
- System Pages overview: see `docs/help/system-pages.md` (or the System Pages section in TPW Core settings) for how `my-profile` and `member-login` are registered and overridden.
- Member Photos configuration: Member Settings → General / Profile tabs.

## Accessibility
- The link has `aria-label="My Profile"` (logged in) or `aria-label="Member Login"` (logged out).
- Fallback initials include `aria-hidden="true"` while the link’s label conveys purpose.

## Version
Introduced in TPW Core 1.1.0.

## Dropdown Options
You can optionally enable a small profile dropdown with quick links. Usage:

```
[tpw_profile_badge dropdown="yes"]
```

### Behaviour
Desktop (hover-capable devices):
- Hovering the badge reveals the dropdown.
- Moving the pointer away hides it.
- Clicking "My Profile" or "Logout" performs navigation/logout.

Mobile / touch (devices matching `(hover: none)`):
- First tap on the badge opens the dropdown (link is not followed).
- Second tap on the badge when open follows the profile link (or tap outside/ESC closes it).
- Tap outside the badge closes the dropdown.

### Links Included
- My Profile → System Page `my-profile`
- Logout → `wp_logout_url( home_url('/') )` (same pattern used elsewhere in Core)

### Accessibility
- Badge link has `aria-haspopup="true"` and `aria-expanded` updated by JS on touch devices.
- Dropdown container uses `role="menu"`; items use `role="menuitem"` for assistive clarity.
- ESC key closes the dropdown on touch devices.

### Styling Classes (Dropdown)
- `.tpw-profile-badge__dropdown` – container panel
- `.tpw-profile-badge__item` – each link row

The dropdown is hidden by default and revealed:
- On desktop via CSS hover `.tpw-profile-badge:hover > .tpw-profile-badge__dropdown`
- On touch when `.tpw-profile-badge--open` is added by JS

### CSS Override Examples
Change dropdown background:
```css
.tpw-profile-badge__dropdown { background:#fffbe6; }
```
Adjust width:
```css
.tpw-profile-badge__dropdown { width:180px; }
```
Dark theme adaptation:
```css
body.dark .tpw-profile-badge__dropdown { background:#1f2937; color:#f9fafb; }
body.dark .tpw-profile-badge__item { color:#f9fafb; }
body.dark .tpw-profile-badge__item:hover { background:#374151; }
```

### Extensibility
Future versions can append more links or inject items via a filter (e.g. `tpw_profile_badge/items`). Current markup is structured to allow easy extension.

### Notes on Caching
- Dropdown markup is user-specific (only appears when logged in and attribute enabled). Standard WordPress practice of bypassing full-page cache for logged-in users applies.

