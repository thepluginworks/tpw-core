=== TPW Core ===
Contributors: thepluginworks
Tags: rsvp, payments, event-management, golf, masonic
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.14.41
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

= 1.14.41 =
- Release delivery: TPW Core now publishes its canonical install package through GitHub Releases instead of the previous Freemius delivery path.
- Release delivery: tagged and manual release runs now build a filtered `tpw-core.zip`, preserve the correct `tpw-core/` archive root, and update the release asset in place when a release already exists.
- Release delivery: release automation now generates a public `tpw-core.json` version manifest and publishes it through GitHub Pages so TPW Core and companion plugins can detect updates from a stable URL.
- Maintenance: this release changes packaging and distribution only; plugin runtime, Freemius initialization, and admin UI behaviour are unchanged.

= 1.14.40 =
- Members: the Sign Ups debug screen is now hidden from normal admin menus while remaining available by direct link for authorised administrators when signup debug mode is enabled.
- Members: signup attempt records now exclude temporary debug and schema trace data, reducing stored noise while preserving the information needed for completion and support.
- Maintenance: TPW Core now loads translations early enough to avoid WordPress admin timing warnings and removes temporary debug logging across payments, menus, postcode lookups, CSV import tools, and TPW Control.
- Maintenance: postcode lookups now use the configured site default country when available, falling back to GB when no site preference has been set.
- Maintenance: Core now records a lightweight support warning for older member records that still use a legacy `society_id = 0` value, without rewriting historical data automatically.

= 1.14.38 =
- Freemius: corrected the TPW Core SDK configuration so the plugin is no longer marked as using paid plans or freemium access.
- Admin and licensing metadata now align with the current TPW Core distribution model, reducing the chance of misleading commercial prompts or plan state handling.

= 1.14.37 =
- Members: TPW Core now uses a single site-level society setting for new member and household records, standardising single-site installs on society ID 1.
- Members: signup finalization, manual member creation, and create-from-user flows now use the canonical site society value instead of mixed defaults or inferred member data.
- Members: household creation and related member resolution paths now resolve to the canonical positive site society value so new real entity rows no longer default to 0.
- Maintenance: existing legacy rows that already have `society_id = 0` are not rewritten in this release and may still need a follow-up migration.

= 1.14.36 =
- Members: Join finalization now keeps the intended society assignment from the signup flow when that information is available during completion.
- Members: new member records created from completed Join requests now avoid falling back to an unrelated default society when the signup already carries the correct society context.
- Members: Core still falls back safely when no society information is provided, preserving existing behaviour for simpler setups.

= 1.14.35 =
- Members: the Sign-Ups settings screen now keeps the baseline Join fields for email, first name, and surname visible but locked as Core-required fields.
- Members: administrators can no longer disable those fields from the public Join schema, reducing broken Join setups when TPW Subscriptions relies on Core Sign-Ups readiness.
- Members: Core now enforces the same protection server-side during settings saves, so crafted requests cannot turn off those baseline Join fields.

= 1.14.34 =
- Packaging: excluded `uninstall.php` from the generated release zip so Freemius package validation accepts the TPW Core deployment archive.
- Packaging: kept the existing `.distignore`-driven build flow unchanged so GitHub release assets and Freemius uploads still use the same filtered package.
- Maintenance: no TPW Core runtime, uninstall logic, or plugin behaviour changed in this release.

= 1.14.33 =
- Release delivery: replaced the custom Freemius upload client with an SDK-based deployment path that uses the official Freemius PHP SDK during tagged releases.
- Release delivery: tagged releases now download a pinned Freemius SDK version on the runner and use a small deployment helper that reports Freemius upload and release responses clearly.
- Maintenance: plugin packaging, GitHub release assets, and TPW Core runtime behaviour are unchanged in this release.

= 1.14.32 =
- Release delivery: aligned Freemius request signing with the official Freemius SDK rules for developer-key deployments, correcting the authorization format used for tagged release uploads.
- Release delivery: kept the existing upload diagnostics in place so failed Freemius publishing attempts remain visible in GitHub Actions with the returned API response.
- Maintenance: plugin packaging, GitHub release assets, and TPW Core runtime behaviour are unchanged in this release.

= 1.14.31 =
- Release delivery: corrected Freemius deployment to use the signed Freemius API tag upload flow instead of the rejected Basic Auth asset upload path.
- Release delivery: tagged releases now log the returned Freemius tag details and fail clearly if Freemius rejects either the upload or the follow-up release-state update.
- Maintenance: kept GitHub release packaging and plugin runtime behaviour unchanged while hardening the Freemius publishing path.

= 1.14.30 =
- Release delivery: updated Freemius uploads to use curl's built-in Basic Auth handling, avoiding malformed authorization headers during tagged release uploads.
- Release delivery: kept the existing upload diagnostics in place so GitHub Actions still shows the requested version, package path, HTTP status, response body size, and raw Freemius response when available.
- Maintenance: no plugin runtime, payment flow, or front-end behaviour changed in this release.

= 1.14.29 =
- Release delivery: expanded Freemius upload logging so tagged releases now show the package path, requested version, response body size, and the raw response returned by Freemius when available.
- Release delivery: added a clear fallback message when Freemius returns an empty response body, making failed uploads easier to diagnose from GitHub Actions logs.
- Maintenance: enabled shell trace output for the Freemius upload step to improve release debugging without changing plugin runtime behaviour.
- Maintenance: no plugin runtime, payment flow, or front-end behaviour changed in this release.

= 1.14.28 =
- Release delivery: hardened the Freemius deployment step so GitHub Actions now reports the HTTP status and response body returned by Freemius for each tagged release upload.
- Release delivery: Freemius upload attempts now fail the workflow when Freemius rejects the package, making release problems visible immediately instead of appearing as successful runs.
- Maintenance: updated the release workflow checkout action from `actions/checkout@v3` to `actions/checkout@v4`.
- Maintenance: no plugin runtime, payment flow, or front-end behaviour changed in this release.

= 1.14.27 =
- Packaging: release zips now stage from the existing `.distignore` rules so non-runtime docs, editor files, and other excluded metadata stay out of production packages.
- Packaging: GitHub release publishing now uploads the filtered plugin zip as a release asset, while tag pushes continue using the same filtered zip for Freemius deployment.
- Maintenance: removed the obsolete Composer install step from the release workflow now that TPW Core no longer ships a root Composer manifest.

= 1.14.26 =
- Maintenance: removed the remaining top-level Composer manifest and lockfile from TPW Core because Core no longer has any direct Composer-managed runtime dependencies.
- Maintenance: retired the last dead bundled Composer package set from Core so the plugin no longer relies on the top-level `vendor/` tree or Composer autoload artifacts.
- Maintenance: no business logic, payment flows, or Square add-on behaviour changed in this release.

= 1.14.25 =
- Maintenance: removed stale Composer vendor packages so the shipped `vendor/` directory now matches the current lockfile after Square SDK externalisation.
- Maintenance: rebuilt the production dependency bundle from the current Composer manifest, leaving only the required Guzzle and PSR/support packages in Core.
- Maintenance: no functional plugin behaviour or payment flows changed in this release.

= 1.14.24 =
- Payments: Core no longer ships the Square PHP SDK and now treats Square checkout ownership as an external TPW Square Gateway add-on concern.
- Payments: preserved the existing Square slug, settings surface, and legacy gateway class boundary while replacing the in-Core Square gateway implementation with an unavailable compatibility shim when the add-on is not active.
- Payments: front-end payment method discovery, payments page config, and checkout validation now keep Square unavailable when the add-on is inactive, preventing broken Square selection on forms.
- Payments: the Payment Methods admin UI now keeps stored Square configuration visible, prevents re-enabling Square while the add-on is inactive, and restores the requested active state when the add-on becomes available again.
- Maintenance: version bump to 1.14.24.

= 1.14.23 =
- Added a managed Join provider registry so the Core-owned Join page can keep using `[tpw_join_form]` while dispatching to the active Join experience.
- Updated `[tpw_join_form]` to switch safely between the built-in Core Join flow and registered external providers, with Core remaining the default and fallback.
- Added an Active Join Provider setting to the Members sign-up settings and stored the provider choice in the existing plugin settings.
- Hardened the managed Join page so it is not cached when an external Join provider is active, reducing stale Join content after provider changes.
- Maintenance: version bump to 1.14.23.

= 1.14.21 =
- Members: updated the Member Details modal flag-read path in `modules/members/includes/class-tpw-member-ajax.php` so legacy member flags are sourced through the compatibility helper boundary.
- Members: preserved the existing modal labels, ordering, formatting, field visibility, and Yes/No rendering behaviour.
- Members: added a same-member guard so the modal falls back to the original loaded member row unless the compatibility lookup resolves back to that exact member record.
- Maintenance: version bump to 1.14.21.

= 1.14.20 =
- Documentation: added `docs/architecture/identity/member-flag-ownership-model.md` as the formal Phase 2C Member Flag Ownership & Classification Model.
- Documentation: classified the current `tpw_members` member flags by ownership, system role, migration risk, and migration difficulty to freeze the safe migration boundary.
- Documentation: clarified that member flags are not canonical identity and that this release does not change runtime behaviour.
- Maintenance: version bump to 1.14.20.

= 1.14.19 =
- Identity: adopted the Phase 2A helper layer inside the read-only Identity Audit admin screen for the first narrow internal Core usage of `TPW_Identity` and `TPW_Identity_Compat`.
- Identity: migrated only the audit reporting paths for user linkage analysis, identity role projection, unknown role reporting, and drift reporting.
- Identity: preserved existing audit behaviour and report semantics, with no permission, authority, role, or admin-elevation changes.
- Maintenance: version bump to 1.14.19.

= 1.14.18 =
- Documentation: clarified that `tpw_members.is_admin` is a Core administrative elevation signal rather than an ordinary compatibility-era responsibility flag.
- Documentation: recorded that `is_admin` affects WordPress Administrator assignment and site-level authority, so migration work must preserve its semantics.
- Documentation: documented that any redesign of TPW-managed WordPress Administrator assignment requires an explicit architecture decision.
- Maintenance: version bump to 1.14.18.

= 1.14.17 =
- Identity: added the first Phase 2A helper-layer scaffolding with a new `TPW_Identity` helper for canonical member lookup, raw and normalized status access, linkage reporting, and canonical membership checks.
- Identity: added a new `TPW_Identity_Compat` helper for centralized legacy member-flag access plus compatibility checks for current WordPress role slugs and legacy identity aliases such as `tpw_member`.
- Identity: preserved the current weak-linkage compatibility path by resolving member records through direct `user_id` linkage first, then the existing email and username fallbacks when enabled.
- Maintenance: version bump to 1.14.17.

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
