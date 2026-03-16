# Visual Core (VC) – Permissions Implementation Playbook

**Status:** Authoritative  
**Applies to:** All TPW plugins  
**Audience:** Visual Core (VC – GPT‑5.2), Developers, Maintainers  
**Goal:** Implement permission changes safely without breaking production.

> **Terminology note:**  
> “VC” refers to **Visual Core**, the internal name for the GPT‑5.2 assistant used inside VS Code to implement changes.  
> This document is written explicitly for Visual Core to follow.

---

## 1. Absolute Rules (Never Violate)

1. **Never demote a WordPress Administrator**
   - If a user is already a WP Administrator, their WP role must not be changed or removed.
   - WP Administrators implicitly have all TPW capabilities.

2. **Never rely on page visibility for security**
   - TPW Access Control may hide pages.
   - Every plugin MUST still enforce capabilities server-side.

3. **Never use WordPress role slugs as capabilities**
   - ❌ `current_user_can('tpw_secretary')`
   - ❌ `current_user_can('tpw_treasurer')`
   - ✔ Only capability strings defined in the permissions specs.

4. **Never invent capability names**
   - Use only capabilities defined in:
     - `tpw-core.permissions.md`
     - the plugin’s own `tpw-<plugin>.permissions.md`
   - If a capability is missing, STOP and update the docs first.

5. **No cross-plugin permission coupling**
   - Do not call permission helpers defined in other plugins
     (e.g. Subscriptions’ `tpw_user_can()` inside RSVP).
   - Each plugin enforces its own permissions.

---

## 2. Mandatory Doc‑First Workflow

Before changing any code, Visual Core MUST:

1. Identify the target plugin.
2. Open the plugin’s permission spec:
   - `docs/architecture/permissions/tpw-<plugin>.permissions.md`
3. Identify:
   - the screen / action being changed
   - the required capability
   - any ownership or field‑level policy
4. Cross‑check intent in:
   - `tpw-core/docs/architecture/permissions/role-capability-matrix.md`

**If anything is unclear, do not code. Update the documentation first.**

---

## 3. Implementation Sequence (Always Follow This Order)

### Step A — Additive First (Preserve Legacy Behaviour)
1. Add capability checks **in addition to** existing checks.
2. Preserve `manage_options` and other legacy access paths.
3. Ensure WP Admin access still works.

### Step B — Server‑Side Before UI
1. Enforce capabilities on:
   - POST handlers
   - AJAX endpoints
   - export endpoints
   - delete actions
2. Only then hide or disable UI controls.

### Step C — Exports Are High‑Risk
1. Exports must have a dedicated capability.
2. Export endpoints must enforce capability directly.
3. Never bundle export permission into a generic “manage” capability.

### Step D — Ownership & Policy Are Separate Layers
If a feature has “own vs all” logic (e.g. Match Manager, Gallery Admin):

1. Capability gates entry
2. Ownership check gates the object
3. Field‑level policy gates editable fields

Do **not** collapse these into a single check.

---

## 4. Mandatory Enforcement Points

For every permissions‑related change, Visual Core must confirm checks exist at:

- wp‑admin menu registration
- front‑end shortcode render entry
- every POST handler
- every AJAX handler
- every export endpoint
- every delete endpoint
- any quick‑action links (nonce + capability)

---

## 5. Backwards Compatibility Rules

Visual Core must preserve:

- existing WP Admin access
- existing production workflows
- existing settings and semantics
- existing URLs and redirects (unless explicitly approved)

If behaviour changes in any way:
- document it explicitly
- provide test steps
- never change silently

---

## 6. Testing Protocol (Required)

Every permissions patch must include:

### A) Users to Test
At minimum:
- WordPress Administrator
- Secretary
- Treasurer
- Membership Admin
- Committee Member
- Auditor
- Standard Member
- Logged‑out user

Plus plugin‑specific roles where relevant:
- Match Manager
- Fixtures / Results Editor
- Noticeboard Editor

### B) Scenarios to Test
- Allowed users can perform intended actions
- Disallowed users are blocked server‑side
- UI hides or disables restricted actions
- Ownership rules still apply
- Legacy access still works

---

## 7. Patch Discipline

Visual Core must:

- keep patches tightly scoped
- avoid refactors unrelated to permissions
- avoid PHPCS or formatting changes unless required
- include a short changelog:
  - files touched
  - permissions added/enforced
  - backwards‑compat preserved
  - test steps

---

## 8. Stop Conditions (Ask Before Proceeding)

Visual Core must STOP and ask for direction if:

- a required capability is not documented
- a change would remove existing access
- permission logic conflicts with another plugin
- the change crosses plugin boundaries

---

## 9. Canonical References

- Core capability contract: `tpw-core.permissions.md`
- Plugin permission specs: `docs/architecture/permissions/tpw-<plugin>.permissions.md`
- Default role mapping: `tpw-core/docs/architecture/permissions/role-capability-matrix.md`

---

## 10. Final Instruction to Visual Core

> **If a permissions decision is not explicitly documented,  
> do not guess. Stop and update the documentation first.**

This playbook is binding for all future permission‑related work.