# TPW Identity & Permissions Implementation Roadmap

**Status:** Planning roadmap  
**Applies to:** TPW Core and all dependent TPW plugins  
**Audience:** Developers, maintainers, architects, QA

## 1. Purpose

This roadmap defines the recommended phased path for implementing the TPW identity and permissions architecture safely.

It translates the completed ecosystem audit and the frozen architecture decisions into an incremental delivery sequence designed to protect live clubs, preserve access continuity, and avoid premature enforcement changes.

This is a roadmap document, not a code specification.

## 2. Guiding Principles

Implementation should follow these principles throughout:

- no access-changing refactor without migration visibility
- compatibility before cleanup
- audit before enforcement
- Core-first identity hardening
- plugin migration only after central identity rules are frozen
- incremental delivery over ecosystem-wide rewrites

These principles exist because identity and permissions are already mixed across multiple plugins, and live production environments may contain role drift, weak linkage, and undocumented operational dependencies.

## 3. Phase 1 - Audit & Migration Safety Tooling

The first phase should deliver visibility and reporting before any access-affecting behaviour changes are made.

Priority outputs in this phase include:

- user/member linkage audit reporting
- role drift audit reporting
- page restriction audit reporting
- unknown role inventory reporting
- projected-role audit reporting
- existing officer-role usage audit reporting

The goal of this phase is to make current-state reality visible across live clubs and development environments. No later permissions or identity cleanup should start until the migration surface is measurable.

Initial TPW Core implementation note:

- TPW Core now provides a read-only Identity Audit screen under TPW Core Settings to report user/member linkage, weak-linkage fallback, projected identity roles, unknown roles, member status distribution, and drift indicators without changing runtime identity behaviour.

## 4. Phase 2 - Core Identity Hardening

The second phase should harden TPW Core as the sole canonical identity owner before feature plugins are migrated.

Priority work in this phase includes:

- freezing the final status vocabulary
- defining Core-only ownership of identity projection
- defining role-sync lifecycle rules
- defining weak-linkage compatibility handling
- creating repair and sync tooling for linkage and projection issues

This phase should produce explicit rules for how Core determines canonical membership identity, how projection is synchronised into WordPress, and how broken or ambiguous links are surfaced and repaired without unsafe lockouts.

### Legacy Flag Compatibility Guardrails

Phase 2 implementation work must apply the following guardrails for legacy member responsibility flags:

- no new direct reads of raw member responsibility flags should be introduced in plugins
- existing reads of fields such as `is_committee` or `is_match_manager` should be migrated to compatibility helper methods once available
- broad business group labels such as committee must not silently become universal permission signals across plugins

The compatibility layer introduced in Phase 2 should centralise interpretation of these legacy signals so permission behaviour remains stable while migration occurs.

This guardrail reduces the risk of privilege escalation caused by responsibility flags being reused inconsistently across the ecosystem.

## 5. Phase 3 - Responsibility Role Boundary

The third phase should settle the ownership and lifecycle model for responsibility roles such as Secretary, Treasurer, Committee, Match Manager, and similar office or operational roles.

The audit found that current ownership is mixed. That must be made explicit before permission enforcement is migrated cleanly.

This phase should define:

- the long-term owner of responsibility roles
- how those roles are provisioned or synchronised
- how they map to capabilities
- how live legacy role usage is preserved during transition

## 6. Phase 4 - Permissions Migration by Plugin

Once Core identity rules and responsibility-role boundaries are frozen, plugins should be migrated in priority order.

Recommended migration order:

- TPW Access Control
- TPW RSVP Lodge Meetings
- TPW Subscriptions
- FlexiGolf
- FlexiPolicy
- remaining plugins

This order reflects risk and architectural sensitivity.

TPW Access Control should move first because it currently owns or influences several legacy and responsibility roles and affects page visibility and access assumptions.

TPW RSVP Lodge Meetings should move early because the audit found direct role-slug checks in a live access path.

TPW Subscriptions should move early because it currently influences `tpw_members.status`, which creates direct architectural tension with Core-owned identity.

FlexiGolf and FlexiPolicy should follow because they are likely to rely on mixed legacy role and permission assumptions, and they may be sensitive to responsibility-role modelling.

Remaining plugins can then migrate once the central rules and high-risk edges are proven in production-safe stages.

## 7. Phase 5 - Legacy Role and Drift Cleanup

The final cleanup phase should only begin after compatibility paths, tooling, and plugin migrations have been proven.

Likely work in this phase includes:

- deprecating `tpw_member`
- handling stale projected roles
- reconciling unknown live roles where ownership is established
- removing direct role-slug checks once compatibility coverage is in place

This phase is explicitly a late-stage cleanup phase, not a prerequisite for earlier architectural hardening.

## 8. Plugin Risk Priorities

The highest migration-risk plugins are:

- TPW Access Control, because it currently creates several legacy and responsibility roles and influences page-level access expectations
- TPW RSVP Lodge Meetings, because it still uses direct role-slug checks in live access logic
- TPW Subscriptions, because it currently writes `tpw_members.status` and therefore materially influences canonical identity
- FlexiGolf, because responsibility-role and ownership boundaries are likely to matter for its access model
- FlexiPolicy, because it is likely to depend on document visibility and operational role assumptions that need a stable capability contract
- Rota, because roster or operational workflow access tends to be sensitive to responsibility-role modelling and live access drift

These plugins should be treated as priority candidates for audit-backed migration work rather than broad ecosystem cleanup being attempted in arbitrary order.

## 9. Required Tooling Before Behaviour Changes

Before any access-affecting behaviour changes are made, the following tooling or reporting should exist:

- linkage audit tooling
- role inventory and drift reporting
- projected-role reporting
- unknown-role reporting
- page restriction visibility reporting
- repair or sync tooling for identity linkage and projection problems

This tooling requirement is a safety gate, not an optional enhancement.

## 10. Recommended First Implementation Target

The recommended first implementation group is:

- TPW Core
- TPW Access Control
- TPW RSVP Lodge Meetings

This is the first critical migration boundary because it combines canonical identity, legacy role ownership, and live officer-access checks in one implementation slice.

If this boundary is not stabilised first, later plugin migrations will inherit unclear identity rules and inconsistent role semantics.

## 11. Out of Scope for Initial Phases

The following should not be attempted too early:

- global removal of unknown live roles
- hard enforcement of strict linkage before audit and repair tooling exists
- wholesale capability migration across all plugins in one pass
- removal of legacy compatibility paths before reporting confirms they are safe to retire

Early phases should aim to reveal and contain migration risk, not to maximise cleanup speed.

## 12. Success Criteria

Before later cleanup phases begin, success should mean:

- TPW Core identity rules are frozen and documented clearly enough to drive implementation
- current live role and linkage drift can be reported reliably
- weak or ambiguous linkage can be identified and repaired safely
- responsibility-role ownership is explicit
- the first migration group has moved to the agreed identity and permissions boundary without unplanned access regressions
- later plugin migrations can proceed against a stable capability and identity contract
