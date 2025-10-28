# Members

## Overview
The Members module manages society members in a dedicated table with front‑end profile and admin tools. It decouples WordPress roles from TPW permissions and uses member statuses and flags (admin, committee, etc.) for access.

## Key Screens / Shortcodes
- Manage Members: /manage-members/ (admin/front‑end UI)
- Shortcodes:
  - [tpw_member_profile] — member self‑service profile
  - [tpw_member_login] — front‑end login form

## Hooks
- tpw_members_admin_form_extra_fields (action) — Render extra fields on Add/Edit forms.
- tpw_members_admin_form_after_save (action) — Persist extra fields after core save.
- tpw_member_login_redirect (filter) — Redirect after successful login.
- tpw_members/allowed_statuses (filter) — Valid statuses for “current member”.
- tpw_members/wp_admin_is_full_admin (filter) — Treat WP admins as full admins.
- tpw_members/mail_from_header (filter) — Override email From header in directory email.

## Extending
- Use the form extension hooks to add custom fields and save them to your own storage or TPW member meta.
- Gate routes and buttons with TPW_Member_Access helpers (is_admin_current, is_member_current).
- Customize visible statuses and admin behavior via the filters above.

## References
- Developer Guide → ../developer-guide.md
- Admin templates: modules/members/templates/admin/
- AJAX: modules/members/includes/class-tpw-member-ajax.php

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
