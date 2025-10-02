


# TPW Core Plugin

This plugin provides shared functionality across all TPW-based plugins (e.g., Lodge Meetings, Ladies Festival), handling shared tables, payment logic, menu choices, and RSVP submission structure.

---

## ✅ Shortcodes

### `[tpw_thank_you]`

Use this shortcode to display a Thank You message after RSVP completion. It works in both block and classic themes.

**Usage:**

```plaintext
[tpw_thank_you]
```

  - RSVP confirmation
  - Submission ID (if passed in the query string)
  - Payment instructions (e.g., Bank Transfer)


```
/rsvp-thank-you/?submission_id=123
```

### `[tpw_logout_link]`

Outputs a logout link that redirects the user to the homepage (useful when the WordPress admin bar is hidden for members):

```plaintext
[tpw_logout_link]
```

Optionally provide custom link text by wrapping content:

```plaintext
[tpw_logout_link]Sign out[/tpw_logout_link]
```

- `[tpw-control]` — Front-end admin hub (TPW Control). Create a page titled "TPW Control" with this shortcode to access Upload Pages, Menu Manager, and plugin-provided tools via /tpw-control/?action=.
---

## ⚙️ Core Settings

Stored in the admin section of TPW Core:

- **Bank Transfer**:
  - Account Name
  - Sort Code
  - Account Number

- **Payment Gateways** (future-ready):
  - SumUp
  - Stripe
  - Cheque placeholder logic (coming soon)

---

## 🧩 RSVP Logic

TPW Core manages:
- `tpw_rsvp_submissions` (main RSVP data)
- `tpw_rsvp_guests` (linked guests per RSVP)
- `tpw_rsvp_payments` (recorded payments)

Plugins using RSVP (like Lodge Meetings) are expected to:
- Use TPW Core functions and filters for submitting and retrieving RSVP data
- Redirect to the shared thank-you endpoint when finished

---

## 🧪 Developer Notes

- You may register additional endpoints using `add_rewrite_rule()` inside `includes/tpw-core-loader.php`.
- All core logic should be reusable and filterable by plugin modules.

---

## 👤 Members Module Behavior

- When the Members module is active, the WordPress admin bar is hidden for regular members (non-admins) on the front-end.
- Add a logout link anywhere using the `[tpw_logout_link]` shortcode; it logs out and redirects to the homepage.