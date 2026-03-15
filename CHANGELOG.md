# Changelog

## [1.14.7] - 2026-03-15
### Added
- Members: added a signup finalization service that converts eligible `members_join` signup attempts into WordPress users and TPW member records.
- Members: added a schema-driven signup field mapper that splits allowed signup fields into WordPress user data, TPW member fields, and member meta values.

### Changed
- Members: finalization now persists created `wp_user_id` and `member_id` references into the signup attempt result payload as soon as they exist, so partial progress can be resumed safely.
- Members: failures after finalization begins now mark the attempt as `finalization_failed` with structured error context instead of leaving the attempt stranded.
- Members: finalized member accounts now apply the existing Core defaults for society resolution, member status, and member capability assignment.
- Maintenance: version bump to 1.14.7.

## [1.14.6] - 2026-03-15
### Added
- Members: added public Join page auto-provisioning and the public `[tpw_join_form]` shortcode for the Branch 3 sign-up flow.
- Members: added schema-driven Join form rendering with section-aware field output, validation, sticky values, and a public confirmation state with a shortened reference code.

### Changed
- Members: valid Join submissions now create and redirect to a recoverable signup attempt confirmation without creating a TPW member record or WordPress user in this branch.
- Members: Sign-Ups settings now expose only public-safe fields, remove the Signup Safe admin column, apply better default field enablement and ordering, and support drag-and-drop field ordering with improved initial grouped display.
- Maintenance: version bump to 1.14.6.

## [1.14.5] - 2026-03-15
### Added
- Members: added the Core signup schema layer for standard and custom member fields, including signup-safe, enabled, required, section, and ordering metadata.
- Members: added a fixed Core signup section registry covering Account Details, Personal Details, Address, and Emergency Contact.
- Members: added a Sign-Ups tab in Member Settings with sign-up enablement, sign-up page selection, and field configuration controls.

### Changed
- Members: existing field settings rows now carry sign-up configuration for both standard and custom fields while keeping the existing Members field system as the single source of truth.
- Members: added a normalized sign-up field schema read path for later branches without introducing public form rendering or lifecycle coupling.
- Maintenance: version bump to 1.14.5.

## [1.14.4] - 2026-03-14
### Added
- Members: added a new Core sign-up attempts table to store in-progress onboarding state, payment progress, retry data, lifecycle locks, and recovery timestamps before permanent account creation.
- Members: added a generic Core signup attempts service for creating, loading, updating, and tracking sign-up attempts across future TPW onboarding flows.

### Changed
- Members: enforced strict lifecycle transitions for draft, payment, finalization, completion, expiry, and abandonment states so sign-up processing stays predictable and resumable.
- Members: added generic helpers for public tokens, request fingerprints, UUID generation/validation, payment state updates, finalization locking, cleanup, and event logging.
- Members: wired the new signup attempts engine into the Core activation and upgrade bootstrap so the schema is created consistently on new and upgraded sites.
- Maintenance: version bump to 1.14.4.

## [1.14.3] - 2026-03-14
### Added
- Documentation: added the TPW Core Sign-Up System design specification as the single architecture reference for the new onboarding framework.

### Changed
- Documentation: defined the generic Core lifecycle model for payment-first onboarding, recoverable signup attempts, plugin-defined sections and repeatable groups, admin recovery tools, and post-payment account creation.
- Documentation: documented the extension contract used by FlexiSubscriptions and other TPW plugins, including plugin finalization callbacks and result-payload-based finalization references.
- Maintenance: version bump to 1.14.3.

## [1.14.2] - 2026-03-13
### Changed
- Payments: the FlexiEvent Payment Methods submenu now opens the current TPW Core Settings Payment Methods tab directly.
- Payments: removed the obsolete legacy Payment Methods compatibility route now that current TPW plugins use the shared settings destination.
- Maintenance: version bump to 1.14.2.

## [1.14.1] - 2026-03-13
### Changed
- Payments: restored TPW Core Payments currency settings persistence in FlexiEvent settings by allowing `currency_symbol` and `currency_code` through the new `flexievent_settings_allowed_keys` filter.
- Maintenance: version bump to 1.14.1.

## [1.14.0] - 2026-03-10
### Added
- Members: added a new core boolean member field `is_manage_members` with the label `Members Manager`.

### Changed
- Members: access to the front-end members management UI and related capability checks now allows WordPress admins, TPW members with `is_admin = 1`, or TPW members with `is_manage_members = 1`.
- Members: `is_manage_members` now follows the same Core checkbox-style handling pattern as other permission-style member flags across schema upgrade, field settings, profile protections, and add/edit forms.
- Members: protected permission fields `is_admin` and `is_manage_members` are now visible but read-only for non-administrator managers, with server-side enforcement to block privilege escalation through form tampering or AJAX requests.
- Maintenance: version bump to 1.14.0.

## [1.13.3] - 2026-03-10
### Added
- Members: added a new core boolean member field `is_gallery_admin` with the label `Gallery Admin`.

### Changed
- Members: `is_gallery_admin` now follows the same Core checkbox-style handling pattern as `is_noticeboard_admin` across schema upgrade, field settings, profile protections, add/edit forms, and the member details modal.
- Gallery: the active front-end gallery admin shortcode and gallery management AJAX/template paths now allow only WordPress admins with `manage_options`, TPW members with `is_admin = 1`, or TPW members with `is_gallery_admin = 1`.
- Gallery: gallery admin access now resolves through a shared helper so page rendering and action handlers stay aligned.
- Maintenance: version bump to 1.13.3.

## [1.13.2] - 2026-03-10
### Added
- Members: added `TPW_Member_Field_Loader::get_condition_eligible_custom_fields()` to return enabled custom checkbox fields that are explicitly allowed for conditional field logic.

### Changed
- Members: the loader now returns sanitized `key`, `label`, and `type` metadata for condition-eligible custom fields so front-end consumers can build conditional UI from a single filtered source.
- Maintenance: version bump to 1.13.2.

## [1.13.1] - 2026-03-10
### Changed
- Notices: active front-end noticeboard management now allows TPW noticeboard admins via `TPW_Control_UI::is_noticeboard_admin()` while preserving existing WordPress admin access.
- Notices: the active notices shortcode render path and AJAX management actions now share the same permission check so front-end controls and endpoints stay aligned.
- Maintenance: version bump to 1.13.1.

## [1.13.0] - 2026-03-10
### Added
- Email: added a persistent core email log table for dispatcher activity, including timestamp, recipient, subject, context, status, error detail, and duration.
- Email: added a new Email Logs tab in TPW Core Settings to inspect the latest 100 dispatcher entries and clear logs when needed.
- Docs: documented the central email logging flow, logged fields, optional dispatcher context usage, retention policy, and admin viewing location.

### Changed
- Email: `TPW_Email::dispatch_mail()` now records real send outcomes around `wp_mail()` and captures failure detail from WordPress mail errors when available.
- Email: log retention is enforced automatically with a daily cleanup that removes entries older than 30 days.
- Maintenance: version bump to 1.13.0.

## [1.12.0] - 2026-03-10
### Added
- Members: My Profile tabs now render from a pluggable profile sections registry, allowing add-on plugins to register their own front-end profile tabs through `tpw_core_register_profile_sections`.
- Docs: added the My Profile tab extension contract and production integration guidance for TPW add-on plugins.

### Changed
- Members: the built-in Profile and Payments sections now use the same normalized section registry, priority sorting, and active-section rendering flow.
- Email: added `TPW_Email::dispatch_mail()` as the shared outbound dispatcher with support for throttling, centralized logging, and slot reservation.
- Email: feedback submissions and member-facing plain-text notifications now route through the shared dispatcher when available.
- Maintenance: version bump to 1.12.0.

## [1.11.2] - 2026-03-09
### Added
- Scheduler: added wrapper diagnostics via `TPW_Core_Scheduler::get_wrapper_diagnostics()` to expose the loaded wrapper file, detected Action Scheduler source/version, and pre-filter registration state.

### Changed
- Scheduler: `TPW_Core_Scheduler::schedule_single()` now records branch-specific debug metadata, raw scheduler return values, and optional admin-only debug log events for before/after call tracing.
- Scheduler: short-circuited `pre_as_schedule_single_action` responses now capture explicit success/failure diagnostics instead of returning an unqualified false value.
- Maintenance: version bump to 1.11.2.

## [1.11.1] - 2026-03-09
### Added
- Scheduler: added request-scoped diagnostics helpers via `TPW_Core_Scheduler::get_last_error()`, `get_last_schedule_debug()`, and `get_schedule_debug_history()`.

### Changed
- Scheduler: `TPW_Core_Scheduler::schedule_single()` now records richer debug context for successful and failed scheduling attempts.
- Scheduler: unique single scheduling now detects existing hook/args/group matches before re-requesting the same action from Action Scheduler.
- Maintenance: version bump to 1.11.1.

## [1.11.0] - 2026-03-09
### Added
- Members: added a new core boolean member field `is_volunteer` with the label `Volunteer`.

### Changed
- Members: `is_volunteer` now follows the same Core handling pattern as `is_committee` and `is_noticeboard_admin` across field settings, add/edit forms, Member Details modal, profile protections, and checkbox-based directory search/filtering.
- Members: new installs and upgraded sites now ensure the `tpw_members.is_volunteer` column exists with a default value of `0`.
- Maintenance: version bump to 1.11.0.

## [1.10.0] - 2026-03-08
### Added
- Gallery: added a same-page gallery browser via `tpw_gallery_index` and a dedicated Gallery Index Elementor widget.

### Changed
- Gallery: clicking a gallery card now switches the same page into a single-gallery view with a Back to Galleries action, reusing the existing gallery renderer.
- Gallery: public gallery index pages now enqueue `tpw-buttons.css` so TPW button styles are available for the embedded browser controls.
- Maintenance: version bump to 1.10.0.

## [1.9.5] - 2026-02-19
### Changed
- Admin UI: scope WP admin tabs styling to `wp-core-ui`-scoped TPW screens.
- Maintenance: version bump to 1.9.5.

## [1.9.4] - 2026-02-19
### Changed
- Admin UI: added `tpw_core_is_tpw_admin_request()` helper to consistently detect TPW wp-admin screens (supports both `tpw-` and `tpw_` slugs).
- Admin UI: `tpw-origin` body class is now applied to all TPW admin screens; `tpw-fe-embed` is opt-in via the `tpw_core_admin_fe_embed_pages` filter.
- Branding: branding/heading CSS variables are now only output on TPW admin screens; on the front-end they output only when TPW styles are enqueued (filters: `tpw_core/should_output_branding_vars`, `tpw_core/should_output_heading_vars`).
- Maintenance: version bump to 1.9.4.

## [1.9.3] - 2026-02-18
### Fixed
- Members Login: preserve full redirect destinations (including nested query args) through the front-end password reset email link and post-reset redirect.
- Members Login: preserve redirect destination after failed login attempts (wrong username/password) so subsequent attempts still land on the intended page.

### Security
- Members Login: validate redirect targets via wp_validate_redirect before redirecting.

### Changed
- Maintenance: version bump to 1.9.3.

## [1.9.2] - 2026-02-18
### Fixed
- Admin: eliminated WP 6.7+ early textdomain JIT notices by deferring tpw-core translation calls until init.

### Changed
- System Pages: deferred System Pages registrations/bootstrapping to init (no functional change).
- Maintenance: version bump to 1.9.2.

## [1.9.1] - 2026-02-18
### Fixed
- Admin: normalized Core Settings notice handling (no Settings API notice plumbing) and removed notice relocation JS.
- Admin: eliminated flicker and duplicate notices on Core Settings.

### Changed
- Admin: Core Settings uses the standard TPW branded header strip.
- Maintenance: version bump to 1.9.1.

## [1.9.0] - 2026-02-18
### Added
- Admin: TPW Core Settings now uses the standard TPW header strip (icon, title/subtitle, and TPW logo).

### Fixed
- Admin: added missing TPW Core icon asset for the header strip to prevent a broken image.

### Changed
- Maintenance: version bump to 1.9.0.

## [1.8.9] - 2026-02-16
### Added
- Menus: introduced the official logout placeholder URL `/?tpw_action=logout` for menu Custom Links.
- Menus: placeholder is rewritten at render-time into a fresh `wp_logout_url( home_url('/') )` so logout is immediate and no WordPress confirmation screen appears.
- Docs: documented the Logout URL Standard contract for admins and developers.

### Changed
- Maintenance: version bump to 1.8.9.

## [1.8.8] - 2026-02-16
### Changed
- Members: removed the “Payment Methods” panel from the member-facing My Profile → My Payments hub.
- Maintenance: version bump to 1.8.8.

## [1.8.7] - 2026-02-13
### Changed
- UI: removed `all: revert-layer` from the scoped Admin UI CSS to avoid impacting other plugins/themes.
- Maintenance: version bump to 1.8.7.

## [1.8.6] - 2026-02-13
### Changed
- UI: tidied Member Profile / My Payments navigation hierarchy (Tier-1 tabs + Tier-2 sidebar) and payments hub layout polish (no behaviour change).
- Maintenance: version bump to 1.8.6.

## [1.8.5] - 2026-02-12
### Added
- UI: added `.tpw-btn-warning` variant to the global button system.
- Docs: added permissions documentation under `docs/permissions/`.

### Changed
- UI: normalized padding for input-based button variants (secondary/danger/warning).
- Maintenance: version bump to 1.8.5.

## [1.8.4] - 2026-02-10
### Added
- Permissions: added `tpw_core_user_can()` bridge helper (additive only; no behaviour change).

### Changed
- Maintenance: version bump to 1.8.4.

## [1.8.3] - 2026-02-10
### Changed
- Maintenance: version bump to 1.8.3.

## [1.8.2] - 2026-02-09
### Fixed
- Members Admin: Export CSV now exports all enabled fields (including custom fields) when no Download fields are selected.
- Members Admin: When Download fields are selected, Export CSV now includes only those selected fields that are enabled.

## [1.8.1] - 2026-02-09
### Fixed
- Members Admin: Export CSV no longer downloads blank output when no Download fields have been configured.

## [1.8.0] - 2026-02-03
### Added
- Settings: new “Payment Methods” tab under TPW Core Settings.
- Settings: extensible tab content hooks (`tpw_core_settings_tab_content` and `tpw_core_settings_tab_content_{tab}`).
- Payments: helper `tpw_core_get_payment_methods_settings_url()` for a stable Payment Methods settings URL.

### Changed
- Admin: legacy Payment Methods menu/page now redirects to the TPW Core Settings “Payment Methods” tab.
- Payments: gateway settings “Back to Payment Methods” links now return to the Settings tab.

## [1.7.2] - 2026-01-23
### Fixed
- Members Admin: Add Member form now validates required fields inline (username, first name, surname, email).
- Members Admin: Edit Member no longer blocks saving when imported records have an empty member username.

### Changed
- Members Admin: When member username is blank but a WP user is linked, the WP username is displayed read-only (display only).

## [1.7.1] - 2026-01-23
### Added
- Members: new setting (default off) to optionally show adult family members on the primary member profile.

### Changed
- Members Directory: member-facing directory list and detail modal only allow primary members.
- Members: dependants/children are never displayed to other members (hard privacy rule).
- Members Admin: improved Household UI on Edit Member (household members list, clearer change controls, safer defaults and validation).

## [1.7.0] - 2026-01-20
### Added
- Scheduler: added `TPW_Core_Scheduler` as the single source of truth for background scheduling across TPW plugins.
- Action Scheduler: Core now provides a lazy init wrapper that uses an existing Action Scheduler instance when present (e.g. WooCommerce) or loads Core's bundled copy when needed.
- Scheduler API: wrappers for single + recurring actions, unscheduling, and basic query helpers, with default group `tpw`.

### Notes
- TPW plugins should call `TPW_Core_Scheduler::init_if_needed()` early (e.g. on `plugins_loaded`) instead of bundling Action Scheduler themselves.

## [1.6.0] - 2026-01-13
### Added
- Members: optional Household support (default off) with new admin tools on the Edit Member screen to create households and attach/move members.
- Members: new core field Date of Birth (DOB).
- Members: select field option lists via `field_options` (one option per line) for enabled select fields.

### Changed
- Members DB: schema upgrades for DOB and household tables; internal members DB version bumped to 0.3.6.

### Notes
- Household support is gated by the new setting “Enable household support” (Members Settings → General) and remains disabled by default.

## [1.5.2] - 2026-01-07
### Added
- Gallery: new `show_heading` attribute for `[tpw_gallery]` (and Elementor widget) to hide the gallery title/description above images.

### Changed
- Gallery Admin: caption editing now uses a textarea modal suitable for long captions (shared across modal + full-page editors), with clamped caption previews to keep card heights stable.

## [1.5.1] - 2026-01-06
### Fixed
- Notices: Noticeboard list shortcode thumbnail now links to the notice (image is clickable).

## [1.5.0] - 2025-12-23
### Added
- Gallery Pagination: New shortcode attributes `per_page` and `paginate` for `[tpw_gallery]` in `grid` and `list` views to limit how many thumbnails render at once.
- Elementor: Optional TPW Gallery Elementor widget (loads only when Elementor is active) supporting Grid/List/Story views.

### Changed
- Caching: Gallery shortcode cache key now varies by pagination query args to avoid serving the wrong page.
- Public UI: Pagination controls added for grid/list; fixed-column rendering uses a CSS variable for more predictable layouts.
- Story view: Preloads previous/next images and sets `decoding="async"` on the story image for smoother navigation.

### Docs
- Gallery docs expanded with Story view, pagination, and Elementor widget usage.

### Notes
- No database schema changes.

## [1.4.0] - 2025-12-23
### Added
- Gallery Help: New front-end help page for the Gallery module accessible at `/gallery-help/` via shortcode `[tpw_gallery_help]`.
- Admin Reordering: Drag-and-drop image reordering in the Gallery admin interface.

### Changed
- Image Editing UI: Enhanced caption editing and focal point controls for faster, more intuitive updates.
- Performance: Optimized styles and scripts to reduce load times and improve interaction smoothness.
- Templates: Refactored gallery templates for clearer structure and easier maintenance.

### Docs
- Comprehensive Gallery Admin documentation covering image management, reordering, captions, focal points, and category handling.
- Cross-linked help topics and updated references in README.

### Notes
- No database schema changes.
- Front-end help page is auto-created by System Pages when the Gallery module is enabled.

## [1.2.3] - 2025-11-21
### Added
- New shortcode `[tpw_profile_badge]` providing a standalone circular profile/login badge (40px). Supports optional `dropdown="yes"` for a hover (desktop) / tap (mobile) profile menu (My Profile, Logout).
- Accessible dropdown: `aria-haspopup`, `aria-expanded` with touch-device JS; ESC/outside tap closes on mobile.

### Changed
- Avatar fallback logic (badge): Member photo → real WP avatar (excluding placeholder grey silhouette) → initials from member `first_name` + `surname` → `display_name` → `user_login`.
- Styling consolidated with CSS variable `--tpw-profile-size: 40px` for easy theme override; enforced perfect circle across avatar and initials.

### Fixed
- Eliminated display of default placeholder avatar (grey silhouette) in profile badge; initials now show when no true photo exists.
- Dropdown position refined (flush against badge, no dead hover gap) ensuring reliable pointer transition.

### Docs
- Added `docs/members/tpw_profile_badge.md` covering usage, dropdown behaviour, accessibility, and override examples.

### Notes
- No database schema changes.
- Menu injection/profile avatar logic untouched; changes isolated to shortcode, CSS, JS, docs.

## [1.2.1] - 2025-11-20
### Changed
- Refactor: Features and Member Menu tabs now save independently via dedicated `admin_post` handlers to prevent the WordPress Settings API from overwriting unrelated options when one tab is saved.
- Removed legacy `register_setting` usage for `tpw_core_default_login_page`, `tpw_login_redirect_page_id`, and `tpw_member_menu_location` (now updated atomically per tab).
- Added explicit nonces and capability checks for new save actions.

### Fixed
- Eliminated cross-tab resets where saving Features reverted Member Menu location and vice versa.

### Notes
- No database schema changes; existing option values are preserved.
- Other tabs (Branding, Email, Templates, System Pages) remain unchanged and continue using their existing handlers.

## [1.2.0] - 2025-11-13
### Added
- Implement consistent branded header for payment settings pages.
- Ensure default courses are created automatically when a menu is inserted.
- Add rename warning script for course choice form to prevent accidental edits.
- Add `tpw_normalise_value()` and apply normalisation across menu classes.
- Ensure `wp_delete_user()` is available outside wp-admin context for member deletion.

### Fixed
(none)

### Changed
(none)

## [1.1.0] - 2025-10-28

- docs: Updated Admin Help content and inline developer annotations for clarity. Improved README with Core overview, relationship to dependent plugins, key components, hooks summary, and extension examples. Added @since/@param/@return tags and filter/action notes in major classes and settings renderers to mirror FlexiEvent 1.1.0 documentation style.
- docs: Expanded annotation coverage across Members, Postcodes, and Menus modules (class-level and public methods) and added a centralized "Core Hooks Index" to `docs/developer-guide.md` listing actions/filters with file references and since tags.
- docs: Added per-module Help topics under `docs/help/` for Members, Payments, System Pages, Menus, Postcodes, Access Control, TPW Control, Feedback, Gallery, and Notices. Each page links back to the Core Hooks Index.
- docs: Aligned Branding help (`docs/help/tpw-branding.md`) to the standardized sections and added a "See also: Core Hooks Index" footer link.
- docs: README now includes a "Developer Documentation" section linking to the Developer Guide and the Help topics index.
- docs: Finalized documentation suite with per-module Help index and cross-linking across README, Developer Guide, and Help topics. Ready for 1.1.0 release.

## [1.0.1] - 2025-10-27

- Fix: Upload Pages lightbox could open the wrong file. In table/list layouts each file was rendered with two preview anchors using the same data-index (icon and label), causing the lightbox index to desync. Rendering updated to use a single anchor per file and the JS lightbox now scopes navigation to the clicked Upload Page instance and resolves items by data-index to avoid mismatch.
- Fix: Upload Pages “Edit Visibility” settings were not obviously persisting. The modal now includes a Save Visibility button that submits the page form so changes are saved immediately.
- Change: Upload Pages visibility now grants access if any selected role OR any selected status matches (OR semantics). For example, ticking Admins and Status: Active allows either group to view the page.
- Security: The secure file serving endpoint now applies the same OR visibility semantics as page rendering for consistent access control.

## [1.0.0] - 2025-10-21

- First stable, public release.
- Core RSVP, guests, and payments logic stabilized.
- WooCommerce HPOS compatibility declaration.
- Freemius SDK integration and onboarding.
- Admin settings UX improvements and security hardening (nonces, escaping).
- Postcode lookup AJAX with nonce verification.
- Thank You page shortcode and templates.

Security: Added global no-cache safeguard for /manage-members/ admin views to prevent stale nonces.

---

For earlier internal versions and development notes, see `RELEASE.md`.
