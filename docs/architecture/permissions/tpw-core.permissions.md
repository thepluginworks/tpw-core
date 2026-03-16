

# TPW Core – Permissions Specification

**Status:** Authoritative  
**Applies to:** TPW Core (Members, Payments, Gallery, Menus, Notices, TPW Control)  
**Audience:** Developers (VC), Maintainers, QA  
**Do not deviate from this document when implementing permissions.**

---

## 1. Purpose

This document defines the **permission model for TPW Core**.

TPW Core provides shared infrastructure used by all TPW plugins.  
It establishes:
- the **canonical role definitions** (club-facing roles)
- the **capability contract** used by all code
- the rules for mapping **club office roles → capabilities**
- non-negotiable safety and compatibility guarantees

All TPW plugins **must rely on these definitions** and must not invent their own permission logic.

---

## 2. Core Principles (Non‑Negotiable)

1. **Capabilities are the contract**
   - All permission checks in code MUST use:
     ```
     current_user_can( 'tpw_xxx' )
     ```
   - Code MUST NOT check WordPress role slugs, TPW member flags, or group labels directly.

2. **Office roles are club facts**
   - Secretary, Treasurer, Committee, Match Manager, etc. are stored in TPW Members.
   - They do NOT imply WordPress Administrator access.

3. **WordPress Administrator is a permanent override**
   - Existing WP Administrators are never demoted.
   - WP Administrators implicitly have all TPW capabilities.

4. **No silent behaviour changes**
   - Any tightening of permissions must be gated and testable.
   - Legacy behaviour must remain functional until explicitly changed.

---

## 3. Platform Roles (Business Roles)

These are **club-facing roles**, not capabilities.

- WordPress Administrator
- Secretary
- Treasurer
- Membership Admin
- Events Manager
- Committee Member
- Match Manager
- Fixtures / Results Editor
- Noticeboard Editor
- Auditor (read-only)

These roles are mapped to capabilities by TPW Core logic.  
Plugins must never infer behaviour directly from these role names.

---

## 4. Capability Naming Rules

All TPW Core capabilities follow this pattern:

```
tpw_<module>_<area>_<verb>
```

Rules:
- Lowercase
- Underscore separated
- Explicit scope (view vs manage, own vs all)
- No role names in capability strings

---

## 5. Capability Register – TPW Core

### 5.1 Members

Used for managing the club’s member register and role flags.

- `tpw_members_view`
- `tpw_members_manage`
- `tpw_members_create`
- `tpw_members_import`
- `tpw_members_status_manage`
- `tpw_members_roles_manage`
- `tpw_members_userlink_manage`

Notes:
- Subscriptions onboarding does **not** require member creation.
- Treasurer may be granted `tpw_members_manage` temporarily for migrations, then removed.

---

### 5.2 Payments (runtime payments & logs)

Used by Subscriptions, RSVP, Ticketing, etc.

- `tpw_payments_view`
- `tpw_payments_manage`
- `tpw_payments_refund_manage`
- `tpw_payments_export`

---

### 5.3 Payment Methods / Gateways (configuration)

High‑risk system configuration.

- `tpw_payments_methods_view`
- `tpw_payments_methods_manage`

Default intent:
- WP Admin: manage
- Secretary: club choice
- Treasurer: view only (unless explicitly granted)

---

### 5.4 Gallery

Supports scoped gallery admins.

- `tpw_gallery_view`
- `tpw_gallery_upload`
- `tpw_gallery_manage_own`
- `tpw_gallery_manage_all`
- `tpw_gallery_settings_manage`

Rules:
- “Own” vs “All” must be enforced.
- Gallery Admins must never gain access outside assigned galleries.

---

### 5.5 Menus (meal choices library)

Shared infrastructure for RSVP and Ticketing.

- `tpw_menus_view`
- `tpw_menus_manage`

---

### 5.6 Notices / Noticeboard

- `tpw_notices_view`
- `tpw_notices_manage`

Used by Noticeboard Editor role.

---

### 5.7 TPW Control – Website Menu Visibility

Controls who sees which front‑end menu items.

- `tpw_control_menu_view`
- `tpw_control_menu_manage`

High‑impact capability.  
Usually restricted to Secretary / WP Admin.

---

### 5.8 TPW Control – Archive / File Upload System

- `tpw_control_archive_view`
- `tpw_control_archive_upload`
- `tpw_control_archive_manage`
- `tpw_control_archive_settings_manage`

---

## 6. Match Manager (Special Case – Do Not Break)

Match Manager permissions are **not** a single capability.

They are enforced by **three layers**:

1. **Role flag**
   - Member is flagged as Match Manager in TPW Members.

2. **Ownership**
   - Match Manager may only edit fixtures assigned to them.

3. **Field‑level policy**
   - Admin‑configured “Match Manager Control” settings restrict editable fields.

This model must be preserved exactly.  
Capabilities must not flatten this into “manage all”.

---

## 7. Public Access (Explicit Non‑Capability)

“Public” is **not** a capability.

Public visibility is handled by:
- TPW Access Control
- Page visibility rules
- Shortcode logic

Logged‑out users do not possess capabilities.

---

## 8. Backwards Compatibility Rules

- Existing WordPress Administrators must remain Administrators.
- Existing front‑end access must not be broken.
- Legacy checks may exist internally but must map to capabilities.
- No plugin may assume another plugin’s permission helpers.

---

## 9. Testing Requirements

Every permission change must be tested against:

- WordPress Administrator
- Secretary
- Treasurer
- Membership Admin
- Committee Member
- Match Manager
- Fixtures / Results Editor
- Noticeboard Editor
- Auditor
- Standard Member
- Logged‑out visitor

Special regression focus:
- Match Manager editing own vs others’ fixtures
- Gallery Admin own vs all galleries
- Archive uploads vs deletes
- Menu visibility changes

---

## 10. Enforcement for VC

When implementing or modifying TPW Core:

- ❌ Do NOT check WP role slugs
- ❌ Do NOT check TPW member flags directly in plugins
- ❌ Do NOT invent new capability names
- ✅ Use only capabilities listed in this file
- ✅ Treat this document as the single source of truth

Any deviation requires an explicit update to this document first.