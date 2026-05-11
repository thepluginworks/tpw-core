# TPW Core Agent Instructions

These instructions apply to all work in this repository.

## Core Operating Rules

- Read TPW Core documentation before changing code.
- Treat TPW Core as shared infrastructure for multiple TPW consumer plugins and sites.
- Do not use TPW Core as a place to patch one consumer plugin in isolation.
- Do not edit consumer plugins unless the user explicitly asks for coordinated cross-plugin work.
- Preserve backwards compatibility for shared contracts, shared helpers, shared hooks, shared wrappers, and shared asset handles.
- Prefer additive changes before removing, renaming, tightening, or repurposing shared behaviour.
- Document shared contracts before implementing or rolling out broad shared changes.
- Validate likely impact on consumer plugins before changing Core behaviour.

## Required Read Order Before Code Changes

Before making code changes, read the baseline Core documents:

1. `readme.md`
2. `CODING_STANDARDS.md`
3. `docs/developer-guide.md`
4. `docs/architecture/README.md`
5. `CHANGELOG.md`

Then read the topic-specific contract documents for the area being changed.

### Shared UI, wrappers, enqueue, branding, or component work

You must read these documents before changing code:

1. `docs/architecture/ui/tpw-core-ui-wrapper-enqueue-contract.md`
2. `docs/help/tpw-branding.md`
3. `docs/help/ui-spec.md`
4. `docs/help/payments-integration.md` when payment or checkout UI is involved
5. `docs/tpw-payments-ui.md` when the Payments Hub is involved

### Permissions or access-control work

You must read these documents before changing code:

1. `docs/architecture/permissions/tpw-core.permissions.md`
2. `docs/architecture/permissions/role-capability-matrix.md`
3. `docs/architecture/permissions/vc-permissions-implementation-playbook.md`

### Identity, member flags, or role-classification work

You must read these documents before changing code:

1. `docs/architecture/identity/identity-model.md`
2. `docs/architecture/identity/role-classification-model.md`
3. `docs/architecture/identity/member-flag-ownership-model.md`

## Contract Hierarchy

When documents overlap, use this priority order:

1. Architecture contracts in `docs/architecture/**`
2. Repository-wide engineering rules in `CODING_STANDARDS.md` and this file
3. Developer reference docs such as `docs/developer-guide.md`
4. Help and integration guides in `docs/help/**`
5. Module-specific guides such as `docs/tpw-payments-ui.md`
6. Changelog and release notes

Do not treat examples, historical notes, or release entries as authority over contract docs.

## No Guessing Rule

Do not guess any of the following from scattered code usage:

- CSS handles
- wrapper classes
- hooks
- helper functions
- selector contracts
- enqueue responsibilities
- consumer-plugin integration rules

If the contract is unclear, stop and update or create documentation first.

## Shared Infrastructure Rule

When changing TPW Core:

- optimize for Core as a reusable platform layer
- check whether the change affects consumer plugins, front-end embeds, admin wrappers, or existing integrations
- do not hard-code assumptions that only fix one plugin's current markup or current flow unless the user explicitly requests a plugin-specific compatibility shim
- avoid introducing Core behaviour that only makes sense for one plugin unless that behaviour is first documented as a shared contract

## Shared UI Rule

For any shared UI work:

- always follow `docs/architecture/ui/tpw-core-ui-wrapper-enqueue-contract.md`
- keep wrappers at the full TPW screen root, not only around inner cards or panels
- preserve existing selectors and handles during migration
- prefer additive selector expansion over renaming or deleting existing classes
- treat Elementor and theme isolation as a Core wrapper concern, not a consumer-plugin workaround

## Backwards Compatibility Rule

Assume existing consumer plugins may depend on current Core behaviour unless the docs say otherwise.

Before making a shared Core change, explicitly check:

- whether current handles or helper functions are already consumed externally
- whether current wrapper classes or selectors are already relied on
- whether the change alters screen structure, hook timing, asset loading, or visible component semantics
- whether an additive migration path exists

If a change is breaking, document the break, the migration path, and the rollout order before implementation.

## Rollout Rule

For shared contracts and shared UI work:

1. Define or update the Core contract doc first.
2. Update Core documentation and discoverability links.
3. Make additive Core changes.
4. Validate likely consumer-plugin impact.
5. Only then update consumer plugins, and only when explicitly in scope.

## Scope Control

- Keep edits inside TPW Core unless the user explicitly authorizes broader work.
- Do not silently repair or refactor unrelated modules while implementing a shared contract.
- If a consumer-plugin issue exposes a missing Core contract, fix the contract and Core documentation first, then implement the smallest Core change that satisfies the documented contract.

## Validation Expectations

After documentation or code changes, validate that:

- the new or updated contract is discoverable from the relevant README or index docs
- existing help docs point back to the canonical contract instead of redefining it inconsistently
- no new documentation claims a handle, helper, or guarantee that does not exist in the current codebase unless it is clearly marked as future work
