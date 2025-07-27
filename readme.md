


# TPW Core Plugin

This plugin provides shared functionality across all TPW-based plugins (e.g., Lodge Meetings, Ladies Festival), handling shared tables, payment logic, menu choices, and RSVP submission structure.

---

## ✅ Shortcodes

### `[tpw_thank_you]`

Use this shortcode to display a Thank You message after RSVP completion. It works in both block and classic themes.

**Usage:**
- Create a WordPress page (e.g., "RSVP Thank You").
- Add the following shortcode to the content block:

```plaintext
[tpw_thank_you]
```

- This page will display:
  - RSVP confirmation
  - Submission ID (if passed in the query string)
  - Payment instructions (e.g., Bank Transfer)

- Redirect payment completions to:

```
/rsvp-thank-you/?submission_id=123
```

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