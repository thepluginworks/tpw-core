# TPW Identity & Permissions Decisions

**Status:** Decision pack for implementation planning  
**Applies to:** TPW Core and all dependent TPW plugins  
**Audience:** Developers, maintainers, architects, QA

## 1. Purpose

This document freezes the next set of architectural decisions required to align TPW identity and permissions across the TPW ecosystem.

It follows the completed workspace audit and is intended to guide implementation planning, migration design, and subsequent plugin-by-plugin delivery.

This is a decision document, not a discussion paper. It records the current agreed direction, while making any still-pending decisions explicit.

## 2. Scope

This document applies to TPW Core and all dependent TPW plugins.

It covers the architectural boundary between identity and permissions, the canonical source of membership identity, the intended direction for identity-role projection, and the migration constraints that must shape later implementation work.

## 3. Confirmed Architectural Direction

The completed audit confirms the following architectural direction:

- TPW Core is the canonical owner of identity.
- Feature plugins must not own canonical membership identity.
- Identity and permissions are separate but related architectural layers.

This means TPW Core owns the rules that determine who a person is in the platform, while feature plugins must increasingly align to a shared permissions model for what that person can do.

## 4. Canonical Identity Rule

The canonical identity rule is frozen as follows:

A person counts as a current TPW member only when both of the following are true:

- they are linked to a canonical TPW Core member record
- that record has a membership-bearing status

WordPress roles are a projection of identity, not the source of truth.

Neither a WordPress user account nor a WordPress role assignment is sufficient on its own to establish canonical TPW membership identity.

## 5. Membership-Bearing Statuses

The current architectural direction is that membership-bearing statuses are the statuses that represent a current member in TPW Core.

Based on the audit and current live-code terminology, the intended membership-bearing set is:

- Active
- Honorary
- Life Member

The audit identified a terminology mismatch: some architecture wording used `Life`, while live code currently refers to `Life Member`.

The preferred canonical business term is `Life Member`, because it is clearer and better aligned with live code. Final naming normalisation across all architecture language, admin language, and any stored status literals remains pending and must be settled before any strict enforcement work begins.

## 6. Identity Role Direction

The intended identity role direction is frozen as follows:

- the canonical projected WordPress identity role is `member`
- `tpw_member` is legacy and should be treated as a deprecation target
- status projection roles must not remain ownerless if they are retained
- TPW Core must be the sole owner of any projected identity roles

This means identity projection remains a Core concern. No feature plugin should create, own, or redefine the canonical WordPress-facing identity projection for TPW membership.

### Identity Role Lifecycle Ownership

If projected identity roles are retained in the TPW platform, their full lifecycle must be owned by TPW Core.

That lifecycle includes:

- role creation
- role assignment and synchronisation
- role repair if drift occurs
- role cleanup when identity changes

Identity roles are projections of canonical Core identity. Because of that, their lifecycle must be controlled centrally by Core rather than by feature plugins.

Feature plugins must not create or manage identity roles.

Plugins should instead rely on Core identity checks or capabilities where they need to determine membership state or enforce authority.

This rule does not apply to plugin-specific responsibility roles.

Responsibility roles such as Match Manager, Secretary, Treasurer, or Committee may be owned by their respective plugins if they represent operational permissions rather than identity.

## 7. Responsibility / Permission Role Direction

Roles such as Secretary, Treasurer, Committee, Match Manager, and similar club-office or operational roles are responsibility roles, not identity roles.

They represent authority, duties, or workflow ownership. They do not define whether a person is canonically a TPW member.

Future implementation work must define one explicit owner for these roles and their lifecycle.

The audit indicates that current ownership sits largely in TPW Access Control, but the long-term architectural boundary for responsibility-role ownership is still pending and must be formalised during implementation planning.

## 8. Subscriptions and Membership Status

The audit confirmed that TPW Subscriptions currently writes `tpw_members.status`.

This is an unresolved architectural tension because it means a feature plugin is materially influencing canonical identity.

The current reality is:

- TPW Core is intended to own identity
- TPW Subscriptions currently participates in changing the Core membership-status field

The architectural problem is that canonical identity ownership and real implementation responsibility are not yet fully aligned.

This issue is not treated as already solved. It must be resolved explicitly before implementation begins, because later migration and enforcement work cannot safely proceed while canonical identity may still be materially controlled by a feature plugin.

## 9. Weak Linkage Compatibility

The audit confirmed that TPW Core currently falls back from direct `user_id` linkage to email and username matching in some identity resolution paths.

This behaviour is to be treated as temporary compatibility behaviour pending repair tooling.

The design intent is frozen as follows:

- strong canonical linkage is the target end state
- weak linkage compatibility may need to remain temporarily for live-site safety
- weak linkage is a compatibility concern, not the ideal architecture

This document does not define repair mechanics. It freezes only the intended direction so implementation can design audit and recovery tooling before tightening identity rules.

## 10. Unknown / Site-Local Roles

The audit confirmed that live environments may already contain roles such as `admin_team` and `master_mason` without a clear owner in the current workspace.

Migration planning must preserve visibility of these roles and must not assume that the repository contains the full production truth.

Implementation work must therefore:

- surface unknown roles before any access-changing cleanup
- avoid deleting or collapsing unknown live roles blindly
- treat live role drift as a first-class migration concern

## 11. Permissions Enforcement Direction

The audit confirmed that the current ecosystem uses a mixture of:

- the dynamic Core capability bridge
- direct role checks
- direct member-table flag checks
- documented plugin capabilities that are not yet fully provisioned through native WordPress capability assignment

The target direction is frozen as follows:

- identity should come from Core identity rules
- permissions should increasingly be enforced through a defined capability model
- direct role-slug checks are legacy and should be migrated carefully

This migration must be incremental and compatibility-aware. The existence of documentation ahead of implementation does not remove the need for migration tooling or staged enforcement.

## 12. Frozen Decisions

The following decisions are frozen enough to guide implementation:

- TPW Core owns canonical identity.
- `member` is the target projected WordPress identity role.
- `tpw_member` is legacy and is a deprecation target.
- Identity roles and responsibility / permission roles must remain separate.
- WordPress roles are projections or permission carriers, not the source of canonical membership truth.
- Migration planning must account for live role drift and unknown site-local roles.
- No access-changing refactor should proceed without migration-safe tooling or compatibility handling.
- Weak linkage is a temporary compatibility concern, not the target identity model.

## 13. Explicitly Pending Decisions

The following decisions are not yet fully frozen:

- the final ownership model for Secretary, Treasurer, Committee, and related responsibility roles
- the exact long-term treatment of status projection roles, if any are retained
- whether TPW Subscriptions may continue to influence `tpw_members.status`, and under what boundary
- the final normalised status vocabulary across code, data, and documentation
- the final migration handling model for unknown live roles and site-local drift

## 14. Relationship to Other Architecture Docs

This document should be read together with the broader identity and permissions architecture set.

- The canonical identity model remains defined in [identity-model.md](identity-model.md).
- The current Core permissions specification is [../permissions/tpw-core.permissions.md](../permissions/tpw-core.permissions.md).
- The default role-to-capability reference is [../permissions/role-capability-matrix.md](../permissions/role-capability-matrix.md).
- The implementation-facing permissions migration guidance is [../permissions/vc-permissions-implementation-playbook.md](../permissions/vc-permissions-implementation-playbook.md).
