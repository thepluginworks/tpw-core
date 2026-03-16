# TPW Role Classification Model

**Status:** Current architectural reference  
**Applies to:** TPW Core and all dependent TPW plugins  
**Audience:** Developers, maintainers, architects, QA

## 1. Purpose

This document defines the TPW Role Classification Model used across the ecosystem to distinguish different kinds of role-like constructs.

Its purpose is to prevent architectural confusion between identity, permissions, and domain-specific assignments.

In TPW architecture, not every construct described as a role answers the same question or belongs to the same owner.

## 2. Scope

This model applies across TPW Core and all dependent TPW plugins.

It exists to provide a shared classification reference so future design, migration, and implementation work uses consistent language when discussing identity roles, responsibility roles, capabilities, assignments, and third-party role systems.

## 3. Guiding Principle

Not everything represented as a role in WordPress is an identity role.

TPW uses multiple categories of role-like concepts. Each category must have the correct owner, lifecycle, and architectural meaning.

Confusion happens when identity, authority, permissions, and record-level assignments are treated as though they are interchangeable. They are not.

## 4. Category 1 - Platform Identity Roles

Platform identity roles answer this question:

Who is this person in the TPW platform?

Examples include:

- `member`
- `candidate` (future, if introduced)

These roles are part of TPW platform identity.

They are:

- owned by TPW Core
- derived from canonical Core identity
- projected to WordPress only if required

If platform identity roles are retained, TPW Core owns their full lifecycle, including:

- create
- assign
- sync
- repair
- cleanup

Feature plugins must not create or manage platform identity roles.

## 5. Category 2 - Shared Responsibility Roles

Shared responsibility roles answer this question:

What recognised office or responsibility does this person hold across one or more TPW domains?

Examples include:

- `tpw_secretary`
- `tpw_treasurer`
- `tpw_committee`

These are not identity roles.

They represent responsibility or authority rather than platform personhood.

They must have one explicit owner.

They must not be used to infer membership identity.

Current ownership of these roles is mixed across the ecosystem. The exact long-term ownership model remains subject to implementation planning.

### Historical Responsibility Flags in Core

Some responsibility indicators currently exist as fields in the TPW Core members table, including examples such as committee membership and match manager status.

Their current storage location does not imply long-term architectural ownership by TPW Core.

These fields are treated as legacy compatibility signals rather than the target responsibility-role model.

Responsibility roles may ultimately move to plugin-owned storage or to more structured assignment models where that better reflects the domain.

During migration, the compatibility layer should abstract access to these signals so plugins are no longer coupled directly to the Core schema.

## 6. Category 3 - Plugin-Local Responsibility Roles

Plugin-local responsibility roles are roles that exist only within a specific plugin domain.

An example would be `tpw_match_manager` if FlexiGolf ever chooses to model Match Manager as a role.

These roles are:

- owned by the relevant plugin
- intended for plugin-specific operational responsibility
- not part of platform identity

They should be used sparingly.

They should exist only when the responsibility is broad and stable enough to justify a role rather than a narrower permission or assignment model.

They must not be promoted into accidental platform identity.

## 7. Category 4 - Capabilities

Capabilities are action-level permission signals.

Examples include:

- `tpw_members_manage`
- `tpw_payments_manage`
- `tpw_subs_manage`

Capabilities answer what a person can do.

They are not identity.

For fine-grained permission control, capabilities are often better than roles.

Long-term permission enforcement across TPW should increasingly favour capabilities over direct role checks.

## 8. Category 5 - Domain Assignments

Domain assignments are record-level or object-level assignments rather than user identity or a persistent global role.

Examples include:

- fixture match manager
- event organiser
- rota lead
- policy owner

These assignments often belong in plugin or domain data rather than in the global WordPress role system.

They are often preferable to creating a WordPress role.

They may be combined with capabilities where a person needs assignment-specific authority to act on the relevant records.

Match Manager is often better treated as a domain assignment plus capability than as a global identity role. In many cases, the responsibility applies only to a specific fixture, match, competition, or operational context. Treating that as a global platform role can overstate the responsibility, complicate lifecycle handling, and blur the boundary between identity and domain data.

## 9. Category 6 - External / Third-Party Roles

External or third-party roles are roles owned by WordPress core or by external plugins.

Examples include:

- `administrator`
- `editor`
- `subscriber`
- `customer`
- `shop_manager`

TPW must coexist with these roles.

They are outside TPW identity ownership.

They must not be confused with TPW identity architecture, even when they participate in access decisions on a live site.

## 10. Category 7 - Legacy / Unknown Roles

Legacy or unknown roles are roles already present in live environments that have no clear current owner or no longer fit the target architecture.

Examples identified through audit work include:

- `tpw_member`
- `master_mason`
- `admin_team`

These roles must be audited before migration.

They must not be removed casually.

Migration planning must treat them as live-site compatibility concerns until they are classified, owned, and handled safely.

## 11. Core Administrative Elevation Signals

Some signals in the TPW ecosystem represent privileged elevation into site-level authority rather than identity or responsibility.

The primary current example is the `is_admin` field in `tpw_members`.

These signals control elevation into WordPress administrative roles.

They must be treated as security-sensitive signals.

They remain owned by TPW Core.

They are not interchangeable with responsibility roles or capabilities.

They must not be modified without careful architectural consideration because they can affect:

- site administration
- plugin management
- user management
- security boundaries

## 12. Decision Flow

When a new role-like concept appears, ask the following questions:

1. Does it define who the person is in TPW?
2. Does it define a broad cross-domain responsibility?
3. Does it define a plugin-specific operational responsibility?
4. Is it really just an action permission?
5. Is it tied to a specific record or object?

The classification mapping is:

- if the concept defines who the person is in TPW, it belongs in Category 1 - Platform Identity Roles
- if it defines a broad recognised office or responsibility across TPW domains, it belongs in Category 2 - Shared Responsibility Roles
- if it defines a plugin-specific operational responsibility, it belongs in Category 3 - Plugin-Local Responsibility Roles
- if it is an action permission, it belongs in Category 4 - Capabilities
- if it is tied to a record, object, or local workflow context, it belongs in Category 5 - Domain Assignments

If the concept is owned by WordPress core or another plugin, classify it under Category 6 - External / Third-Party Roles.

If it already exists on live sites but has no clear current owner or classification, treat it as Category 7 - Legacy / Unknown Roles until audit work resolves it.

Signals that elevate a person into WordPress `administrator` authority are not classified by the role flow above. They must instead be treated as Core administrative elevation signals and assessed with explicit security and architecture review.

## 13. Design Guidance

Future design work should follow these rules:

- prefer Core identity rules for identity
- prefer capabilities for permissions
- prefer domain assignments for record-level responsibility
- avoid creating new global roles unless clearly justified
- do not let plugin-local roles become accidental platform identity

This guidance exists to keep identity ownership stable, permission enforcement precise, and plugin data models proportionate to the responsibilities they represent.

## 14. Relationship to Other Architecture Docs

This document should be read together with the rest of the TPW identity and permissions architecture set.

- The canonical identity model is defined in [identity-model.md](identity-model.md).
- The identity and permissions decisions reference is [identity-permissions-decisions.md](identity-permissions-decisions.md).
- The implementation roadmap is [identity-permissions-implementation-roadmap.md](identity-permissions-implementation-roadmap.md).
- The permissions-side specification is [../permissions/tpw-core.permissions.md](../permissions/tpw-core.permissions.md).