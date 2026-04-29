# Access Control

## Overview
Access in TPW is driven mainly by the Members table (statuses and flags), with linked-member administrator status synchronised to the linked WordPress `administrator` role.

## Key Screens / Shortcodes
- Applies to: Manage Members, TPW Control, and other protected UIs.
- Shortcodes: [tpw_member_login] for front‑end login.

## Hooks
- tpw_members/allowed_statuses (filter) — List of statuses considered valid members.
- tpw_members/wp_admin_is_full_admin (filter) — Whether WP admins are always TPW admins.

## Extending
- Use TPW_Member_Access::is_admin_current() and ::is_member_current() to guard screens and AJAX.
- Adjust organization‑specific rules via the filters above.

## Linked Administrators
- For linked members, `tpw_members.is_admin = 1` and the linked WordPress `administrator` role are kept in sync.
- If the Edit Member screen omits the `is_admin` checkbox because it was hidden, disabled, or not editable, TPW preserves the existing admin state.
- An explicit tick of `Administrator` on the editable Edit Member form sets `tpw_members.is_admin = 1` and grants the linked WordPress user the `administrator` role.
- An explicit untick of `Administrator` on the editable Edit Member form sets `tpw_members.is_admin = 0` and removes the linked WordPress user's `administrator` role.
- If a linked WordPress user already has the `administrator` role, TPW auto-corrects the linked member row upward so the Edit Member screen reflects the real admin state.

## References
- Developer Guide → ../developer-guide.md
- Class: modules/members/includes/class-tpw-member-access.php

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
