# TPW Identity Architecture Specification

**Status:** Current architectural direction (subject to ecosystem audit and migration validation).  
**Applies to:** TPW Core and all dependent TPW plugins  
**Audience:** Developers, maintainers, architects, QA

## 1. Purpose

This document defines the identity model for the TPW ecosystem.

Its purpose is to establish the architectural rules for how people are represented across the platform and to make the boundary between identity and permissions explicit.

In TPW architecture:

- Identity answers who a person is in the platform.
- Permissions answer what a person can do in the platform.

These are related concerns, but they are not the same concern and must not be designed as though they are interchangeable.

## 2. Scope

This document applies to TPW Core and to every dependent TPW plugin that relies on Core member, onboarding, or user-linking behaviour.

Feature plugins may extend workflows, responsibilities, and operational rules, but they must do so within the identity model defined here.

## 3. Core Principle

The platform identity model is based on a strict ownership split:

- TPW Core owns identity.
- Feature plugins own permission roles and capabilities.

This rule exists so that personhood, membership state, and canonical account linkage remain consistent across the ecosystem, even when multiple plugins add their own operational responsibilities.

## 4. Canonical Identity Source

The canonical member record lives in `tpw_members`.

The `tpw_members` table is the source of truth for platform membership identity.

A WordPress user account may exist for a person, and that user account may be linked to a Core member record, but the authoritative identity record for TPW membership remains the Core member record.

Where WordPress user data and Core member data differ, Core member data is authoritative for TPW identity decisions.

## 5. Identity vs Permissions

Identity and permissions must be distinguished explicitly.

Identity answers who a person is.

Examples of identity categories:

- member
- candidate (future)
- guest / external user (future or optional)

Permissions and responsibilities answer what a person can do.

Examples of responsibility or permission roles:

- secretary
- treasurer
- committee
- match manager

These concepts must not be mixed.

A person may be a member and also hold one or more responsibilities. A person may also hold a responsibility projection in a feature workflow without that responsibility redefining their identity category.

## 6. Membership Identity Rule

A person counts as a current TPW member only when both of the following are true:

- they are linked to a canonical TPW Core member record
- that record has a status that confers membership

`tpw_members.status` is the primary business signal used to determine whether the person currently carries membership status.

Membership identity is therefore not derived from WordPress roles alone, from feature-plugin permissions, or from the existence of a user account. It is determined from the Core member record plus a membership-bearing status.

## 7. Status Mapping

The current proposed architectural mapping is:

### Membership-bearing statuses

- Active
- Honorary
- Life

### Non-membership-bearing statuses

- Pending
- Inactive
- Resigned
- Deceased

This mapping represents the current architectural direction.

It may be refined after wider ecosystem audit, real-site validation, and implementation testing confirm how existing data and workflows behave in practice.

## 8. WordPress Identity Roles

The current proposed WordPress identity roles are:

- `member`
- `candidate` (future, if needed)

`guest` should not be introduced yet unless a clear business need arises and the lifecycle implications are defined.

WordPress identity roles are a synchronised projection of Core identity. They are not the source of truth.

Their purpose is to provide a practical WordPress-facing representation of Core identity where that projection is needed for platform integration, login experience, or compatibility.

### Lifecycle Ownership of Identity Projection

Projected identity roles are managed exclusively by TPW Core.

If identity projection roles such as `member` are retained, Core owns their lifecycle, including projection, synchronisation, repair, and cleanup.

See [Identity & Permissions Decisions](identity-permissions-decisions.md) for the detailed lifecycle-ownership rule.

## 9. Responsibility / Permission Roles

Responsibility roles such as the following are not identity roles:

- `tpw_secretary`
- `tpw_committee`
- `tpw_treasurer`
- other feature roles

These are responsibility or permission roles owned by feature plugins and related permissions architecture. They define operational authority, not platform identity.

## 10. Ownership Rules

The ownership rules for identity and permissions are:

- Core owns identity roles.
- Feature plugins own permission roles.
- Feature plugins must not create or own `member`.
- Feature plugins must not infer membership solely from their own permission roles.

If a plugin needs to know whether a person is a TPW member, it must rely on Core identity and membership-state signals rather than on plugin-local role assignments.

## 11. Lifecycle Rules

Identity synchronisation is expected to occur when Core identity facts are created, changed, repaired, or re-linked.

The intended lifecycle triggers include:

- signup completion
- member creation
- member status change
- member record edit
- user/member link changes
- manual sync or repair tools

These are architecture rules, not implementation details. The exact mechanisms may evolve, but the platform must treat identity projection as a synchronised outcome of canonical Core identity changes.

## 12. Legacy Role Deprecation

`tpw_member` is considered a legacy role and is a candidate for deprecation in favour of `member`, subject to audit and migration planning.

This document does not introduce that implementation change now. It records the intended long-term architectural direction so future work can converge on a cleaner identity model.

## 13. Relationship to Permissions Architecture

Permissions architecture is separate from identity architecture and should be read as a complementary domain.

Identity defines who a person is. Permissions define what that person can do.

See [Permissions Architecture](../permissions/tpw-core.permissions.md) for the permissions-side specification.

## 14. Open Questions / Pending Audit

The following areas remain subject to validation before the architecture can be treated as fully settled in implementation terms:

- current ecosystem role usage
- existing clubs using legacy roles
- migration impact
- final decision on candidate and guest identity roles
- exact sync and repair workflow

These items are intentionally recorded here so the architecture remains accurate about what is already decided and what still requires audit.