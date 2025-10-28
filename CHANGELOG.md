# Changelog

All notable changes to TPW Core will be documented in this file.

## [Unreleased]

_(No changes yet)_

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
