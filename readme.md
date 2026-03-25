# TPW Core

TPW Core is the shared foundation plugin used by TPW sites and TPW add-on plugins. It provides the common member, payment, page, branding, and admin tools that other TPW modules rely on.

## What TPW Core is

TPW Core is the central service layer for TPW-powered sites. It helps administrators run consistent member journeys, shared pages, payments, and reusable admin tools across the TPW plugin suite.

This repository keeps the public overview concise while preserving the deeper implementation and architecture documentation in the repo for development use.

## What it provides

- Shared member and profile features
- Shared payment methods and payment records
- Front-end login, profile, and join flows
- System page registration for TPW plugins
- Shared TPW settings, branding, and admin UI assets
- Common utilities such as email and postcode lookup support

## TPW plugins that depend on it

TPW Core is intended to support TPW feature plugins such as:

- TPW event and RSVP plugins
- TPW payments-enabled modules
- TPW members and join-flow extensions
- TPW gallery, notices, menus, and control tools

If a TPW plugin expects shared member, payment, or front-end account functionality, it will typically depend on TPW Core being active.

## Installation and updates

1. Install and activate TPW Core in WordPress.
2. Install any TPW add-on plugins that depend on it.
3. Review the shared settings under WordPress Settings -> TPW Core.
4. Confirm that required pages such as login, profile, join, or thank-you pages are set up for your site.

TPW Core includes its own update and packaging flow so production sites receive the intended install package structure.

## Key admin areas

- Settings -> TPW Core for shared configuration such as branding, payments, and core options
- Member-related TPW screens used by dependent plugins
- TPW Control for front-end admin tools where enabled

## Common public shortcodes

- `[tpw_member_login]`
- `[tpw_member_profile]`
- `[tpw_join_form]`
- `[tpw_thank_you]`
- `[tpw-control]`

Exact shortcodes used on a site depend on which TPW modules are active.

## Support and documentation

- Admin and help topics: [docs/help/README.md](docs/help/README.md)
- General developer guidance: [docs/developer-guide.md](docs/developer-guide.md)
- Full release history: [CHANGELOG.md](CHANGELOG.md)

## For developers

Developer and internal documentation remains in the repository. Start with:

- [docs/developer-guide.md](docs/developer-guide.md)
- [docs/architecture/README.md](docs/architecture/README.md)
- [docs/help/README.md](docs/help/README.md)
- [CODING_STANDARDS.md](CODING_STANDARDS.md)

This README is intentionally high level. Detailed implementation notes, architecture material, and internal reference docs stay in the repo rather than being duplicated here.