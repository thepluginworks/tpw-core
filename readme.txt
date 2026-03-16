=== TPW Core ===
Contributors: thepluginworks
Tags: rsvp, payments, event-management, golf, masonic
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.14.16
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

= 1.14.16 =
- Documentation: strengthened the identity architecture docs with Phase 2 migration guardrails for legacy member responsibility flags such as `is_committee`, `is_match_manager`, and `is_admin`.
- Documentation: clarified that these legacy responsibility flags are compatibility-era signals, must move behind Core compatibility helpers, and must not become broad cross-plugin permission shortcuts.
- Documentation: documented the privilege-escalation risk of reusing broad responsibility labels inconsistently across plugins during migration.
- Maintenance: version bump to 1.14.16.

= 1.14.15 =
- Identity: added a new read-only Identity Audit screen under TPW Core Settings as the first Phase 1 safety tooling for the identity and permissions roadmap.
- Identity: the audit reports user/member linkage, weak-linkage fallback matches, projected identity roles, unknown assigned roles, member status distribution, and drift indicators without modifying data.
- Documentation: updated the identity and permissions implementation roadmap to reference the new TPW Core Identity Audit tooling.
- Maintenance: version bump to 1.14.15.

= 1.14.14 =
- Documentation: added `docs/architecture/identity/role-classification-model.md` as the formal TPW Role Classification Model.
- Documentation: updated `docs/architecture/README.md` to link the new role classification reference alongside the existing identity architecture materials.
- Maintenance: version bump to 1.14.14.

= 1.14.13 =
- Documentation: clarified that TPW Core owns the lifecycle of projected identity roles in `docs/architecture/identity/identity-permissions-decisions.md`.
- Documentation: updated `docs/architecture/identity/identity-model.md` to cross-reference the identity projection lifecycle ownership rule.
- Maintenance: version bump to 1.14.13.

= 1.14.12 =
- Documentation: added `docs/architecture/identity/identity-permissions-decisions.md` as the formal identity and permissions decisions document.
- Documentation: added `docs/architecture/identity/identity-permissions-implementation-roadmap.md` as the phased implementation roadmap for safe ecosystem migration.
- Documentation: updated `docs/architecture/README.md` to link the identity model, decisions document, roadmap, and permissions architecture references more clearly.
- Maintenance: version bump to 1.14.12.

= 1.14.11 =
- Documentation: refined the Identity Architecture specification status wording to clarify that the current design direction remains subject to ecosystem audit and migration validation.
- Maintenance: version bump to 1.14.11.

= 1.14.10 =
- Documentation: added the first formal TPW Identity Architecture specification under `docs/architecture/identity/identity-model.md`.
- Documentation: clarified the canonical separation between identity and permissions in the architecture overview.
- Maintenance: version bump to 1.14.10.

= 1.14.9 =
- Documentation: introduced `docs/architecture/` as the new home for TPW platform architecture documentation.
- Documentation: separated the architecture structure into dedicated `identity` and `permissions` domains.
- Documentation: moved the existing permissions documentation into `docs/architecture/permissions/` and updated repository documentation links.
- Maintenance: version bump to 1.14.9.

= 1.14.8 =
- Members: added a complete Join finalization flow so eligible signup attempts can be turned into live WordPress users and TPW member records.
- Members: added an internal completion bridge for draft signup attempts, with clear non-gateway audit tracking in the attempt payload and event log.
- Members: added a temporary Sign Ups (Debug) admin screen so administrators can trigger internal completion from WordPress without manual admin-post testing.
- Members: finalization continues to persist created account references early and leaves failed runs in a recoverable finalization-failed state.
- Maintenance: version bump to 1.14.8.

= 1.14.6 =
- Members: added Branch 3 of the Join flow with automatic Join page provisioning and the public `[tpw_join_form]` shortcode.
- Members: added schema-driven Join form rendering with field validation, sticky values, and a clearer public confirmation message with a shortened reference code.
- Members: successful Join submissions now create a signup attempt only; no TPW member record or WordPress user is created at this stage.
- Members: Sign-Ups settings now show only public-safe fields, apply better default field handling, and support drag-and-drop field ordering with improved initial grouping.
- Maintenance: version bump to 1.14.6.

= 1.14.5 =
- Members: added the Core signup schema layer for standard and custom member fields, including signup-safe, enabled, required, section, and ordering settings.
- Members: added fixed Core signup sections for Account Details, Personal Details, Address, and Emergency Contact.
- Members: added a Sign-Ups tab in Member Settings with sign-up enablement, sign-up page selection, and field configuration controls.
- Maintenance: version bump to 1.14.5.

= 1.14.4 =
- Members: added the Core signup attempts engine foundation for future payment-first sign-up flows.
- Members: new installs and upgraded sites now provision the signup attempts table and lifecycle service bootstrap automatically.
- Members: sign-up attempts now support strict status transitions, payment state tracking, finalization locking, cleanup, and event logging for future TPW onboarding features.
- Maintenance: version bump to 1.14.4.

= 1.14.3 =
- Documentation: added the TPW Core Sign-Up System design specification covering payment-first onboarding, recoverable signup attempts, plugin-defined sections and repeatable groups, admin recovery tools, and post-payment account creation.
- Documentation: established the agreed generic Core lifecycle model for future sign-up flows used by FlexiSubscriptions and other TPW plugins.
- Maintenance: version bump to 1.14.3.

= 1.14.2 =
- Payments: the FlexiEvent Payment Methods submenu now opens the current TPW Core Settings Payment Methods tab directly.
- Payments: removed the obsolete legacy Payment Methods compatibility route now that current TPW plugins use the shared settings destination.
- Maintenance: version bump to 1.14.2.

= 1.14.1 =
- Payments: restored TPW Core Payments currency settings persistence in FlexiEvent settings by allowing `currency_symbol` and `currency_code` through the FlexiEvent allowed-keys filter.
- Maintenance: version bump to 1.14.1.

= 1.14.0 =
- Members: added a new core boolean member field `is_manage_members` with the label `Members Manager`.
- Members: access to the front-end members management UI and related capability checks now allows WordPress admins, TPW members with `is_admin = 1`, or TPW members with `is_manage_members = 1`.
- Members: `is_manage_members` now follows the same Core checkbox-style handling pattern as other permission-style member flags across schema upgrade, field settings, profile protections, and add/edit forms.
- Members: protected permission fields `is_admin` and `is_manage_members` are now visible but read-only for non-administrator managers, with server-side enforcement to block privilege escalation through form tampering or AJAX requests.
- Maintenance: version bump to 1.14.0.

= 1.13.3 =
- Members: added a new core boolean member field `is_gallery_admin` with the label `Gallery Admin`.
- Members: `is_gallery_admin` now follows the same Core checkbox-style handling pattern as `is_noticeboard_admin` across schema upgrade, field settings, profile protections, add/edit forms, and the member details modal.
- Gallery: the active front-end gallery admin shortcode and gallery management AJAX/template paths now allow only WordPress admins with `manage_options`, TPW members with `is_admin = 1`, or TPW members with `is_gallery_admin = 1`.
- Gallery: gallery admin access now resolves through a shared helper so page rendering and action handlers stay aligned.
- Maintenance: version bump to 1.13.3.

= 1.13.2 =
- Members: added `TPW_Member_Field_Loader::get_condition_eligible_custom_fields()` to return enabled custom checkbox fields that are also allowed for conditional field logic.
- Members: the loader now exposes sanitized key, label, and type metadata for condition-eligible custom fields so front-end consumers can build conditional UI from a single source.
- Maintenance: version bump to 1.13.2.

= 1.13.1 =
- Notices: active front-end noticeboard management now allows TPW noticeboard admins via `TPW_Control_UI::is_noticeboard_admin()` while preserving existing WordPress admin access.
- Notices: the active notices shortcode render path and AJAX management actions now share the same permission check so controls and endpoints stay aligned.
- Maintenance: version bump to 1.13.1.

= 1.13.0 =
- Email: added a persistent dispatcher log table recording timestamp, recipient, subject, context, status, error details, and send duration.
- Email: added an Email Logs tab in TPW Core Settings showing the latest 100 log entries with a clear-logs action.
- Email: dispatcher logging now captures real `wp_mail()` failures and applies automatic 30-day retention cleanup.
- Docs: documented central email logging, optional context usage, retention behaviour, and admin viewing guidance.
- Maintenance: version bump to 1.13.0.

= 1.12.0 =
- Members: My Profile tabs now render from a pluggable section registry, allowing TPW add-ons to register their own tabs with `tpw_core_register_profile_sections`.
- Members: built-in Profile and Payments sections now share the same normalized tab registry and priority-based rendering flow.
- Email: added `TPW_Email::dispatch_mail()` as the shared outbound dispatcher with throttling-aware slot reservation and centralized logging support.
- Email: feedback and member notification emails now route through the shared dispatcher when available.
- Docs: documented the My Profile tab extension contract and add-on integration guidance.
- Maintenance: version bump to 1.12.0.

= 1.11.2 =
- Scheduler: added `TPW_Core_Scheduler::get_wrapper_diagnostics()` for support/debugging insight into the loaded wrapper, detected Action Scheduler source/version, and pre-filter registration state.
- Scheduler: `schedule_single()` now records branch-specific debug metadata, raw scheduler return values, and optional admin-only debug log events around scheduling calls.
- Scheduler: short-circuited `pre_as_schedule_single_action` responses now retain explicit success/failure diagnostics.
- Maintenance: version bump to 1.11.2.

= 1.11.1 =
- Scheduler: `TPW_Core_Scheduler::schedule_single()` now records richer debug context for failed and successful scheduling attempts.
- Scheduler: added request-scoped diagnostics accessors via `TPW_Core_Scheduler::get_last_error()`, `get_last_schedule_debug()`, and `get_schedule_debug_history()`.
- Scheduler: unique single actions now fail early when the same hook, args, and group are already scheduled instead of silently re-requesting the same job.
- Maintenance: version bump to 1.11.1.

= 1.11.0 =
- Members: added a new core boolean member field `is_volunteer` with the label `Volunteer`.
- Members: `is_volunteer` now follows the same Core handling pattern as `is_committee` and `is_noticeboard_admin` across field settings, add/edit forms, Member Details modal, profile protections, and checkbox-based directory search/filtering.
- Members: new installs and upgraded sites now ensure the `tpw_members.is_volunteer` column exists with a default value of `0`.
- Maintenance: version bump to 1.11.0.

= 1.10.0 =
- Gallery: added a same-page gallery browser via the `tpw_gallery_index` shortcode and a dedicated Gallery Index Elementor widget.
- Gallery: clicking a gallery card now hides the index and renders only the selected gallery with a Back to Galleries action on the same page.
- Gallery: public gallery index pages now enqueue `tpw-buttons.css` so TPW button styles are available for embedded browser controls.
- Maintenance: version bump to 1.10.0.

= 1.9.5 =
- Admin UI: scope WP admin tabs styling to `wp-core-ui`-scoped TPW screens.
- Maintenance: version bump to 1.9.5.

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
- Docs: added permissions documentation under `docs/architecture/permissions/`.
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
