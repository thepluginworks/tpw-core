=== TPW Core ===
Contributors: thepluginworks
Tags: rsvp, payments, event-management, golf, masonic
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.9.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

TPW Core provides shared RSVP, guest, menu, and payment logic for all TPW plugins.

== Description ==

TPW Core is a foundational plugin that powers the RSVP and payment features for all TPW modules including:
- Lodge Meetings
- Ladies Festival
- Christmas Party
- Golf Fixtures

It manages:
- RSVP submissions and guests
- Member and guest payments
- Menu selection and preferences
- Shared cost handling and checkout logic

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/tpw-core/`.
2. Activate the plugin via the Plugins menu in WordPress.
3. Configure core settings under “TPW Core” in the admin menu.

== Shortcodes ==

= [tpw_thank_you] =
Embed this on a Thank You page to display RSVP confirmation and payment details.
Supports optional query string `submission_id`.

Example URL:
```
/rsvp-thank-you/?submission_id=123
```

= [tpw_gallery_help] =
Front-end help page for the Gallery module. Creates an accessible overview of gallery features, including image reordering, caption editing, focal points, and category management.

Example route:
```
/gallery-help/
```

== Frequently Asked Questions ==

= Do I need this plugin for other TPW modules to work? =
Yes. All RSVP and payment logic is centralized in TPW Core.

= Can I customize payment methods? =
Yes. You can enable and configure methods like Bank Transfer and Cheque under TPW Core settings.

== Changelog ==

= 1.9.4 =
- Admin UI: added single helper to detect TPW wp-admin requests and gate UI-only styling.
- Admin UI: `tpw-fe-embed` body class is now opt-in via filter (defaults to off).
- Branding: front-end branding/heading CSS vars only output when TPW styles are enqueued.
- Maintenance: version bump to 1.9.4.

= 1.9.3 =
- Fix: preserve full redirect destinations (including nested query args) through front-end password reset emails and post-reset redirects.
- Fix: preserve redirect destination after failed login attempts so subsequent tries still land on the intended page.
- Security: validate redirect targets before redirecting.

= 1.9.2 =
- Fix: eliminated WP 6.7+ early textdomain JIT notices by ensuring tpw-core translations are not invoked before init.
- Maintenance: version bump to 1.9.2.

= 1.9.1 =
- Admin: Core Settings now uses the standard TPW branded header strip.
- Admin: removed notice relocation JS and normalized notice handling.
- Admin: removed Settings API notice usage for Core Settings (no add_settings_error/settings_errors plumbing).
- Admin: eliminated flicker and duplicate notices on Core Settings.

= 1.9.0 =
- Admin: TPW Core Settings now uses the standard TPW header strip (icon, title/subtitle, and TPW logo).
- Admin: added missing TPW Core icon asset for the header strip to prevent a broken image.
- Maintenance: version bump to 1.9.0.

= 1.8.9 =
- Menus: introduced the official logout placeholder URL `/?tpw_action=logout` for menu Custom Links.
- Menus: placeholder is rewritten at render-time into a fresh `wp_logout_url( home_url('/') )` so logout is immediate and no WordPress confirmation screen appears.
- Docs: documented the Logout URL Standard contract for admins and developers.
- Maintenance: version bump to 1.8.9.

= 1.8.8 =
- Members: removed the "Payment Methods" panel from the member-facing My Profile → My Payments hub.
- Maintenance: version bump to 1.8.8.

= 1.8.7 =
- UI: removed `all: revert-layer` from the scoped Admin UI CSS to avoid impacting other plugins/themes.
- Maintenance: version bump to 1.8.7.

= 1.8.6 =
- UI: tidied Member Profile / My Payments navigation hierarchy (Tier-1 tabs + Tier-2 sidebar) and payments hub layout polish (no behaviour change).
- Maintenance: version bump to 1.8.6.

= 1.8.5 =
- UI: added `.tpw-btn-warning` variant to the global button system.
- Docs: added permissions documentation under `docs/permissions/`.
- Maintenance: version bump to 1.8.5.

= 1.8.4 =
- Permissions: added `tpw_core_user_can()` bridge helper (additive only; no behaviour change).
- Maintenance: version bump to 1.8.4.

= 1.8.3 =
- Maintenance: version bump to 1.8.3.

= 1.8.2 =
- Members Admin: Export CSV now exports all enabled fields (including custom fields) when no Download fields are selected.
- Members Admin: When Download fields are selected, Export CSV now includes only those selected fields that are enabled.

= 1.8.1 =
- Members Admin: Export CSV no longer downloads blank output when no Download fields have been configured.

= 1.8.0 =
- Settings: “Payment Methods” is now a tab in TPW Core Settings.
- Settings: added extensibility hooks for tab content (`tpw_core_settings_tab_content` and `tpw_core_settings_tab_content_{tab}`).
- Payments: added helper `tpw_core_get_payment_methods_settings_url()`; legacy Payment Methods page/menu redirects to the Settings tab.

= 1.7.2 =
- Members Admin: Add Member form now validates required fields inline (username, first name, surname, email).
- Members Admin: Edit Member no longer blocks saving when imported records have an empty member username.
- Members Admin: When member username is blank but a WP user is linked, the WP username is displayed read-only (display only).

= 1.7.1 =
- Members: added a new setting (default off) to optionally show adult family members on the primary member profile.
- Members: dependants/children are never displayed to other members (hard privacy rule).
- Members Directory: member-facing directory list and detail modal only allow primary members.
- Members Admin: improved Household UI on Edit Member (household members list, clearer change controls, safer defaults and validation).

= 1.7.0 =
- Scheduler: vendored Action Scheduler is now available via a single Core manager with safe duplicate-load detection.
- Scheduler API: added `TPW_Core_Scheduler` wrapper methods for single/recurring scheduling, unscheduling, and basic queries.
- Stability: avoids fatals when WooCommerce (or another plugin) already loads Action Scheduler.

= 1.6.0 =
- Members: optional Household support (default off) with new tools on the Edit Member screen.
- Members: added Date of Birth (DOB) core field.
- Members: select fields can now use option lists via `field_options` (one option per line).
- Members DB: schema upgrades for DOB and household tables; internal members DB version bumped to 0.3.6.

= 1.5.2 =
- Gallery: public display can now hide the gallery heading (title/description) via `show_heading="0"` (shortcode + Elementor).
- Gallery Admin: caption editing uses a textarea modal suitable for long captions; card caption previews are clamped to prevent layout expansion.

= 1.5.1 =
- Notices: Noticeboard list shortcode thumbnail is now clickable (links to the notice).

= 1.5.0 =
- Gallery shortcode:
	- Added optional pagination for large galleries via `per_page` and `paginate` (grid/list views).
	- Cache key now varies by pagination query args to avoid serving the wrong page.
- Elementor (optional): added TPW Gallery widget with Grid/List/Story views (loads only when Elementor is active).
- Story view: improved navigation performance via neighbor image preloading.
- Docs: updated Gallery help topics and admin guide.

= 1.4.0 =
- Gallery module enhancements:
	- New front-end help page accessible at `/gallery-help/` (shortcode `[tpw_gallery_help]`).
	- Drag-and-drop image reordering in the Gallery admin interface.
	- Improved image editing UI with streamlined caption editor and focal point controls.
	- Optimized styles and scripts for faster loading and smoother interactions.
	- Expanded documentation covering Gallery Admin, image management, and categories.
	- Refactored templates for clearer structure and maintainability.

= 1.3.0 =
- Added Square SCA (Strong Customer Authentication) support in tpw-payments.js via card.tokenize(verificationDetails).
- Introduced configurable payment context using 'tpw_core/payments_page_config_localized' to allow RSVP and other plugins to inject SCA amount and billingContact.
- Implemented full WP_Error mapping inside Square gateway (TPW_Square_Gateway::process_payment), preventing fatal errors when Square declines.
- Added structured error metadata (require_new_nonce, category, codes, details) for upstream plugins to handle UI flow safely.
- Improved reliability of idempotency, error detection, and decline handling in Square payments.
- Added developer-friendly debug logging for sandbox SCA testing.

= 1.2.1 =
* Refactor: Features and Member Menu tabs now save independently via dedicated `admin_post` handlers to prevent Settings API overwrites of unrelated options.
* Fixed cross-tab resets where saving one tab reverted the other's settings.
* Added nonce & capability checks for new save actions; no schema changes.

= 1.2.0 =
* Implemented consistent branded header for payment settings pages.
* Ensured default courses are created automatically when a menu is inserted.
* Added rename warning script for course choice form to prevent accidental edits.
* Added `tpw_normalise_value()` and applied normalisation across menu classes.
* Ensured `wp_delete_user()` is available outside wp-admin context for member deletion.

= 1.1.0 =
* Documentation refresh across admin help and developer guides.
* Added Core Hooks Index and improved inline @since/@param annotations.
* No functional changes; internal version bumped for docs alignment.

= 1.0.0 =
* Initial stable public release.
* RSVP, guest, and payment table logic.
* Thank you page endpoint and shortcode.
* BACS and cheque method support.
* WooCommerce compatibility (HPOS declare).
