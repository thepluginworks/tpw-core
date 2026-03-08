# TPW Core (v1.10.0)

TPW Core provides shared building blocks for TPW plugins (e.g., FlexiEvent, FlexiGolf, RSVP-based add‑ons). It centralizes members, payments, branding, system pages, and common utilities so that dependent plugins remain small and consistent.

## Help Topics
Browse all module documentation → [docs/help/README.md](docs/help/README.md)

## What it does (at a glance)

- Members and Access Control: shared helpers, profile and login pages, status/flags checks
- Payments: unified payment tables, logger, settings (BACS/Cheque/Cash, SumUp, WooCommerce hooks)
- Branding and UI Theme: global button system and admin UI tokens used across TPW screens
- System Pages: registry for required front‑end pages (My Profile, TPW Control, Member Login, etc.)
- Email: reusable email settings, templates, and sending helpers
- Postcodes: AJAX postcode lookup utilities with multiple providers
- TPW Control: front‑end admin hub that other plugins extend via hooks

### Payments updates (1.8.0)
- Payment Methods management is now a tab in TPW Core Settings.
- Settings tabs can now be extended via `tpw_core_settings_tab_content` and `tpw_core_settings_tab_content_{tab}`.
- Added `tpw_core_get_payment_methods_settings_url()` and redirected the legacy Payment Methods page/menu to the new tab.

### Gallery updates (1.5.0)
- Public shortcode: optional pagination for large galleries (`per_page` / `paginate`) in `grid` and `list` views.
- Elementor: TPW Gallery widget (Grid/List/Story) when Elementor is installed and active.
- Story view: smoother navigation via neighbor preloading and improved image decoding.
- Docs: updated Gallery help topics and admin guide.

### Notices update (1.5.1)
- Noticeboard list: notice thumbnail image is now clickable (links to the notice).

### Gallery updates (1.5.2)
- Public shortcode + Elementor: added `show_heading` option to hide the gallery title/description above images (useful when page already has a heading).
- Gallery Admin: caption editing now uses a single textarea modal (auto-growing) suitable for long narrative captions, with clamped previews so cards don’t expand.

### Members updates (1.6.0)
- Optional Household support (default off) with new tools on the Edit Member screen to create households and attach/move members.
- Added Date of Birth (DOB) core field.
- Select fields can now use option lists via `field_options` (one option per line).

### Scheduler updates (1.7.0)
- Core now provides an ecosystem-wide scheduling engine via Action Scheduler, with safe detection/avoidance of duplicate loads.
- Other TPW plugins should call `TPW_Core_Scheduler::init_if_needed()` early (e.g. on `plugins_loaded`) and use the wrapper methods (single/recurring/unschedule/query).

### Members updates (1.7.2)
- Members Admin: Add Member now validates required fields inline (username, first name, surname, email).
- Members Admin: Edit Member can be saved even when imported records have a blank member username (no longer blocked by JS).

### Members updates (1.7.1)
- New setting (default off) to optionally show adult family members on the primary member profile.
- Member-facing directory and details now only allow primary members; dependants/children are never displayed to other members.
- Admin Edit Member: improved Household UI with a household members list and safer change controls.

## Works with other TPW plugins

TPW Core is a dependency of feature plugins such as FlexiEvent and FlexiGolf. Those plugins:

- Register their System Pages via Core’s page registry
- Use Core’s members/access checks to gate front‑end routes
- Hook into Core’s email/template system for consistent messages
- Use Core’s payment methods and logger, or add new gateways by following the same patterns

If you build new TPW add‑ons, depend on this plugin and use the extension points below.

### Gallery updates (1.10.0)
- Added a same-page gallery browser via the `tpw_gallery_index` shortcode and a dedicated Gallery Index Elementor widget.
- Clicking a gallery card now hides the index and renders only the selected gallery with a Back to Galleries action on the same page.
- Gallery index pages now enqueue the shared TPW button stylesheet so embedded browser controls render correctly.

### Maintenance updates (1.9.5)
- Admin UI: scope WP admin tabs styling to `wp-core-ui`-scoped TPW screens.
- Maintenance: version bump to 1.9.5.

### Maintenance updates (1.9.4)
- Admin UI: added single helper to detect TPW wp-admin requests and gate UI-only styling.
- Admin UI: `tpw-fe-embed` body class is now opt-in via filter (defaults to off).
- Branding: front-end branding/heading CSS vars only output when TPW styles are enqueued.
- Maintenance: version bump to 1.9.4.

### Members / Login updates (1.9.3)
- Fix: preserve full `redirect_to` destinations (including nested query args) through front-end password reset emails and post-reset redirects.
- Fix: preserve `redirect_to` after failed login attempts so subsequent tries still land on the intended page.

### Admin / Settings updates (1.9.2)
- Fix: eliminated WP 6.7+ early textdomain JIT notices by ensuring tpw-core translations are not invoked before init.
- Maintenance: version bump to 1.9.2.

### Admin / Settings updates (1.9.1)
- Core Settings: normalized notice handling (no Settings API notice plumbing), removed notice relocation JS, and eliminated flicker/duplicate notices.
- Maintenance: version bump to 1.9.1.

### Admin / Settings updates (1.9.0)
- Core Settings: now uses the standard TPW admin header strip (icon + title/subtitle, TPW logo).
- Maintenance: version bump to 1.9.0.

### Menus / Logout updates (1.8.9)
- Menus: introduced the official logout placeholder URL `/?tpw_action=logout` for menu Custom Links.
- Menus: placeholder is rewritten at render-time into a fresh `wp_logout_url( home_url('/') )` so logout is immediate and no WordPress confirmation screen appears.
- Docs: documented the Logout URL Standard contract for admins and developers.
- Maintenance: version bump to 1.8.9.

### Members updates (1.8.8)
- My Profile: removed the “Payment Methods” panel from the member-facing My Payments hub.
- Maintenance: version bump to 1.8.8.

### UI updates (1.8.7)
- UI: removed `all: revert-layer` from the scoped Admin UI CSS to avoid impacting other plugins/themes.
- Maintenance: version bump to 1.8.7.

### UI updates (1.8.6)
- UI: tidied Member Profile / My Payments navigation hierarchy (Tier-1 tabs + Tier-2 sidebar) and payments hub layout polish (no behaviour change).
- Maintenance: version bump to 1.8.6.

### UI and Permissions updates (1.8.5)
- UI: added `.tpw-btn-warning` variant to the global button system.
- Docs: added permissions documentation under `docs/permissions/`.
- Maintenance: version bump to 1.8.5.

### Permissions updates (1.8.4)
- Permissions: added `tpw_core_user_can()` bridge helper (additive only; no behaviour change).
- Maintenance: version bump to 1.8.4.

### Members updates (1.8.3)
- Maintenance: version bump to 1.8.3.

### Members updates (1.8.2)
- Members Admin: Export CSV now exports all enabled fields (including custom fields) when no Download fields are selected.
- Members Admin: When Download fields are selected, Export CSV now includes only those selected fields that are enabled.

### Members updates (1.8.1)
- Members Admin: Export CSV no longer downloads blank output when no Download fields have been configured.

## Key components

- Members: profile page, login URL resolution, access helpers, admin forms extendable via actions
- Payments: create and record payments; optional surcharges; logger table and admin viewer
- Branding and UI: `.tpw-btn` button system and `.tpw-admin-ui` scoped styles driven by saved tokens
- System Pages: register/ensure/get front‑end pages required by modules
- Email: settings, template registry/manager, reusable form and send wrapper
- Postcodes: client + server helpers and a small JS library
- TPW Control: front‑end admin hub ([docs/help/admin-guide-tpw-control.md](docs/help/admin-guide-tpw-control.md))

## Developer entry points (hooks)

Common actions/filters used by add‑ons (see inline docs for full signatures):

- Filter: `tpw_core/login_url` — Resolve the front‑end login URL (Core provides sane defaults)
- Filter: `tpw_member_login_redirect` — Adjust post‑login redirect destination
- Action: `tpw_members_admin_form_extra_fields` — Render extra fields in Members admin Add/Edit
- Action: `tpw_members_admin_form_after_save` — Persist extra fields after Core saves
- Actions: `tpw_members_admin_buttons_end`, `tpw_members_tools_buttons_end` — Add buttons to Manage Members
- Filter: `tpw_control/sections` — Register TPW Control sections

For examples, see `docs/developer-guide.md`.

## Extending Core (short examples)

Add a Members admin form field:

```php
add_action( 'tpw_members_admin_form_extra_fields', function( string $context, ?int $member_id, ?object $member, array $meta ) {
    echo '<p><label>Membership No. <input name="my_membership_no" type="text" /></label></p>';
}, 10, 4 );

add_action( 'tpw_members_admin_form_after_save', function( string $context, int $member_id ) {
    if ( isset($_POST['my_membership_no']) ) {
        update_user_meta( (int) get_current_user_id(), 'my_membership_no', sanitize_text_field( wp_unslash($_POST['my_membership_no']) ) );
    }
}, 10, 2 );
```

Register a System Page for your add‑on:

```php
TPW_Core_System_Pages::register_page( 'my-addon', [
    'title'     => 'My Add‑on',
    'shortcode' => '[my_addon]',
    'plugin'    => 'tpw-my-addon',
    'required'  => 1,
] );
TPW_Core_System_Pages::ensure_page( 'my-addon' );
```

## Shortcodes

### `[tpw_thank_you]`

Displays a Thank You message after RSVP completion. Works in both block and classic themes.

Usage:

```plaintext
[tpw_thank_you]
```

- Shows RSVP confirmation
- Displays the submission ID (when present in the URL)
- Outputs payment instructions (e.g., Bank Transfer)

Example route: `/rsvp-thank-you/?submission_id=123`

### `[tpw_gallery_help]`
Renders the Gallery Help page content explaining image management, categories, reordering, captions, and focal point adjustments.

Example route: `/gallery-help/`

### `[tpw_logout_link]`

Renders a logout link that redirects the user to the homepage (useful when the WP admin bar is hidden for members):

```plaintext
[tpw_logout_link]
```

Custom link text:

```plaintext
[tpw_logout_link]Sign out[/tpw_logout_link]
```

## Logout URL Standard

TPW Core does not support hardcoded WordPress logout URLs such as `wp-login.php?action=logout`.

WordPress logout URLs contain a security nonce. If a nonce-bearing logout URL is saved into a WordPress menu, that nonce will eventually expire. When it does, users will see the default WordPress confirmation screen (“Are you sure you want to log out?”) instead of being logged out immediately.

The official TPW Core logout URL contract is:

`/?tpw_action=logout`

When this URL is used in a WordPress menu:

- It is rewritten at render time into `wp_logout_url( home_url('/') )`
- A fresh nonce is generated per user/session
- Logout happens immediately
- The user is redirected to the homepage
- No confirmation screen appears

### Admin Instruction

To add Logout to a menu:

1. Go to Appearance → Menus
2. Add a Custom Link
3. URL: `/?tpw_action=logout`
4. Link text: Logout
5. Save

Admins must not paste `wp-login.php?action=logout` URLs into menus.

### `[tpw-control]`
### `[tpw_profile_badge]`

Standalone circular profile/login badge (40px). Shows member photo, real avatar, or initials. Optional dropdown:

```
[tpw_profile_badge]
[tpw_profile_badge dropdown="yes"]
```

Dropdown behaviour: hover opens on desktop; first tap opens on touch devices, second tap follows link or outside tap/ESC closes. Links: My Profile, Logout.


Front‑end admin hub (TPW Control). Create a page titled “TPW Control” with this shortcode to access Upload Pages, Menu Manager, and plugin‑provided tools via `/tpw-control/?action=`.

## Core settings (admin)

Found under Settings → TPW Core. Highlights:

- Default Login Page and Redirect After Login
- Branding and UI Theme tokens
- Email settings and template overrides
- System Pages registry viewer

## RSVP data

Core manages the following tables:

- `tpw_rsvp_submissions` — main RSVP data
- `tpw_rsvp_guests` — linked guests per RSVP
- `tpw_rsvp_payments` — recorded payments

Add‑ons should use Core functions/filters to submit/retrieve RSVP data and redirect to the shared thank‑you endpoint when finished.

## References

- Admin Help — TPW Control: `docs/help/admin-guide-tpw-control.md`
- Admin Help — TPW Core Settings: `docs/help/tpw-core-settings.md`
- Branding and UI tokens: `docs/help/tpw-branding.md`
- Developer Guide (hooks, examples): `docs/developer-guide.md`

## Developer Documentation

- Developer Guide (Core Hooks Index, extension patterns): `docs/developer-guide.md`
- Module Help topics (Overview/Usage/Hooks/Extending/References): `docs/help/README.md`