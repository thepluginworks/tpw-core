

# TPW Platform – Default Role ↔ Capability Matrix

**Status:** Authoritative (Human‑readable reference)  
**Location:** TPW Core (single source of truth)  
**Audience:** Product, Sales, QA, Developers (VC)  

This document defines the **default capability mapping** for TPW platform roles.

It is:
- a **reference and testing guide**
- a **sales/onboarding explanation**
- the canonical place to understand “who can do what”

It is **not executable code**.

All plugins must enforce permissions using their own
`tpw-<plugin>.permissions.md` specification files.  
This matrix explains the *default intent*.

---

## 1. Core Principles

1. **WordPress Administrator is absolute**
   - Existing WP Administrators are never demoted.
   - WP Admin implicitly has all TPW capabilities.

2. **Capabilities, not roles, are enforced**
   - Plugins check `current_user_can( 'tpw_xxx' )`.
   - Roles map to capabilities via TPW Core logic.

3. **Defaults are adjustable**
   - Clubs may grant or revoke capabilities (e.g. give Treasurer temporary member-import access).
   - This matrix shows *recommended defaults*, not hard limits.

4. **Read-only roles exist**
   - Auditor and Committee are intentionally limited.

---

## 2. Platform Roles (Business Meaning)

| Role | Description |
|-----|------------|
| WordPress Administrator | Site owner / IT admin |
| Secretary | Operational admin for the club |
| Treasurer | Financial admin (subs, payments) |
| Membership Admin | Manages member records |
| Events Manager | Manages events only |
| Committee Member | Oversight / limited admin |
| Match Manager | Manages own golf fixtures |
| Fixtures / Results Editor | Manages all golf fixtures/results |
| Noticeboard Editor | Manages notices only |
| Auditor | Read-only oversight |

---

## 3. TPW Core – Default Capability Mapping

### Members
| Capability | Admin | Secretary | Treasurer | Membership Admin | Committee | Auditor |
|-----------|------|-----------|-----------|------------------|-----------|---------|
| `tpw_members_view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `tpw_members_manage` | ✔ | ✔ | ✖ | ✔ | ✖ | ✖ |
| `tpw_members_create` | ✔ | ✔ | ✖ | ✔ | ✖ | ✖ |
| `tpw_members_import` | ✔ | ✔ | ✖* | ✔ | ✖ | ✖ |
| `tpw_members_status_manage` | ✔ | ✔ | ✖ | ✔ | ✖ | ✖ |
| `tpw_members_roles_manage` | ✔ | ✔ | ✖ | ✔ | ✖ | ✖ |

\* Treasurer may be granted temporarily for onboarding/migration.

---

### Payments (Core runtime)
| Capability | Admin | Secretary | Treasurer | Committee | Auditor |
|-----------|------|-----------|-----------|-----------|---------|
| `tpw_payments_view` | ✔ | ✔ | ✔ | ✖ | ✔ |
| `tpw_payments_manage` | ✔ | ✔ | ✔ | ✖ | ✖ |
| `tpw_payments_export` | ✔ | ✔ | ✔ | ✖ | ✔ |

---

### Payment Methods / Gateways
| Capability | Admin | Secretary | Treasurer |
|-----------|------|-----------|-----------|
| `tpw_payments_methods_view` | ✔ | ✔ | ✖ |
| `tpw_payments_methods_manage` | ✔ | ✔* | ✖ |

\* Club choice – often restricted to Admin/Secretary only.

---

### Gallery
| Capability | Admin | Secretary | Gallery Admin | Committee | Member |
|-----------|------|-----------|---------------|-----------|--------|
| `tpw_gallery_view` | ✔ | ✔ | ✔ | ✔ | ✔ |
| `tpw_gallery_upload` | ✔ | ✔ | ✔ | ✖ | ✖ |
| `tpw_gallery_manage_own` | ✔ | ✔ | ✔ | ✖ | ✖ |
| `tpw_gallery_manage_all` | ✔ | ✔ | ✖ | ✖ | ✖ |

---

### Menus (meal choices)
| Capability | Admin | Secretary | Treasurer |
|-----------|------|-----------|-----------|
| `tpw_menus_view` | ✔ | ✔ | ✔ |
| `tpw_menus_manage` | ✔ | ✔ | ✖ |

---

### Notices
| Capability | Admin | Secretary | Noticeboard Editor |
|-----------|------|-----------|--------------------|
| `tpw_notices_view` | ✔ | ✔ | ✔ |
| `tpw_notices_manage` | ✔ | ✔ | ✔ |

---

### TPW Control – Menu Visibility
| Capability | Admin | Secretary |
|-----------|------|-----------|
| `tpw_control_menu_view` | ✔ | ✔ |
| `tpw_control_menu_manage` | ✔ | ✔ |

---

### TPW Control – Archive
| Capability | Admin | Secretary | Committee |
|-----------|------|-----------|-----------|
| `tpw_control_archive_view` | ✔ | ✔ | ✔ |
| `tpw_control_archive_upload` | ✔ | ✔ | ✔ |
| `tpw_control_archive_manage` | ✔ | ✔ | ✖ |

---

## 4. TPW Subscriptions – Default Mapping

| Capability | Admin | Secretary | Treasurer | Committee | Auditor |
|-----------|------|-----------|-----------|-----------|---------|
| `tpw_subs_view` | ✔ | ✔ | ✔ | ✔ | ✔ |
| `tpw_subs_manage` | ✔ | ✔ | ✔ | ✖ | ✖ |
| `tpw_subs_onboarding_manage` | ✔ | ✔ | ✔ | ✖ | ✖ |
| `tpw_subs_renewals_manage` | ✔ | ✔ | ✔ | ✖ | ✖ |
| `tpw_subs_payments_view` | ✔ | ✔ | ✔ | ✖ | ✔ |
| `tpw_subs_payments_manage` | ✔ | ✖* | ✔ | ✖ | ✖ |
| `tpw_subs_plans_manage` | ✔ | ✔ | ✖ | ✖ | ✖ |
| `tpw_subs_logs_view` | ✔ | ✔ | ✔ | ✖ | ✔ |

\* Club choice – some clubs allow Secretary to manage payments.

---

## 5. FlexiGolf – Default Mapping

| Capability | Admin | Fixtures Editor | Match Manager |
|-----------|------|------------------|---------------|
| `tpw_golf_fixtures_view` | ✔ | ✔ | ✔ |
| `tpw_golf_fixtures_manage_all` | ✔ | ✔ | ✖ |
| `tpw_golf_fixtures_edit_own` | ✔ | ✖ | ✔ |
| `tpw_golf_results_manage_all` | ✔ | ✔ | ✖ |
| `tpw_golf_results_edit_own` | ✔ | ✖ | ✔ |

---

## 6. FlexiEvent (Standalone)

| Capability | Admin | Events Manager |
|-----------|------|----------------|
| `tpw_events_view` | ✔ | ✔ |
| `tpw_events_manage` | ✔ | ✔ |
| `tpw_venues_manage` | ✔ | ✔ |
| `tpw_events_settings_manage` | ✔ | ✖ |

---

## 7. FlexiTicket

| Capability | Admin | Treasurer | Events Manager |
|-----------|------|-----------|----------------|
| `tpw_tickets_view_sales` | ✔ | ✔ | ✔ |
| `tpw_tickets_manage_sales` | ✔ | ✔ | ✖ |
| `tpw_tickets_payments_view` | ✔ | ✔ | ✖ |
| `tpw_tickets_payments_manage` | ✔ | ✔ | ✖ |
| `tpw_tickets_export_sales` | ✔ | ✔ | ✖ |
| `tpw_tickets_settings_manage` | ✔ | ✖ | ✖ |

---

## 8. RSVP Lodge Meetings

| Capability | Admin | Secretary | Treasurer | Auditor |
|-----------|------|-----------|-----------|---------|
| `tpw_rsvp_view_submissions` | ✔ | ✔ | ✔ | ✔ |
| `tpw_rsvp_manage_submissions` | ✔ | ✔ | ✔ | ✖ |
| `tpw_rsvp_export_submissions` | ✔ | ✔ | ✔ | ✔ |
| `tpw_rsvp_payments_view` | ✔ | ✔ | ✔ | ✔ |
| `tpw_rsvp_payments_manage` | ✔ | ✔ | ✔ | ✖ |

---

## 9. Usage Notes (Important)

- Clubs may **temporarily grant** additional capabilities (e.g. Treasurer importing members).
- Removal of a capability must immediately restrict access.
- Access Control determines **visibility**, not authority.
- This matrix must be used for:
  - QA testing
  - onboarding discussions
  - sales expectations

---

## 10. Change Control

Any change to default permissions requires:
1. Updating this matrix
2. Updating the relevant plugin permissions spec
3. Regression testing against production roles

This document is the **human contract** of the TPW platform.