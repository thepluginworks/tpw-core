# Access Control

## Overview
Access in TPW is driven by the Members table (statuses and flags) rather than WordPress roles. Helpers in TPW_Member_Access evaluate the current user.

## Key Screens / Shortcodes
- Applies to: Manage Members, TPW Control, and other protected UIs.
- Shortcodes: [tpw_member_login] for front‑end login.

## Hooks
- tpw_members/allowed_statuses (filter) — List of statuses considered valid members.
- tpw_members/wp_admin_is_full_admin (filter) — Whether WP admins are always TPW admins.

## Extending
- Use TPW_Member_Access::is_admin_current() and ::is_member_current() to guard screens and AJAX.
- Adjust organization‑specific rules via the filters above.

## References
- Developer Guide → ../developer-guide.md
- Class: modules/members/includes/class-tpw-member-access.php

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
