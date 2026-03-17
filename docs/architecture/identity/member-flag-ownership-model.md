# TPW Member Flag Ownership & Classification Model

**Status:** Phase 2C boundary-definition reference  
**Applies to:** TPW Core and all dependent TPW plugins  
**Audience:** Developers, maintainers, architects, QA

## 1. Purpose

This document defines the ownership and classification model for member-level flags stored in `tpw_members`.

Its purpose is to freeze the Phase 2C architectural boundary so identity and permissions migration can proceed safely without architectural drift.

These flags are not canonical identity. They are compatibility-era authority or responsibility signals that remain widely used across the TPW ecosystem and therefore require explicit preservation rules before any later migration work can standardise or replace them.

## 2. Scope

This document applies across TPW Core and all dependent plugins that read, write, interpret, or otherwise rely on member-level flags stored in `tpw_members`.

It covers the current Phase 2C classification of the known member flags:

- `is_admin`
- `is_manage_members`
- `is_committee`
- `is_match_manager`
- `is_noticeboard_admin`
- `is_gallery_admin`
- `is_volunteer`

## 3. Architectural Status

This document is a Phase 2C boundary-definition reference.

It does not introduce runtime behaviour changes.

It does not redefine permissions, role behaviour, identity ownership, or data storage.

It exists to freeze the current conservative interpretation of member flags so later migration work can be planned without silently changing live behaviour.

## 4. Core Rule

The Phase 2C core rule is frozen as follows:

- member flags are not canonical identity
- member flags must not be reinterpreted silently
- migration must preserve current behaviour until explicitly redesigned
- member flags are compatibility-era signals and must be treated conservatively

This rule applies even where a flag currently influences authority-sensitive behaviour.

The existence of a flag in `tpw_members` does not make that flag part of the canonical identity model. Storage location is not the same as architectural meaning or long-term ownership.

## 5. Classification Model

Member flags are classified using four dimensions.

### 5.1 Ownership

Ownership identifies which architectural layer currently has the strongest claim over the meaning and lifecycle of the flag.

- **Core-owned:** the flag is primarily interpreted by TPW Core or materially affects Core-controlled authority
- **Plugin-owned:** the flag is primarily tied to one plugin or domain workflow
- **Shared / ambiguous:** current usage or meaning spans multiple areas and does not yet have one safe long-term owner

### 5.2 System Role

System role identifies what kind of signal the flag represents in the current system.

- **Identity:** defines who the person is in the TPW platform
- **Permission:** contributes to authority or access decisions
- **Responsibility:** indicates an office, operational duty, or recognised function
- **Legacy signal:** exists mainly because of historical implementation patterns and compatibility expectations

For Phase 2C, no member flag in this document is classified as canonical identity.

### 5.3 Risk

Risk identifies how dangerous it would be to reinterpret, remove, or remap the flag without deeper migration work.

- **High:** likely to affect live authority or cross-plugin behaviour materially
- **Medium:** still important, but the blast radius appears narrower or more domain-specific
- **Low:** lower platform-wide risk, though still subject to compatibility preservation

### 5.4 Migration Difficulty

Migration difficulty identifies how safely the flag could be moved, wrapped, replaced, or retired.

- **Safe:** can likely be standardised later with low ecosystem risk once ownership is confirmed
- **Complex:** requires targeted migration planning, plugin coordination, or compatibility handling
- **Dangerous:** unsafe to change without explicit architecture decisions, migration tooling, and production validation

## 6. Flag-by-Flag Classification

### 6.1 `is_admin`

- **Ownership:** Core-owned
- **System role:** permission / legacy signal
- **Why it exists today:** historical Core administrative elevation signal used to drive authority-sensitive behaviour, including WordPress Administrator assignment
- **Risk level:** high
- **Migration difficulty:** dangerous
- **Conservative notes:** this flag is not canonical identity, but it is more sensitive than an ordinary plugin responsibility marker because it affects site-level authority. It must be preserved exactly until an explicit future redesign authorises any change.

### 6.2 `is_manage_members`

- **Ownership:** Core-owned
- **System role:** permission / legacy signal
- **Why it exists today:** historical Core member-management authority flag used to gate member-administration behaviour in compatibility-era paths
- **Risk level:** high
- **Migration difficulty:** dangerous
- **Conservative notes:** this flag remains a live authority signal. It must not be silently remapped into a different permission model during Phase 2C, and its current behaviour must be preserved until a later migration phase defines an explicit replacement boundary.

### 6.3 `is_committee`

- **Ownership:** shared / ambiguous
- **System role:** responsibility / legacy signal
- **Why it exists today:** historical broad committee marker reused across more than one area as a shorthand for recognised responsibility and sometimes for authority assumptions
- **Risk level:** high
- **Migration difficulty:** complex
- **Conservative notes:** committee is too broad to treat as a safe universal permission signal and too widely reused to move casually. Ownership must be clarified before migration, and current behaviour must be preserved while that clarification happens.

### 6.4 `is_match_manager`

- **Ownership:** plugin-owned (FlexiGolf)
- **System role:** responsibility / legacy signal
- **Why it exists today:** historical match-management marker used for FlexiGolf operational workflows and compatibility-era responsibility checks
- **Risk level:** medium
- **Migration difficulty:** complex
- **Conservative notes:** this flag should not be promoted into Core identity or cross-plugin authority. Later migration may standardise its read path, but any redesign should remain anchored to the FlexiGolf domain.

### 6.5 `is_noticeboard_admin`

- **Ownership:** plugin-owned
- **System role:** permission / responsibility / legacy signal
- **Why it exists today:** historical plugin-scoped noticeboard administration marker used to represent both operational responsibility and access within noticeboard-related workflows
- **Risk level:** medium
- **Migration difficulty:** complex
- **Conservative notes:** this flag should remain plugin-scoped. It must not be elevated into Core identity or treated as a general-purpose platform authority signal during Phase 2C.

### 6.6 `is_gallery_admin`

- **Ownership:** plugin-owned
- **System role:** permission / responsibility / legacy signal
- **Why it exists today:** historical plugin-scoped gallery administration marker used to drive gallery-specific authority and operational workflows
- **Risk level:** medium
- **Migration difficulty:** complex
- **Conservative notes:** this flag should remain plugin-scoped and compatibility-preserved. Phase 2C does not authorise broad reinterpretation or promotion into platform identity or Core-wide authority.

### 6.7 `is_volunteer`

- **Ownership:** plugin-owned or domain-owned
- **System role:** responsibility / legacy signal
- **Why it exists today:** historical volunteer marker used to represent a domain responsibility or participation state rather than canonical identity
- **Risk level:** low
- **Migration difficulty:** safe
- **Conservative notes:** this is the least migration-blocking flag in the current set, but it is still not canonical identity and must not be repurposed during Phase 2C.

## 7. Migration Blockers

The primary member-flag blockers for safe forward migration are:

- `is_admin`
- `is_manage_members`
- `is_committee`

These are the main blockers because they combine one or more of the following risks:

- they influence live authority decisions directly
- they are reused beyond one narrow plugin boundary
- their current meaning is broad, historically layered, or architecturally ambiguous
- changing them incorrectly could cause privilege loss, privilege escalation, or cross-plugin behavioural drift

`is_admin` and `is_manage_members` are especially sensitive because they sit closest to Core-controlled authority.

`is_committee` is the main shared-meaning blocker because its breadth makes it unsafe to treat as either a clean permission or a clean domain-only responsibility without prior ownership clarification.

## 8. Safe Conclusions

The following Phase 2C conclusions are frozen:

- no member flag is canonical identity
- Core authority flags must be preserved exactly for now
- shared or ambiguous flags require ownership clarification before migration
- plugin-scoped flags must not be promoted to Core identity
- no flag should be removed or repurposed during Phase 2C

These conclusions are intended to preserve current production behaviour while preventing architectural drift in later implementation planning.

## 9. Next-Phase Boundary

This model enables the next migration boundary to be defined safely.

- the next safe migration boundary is not flag removal
- the next safe step is later read-path standardisation or wrapping, not behaviour change
- this document does not authorise implementation yet

Later phases may introduce standardised read paths, wrappers, or compatibility-layer enforcement once ownership and migration plans are explicit. Phase 2C itself authorises only the documentation boundary, not runtime change.

## 10. Relationship to Other Architecture Docs

This document should be read together with the rest of the TPW identity architecture set.

- The canonical identity model is defined in [identity-model.md](identity-model.md).
- The current identity and permissions decisions reference is [identity-permissions-decisions.md](identity-permissions-decisions.md).
- The phased implementation roadmap is [identity-permissions-implementation-roadmap.md](identity-permissions-implementation-roadmap.md).
- The broader role classification reference is [role-classification-model.md](role-classification-model.md).
