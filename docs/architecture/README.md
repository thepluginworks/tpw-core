# TPW Platform Architecture

This section contains the architecture documentation for TPW Core and the wider TPW platform.

TPW Core is the shared foundation of the TPW plugin ecosystem. The architecture documentation is organised into separate domains so platform rules can be defined clearly and maintained independently as the system evolves.

## Identity Architecture

Identity architecture defines who a person is in the platform and how identity is derived from Core data.

Identity determines who a person is.

The canonical identity specification is [docs/architecture/identity/identity-model.md](identity/identity-model.md).

The audit-backed identity and permissions decision pack is [docs/architecture/identity/identity-permissions-decisions.md](identity/identity-permissions-decisions.md).

The phased implementation roadmap is [docs/architecture/identity/identity-permissions-implementation-roadmap.md](identity/identity-permissions-implementation-roadmap.md).

## Permissions Architecture

Permissions architecture defines capabilities, permission roles, and how authority is enforced across plugins.

Permissions determine what a person can do.

The canonical permissions specification is [docs/architecture/permissions/tpw-core.permissions.md](permissions/tpw-core.permissions.md).

Supporting permissions architecture references include:

- [docs/architecture/permissions/role-capability-matrix.md](permissions/role-capability-matrix.md)
- [docs/architecture/permissions/vc-permissions-implementation-playbook.md](permissions/vc-permissions-implementation-playbook.md)

## Architectural Separation

Identity and permissions are separate architectural layers.

- Identity determines who a person is.
- Permissions determine what a person can do.

They are related, but they are not the same concern and should be documented separately.