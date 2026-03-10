# Members

## Overview
The Members module manages society members in a dedicated table with front‑end profile and admin tools. It decouples WordPress roles from TPW permissions and uses member statuses and flags (admin, committee, etc.) for access.

## Key Screens / Shortcodes
- Manage Members: /manage-members/ (admin/front‑end UI)
- Shortcodes:
  - [tpw_member_profile] — member self‑service profile
  - [tpw_member_login] — front‑end login form

## Create WordPress User (manual)
When a member has an email address but no linked WordPress user, admins can create/link an account from the Edit Member screen.

Eligibility
- Member has no linked user and a valid email.
- You have permission to manage members (Admins always; Committee if enabled in Member Settings).

How to use
1) Open Manage Members → Edit for the person.
2) If you just added an email, click Save; the form will reload once so the button appears.
3) Under the Email field, optionally tick “Send login credentials to this member”.
4) Click “Create WordPress User”.

What it does
- Links an existing WP user by email, or creates a new one with a generated password and member capabilities, then links it to the member record.
- If email option is selected, sends a credentials email with friendly links to /member-login/ for both login and password reset.
- Shows a success notice on return to the Edit screen.

## Hooks
- tpw_members_admin_form_extra_fields (action) — Render extra fields on Add/Edit forms.
- tpw_members_admin_form_after_save (action) — Persist extra fields after core save.
- tpw_core_register_profile_sections (filter) — Register additional My Profile tabs rendered by Core.
- tpw_member_login_redirect (filter) — Redirect after successful login.
- tpw_members/allowed_statuses (filter) — Valid statuses for “current member”.
- tpw_members/wp_admin_is_full_admin (filter) — Treat WP admins as full admins.
- tpw_members/mail_from_header (filter) — Override email From header in directory email.

## Extending
- Use the form extension hooks to add custom fields and save them to your own storage or TPW member meta.
- Use tpw_core_register_profile_sections to add front-end My Profile tabs from add-on plugins. Register a section with a unique slug, label, priority, and callback; Core will render the tab and active content automatically.
- Gate routes and buttons with TPW_Member_Access helpers (is_admin_current, is_member_current).
- Customize visible statuses and admin behavior via the filters above.

## References
- Developer Guide → ../developer-guide.md
- My Profile tab extension contract → ../developer-guide.md#my-profile-tab-extension-hook
- Admin templates: modules/members/templates/admin/
- AJAX: modules/members/includes/class-tpw-member-ajax.php

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
