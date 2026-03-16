# TPW Platform Architecture

This section contains the architecture documentation for TPW Core and the wider TPW platform.

TPW Core is the shared foundation of the TPW plugin ecosystem. The architecture documentation is organised into separate domains so platform rules can be defined clearly and maintained independently as the system evolves.

## Identity Architecture

Identity architecture defines who a person is in the platform and how identity is derived from Core data.

Identity determines who a person is.

Identity documentation will be added under `docs/architecture/identity/` as the architecture work progresses.

## Permissions Architecture

Permissions architecture defines capabilities, permission roles, and how authority is enforced across plugins.

Permissions determine what a person can do.

The permissions documentation currently lives under `docs/architecture/permissions/`.

## Architectural Separation

Identity and permissions are separate architectural layers.

- Identity determines who a person is.
- Permissions determine what a person can do.

They are related, but they are not the same concern and should be documented separately.