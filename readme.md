# TPW Core (v1.2.3)

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

## Works with other TPW plugins

TPW Core is a dependency of feature plugins such as FlexiEvent and FlexiGolf. Those plugins:

- Register their System Pages via Core’s page registry
- Use Core’s members/access checks to gate front‑end routes
- Hook into Core’s email/template system for consistent messages
- Use Core’s payment methods and logger, or add new gateways by following the same patterns

If you build new TPW add‑ons, depend on this plugin and use the extension points below.

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

### `[tpw_logout_link]`

Renders a logout link that redirects the user to the homepage (useful when the WP admin bar is hidden for members):

```plaintext
[tpw_logout_link]
```

Custom link text:

```plaintext
[tpw_logout_link]Sign out[/tpw_logout_link]
```

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