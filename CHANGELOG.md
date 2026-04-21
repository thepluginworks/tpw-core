# Changelog

## [1.23.5] - 2026-04-21
### Changed
- UI: updated the shared light button selector so link elements using `.tpw-btn-light` receive the same light button styling as button elements.

## [1.23.4] - 2026-04-14
### Fixed
- Members: corrected the front-end member login and password reset flows so submitted passwords are unslashed consistently before WordPress authentication and reset APIs run, preventing quoted or slashed passwords from being saved and checked differently.
- Members: reset and login notices now persist reliably across redirects, clear stale reset errors after a successful password change, and show a clear success notice after password reset completion.
- Members: the member login shortcode now renders login, lost-password, and reset-password states separately so the normal login page no longer includes a competing hidden auth form that can interfere with password-manager autofill.

## [1.23.3] - 2026-04-14
### Changed
- TPW Control: moved Upload Pages admin styling out of the section template and into the existing scoped TPW Control stylesheet, preserving the current interface behaviour while reducing template-embedded presentation code.
- TPW Control: centred the existing TPW Control shell within the viewport using the existing `.tpw-control` container, keeping the current sidebar and content layout structure unchanged.

## [1.23.2] - 2026-04-07
### Fixed
- Updates: the WordPress plugin details modal now reads Description and Changelog content from the bundled `readme.txt`, so GitHub-delivered TPW Core updates show the intended release information without depending on `CHANGELOG.md` in the installed package.

### Changed
- Documentation: refreshed the public-facing `readme.txt` description so the plugin details modal and install readme better explain TPW Core's role in the ThePluginWorks plugin ecosystem.

## [1.23.1] - 2026-04-07
### Fixed
- Members: fixed a regression where clearing the TPW `is_admin` flag on a linked member could leave the linked WordPress account with the `administrator` role and continue granting admin access.
- Members: centralized member-to-WordPress role synchronization in the member controller so linked users are re-synced consistently when `user_id`, `status`, or `is_admin` changes.

## [1.23.0] - 2026-04-07
### Changed
- Members: added a dedicated Privacy settings tab so member visibility and profile-sharing controls are separated from general member system settings.
- Members: added a configurable, extensible privacy-override setting for view-only access so selected roles or capabilities can bypass member privacy restrictions without gaining member management permissions.

## [1.22.1] - 2026-04-07
### Changed
- Members: refined the self-service profile editor for the member visibility checkbox with clearer spacing, a larger touch target, and a simpler inline Yes label.
- Members: the shared admin label for the visibility field is now neutral, while the member-facing profile page keeps the fuller explanatory wording.

## [1.22.0] - 2026-04-07
### Changed
- Members: added a member-controlled profile visibility setting so standard members can opt out of appearing in member-facing directories, detail views, contact actions, and related member selection lists.
- Members: management views and authorised admin or manager access continue to bypass member-to-member visibility restrictions.

## [1.21.2] - 2026-04-07
### Changed
- UI: refined shared primary and secondary TPW button classes so they consistently keep the configured padding, height, and border-radius tokens across shared button usage.

## [1.21.1] - 2026-04-07
### Changed
- UI: refined primary TPW button styling so shared primary button classes consistently keep the configured padding, height, and border radius tokens.

## [1.21.0] - 2026-04-07
### Changed
- UI: added configurable large button tokens for `.tpw-btn-lg`, including large font size, padding, and height values that now emit as shared branding CSS variables in both admin and front-end output.
- UI: reorganised the Branding settings page so button-specific controls sit with the Buttons section and the scoped UI Theme section appears after Semantic Notice Colours.

## [1.20.1] - 2026-04-07
### Changed
- UI: updated the shared TPW button stylesheet to provide a consistent outline button variant for primary and secondary buttons.
- Members: refined FlexiGolf activation detection used for conditional member/admin field visibility.

## [1.20.0] - 2026-04-02
### Changed
- Menus: TPW Control menu management now supports the `is_gallery_admin` visibility flag across add-item flows, edit-item saves, current-item summaries, and the edit modal visibility UI.
- Menus: editing a menu item from the menu-manager modal now preserves the existing menu position, parent, and structural nav-menu metadata so item edits no longer push the entry to the bottom of the menu.
- Menus: Match Managers visibility is now conditionally rendered from the existing FlexiGolf activation gate in both add and edit flows, and the edit modal now captures that gate correctly inside the nested tree renderer.
- Members: FlexiGolf activation detection now recognises TPW FlexiGolf bootstrap constants and loader classes in addition to the existing legacy markers.

## [1.19.0] - 2026-04-01
### Changed
- Members: TPW Core now stores a canonical `membership_entitlement` value on member records through a dedicated `tpw_members` column, with strict normalization so only allowed machine values persist and invalid submissions collapse back to unset.
- Members: membership entitlement is now a Core-owned field with dedicated helper filters for visibility and option values, allowing dependent plugins to enable the admin field and supply code-controlled labels without relying on loose field-settings options.
- Members: the Add Member and Edit Member entitlement select is hidden by default, only appears when enabled by a dependent plugin, and default or fallback member downloads continue to omit the field while it remains hidden.

## [1.18.0] - 2026-03-30
### Changed
- Menus: added a front-end admin dining menus management screen with create, edit, delete, course naming, and course choice management flows for authorised administrators when the dining menus module is enabled.

## [1.17.1] - 2026-03-27
### Changed
- UI: refined the shared TPW button stylesheet so link-style buttons consistently suppress link underlines and secondary buttons keep the intended rounded corners across supported contexts.

## [1.17.0] - 2026-03-26
### Changed
- Members: new WordPress users created through Add Member, admin create/link, CSV import, and signup finalization now receive system-generated usernames derived from member name data, with deterministic numeric suffixes when needed.
- Members: CSV import now generates new usernames by default for new WordPress users and adds an explicit preserve-imported-usernames mode for migration scenarios, while keeping final resolved usernames aligned between `wp_users.user_login` and `tpw_members.username`.
- Members: normal member admin and profile surfaces now treat usernames as internal identifiers for new accounts instead of a manually managed field, without renaming existing users or changing login and password-reset behaviour.

## [1.16.0] - 2026-03-25
### Changed
- Members: linked member email updates now synchronize `tpw_members.email` and the linked WordPress account email together across self-service profile edits, admin Edit Member saves, and existing-member signup finalization, while preserving existing usernames.
- Members: linked email sync now handles duplicate-email conflicts, broken linked-user records, drift-heal cases where only one side is stale, and rollback when a WordPress email update fails after the member record changes.
- Members: the Member Field Settings screen now uses stronger non-auth autofill suppression and page-local safeguards to reduce unwanted browser or password-manager injection into label, section, custom-field, and search-option inputs.
- Members: the core `username` field label remains visible in Member Field Settings but is now fixed in the UI and enforced server-side so posted overrides are ignored.

## [1.15.7] - 2026-03-25
### Changed
- Maintenance: removed the bundled Freemius SDK, bootstrap include, and Core-side startup call now that TPW Core updates are fully handled by the GitHub release manifest flow.
- Maintenance: retained the existing manifest fetch, update injection, GitHub release packaging, and normal plugin runtime behaviour without further updater logic changes.

## [1.15.6] - 2026-03-25
### Changed
- Updates: removed temporary upgrader trace hooks, request-scoped trace summaries, and the admin-only updater refresh helper now that release validation is complete.
- Maintenance: retained the existing manifest fetch, cache bypass, update injection, and upgrade cache-clearing behaviour without further updater logic changes.

## [1.15.5] - 2026-03-25
### Changed
- Maintenance: clarified updater inline documentation without changing TPW Core runtime behaviour.

## [1.15.4] - 2026-03-25
### Changed
- Updates: TPW Core now logs the exact WordPress upgrader stage reached during one-click updates, including the package URL handed to WordPress and the final upgrader result.
- Updates: upgrader diagnostics now capture precise `WP_Error` codes, messages, and data for remote download failures, filesystem credential or write-access failures, unpack or package validation failures, and plugin replacement failures.
- Maintenance: each traced update request now records a concise end-of-request summary so failed upgrades can be classified quickly without changing updater behaviour.

## [1.15.3] - 2026-03-25
### Changed
- Updates: reduced temporary updater diagnostics so short-term validation keeps signal for active update checks and remote manifest failures without the previous debug noise.

## [1.15.2] - 2026-03-25
### Changed
- Updates: TPW Core now bypasses stale manifest data during WordPress plugin update checks so new releases appear reliably in scheduled checks and after using Dashboard > Updates > Check Again.
- Updates: updater cache refresh now clears both the TPW Core manifest cache and WordPress plugin update cache automatically when WordPress performs an active check or after a TPW Core upgrade completes.
- Maintenance: temporary updater diagnostics now log whether cached or freshly fetched manifest data was used, alongside the installed version, available version, and final update injection decision.

## [1.15.1] - 2026-03-25
### Changed
- Maintenance: removed the obsolete `scripts/freemius-deploy.php` helper as part of updater release validation.

## [1.15.0] - 2026-03-25
### Changed
- Updates: TPW Core now injects update metadata on both the plugin update check path and the cached plugin update read path, so available releases surface more reliably in Plugins and Dashboard > Updates.
- Updates: version comparisons now resolve against the installed TPW Core plugin version reported by WordPress, reducing the risk of stale or mismatched update decisions.
- Maintenance: updater cache clearing now refreshes both the TPW Core manifest cache and WordPress' plugin update cache after upgrades and manual refresh checks.
- Maintenance: added tightly scoped temporary updater diagnostics and an admin-only refresh trigger to support release validation and troubleshooting.

## [1.14.43] - 2026-03-24
### Changed
- Documentation: refreshed the public plugin readme so setup notes, shortcode references, and release notes are clearer for site owners and administrators.
- Documentation: simplified the public repository overview for GitHub visitors while keeping the full developer and internal documentation set in the repository.

## [1.14.42] - 2026-03-24
### Changed
- Updates: TPW Core now checks the public TPW version manifest and registers available updates through WordPress' standard plugin update system.
- Updates: plugin information for TPW Core now includes the current available version, release download link, and homepage details for a clearer in-dashboard update experience.
- Maintenance: manifest responses are cached to keep update checks lightweight and reduce unnecessary remote requests.
- Maintenance: this release adds update detection only; no new admin UI has been introduced.

## [1.14.41] - 2026-03-24
### Changed
- Release delivery: TPW Core now publishes its canonical install package through the public release channel used for site downloads.
- Release delivery: tagged and manual release runs now build a filtered `tpw-core.zip`, preserve the correct `tpw-core/` archive root, and refresh the published asset when needed.
- Release delivery: release automation now generates a public `tpw-core.json` version manifest so TPW Core and companion plugins can detect updates from a stable URL.
- Maintenance: this release changes packaging and distribution only; plugin runtime and admin UI behaviour are unchanged.

## [1.14.40] - 2026-03-24
### Changed
- Members: the Sign Ups debug screen is now hidden from normal admin menus while remaining available by direct link for authorised administrators when signup debug mode is enabled.
- Members: signup attempt records now exclude temporary debug and schema trace data, reducing stored noise while preserving the information needed for completion and support.
- Maintenance: TPW Core now loads translations early enough to avoid WordPress admin timing warnings and removes temporary debug logging across payments, menus, postcode lookups, CSV import tools, and TPW Control.
- Maintenance: postcode lookups now use the configured site default country when available, falling back to GB when no site preference has been set.
- Maintenance: Core now records a lightweight support warning for older member records that still use a legacy `society_id = 0` value, without rewriting historical data automatically.

## [1.14.38] - 2026-03-23
### Changed
- Distribution: corrected plugin commercial metadata so the plugin is no longer marked as using paid plans or freemium access.
- Distribution: metadata now matches the current TPW Core distribution model, reducing the risk of incorrect plan-related prompts or state handling.

## [1.14.37] - 2026-03-23
### Changed
- Members: TPW Core now standardises the canonical site society assignment on the Core-owned `tpw_site_society_id` option, defaulting current single-site installs to `1`.
- Members: signup finalization, manual member creation, and create-from-user flows now resolve new member records through the canonical site society value instead of mixing hardcoded defaults, empty fallbacks, or inferred existing-member data.
- Members: household creation and related member society resolution paths now resolve to the same canonical positive site society value, so new real member and household rows no longer default to `0`.
- Maintenance: this release establishes the correct source of truth for new entity creation but does not rewrite historical legacy rows that already have `society_id = 0`.

## [1.14.36] - 2026-03-23
### Changed
- Members: signup finalization now preserves the intended society assignment for new member records by preferring society information already carried by the completed Join attempt.
- Members: finalized Join requests no longer fall back to an unrelated default society when provider or request context already includes the correct society value.
- Members: Core still uses the existing default society fallback when no society information is present, preserving compatibility for existing single-society setups.

## [1.14.35] - 2026-03-23
### Changed
- Members: the Sign-Ups settings UI now marks `email`, `first_name`, and `surname` as Core-required baseline Join fields and keeps them visible but locked in configuration.
- Members: administrators can no longer disable those baseline Join fields from the public signup schema, reducing broken paid Join setups when TPW Subscriptions depends on Core readiness.
- Members: server-side Sign-Ups settings saves now force those baseline fields to remain enabled and required, preventing tampered requests from disabling them.

## [1.14.34] - 2026-03-20
### Changed
- Packaging: excluded `uninstall.php` from the generated release zip so package validation no longer rejects the TPW Core deployment archive.
- Packaging: kept the existing `.distignore`-driven build flow unchanged so published release assets continue to use the same filtered package.
- Maintenance: no TPW Core runtime, uninstall logic, or plugin behaviour changed in this release.

## [1.14.33] - 2026-03-20
### Changed
- Release delivery: replaced the previous custom deployment client with an SDK-based publishing flow for tagged releases.
- Release delivery: tagged release publishing now uses a pinned SDK version on the runner and logs returned upload and release payloads more clearly when publishing fails.
- Maintenance: kept package building, GitHub release asset uploads, and plugin runtime behaviour unchanged while simplifying the Freemius deployment path.

## [1.14.32] - 2026-03-20
### Changed
- Release delivery: corrected deployment request signing so tagged publishing now follows the expected SDK rules for canonical paths, multipart uploads, and signatures.
- Release delivery: retained the existing diagnostics so failed publishing attempts still surface the HTTP status and returned API response clearly.
- Maintenance: kept package building, GitHub release asset uploads, and plugin runtime behaviour unchanged while fixing the Freemius signing path.

## [1.14.31] - 2026-03-20
### Changed
- Release delivery: replaced the rejected Basic Auth publishing path with the correct signed API deployment flow for plugin tags.
- Release delivery: tagged release uploads now log returned tag metadata and fail explicitly when either the upload or the follow-up release-state change is rejected.
- Maintenance: kept package building, GitHub release asset uploads, and plugin runtime behaviour unchanged while correcting the Freemius publishing path.

## [1.14.30] - 2026-03-20
### Changed
- Release delivery: updated tagged publishing to use curl's built-in Basic Auth handling, avoiding malformed authorization headers during uploads.
- Release delivery: kept the existing upload diagnostics in place so release runs still show the requested version, package path, HTTP status, response body size, and raw response when available.
- Maintenance: no plugin runtime, payment flow, or front-end behaviour changed in this release.

## [1.14.29] - 2026-03-20
### Changed
- Release delivery: expanded tagged upload logging so release runs now show the package path, requested version, response body size, and raw response when available.
- Release delivery: added a clear fallback message when a publishing response body is empty, making failed uploads easier to diagnose from release logs.
- Maintenance: enabled shell trace output for the Freemius upload step to improve release debugging without changing plugin runtime behaviour.
- Maintenance: no plugin runtime, payment flow, or front-end behaviour changed in this release.

## [1.14.28] - 2026-03-20
### Changed
- Release delivery: hardened the publishing step so release runs now report the HTTP status and response body returned for each tagged upload.
- Release delivery: failed package uploads now fail the workflow immediately instead of appearing as successful runs.
- Maintenance: updated the release workflow checkout action from `actions/checkout@v3` to `actions/checkout@v4`.
- Maintenance: no plugin runtime, payment flow, or front-end behaviour changed in this release.

## [1.14.27] - 2026-03-20
### Changed
- Packaging: updated the release workflow to build plugin zips from the existing `.distignore` file so non-runtime docs, editor files, and metadata are excluded consistently.
- Packaging: published GitHub releases now receive the filtered plugin zip as a release asset, and Freemius deployments use the same filtered package.
- Maintenance: removed the obsolete Composer install step from the packaging workflow now that TPW Core no longer has a root Composer manifest.

## [1.14.26] - 2026-03-20
### Changed
- Maintenance: removed the remaining top-level Composer manifest and lockfile from TPW Core because Core no longer has any direct Composer-managed runtime dependencies.
- Maintenance: retired the last dead bundled Composer package set from Core so the plugin no longer relies on the top-level `vendor/` tree or Composer autoload artifacts.
- Maintenance: no business logic, payment flows, or Square add-on behaviour changed in this release.

## [1.14.25] - 2026-03-20
### Changed
- Maintenance: removed stale Composer vendor packages so the shipped `vendor/` directory now matches the current lockfile after Square SDK externalisation.
- Maintenance: regenerated the production dependency bundle from the current Composer manifest, leaving only Guzzle and its required PSR/support packages in Core.
- Maintenance: no functional plugin behaviour or payment flows changed in this release.

## [1.14.24] - 2026-03-20
### Changed
- Payments: Core no longer ships the Square PHP SDK and now treats Square checkout ownership as an external TPW Square Gateway add-on concern.
- Payments: preserved the existing Square slug, settings surface, and legacy gateway class boundary while replacing the in-Core Square gateway implementation with an unavailable compatibility shim when the add-on is not active.
- Payments: front-end payment method discovery, payments page config, and checkout validation now keep Square unavailable when the add-on is inactive, preventing broken Square selection on forms.
- Payments: the Payment Methods admin UI now keeps stored Square configuration visible, prevents re-enabling Square while the add-on is inactive, and restores the requested active state when the add-on becomes available again.
- Maintenance: version bump to 1.14.24.

## [1.14.23] - 2026-03-18
### Added
- Members: added a runtime Join provider registry so the Core-owned Join page can keep using `[tpw_join_form]` while dispatching to the active Join experience.

### Changed
- Members: `tpw_join_form` now dispatches safely between the built-in Core Join flow and registered external shortcode-based providers, while keeping Core as the default and fallback provider.
- Members: added an Active Join Provider setting to the existing Members sign-up settings and stored the provider key inside the existing `tpw_members_settings` option.
- Members: hardened the managed Join page so it sends no-cache headers even when an external Join provider is active, reducing stale cached Join content after provider switches.
- Maintenance: version bump to 1.14.23.

## [1.14.22] - 2026-03-18
### Changed
- Members: aligned the signup finalizer in [modules/members/signups/class-tpw-signup-finalizer.php](modules/members/signups/class-tpw-signup-finalizer.php) with the Core identity architecture so canonical `tpw_members` creation or linking now happens before any WordPress identity projection is applied.
- Members: signup finalization now reuses or links an existing canonical member row for the resolved WordPress user, including weak-linkage repair by email or username where that resolves unambiguously, instead of failing or creating duplicate member records.
- Members: signup finalization now preserves an existing member record's canonical status when one already exists, writes the configured default status only when a member row is newly created or missing status, and returns normalized `member_status` plus projected `identity_role` in the success result.
- Members: signup finalization is now idempotent for already-completed attempts with valid stored refs, and new WordPress users created through the signup path no longer receive the `member` role before the canonical member status is known.
- Identity: added a narrow Core-owned projection helper in [modules/members/includes/class-tpw-member-roles.php](modules/members/includes/class-tpw-member-roles.php) so the signup path assigns `member` only for membership-bearing statuses and removes legacy identity roles for non-membership-bearing statuses.
- Maintenance: version bump to 1.14.22.

## [1.14.21] - 2026-03-17
### Changed
- Members: updated the Member Details modal flag-read path in [modules/members/includes/class-tpw-member-ajax.php](modules/members/includes/class-tpw-member-ajax.php) to source legacy member flags through the compatibility helper boundary.
- Members: kept the existing Member Details modal labels, ordering, formatting, field visibility, and Yes/No rendering logic unchanged.
- Members: preserved existing behaviour for direct-link, missing-member, email-fallback, and username-fallback scenarios by falling back to the original member row unless the compatibility lookup resolves back to the same member record.
- Maintenance: version bump to 1.14.21.

## [1.14.20] - 2026-03-17
### Changed
- Documentation: added [docs/architecture/identity/member-flag-ownership-model.md](docs/architecture/identity/member-flag-ownership-model.md) as the formal Phase 2C Member Flag Ownership & Classification Model.
- Documentation: classified the current `tpw_members` member flags by ownership, system role, migration risk, and migration difficulty to freeze the safe migration boundary.
- Documentation: clarified that these member flags are compatibility-era signals rather than canonical identity and that the release does not change runtime behaviour.
- Documentation: updated [docs/architecture/README.md](docs/architecture/README.md) to link the new member-flag ownership reference alongside the existing identity architecture set.
- Maintenance: version bump to 1.14.20.

## [1.14.19] - 2026-03-17
### Changed
- Identity: adopted `TPW_Identity` and `TPW_Identity_Compat` inside the read-only Identity Audit admin class as the first narrow internal Core usage of the Phase 2A helper layer.
- Identity: migrated only the audit-reporting methods for user linkage analysis, identity role projection, unknown role reporting, and drift reporting.
- Identity: preserved the existing report semantics and avoided permission, authority, role, member-flag, or admin-elevation behaviour changes.
- Maintenance: version bump to 1.14.19.

## [1.14.18] - 2026-03-16
### Changed
- Documentation: clarified in [docs/architecture/identity/identity-permissions-decisions.md](docs/architecture/identity/identity-permissions-decisions.md) that `tpw_members.is_admin` is a Core administrative elevation signal rather than an ordinary compatibility-era responsibility flag.
- Documentation: added [docs/architecture/identity/role-classification-model.md](docs/architecture/identity/role-classification-model.md) guidance for Core administrative elevation signals that affect WordPress Administrator assignment and site-level authority.
- Documentation: recorded that any redesign of TPW-managed WordPress Administrator assignment must be handled as an explicit architecture decision, not helper migration or flag cleanup.
- Maintenance: version bump to 1.14.18.

## [1.14.17] - 2026-03-16
### Added
- Identity: added `TPW_Identity` as the first Phase 2A helper-layer scaffold for canonical member lookup, raw and normalized status access, linkage-mode reporting, and canonical membership checks.
- Identity: added `TPW_Identity_Compat` to centralize compatibility-era member-flag reads plus legacy WordPress role and identity-alias checks.

### Changed
- Identity: preserved the current weak-linkage compatibility path by resolving members through direct `user_id` linkage first, then the existing email and username fallback paths when enabled.
- Identity: loaded the new helper classes through the existing Core members bootstrap without broad internal call-site migration.
- Maintenance: version bump to 1.14.17.

## [1.14.16] - 2026-03-16
### Changed
- Documentation: added Phase 2 migration guardrails for legacy member responsibility flags in [docs/architecture/identity/identity-permissions-decisions.md](docs/architecture/identity/identity-permissions-decisions.md).
- Documentation: clarified in [docs/architecture/identity/role-classification-model.md](docs/architecture/identity/role-classification-model.md) that historical responsibility flags stored in Core are legacy compatibility signals rather than long-term ownership.
- Documentation: updated [docs/architecture/identity/identity-permissions-implementation-roadmap.md](docs/architecture/identity/identity-permissions-implementation-roadmap.md) with compatibility-layer rules to prevent new direct dependencies and reduce privilege escalation risk during migration.
- Maintenance: version bump to 1.14.16.

## [1.14.15] - 2026-03-16
### Added
- Identity: added a new read-only Identity Audit screen under TPW Core Settings as the first Phase 1 safety tooling for the identity and permissions implementation roadmap.
- Identity: the audit reports user/member linkage, weak-linkage fallback matches, projected identity roles, unknown assigned roles, member status distribution, and drift indicators without modifying data.

### Changed
- Documentation: updated [docs/architecture/identity/identity-permissions-implementation-roadmap.md](docs/architecture/identity/identity-permissions-implementation-roadmap.md) to reference the new TPW Core Identity Audit tooling.
- Maintenance: version bump to 1.14.15.

## [1.14.14] - 2026-03-16
### Added
- Documentation: added [docs/architecture/identity/role-classification-model.md](docs/architecture/identity/role-classification-model.md) as the formal TPW Role Classification Model for the TPW ecosystem.

### Changed
- Documentation: updated [docs/architecture/README.md](docs/architecture/README.md) to link the role classification reference alongside the existing identity architecture materials.
- Maintenance: version bump to 1.14.14.

## [1.14.13] - 2026-03-16
### Changed
- Documentation: clarified that TPW Core owns the full lifecycle of projected identity roles in [docs/architecture/identity/identity-permissions-decisions.md](docs/architecture/identity/identity-permissions-decisions.md).
- Documentation: updated [docs/architecture/identity/identity-model.md](docs/architecture/identity/identity-model.md) to cross-reference the lifecycle ownership rule for identity projection.
- Maintenance: version bump to 1.14.13.

## [1.14.12] - 2026-03-16
### Added
- Documentation: added `docs/architecture/identity/identity-permissions-decisions.md` as the formal architecture decisions document for ecosystem identity and permissions alignment.
- Documentation: added `docs/architecture/identity/identity-permissions-implementation-roadmap.md` as the phased implementation roadmap for safe identity and permissions migration.

### Changed
- Documentation: updated `docs/architecture/README.md` so the identity model, decisions document, roadmap, and permissions architecture references are linked together from the overview.
- Maintenance: version bump to 1.14.12.

## [1.14.11] - 2026-03-16
### Changed
- Documentation: refined the TPW Identity Architecture specification status wording to clarify that the current design direction remains subject to ecosystem audit and migration validation.
- Maintenance: version bump to 1.14.11.

## [1.14.10] - 2026-03-16
### Added
- Documentation: added the first formal TPW Identity Architecture specification under `docs/architecture/identity/identity-model.md`.

### Changed
- Documentation: updated the architecture overview to link identity and permissions as separate platform architecture domains.
- Maintenance: version bump to 1.14.10.

## [1.14.9] - 2026-03-16
### Changed
- Documentation: introduced `docs/architecture/` as the new home for TPW platform architecture documentation.
- Documentation: separated platform architecture docs into `identity` and `permissions` domains.
- Documentation: moved the existing permissions documentation into `docs/architecture/permissions/` and updated documentation references.
- Maintenance: version bump to 1.14.9.

## [1.14.8] - 2026-03-16
### Added
- Members: added a signup finalization service that converts eligible `members_join` signup attempts into WordPress users and TPW member records.
- Members: added a schema-driven signup field mapper that splits allowed signup fields into WordPress user data, TPW member fields, and member meta values.
- Members: added a controlled internal completion bridge and privileged recovery action so draft Join attempts can be advanced through a non-gateway success path before finalization.
- Members: added a temporary Sign Ups (Debug) admin screen so privileged users can review signup attempts and trigger internal completion through WordPress.

### Changed
- Members: finalization now persists created `wp_user_id` and `member_id` references into the signup attempt result payload as soon as they exist, so partial progress can be resumed safely.
- Members: failures after finalization begins now mark the attempt as `finalization_failed` with structured error context instead of leaving the attempt stranded.
- Members: finalized member accounts now apply the existing Core defaults for society resolution, member status, and member capability assignment.
- Members: the internal completion path now records explicit non-gateway provenance in the signup attempt result payload and event log before the existing finalizer runs.
- Maintenance: version bump to 1.14.8.

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
- Docs: added permissions documentation under `docs/architecture/permissions/`.

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
