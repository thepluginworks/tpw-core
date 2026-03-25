=== TPW Core ===
Contributors: thepluginworks
Tags: members, payments, rsvp, admin-tools, tpw
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.15.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shared member, payment, page, and admin tools for TPW-powered WordPress sites and TPW add-on plugins.

== Description ==

TPW Core is the shared foundation used by TPW plugins.

It helps site owners and administrators run consistent TPW features across their site, including:

- member login and profile flows
- join or signup journeys
- payment method settings and payment records
- required shared pages for TPW modules
- common admin tools and front-end utilities

TPW Core is typically installed alongside other TPW plugins that depend on it.

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
