# Feedback

## Overview
Feedback provides a simple way for users to submit comments or issue reports from TPW screens. Modules can log or route feedback to email or custom stores.

## Key Screens / Shortcodes
- Typically rendered within admin/front‑end tools; no public shortcode by default.

## Hooks
- (Module‑specific) Add your own action to capture form submissions and route to email/CRM.

## Extending
- Add a small form to your UI and post to a secured AJAX endpoint you control. Use wp_send_json_success/error for responses.
- Consider reusing the Email module if you need HTML emails and attachments.

## References
- Developer Guide → ../developer-guide.md
- Email module: ../help/email.md (if present in your distribution)

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
