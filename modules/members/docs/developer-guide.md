# TPW Members – Developer Guide

## Settings > General

- Member Profile Change Notification Email
  - Option key: `tpw_member_change_notify_email`
  - Type: single email address (string)
  - When set, TPW Members will send a notification email whenever a member updates their own profile via the My Profile page (`[tpw_member_profile]`).
  - The email includes the member name, the date/time of the change, and a concise listing of field changes (for single-field inline edits it shows the field label with “Old → New”).
  - Delivery uses `wp_mail()` with `Content-Type: text/plain; charset=UTF-8`. A `Reply-To` header is set to the member’s email when available.
  - If the option is empty or invalid, no notification is sent (silently ignored).

Notes:
- Notifications only fire after a valid nonce and a successful save.
- Only self‑service profile edits trigger this notification. Admin edits via the Manage Members interface do not currently send a notification.
- Admin-like contexts already use no-cache guards; the notification trigger runs inside the secure, post-save block.
