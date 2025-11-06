# Managing Members

This guide covers day‑to‑day admin tasks in the Members area.

## Search and filters

- Use the main search box to find members by name or email.
- Choose a Status (e.g., Active, Honorary) to narrow results.
- Click Advanced Search for:
  - Field‑specific text filters (e.g., town)
  - Select lists (static or dynamic from data)
  - Date ranges (for core date columns)
  - Has value checks (for core or custom fields)
  - Boolean checkboxes for core yes/no columns

## Views

- Switch between List and Card view with the toggle button.
- Your choice is saved locally and respected on reload.

## Email a member

- Click a member’s email to open the email modal.
- Sender defaults to the current user (locked).
- Supports attachments, Rich Text (TinyMCE), and optional “send me a copy”.

## Edit or delete

- Use Edit to change details.
- Delete is available if allowed in settings (General → Allow admins to delete members).

## Create a WordPress user from a member

Use this when a member has a valid email address but no linked WordPress user account. This lets them log in to member-only pages.

Eligibility and visibility:
- The button appears on the Edit Member screen directly under the Email field when all are true:
  - The member has no linked WordPress user.
  - The member has a valid email address.
  - You have permission to manage members (Admins always; Committee if Settings → Member Settings → "Who can manage the Member Directory" is set to "Admins and Committee").

Steps:
1) Go to Manage Members → Edit for the person.
2) If the Email field was previously empty, add the email and Save. The page will reload and show an info notice so the button can appear immediately.
3) Under the Email field, tick “Send login credentials to this member” if you want an email sent.
4) Click “Create WordPress User”.

What happens:
- If a WordPress user with that email already exists, it is linked to the member.
- Otherwise a new WordPress user is created with a generated password and basic Member role/capabilities, then linked to the member.
- If you checked “Send login credentials…”, an email is sent to the member with friendly links to log in or reset their password. Both links point to /member-login/ (no extra query strings).
- You’ll be redirected back to the Edit screen with a success notice.

Troubleshooting:
- No button shown: make sure the member has an email saved and is not already linked to a WP user, and that you have the required permission.
- Error after clicking: the email may be invalid or the user already exists with a conflicting username. Fix the email or try again; contact a site admin if it persists.
- Email template: the “new account created” email content can be customized by developers via the template system. See the Developer Guide.

## Export CSV

- Use Tools → Export CSV to download the current result set (respects filters).

## Import CSV

- Use Tools → Import CSV to add or update members in bulk.
- Follow the mapping prompts and review the summary after upload.

## Member photos

- When enabled in settings, cards show either the uploaded photo or initials fallback.

## Access control notes

- WordPress roles aren’t used. TPW manages access via member status.
- Members with no WP role can still log in for front‑end screens.
