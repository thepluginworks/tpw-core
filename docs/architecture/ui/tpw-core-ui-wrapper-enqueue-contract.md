# TPW Core UI Wrapper and Enqueue Contract

**Status:** Authoritative  
**Applies to:** TPW Core and all dependent TPW consumer plugins  
**Audience:** Developers, maintainers, QA  
**Do not deviate from this document when implementing shared TPW UI wrappers, shared component usage, or shared asset-loading behaviour.**

---

## 1. Purpose

This document defines the canonical TPW Core contract for:

- UI root wrappers
- shared UI component scope
- shared stylesheet handles
- shared UI helper usage
- consumer-plugin integration rules
- backwards compatibility during UI migration

TPW Core is shared infrastructure. Shared UI changes must work for TPW Core and its consumer plugins as a platform, not as a one-plugin patch surface.

---

## 2. Core Principles

1. **Core owns the shared UI contract**
   - Shared wrappers, shared component classes, shared asset handles, and shared compatibility rules are owned by TPW Core.
   - Consumer plugins must not invent alternate Core contracts.

2. **Wrappers are screen-level contracts**
   - The canonical wrapper must sit around the full rendered TPW screen.
   - Do not wrap only a card, panel, widget, or inner fragment and treat that as contract-compliant.

3. **Core is not a one-plugin patch layer**
   - Shared UI changes must support current and future consumer plugins.
   - Do not reshape Core wrappers or selectors only to match one consumer plugin's current DOM.

4. **Backwards compatibility is mandatory**
   - Existing shared handles, wrappers, selectors, and helper entry points must remain stable during migration unless a documented breaking change is explicitly approved.

5. **Prefer additive expansion**
   - Add new selectors, wrapper support, and compatibility layers before removing or renaming existing shared classes.

6. **Document first, then roll out**
   - Shared UI contract changes must be documented in Core before they are implemented broadly in Core or consumer plugins.

---

## 3. Mandatory Read Order Before UI Changes

Before changing shared UI behaviour, read in this order:

1. `readme.md`
2. `CODING_STANDARDS.md`
3. `docs/developer-guide.md`
4. `docs/architecture/README.md`
5. this document
6. `docs/help/tpw-branding.md`
7. `docs/help/ui-spec.md`
8. `docs/help/payments-integration.md` if payment or checkout UI is involved
9. `docs/tpw-payments-ui.md` if the Payments Hub is involved

If the required behaviour is not clearly documented after reading these sources, stop and update the docs before changing code.

---

## 4. Canonical Root Wrappers

### 4.1 `.tpw-admin-ui`

Use `.tpw-admin-ui` for:

- wp-admin pages rendered by TPW Core
- wp-admin pages rendered by TPW consumer plugins that intentionally adopt Core UI
- intentionally admin-like screens embedded outside normal wp-admin where the admin UI scope is the intended presentation layer

### 4.2 `.tpw-frontend-ui`

Use `.tpw-frontend-ui` for:

- public-facing member screens
- front-end account, profile, join, payment, and similar TPW experiences
- other non-admin screens that need TPW front-end wrapper semantics while still consuming shared Core components

### 4.3 Root Placement Rule

The wrapper must sit around the full rendered TPW screen, route view, or embedded TPW application surface.

Correct:

```html
<div class="tpw-frontend-ui my-plugin-checkout">
  <!-- full TPW screen -->
</div>
```

Incorrect:

```html
<div class="my-plugin-checkout">
  <div class="tpw-frontend-ui tpw-card">
    <!-- one inner card only -->
  </div>
</div>
```

### 4.4 Plugin-Specific Wrapper Rule

Plugin-specific wrapper classes may:

- appear on the same root element as `.tpw-admin-ui` or `.tpw-frontend-ui`, or
- appear on descendants inside the root wrapper

Consumer plugins must not replace the canonical Core wrapper with a plugin-specific root class.

---

## 5. Core-Owned Shared Components

The following are Core-owned shared UI component families and must follow Core wrapper and compatibility rules:

- buttons
- cards and panel surfaces
- tables and list containers
- forms and field states
- notices and semantic notice states
- app navigation

When changing shared component behaviour, treat the change as a Core-wide contract change, not a local module tweak.

---

## 6. Canonical Stylesheet Handles and Helpers

### 6.1 Canonical stylesheet handles

The canonical Core stylesheet handles are:

- `tpw-ui`
  - front-end/base TPW UI layer
  - use for shared front-end UI foundations and token-bearing public/member screens
- `tpw-admin-ui`
  - scoped admin/admin-like UI layer
  - use with `.tpw-admin-ui`
- `tpw-buttons`
  - global TPW button system
  - use anywhere TPW button components are rendered

### 6.2 Canonical helper functions

Current documented Core helper entry points include:

- `tpw_core_build_ui_theme_style_attr()`
  - injects current UI theme variables onto the wrapper root when needed
- `tpw_core_build_branding_css()`
  - builds branding variables
- `tpw_core_build_heading_css()`
  - builds heading variables
- `tpw_core_enqueue_payments_assets()`
  - payments-specific helper for the payments bootstrap bundle

This contract does not invent undocumented generic enqueue helpers. If a generic helper does not exist yet, do not guess one.

### 6.3 Consumer-plugin asset-loading rule

Consumer plugins should use documented Core handles and documented Core helper functions rather than inventing their own handle names or treating random Core file paths as the contract.

If current implementation reality still requires a direct enqueue by URL in a specific integration surface, that usage must align with the documented Core handle names and this contract. Do not create alternate path conventions or alternate handle names.

---

## 7. Canonical Wrapper and Enqueue Patterns

### 7.1 Admin or intentionally admin-like screens

- root wrapper: `.tpw-admin-ui`
- canonical styles: `tpw-admin-ui`, `tpw-buttons`
- add `tpw-ui` when the screen also depends on shared front-end/base UI tokens or shared layout helpers already documented for that surface

### 7.2 Public/member front-end screens

- root wrapper: `.tpw-frontend-ui`
- canonical base style: `tpw-ui`
- add `tpw-buttons` when TPW buttons are rendered
- add `tpw-admin-ui` only where the screen intentionally consumes shared admin-like component scope documented by Core

### 7.3 Wrapper completeness rule

The canonical wrapper should enclose:

- the page header or screen heading
- notices
- navigation for the TPW screen
- primary content region
- forms, cards, lists, and actions belonging to the same TPW screen

Do not place notices, navigation, or major layout containers outside the canonical wrapper if they are part of the TPW screen contract.

---

## 8. Theme and Builder Isolation

Theme, Elementor, and inherited style isolation for shared TPW UI is a Core wrapper concern.

- Consumer plugins should rely on the documented Core wrappers and Core styles.
- Do not work around theme leakage by inventing alternate root wrappers or one-off selector forks in consumer plugins.
- If wrapper isolation is insufficient, fix the Core contract and Core implementation additively.

---

## 9. Backwards Compatibility Rules

### 9.1 Shared handles and selectors must remain stable during migration

During migration:

- keep existing handles working
- keep existing shared selectors working
- keep existing wrapper classes working
- add new selectors or wrapper support before considering removals

### 9.2 Additive selector expansion is preferred

Preferred migration approach:

- extend selectors to include new wrapper-aware forms
- add missing scoped variants
- add compatibility classes where needed

Avoid:

- renaming shared wrapper classes
- deleting widely used selectors before migration completes
- replacing a shared selector with a new selector that only one plugin currently uses

### 9.3 Consumer-plugin safety check

Before changing shared UI behaviour in Core, check likely impact on:

- current consumer-plugin markup
- front-end shortcodes and embeds
- wp-admin screens using Core wrappers
- payment and checkout flows using Core handles or helper bundles

---

## 10. Breaking Change Definition

A shared UI change counts as breaking if it does any of the following without a documented compatibility path:

- removes or renames a documented wrapper class
- removes or renames a documented stylesheet handle
- changes the required root wrapper for an existing screen class of integration
- moves shared component semantics to a new class name without additive support for the old one
- requires consumer plugins to change enqueue logic or DOM structure to keep current behaviour
- narrows selector support so previously valid Core consumer markup no longer renders correctly
- changes helper entry points or their expected responsibility in a way that breaks existing consumers

Breaking changes require explicit documentation, migration guidance, and rollout planning before implementation.

---

## 11. Rollout Order

Shared UI rollout order must be:

1. update the Core contract document
2. update discoverability links in Core docs
3. make additive Core changes first
4. validate likely impact on current consumer plugins
5. update consumer plugins only after Core is ready, and only when those plugin updates are explicitly in scope

Do not require consumer plugins to move first in order to preserve current Core behaviour.

---

## 12. Implementation Checklist

Before implementation, confirm:

- the correct root wrapper has been chosen
- the wrapper covers the full TPW screen, not just an inner card
- documented Core handles are being used
- no undocumented helper, handle, or selector has been invented
- additive compatibility has been considered first
- likely consumer-plugin impact has been reviewed
- docs were updated before broad rollout when the contract changed

---

## 13. Related Documents

- `docs/architecture/README.md`
- `docs/help/tpw-branding.md`
- `docs/help/ui-spec.md`
- `docs/help/payments-integration.md`
- `docs/tpw-payments-ui.md`
- `docs/core-branding-css-reference.md`
