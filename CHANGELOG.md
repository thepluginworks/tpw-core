# Changelog

All notable changes to TPW Core will be documented in this file.

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
