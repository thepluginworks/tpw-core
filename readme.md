# FlexiClub

FlexiClub is the free foundation plugin used by FlexiClub-powered sites and add-on plugins. It provides the shared member, payment, page, branding, and admin tools that the wider ecosystem relies on.

## What FlexiClub is

FlexiClub is the central service layer for FlexiClub-powered sites. It helps administrators run consistent member journeys, shared pages, payments, and reusable admin tools across the plugin suite.

This repository keeps the public overview concise while preserving the deeper implementation and architecture documentation in the repo for development use.

## What it provides

- Shared member and profile features
- Shared payment methods and payment records
- Front-end login, profile, and join flows
- System page registration for FlexiClub plugins
- Shared FlexiClub settings, branding, and admin UI assets
- Common utilities such as email and postcode lookup support

## Plugins that depend on it

FlexiClub is intended to support ecosystem plugins such as:

- event and RSVP add-ons
- payments-enabled modules
- members and join-flow extensions
- gallery, notices, menus, and control tools

If a plugin expects shared member, payment, or front-end account functionality, it will typically depend on FlexiClub being active.

## Installation and updates

1. Install and activate FlexiClub in WordPress.
2. Install any add-on plugins that depend on it.
3. Review the shared settings under WordPress Settings -> FlexiClub.
4. Confirm that required pages such as login, profile, join, or thank-you pages are set up for your site.

FlexiClub includes its own update and packaging flow so production sites receive the intended install package structure.

## Key admin areas

- Settings -> FlexiClub for shared configuration such as branding, payments, and platform options
- Member-related FlexiClub screens used by dependent plugins
- FlexiClub Control for front-end admin tools where enabled

## Common public shortcodes

- `[tpw_member_login]`
- `[tpw_member_profile]`
- `[tpw_join_form]`
- `[tpw_thank_you]`
- `[tpw-control]`

Exact shortcodes used on a site depend on which FlexiClub modules are active.

## Support and documentation

- Admin and help topics: [docs/help/README.md](docs/help/README.md)
- General developer guidance: [docs/developer-guide.md](docs/developer-guide.md)
- Shared UI contract: [docs/architecture/ui/tpw-core-ui-wrapper-enqueue-contract.md](docs/architecture/ui/tpw-core-ui-wrapper-enqueue-contract.md)
- Full release history: [CHANGELOG.md](CHANGELOG.md)

## For developers

Developer and internal documentation remains in the repository. Start with:

- [docs/developer-guide.md](docs/developer-guide.md)
- [docs/architecture/README.md](docs/architecture/README.md)
- [docs/architecture/ui/tpw-core-ui-wrapper-enqueue-contract.md](docs/architecture/ui/tpw-core-ui-wrapper-enqueue-contract.md)
- [docs/help/README.md](docs/help/README.md)
- [CODING_STANDARDS.md](CODING_STANDARDS.md)

This README is intentionally high level. Detailed implementation notes, architecture material, and internal reference docs stay in the repo rather than being duplicated here.