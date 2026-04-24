=== TPW Core ===
Contributors: thepluginworks
Tags: members, payments, rsvp, admin-tools, tpw
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.25.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shared member, payment, page, and admin tools for TPW-powered WordPress sites and TPW add-on plugins.

== Description ==

TPW Core is the central engine that powers ThePluginWorks plugin ecosystem.

It provides shared functionality used across TPW plugins, ensuring everything works together consistently and reliably.

With TPW Core installed, your plugins can:

- manage member accounts, roles, and profiles
- handle login, registration, and access control
- process payments and track transactions
- apply consistent branding and UI styles
- share system pages and common functionality across modules

TPW Core is required by many TPW plugins and is typically installed automatically when needed.

You do not use TPW Core directly — it works behind the scenes to support the plugins you install.

Learn more about ThePluginWorks ecosystem:
https://www.thepluginworks.com

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/tpw-core/` or install it using your normal plugin deployment process.
2. Activate TPW Core in WordPress.
3. Activate any TPW add-on plugins that require TPW Core.
4. Review shared settings under Settings > TPW Core.
5. Check that your required login, profile, join, or thank-you pages are set up for the TPW modules you use.

== Frequently Asked Questions ==

= What is TPW Core used for? =
TPW Core provides the shared services and admin tools used by TPW plugins, including member flows, payments, shared pages, and common settings.

= Can I use TPW Core on its own? =
Usually it is used together with other TPW plugins. Some shared screens and shortcodes may still be available on their own, but its main role is to support the wider TPW plugin set.

= Where do I manage core settings? =
Go to Settings > TPW Core in the WordPress admin area.

= Do I need to create any pages? =
Many TPW setups use shared pages such as login, profile, join, control, or thank-you pages. The exact pages depend on which TPW plugins are active on your site.

== Shortcodes ==

= [tpw_member_login] =
Displays a front-end member login form.

= [tpw_member_profile] =
Displays the member profile area.

= [tpw_join_form] =
Displays the public join or signup form when the join system is enabled.

= [tpw_thank_you] =
Displays a thank-you or confirmation view for supported TPW payment and RSVP flows.

= [tpw-control] =
Displays the TPW Control front-end admin hub where this is enabled for your site.

== Changelog ==

= 1.25.0 =
- Members: CSV member imports now stage password setup recipients only for newly created and linked WordPress users, then present a post-import action to send secure setup emails after the import completes.
- Members: the CSV import results screen now shows password setup eligibility, excluded-row counts, and final sent, failed, skipped, and remaining totals for each import run.
- Members: CSV import password setup sending now runs through a protected batched admin-post flow with transient-backed progress tracking so imports do not send email inline and authorised managers can resume a saved run safely.

= 1.24.0 =
- Members: added an optional password setup email during Add Member so linked WordPress users can receive a secure setup link as soon as the member record is created.
- Members: linked members can now receive a fresh password setup link directly from Edit Member, with dedicated success and failure notices in Manage Members.
- Email: registered a dedicated Member Password Setup email template so the password setup subject and body can be overridden from TPW Core settings.

= 1.23.5 =
- UI: updated the shared light button selector so link elements using `.tpw-btn-light` receive the same light button styling as button elements.

= 1.23.4 =
- Members: corrected front-end member login and password reset handling so quoted and slashed passwords are passed consistently to WordPress during reset and login.
- Members: reset and login notices now survive redirects more reliably, clear stale reset errors after a successful reset, and show a clear password reset success message.
- Members: the member login shortcode now renders separate login, lost-password, and reset-password states so the normal login page no longer includes a competing hidden auth form.

= 1.23.3 =
- TPW Control: moved Upload Pages admin styling out of the section template and into the existing scoped TPW Control stylesheet, keeping the current interface and behaviour unchanged.
- TPW Control: centred the existing TPW Control shell within the viewport using the existing container so the sidebar and content layout stay unchanged.

= 1.23.2 =
- Updates: the WordPress View version details modal now loads its Description and Changelog content from the bundled plugin readme so release notes display properly for GitHub-delivered updates.
- Documentation: refreshed the public plugin description to better explain TPW Core's role within the wider ThePluginWorks plugin ecosystem.

= 1.23.1 =
- Members: fixed a regression where removing the TPW Administrator flag from a linked member could leave the linked WordPress account with the Administrator role.
- Members: member-to-WordPress role synchronization is now enforced centrally during member create and update flows so administrator downgrades are applied consistently.

= 1.23.0 =
- Members: added a dedicated Privacy settings tab to separate visibility and profile-sharing controls from general member settings.
- Members: added a configurable view-only privacy override so selected roles or capabilities can see hidden members without gaining management access.

= 1.22.1 =
- Members: refined the self-service profile editor for the visibility checkbox with clearer spacing, a larger touch target, and a simple Yes label.
- Members: admin screens now use the neutral field label while the member profile keeps the fuller explanatory wording.

= 1.22.0 =
- Members: added a profile visibility setting so members can opt out of appearing in member-facing directories, detail views, contact actions, and related member selection lists.
- Members: authorised administrators and members managers continue to see all member records in management and oversight views.

= 1.21.2 =
- UI: refined shared primary and secondary TPW button classes so they consistently keep the configured padding, height, and border-radius tokens across shared button usage.

= 1.21.1 =
- UI: refined primary TPW button styling so shared primary button classes consistently keep the configured padding, height, and border radius tokens.

= 1.21.0 =
- UI: added configurable large button size tokens for `.tpw-btn-lg`, including large font size, padding, and height values that are now emitted as shared branding CSS variables.
- UI: reorganised the Branding settings layout so button controls appear with the Buttons section and the scoped UI Theme section now sits after Semantic Notice Colours.

= 1.20.1 =
- UI: refined the shared TPW button stylesheet to provide a clearer outline button variant for primary and secondary buttons.
- Members: refined FlexiGolf activation detection used for conditional visibility in member/admin field flows.

= 1.20.0 =
- Menus: menu item visibility settings now support Gallery Admins across add and edit flows in TPW Control menu management.
- Menus: editing a menu item from the menu manager modal now preserves its existing order and structure instead of moving the item to the bottom of the menu.
- Members: FlexiGolf activation detection now recognises TPW FlexiGolf bootstrap markers so Match Managers visibility can be shown reliably when the TPW FlexiGolf plugin is active.

= 1.19.0 =
- Members: TPW Core now stores a canonical membership entitlement on member records for dependent TPW plugins to read consistently.
- Members: membership entitlement support includes a dedicated database column, strict machine-value validation, and code-controlled options rather than loose field configuration.
- Members: the admin Add Member and Edit Member field remains hidden by default and is only exposed when a relevant dependent plugin enables it.

= 1.17.0 =
- Improved new member account creation so TPW Core now generates WordPress usernames automatically from member name data instead of relying on manually entered usernames.
- Added a safer CSV import option so administrators can generate new usernames by default while still preserving imported usernames when performing true migrations.
- Reduced username-related admin confusion by treating usernames as internal identifiers for new accounts while leaving existing usernames unchanged.

= 1.16.0 =
- Improved linked member account email updates so TPW Core now keeps the member record and linked WordPress account email address in sync more reliably.
- Reduced confusion on the Member Field Settings screen by hardening non-auth setting fields against unwanted browser or password-manager autofill.
- Fixed the core Username field label in Member Field Settings so it remains visible but cannot be edited accidentally.

= 1.15.7 =
- Removed the bundled Freemius SDK and startup hooks now that TPW Core updates are delivered through the GitHub release and manifest flow.
- Kept the existing GitHub-based updater, release packaging, and normal plugin runtime behaviour unchanged.

= 1.15.4 =
- Improved update diagnostics so one-click TPW Core updates now record the exact WordPress failure point when an update cannot be completed.
- Added clearer logging for package download, filesystem access, unpacking, and plugin replacement steps to help identify update issues more precisely.

= 1.15.0 =
- Improved WordPress update visibility so administrators can see new TPW Core releases more reliably in Plugins and Dashboard > Updates.
- Improved updater refresh behaviour after releases so WordPress picks up the latest TPW Core package information more consistently.

= 1.14.43 =
- Refreshed the public plugin documentation to make setup and usage guidance clearer for site owners and administrators.
- Improved the public repository overview while keeping full developer and internal documentation in the source repository.

= 1.14.42 =
- Improved plugin update detection in WordPress so administrators can see available TPW Core updates more reliably.
- Added clearer version and package details in the plugin update information screen.

= 1.14.41 =
- Improved release packaging and delivery so TPW Core install packages are provided more consistently.
- Added a stable public version manifest to support update checking across TPW sites.

= 1.14.40 =
- Reduced signup debug noise in stored records while keeping support-relevant data intact.
- Improved admin stability by loading translations at the correct time and removing temporary debug logging.
- Improved postcode lookup defaults when a site country is configured.

= 1.14.38 =
- Corrected plugin commercial metadata to better match the current TPW Core distribution model.

= 1.14.37 =
- Standardised the default site society assignment used for new member and household records.

= 1.14.36 =
- Improved join finalization so new member records keep the correct society assignment when that information is already present.

= 1.14.35 =
- Protected required join fields so core signup essentials cannot be disabled accidentally.

= 1.14.34 =
- Improved release package compatibility without changing plugin runtime behaviour.

= 1.14.24 =
- Updated Square payment handling so TPW Core works cleanly with the external Square add-on when used.

= 1.14.23 =
- Added support for switching the active join provider while keeping the shared TPW join page in place.

= 1.14.21 =
- Improved compatibility handling in the Member Details modal without changing the visible admin experience.

= 1.14.15 =
- Added a read-only Identity Audit screen under TPW Core Settings for site diagnostics.

= 1.14.6 =
- Added the public `[tpw_join_form]` shortcode and automatic Join page support.

= 1.14.0 =
- Added the Members Manager permission flag for delegated member administration.

= 1.13.0 =
- Added central email logging with an Email Logs tab in TPW Core Settings.

= 1.12.0 =
- Added a shared profile sections registry so TPW plugins can extend the member profile area.

= 1.11.0 =
- Added a Volunteer field for member records.

= 1.10.0 =
- Added a same-page gallery browser with gallery index support.

= 1.9.3 =
- Improved front-end login and reset redirects so destination pages are preserved more reliably.
