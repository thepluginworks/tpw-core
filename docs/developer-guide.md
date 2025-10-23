### Reusable Email Module

TPW Core includes a reusable Email module intended to be shared by features like Members, FlexiGolf, and Candidates. It provides:

- HTML email sending with a simple wrapper and Reply-To headers
- A contact form UI (modal) with TinyMCE-enabled message and attachment support
- Optional “send a copy to me” support

Files:

- `modules/email/class-tpw-email.php` — TPW_Email: sending, validation, and basic logging hooks
- `modules/email/class-tpw-email-form.php` — TPW_Email_Form: renders modal and handles AJAX submission
- `modules/email/templates/email-form.php` — Form layout
- `modules/email/assets/email.js` — TinyMCE init, validation, and AJAX submit
- `modules/email/assets/email.css` — Optional styling

Initialization:

The loader wires the module automatically. No extra setup needed.

Rendering a form:

```php
echo TPW_Email_Form::render([
	'recipient_name'  => $member->first_name . ' ' . $member->surname,
	'recipient_email' => $member->email,
	'from_name'       => $current_user->display_name,
	'from_email'      => $current_user->user_email,
	'plugin_slug'     => 'flexigolf',
	'subject'         => 'Hello from ' . get_bloginfo('name'),
	'message'         => '',
	'send_copy'       => true,
]);
```

On submission, the form posts to the secured `tpw_email_send` AJAX action. The handler validates the request and calls `TPW_Email::send_email()` with sanitized data. Errors are surfaced to the user and success is acknowledged.

Programmatic send:

```php
$result = TPW_Email::send_email(
	'recipient@example.com',
	[ 'name' => 'Sender Name', 'email' => 'sender@example.com' ],
	'Subject here',
	'<p>Hello world</p>',
	[],            // attachments (validated file paths)
	true           // send copy to sender
);
```

Attachments

- Allowed: PDF, DOCX, JPG, PNG
- Max size: 5MB each
- Use `TPW_Email::validate_attachments( $_FILES['attachments'] )` to validate and move uploads to a temp directory; pass resulting file paths to `send_email()`.

Hooks

- `tpw_email/log` — Action to receive email log details (direction, to/from, subject, attachments, sent). Use to integrate with audit logs.
- `tpw_email/require_login` — Filter to relax or enforce login requirement for sending (default true).

Security notes

- Nonces (`tpw_email_send`) protect submissions.
- Sanitization is applied to all fields.
- Reply-To is set to the sender; the From header is not forced (to allow SMTP plugins to control it), but you can customize via standard WP filters if needed.



## AJAX handlers — required security pattern

When adding new AJAX endpoints in TPW Core or related plugins, follow this pattern consistently:

- Verify nonce with `check_ajax_referer( 'your_action_nonce' )` at the top of the handler. For GET-only endpoints, use `check_ajax_referer( 'your_action_nonce', 'nonce' )` to read a named parameter.
- Check permissions before doing anything: use `current_user_can( 'manage_options' )` or a module-specific gate (e.g., TPW_Member_Access flags). Fail fast if unauthorized.
- Sanitize every input. Prefer specific sanitizers:
	- `sanitize_text_field()` for short text
	- `sanitize_email()`, `sanitize_key()`, `absint()` etc. where appropriate
	- `wp_kses_post()` for limited HTML, and `wp_unslash()` when reading from `$_POST`
- Return JSON using the standard helpers only:
	- `wp_send_json_success( $data )` on success
	- `wp_send_json_error( $message_or_data )` on failures

Notes:
- Never echo raw output in AJAX; always terminate with one of the helpers above (they call `wp_die()` for you).
- Document the expected parameters and required capability in code comments near the handler.



## Accessing Payment Settings

The payment-related settings are also stored within the same `flexievent_settings` option. You can access them using:

```php
$settings = get_option( 'flexievent_settings', [] );
$currency_symbol = $settings['currency_symbol'] ?? '£';
$currency_code = $settings['currency_code'] ?? 'GBP';
```

### Payment Settings and Defaults

| Key               | Default Value | Description |
|------------------|----------------|-------------|
| `currency_symbol` | `£`            | Currency symbol used in price displays (e.g., £10.00) |
| `currency_code`   | `GBP`          | ISO 4217 currency code used for integrations or display |


## Core Email Settings (TPW_Core_Email_Settings)

TPW Core provides a centralised email settings manager that other modules/plugins can consume.

- Storage: WordPress option `tpw_core_email_settings` (array)
- Class: `TPW_Core_Email_Settings`
- Availability: Autoloaded by the core loader; no extra includes necessary

### Defaults

Key                       | Type  | Default | Notes
------------------------- | ----- | ------- | -----
`enable_throttling`       | bool  | true    | Whether to throttle email sends
`max_emails_per_minute`   | int   | 60      | Throttle ceiling
`delay_between_emails`    | int   | 1       | Seconds delay between sends when throttling
`enable_logging`          | bool  | true    | Enable email activity logging via hooks
`send_test_mode`          | bool  | false   | If enabled, force emails to a test recipient
`test_mode_recipient`     | string| ''      | Email to use in test mode
`default_logo_url`        | string| ''      | Fallback logo URL for email templates

Internally, the class merges saved options with these defaults using `wp_parse_args()`, so missing keys are filled safely.

### Getting settings

```php
// Get a single value
$throttling = TPW_Core_Email_Settings::get( 'enable_throttling' );

// Get the full array (merged with defaults)
$email_settings = TPW_Core_Email_Settings::get();
```

### Updating settings

```php
// Provide only the keys you want to change; they will be merged and validated
$saved = TPW_Core_Email_Settings::update([
	'enable_throttling'      => true,
	'max_emails_per_minute'  => 120,
	'default_logo_url'       => 'https://example.com/logo.png',
]);
```

Notes:
- `update()` performs light validation/coercion (booleans/ints/URLs/sanitization) and merges with existing values and defaults.
- UI wiring will come later; this class only manages storage/logic.


## Email Templating System

TPW Core provides a reusable email templating system so plugins can register templates that site admins can customise (subject/body) and render them with dynamic tokens.

### Registering Templates

Register your templates during `init` by calling:

```php
TPW_Email_Template_Registry::register_template([
	'key'              => 'fixture-confirmation', // required, unique (letters, numbers, hyphens, underscores)
	'group'            => 'flexigolf',            // required, plugin/scope group
	'label'            => 'Fixture Confirmation', // required, admin-facing label
	'default_subject'  => 'Your fixture: {fixture-name} on {fixture-date}', // required
	'default_body'     => '<p>Hi {member-name},</p><p>We look forward to seeing you at {fixture-name} on {fixture-date}.</p>', // required HTML
	'editable_subject' => true,                   // optional (default false)
	'editable_body'    => true,                   // optional (default false)
	'placeholders'     => [                       // optional associative array token => description
		'{fixture-name}' => 'Name of the fixture',
		'{fixture-date}' => 'Date of the fixture',
		'{member-name}'  => 'Name of the member receiving the email',
	],
]);
```

Parameters:
- key (string, required): Unique identifier. Allowed: a–z, 0–9, `_` and `-`.
- group (string, required): Plugin or domain group, e.g., `flexigolf`, `members`, `core`.
- label (string, required): Human-friendly name shown in the admin UI.
- default_subject (string, required): Default subject containing tokens.
- default_body (HTML string, required): Default HTML email body containing tokens.
- editable_subject (bool, optional): If false, subject is not editable in admin.
- editable_body (bool, optional): If false, body is not editable in admin.
- placeholders (array, optional): Map of token => description for admin reference.

Templates are stored in memory only (static registry). Admin overrides are stored in the `wp_`-prefixed `tpw_email_templates` DB table.

### Rendering a Template

Use the manager to merge any admin overrides and replace tokens:

```php
$rendered = TPW_Email_Template_Manager::get_rendered_template(
	'fixture-confirmation',
	[
		'{fixture-name}' => 'Oxford v Cambridge',
		'{fixture-date}' => '25 Sept 2025',
	### System Pages Manager

	TPW Core provides a lightweight registry for front-end WordPress pages required by TPW plugins (e.g., Members Profile, Control, Notices). It stores page definitions in a single table and ensures the linked WP pages exist.

	Key API (class `TPW_Core_System_Pages`):
	- `register_page( $slug, [ 'title' => 'My Title', 'shortcode' => '[my_shortcode]', 'plugin' => 'tpw-core', 'required' => 1 ] )` – register or update the page meta.
	- `get_page_id( $slug )` – get the WP page ID (0 if not linked).
	- `get_permalink( $slug )` – get the front-end URL if linked.
	- `ensure_page( $slug )` – create the WP page if missing and link it.
	- `delete_page( $slug )` – trash the page and unlink it.

	Tables are auto-created on plugin load and activation. Other TPW plugins can call `register_page` on `plugins_loaded` to declare their pages.

	Note — My Profile registration:
	- TPW Core now registers the Members “My Profile” page in the System Pages table under slug `my-profile` with shortcode `[tpw_member_profile]`.
	- Existing logic remains: Core still creates the WP page on activation where needed and keeps using the `tpw_member_profile_page_id` option.
	- Where Core resolves the profile URL, it first tries `TPW_Core_System_Pages::get_permalink( 'my-profile' )` and falls back to `get_permalink( get_option( 'tpw_member_profile_page_id' ) )` or the front‑end route. This provides a safe migration path without breaking existing menus or links.

		'{member-name}'  => 'Stuart Moodey',
	]
);

// $rendered = [
//   'subject' => 'Your fixture: Oxford v Cambridge on 25 Sept 2025',
//   'body'    => '<p>Hi Stuart Moodey,</p><p>We look forward to seeing you at Oxford v Cambridge on 25 Sept 2025.</p>',
//   'use_logo'=> true,
// ];
```

Notes:
- Token replacement is performed with simple `str_replace()` across both subject and body.
- Tokens that aren’t present in the provided array are left as-is. You may post-process to replace missing values with `N/A` if desired.
- The `use_logo` boolean indicates whether to include the fallback logo for this template when rendering/sending. Honour this in your email wrapper.

### Admin Editing

Under Settings → TPW Core → Email Templates, site admins can:
- See all registered templates grouped by plugin/scope
- Edit subject/body (only if the template marked them as editable)
- Toggle “Include fallback logo” for that template
- View the list of available placeholders (with descriptions)
- Reset to defaults (removes overrides from the DB)

## Members Admin Form Extension Hooks

You can extend the Core Members module admin forms at:

- `/manage-members/?action=add`
- `/manage-members/?action=edit&id=123`

These hooks let other plugins add custom fields (render) and save their values.

### Render Hook

Action: `tpw_members_admin_form_extra_fields`

Signature:

```php
do_action( 'tpw_members_admin_form_extra_fields', string $context, ?int $member_id, ?object $member, array $meta );
```

Args:
- `$context`: 'add' or 'edit'
- `$member_id`: null on add; member ID on edit
- `$member`: null on add; member object (core fields) on edit
- `$meta`: associative array of all member meta (empty on add)

Where it runs:
- Add form: after the core fields loop and before the submit button
- Edit form: after the core fields loop (and before photos section)

Use standard HTML inputs and ensure your names are unique (e.g., prefix with your plugin slug). If you need file uploads on the Add form, use a custom AJAX endpoint; the Edit form already supports file uploads.

### Save Hook

Action: `tpw_members_admin_form_after_save`

Signature:

```php
do_action( 'tpw_members_admin_form_after_save', string $context, int $member_id );
```

Runs after core fields and meta are saved for both add and edit submissions, allowing you to persist any extra fields you rendered.
## Members module activation and system pages

- To enable the Members module UI from an add-on plugin, define the constant `TPW_MEMBERS_ACTIVE` as true as early as possible in your plugin bootstrap. Core provides a convenience helper:

```php
// Returns true when Members module is active
tpw_members_module_enabled(): bool
```

- System Pages: Core registers the "My Profile" page under slug `my-profile` and shortcode `[tpw_member_profile]`. Add-on plugins can ensure this page exists by calling:

```php
TPW_Core_System_Pages::ensure_page( 'my-profile' );
```

- Add-on owned pages: If an add-on provides a front-end management UI for members (e.g. Lodge Meetings Manage Members), it should register its own System Page row so Core can manage the linked WP page. Example:

```php
TPW_Core_System_Pages::register_page( 'manage-members', [
	'title'     => 'Manage Members',
	'shortcode' => '[tpw_manage_members]',
	'plugin'    => 'tpw-rsvp-lodge-meetings',
	'required'  => 1,
] );
TPW_Core_System_Pages::ensure_page( 'manage-members' );
```

- Access control: Use `TPW_Member_Access` helpers to gate UI routes. Only admins or committee should access the Manage Members UI. Do not expose admin actions to regular members.


### Example: Player Home Clubs

Add a small section to capture a member's home clubs (multiple) and save them as user meta.

```php
// In your plugin bootstrap, e.g. on init or plugins_loaded
add_action( 'tpw_members_admin_form_extra_fields', function( $context, $member_id, $member, $meta ) {
	// Read existing values on edit; none on add
	$clubs = [];
	if ( $context === 'edit' && $member_id && $member && ! empty($member->user_id) ) {
		$clubs = (array) get_user_meta( (int) $member->user_id, 'tpw_home_clubs', true );
		if ( empty($clubs) ) $clubs = [];
	}
	?>
	<fieldset class="tpw-fieldset">
		<legend>Player Home Clubs</legend>
		<div id="tpw-home-clubs">
			<?php
			$values = !empty($clubs) ? $clubs : [''];
			foreach ( $values as $idx => $val ) {
				echo '<div class="tpw-inline-input-action" style="margin-bottom:6px;">';
				echo '<input type="text" name="tpw_home_clubs[]" value="' . esc_attr( $val ) . '" placeholder="e.g., Royal Liverpool GC" />';
				echo '</div>';
			}
			?>
		</div>
		<p class="description">Add one or more clubs (free text). Duplicate and empty values will be filtered on save.</p>
		<button type="button" class="button" onclick="(function(btn){ var c=document.getElementById('tpw-home-clubs'); var d=document.createElement('div'); d.className='tpw-inline-input-action'; d.style.marginBottom='6px'; d.innerHTML='<input type=\'text\' name=\'tpw_home_clubs[]\' placeholder=\'e.g., Royal Liverpool GC\' />'; c.appendChild(d); })(this)">Add another club</button>
	</fieldset>
	<?php
}, 10, 4 );

add_action( 'tpw_members_admin_form_after_save', function( $context, $member_id ) {
	// Persist as user meta, deduplicate and sanitize
	$controller = new TPW_Member_Controller();
	$member = $controller->get_member( (int) $member_id );
	if ( ! $member || empty($member->user_id) ) return;

	$clubs = isset($_POST['tpw_home_clubs']) && is_array($_POST['tpw_home_clubs']) ? $_POST['tpw_home_clubs'] : [];
	$clubs = array_map( 'sanitize_text_field', array_filter( array_map( 'trim', $clubs ) ) );
	$clubs = array_values( array_unique( $clubs ) );

	update_user_meta( (int) $member->user_id, 'tpw_home_clubs', $clubs );
}, 10, 2 );
```

Notes:
- If you need to store data in the TPW member meta table instead, use `TPW_Member_Meta::save_meta( $member_id, 'your_key', $value )` in the save hook.
- For complex UIs, you can enqueue your own admin JS/CSS on the manage-members page using standard WP enqueue hooks and checking `is_page('manage-members')`.


## Manage Members — Buttons Extension Hooks

On `/manage-members/`, the top toolbar groups actions into two sections with buttons: Admin (left) and Tools (right). You can append your own buttons to either group using these actions:

### Hooks

- `tpw_members_admin_buttons_end` — Fires at the end of the Admin button group. Use to add admin actions like “Bulk Invite”, “Sync from CRM”, etc.
- `tpw_members_tools_buttons_end` — Fires at the end of the Tools button group. Use to add utilities like exports, reports, or settings shortcuts.

Both hooks receive a single `$context` array with useful values:

Key                | Type   | Description
------------------ | ------ | -----------
`page_url`         | string | Permalink URL for the Manage Members page
`export_url`       | string | Only for Tools hook; pre-built Export CSV URL reflecting current filters
`current_view`     | string | `list` or `card`
`selected_status`  | string | Current status filter value (may be empty)
`search`           | string | Current text search
`per_page`         | int    | Current per-page value
`is_admin`         | bool   | True when the current user has admin rights in this module

### Examples

Append a new Admin button that navigates to a custom action on the same page:

```php
add_action( 'tpw_members_admin_buttons_end', function( array $ctx ) {
	// Build a URL on the same page: /manage-members/?action=bulk_invite
	$url = add_query_arg( 'action', 'bulk_invite', $ctx['page_url'] );
	echo '<a class="tpw-btn tpw-btn-secondary" href="' . esc_url( $url ) . '" role="button">Bulk Invite</a>';
});
```

Append a Tools button that opens a custom report:

```php
add_action( 'tpw_members_tools_buttons_end', function( array $ctx ) {
	$url = admin_url( 'admin.php?page=my-report&from=members' );
	echo '<a class="tpw-btn tpw-btn-light" href="' . esc_url( $url ) . '" role="button">Member Report</a>';
});
```

### Styling guidance

Use the existing button classes for visual consistency:

- `tpw-btn tpw-btn-primary` — Primary call to action
- `tpw-btn tpw-btn-secondary` — Secondary action
- `tpw-btn tpw-btn-light` — Tertiary/light button
- `tpw-btn tpw-btn-admin` — Admin/settings emphasis

Buttons are inline within a flex container, so keep labels short. Use `role="button"` on links for accessibility parity with buttons.


## WordPress Roles and Member Access

Imported members are created with no WordPress role (wp_capabilities = none). This is intentional.

- WordPress roles are not used for access control in TPW Core or FlexiGolf.
- Members with no role can still log in to WordPress.
- Front-end access (e.g., FlexiGolf screens) is determined by `tpw_members` table values such as `status` (Active, Honorary, Life Member).
- Leaving role = none prevents unwanted backend access while still allowing front-end login.
- Do not assign default roles (e.g., Subscriber) unless there is a specific need to grant WordPress dashboard access.

This ensures that login and permissions remain managed entirely by TPW Core and related plugins, not by native WordPress roles.


## Postcode Lookup — Full Address Mode (Google)

The core postcode helper supports two modes:

- basic: Returns town/county/coords for a postcode using the configured provider (Postcodes.io, GetAddress.io, or Google).
- full: Returns a list of street-level address options for a postcode — available with Google only.

Settings

- Stored under `tpw_postcode_settings` with `provider` set to one of `postcodesio`, `getaddress`, or `google` and `google_api_key` when using Google.
- The frontend enqueues `assets/js/tpw-postcode-lookup.js` and passes the selected provider to `window.tpwPostcode.provider`.

Server API

- AJAX action: `tpw_lookup_postcode`
- Params:
	- `postcode` (string, required)
	- `country` (string, optional, default `GB`)
	- `mode` (string, `basic`|`full`, optional, default `basic`)
	- `street_prefix` (string, optional, used to filter addresses starting with a number prefix)
- Behavior:
	- If `mode=full` and provider is not Google, returns `{ error: 'full_not_supported', message: 'Full address lookup is only available with Google Maps.' }`.
	- If `mode=full` and Google API key is available, queries the Geocoding API with `address=<postcode>` and returns an `addresses` array of options with fields: `label`, `address1`, `town`, `county`, `postcode`, `country`.

Frontend behavior

- When the user clicks "Lookup" with a postcode:
	- If provider is Google, a "Select Address" dropdown is shown with any returned options.
	- On selection, the following fields are populated when present: `address1` (street_number + route), `town` (postal_town/locality), `county` (admin_area_level_2 or 1), `postcode`, and `country`.
	- If provider does not support full lists, a message is shown: "This provider does not support full address lists." and the basic town/county lookup still runs.
- The postcode input remains editable and changing it hides the dropdown and clears any inline warning.
- Results are cached per postcode in `sessionStorage` for the session.

Notes

- Google API errors (invalid key, over quota) are written to the debug log when `WP_DEBUG` is enabled with a `[TPW Postcodes][google-full]` prefix.
- For basic lookups, the helper continues to map UK postcodes to `town` from `post_town` and `county` from `admin_county` (fallback `admin_district`).

## TPW Control (Front-end Admin Hub)

TPW Control centralizes front‑end admin tools behind a single shortcode and routed sub‑pages.

- Shortcode: `[tpw-control]`
- Route format: `/tpw-control/?action=` where `action` matches a registered section key.
- Default page (no `action`): Dashboard.

Conventions
- Front‑end only for now; architected for an optional future wp‑admin UI.
- Permissions leverage Members module flags and statuses (no `is_member` flag).
- Sections can be added by other plugins via filter and action hooks.

Autocreate Control Page (for TPW plugins)
- TPW plugins should auto‑create a WordPress Page titled `TPW Control` with content `[tpw-control]` on activation, if one does not exist. This keeps a stable front‑end entry point for society admins.

Sections Registry
Use the `tpw_control/sections` filter to register or modify sections. Each section is an associative array with:

```
[
	'key'        => 'my-section',         // unique id (also used in ?action=)
	'label'      => 'My Section',         // sidebar label
	'capability' => '__tpw_control_is_admin__', // see capability markers below
	'callback'   => [ $class, 'render' ], // callable to render content
	'position'   => 30,                   // sort order in sidebar
	'icon'       => 'dashicons-admin-generic' // optional Dashicons class or URL
]
```

Capability markers understood by TPW Control:
- `__tpw_control_is_member__` — current user is a valid member per `TPW_Member_Access::is_member_current()`
- `__tpw_control_is_admin__` — current user is an admin per `TPW_Member_Access::is_admin_current()`
- `__tpw_control_is_committee_or_admin__` — member with committee flag or admin

Alternatively, set `capability` to a callable `(array $section) => bool`, a WordPress capability string, `true` (any logged‑in user), or `false` (public).

Router and Hooks
- All rendering goes through `TPW_Control_Router` which reads `?action=` and dispatches.
- Hooks:
	- `tpw_control/register_sections` — fire early to let plugins prepare section definitions.
	- `tpw_control_register_sections` (filter) — preferred filter to add/modify sections (new in Phase 5). Back-compat: `tpw_control/sections` still runs after this.
	- `tpw_control_can_manage` (filter) — global gate for accessing the TPW Control hub; defaults to admins only. Return true to allow more roles.
	- `tpw_control/sidebar_after_menu` — append content below the menu list.
	- `tpw_control/render_upload_pages` — output Upload Pages UI into the Upload Pages section.
	- `tpw_control/upload_pages_shortcode` (filter) — return a shortcode string to be rendered inside the Upload Pages section.
	- `tpw_control/render_menu_manager` — output the front‑end WP Menu Manager UI.
	- `tpw_control_render_section_{slug}` — action fired when a section with key `{slug}` does not provide a callable `callback`. Use this to render fully external sections (new in Phase 5).

Templates
- Layout: `modules/tpw-control/templates/layout.php` (sidebar + content).
- Dashboard: `modules/tpw-control/templates/dashboard.php`.
- Sections: `modules/tpw-control/templates/sections/*.php`.

Assets
- CSS: `modules/tpw-control/assets/css/tpw-control.css`
- JS: `modules/tpw-control/assets/js/tpw-control.js`

Shortcode Routing
- The shortcode renders the layout and content for the section indicated by `?action=`.
- Example URLs after you place the shortcode on a page:
	- `https://example.com/tpw-control/?action=upload-pages`
	- `https://example.com/tpw-control/?action=menu-manager`

### Developer helpers (Phase 5)

- `tpw_control_section_url( $slug )` — returns a URL to the current page with `?action=$slug` appended, e.g., `/tpw-control/?action=upload-pages`.
- `tpw_control_user_has_access( $visibility, $member = null )` — evaluates a Visibility JSON object/array for the current user (or a provided `$member`). Admins always pass. See Visibility JSON spec below.

### Adding external sections (Phase 5)

1) Register your section using the filter:

```php
add_filter( 'tpw_control_register_sections', function( array $sections ) {
	$sections['fixtures'] = [
		'key'        => 'fixtures',
		'label'      => 'Fixtures',
		'visibility' => [ 'logged_in' => true, 'flags_any' => ['is_admin','is_committee'] ],
		// No callback provided → router will fire tpw_control_render_section_fixtures
		'position'   => 40,
		'icon'       => 'dashicons-calendar-alt',
	];
	return $sections;
});
```

2) Render via the dynamic action:

```php
add_action( 'tpw_control_render_section_fixtures', function( array $section ) {
	echo '<h2>Fixtures</h2>';
	// Output your UI here; honour nonces/POST handling and use tpw_control_section_url('fixtures') for links.
});
```

Notes:
- Admins are always allowed; otherwise `visibility` is checked via `tpw_control_user_has_access()`.
- You can still use the legacy `tpw_control/sections` filter; it runs after `tpw_control_register_sections`.

### Upload Pages — Section spec

Purpose
- Front-end tool for creating simple “Upload Pages” that display downloadable files grouped by year.

UI fields
- Page
	- Title (string, required)
	- Slug (string, required, unique)
	- Description (string, optional)
	- Visibility (JSON, optional; defaults to admin-only)
- Files Manager
	- Upload files (multiple)
	- Year (int/string, optional, saved per file)
	- Label (string, optional, saved per file)
	- Reorder (drag/sort preserves `sort_order`)
	- Delete file

Routing
- List: `/tpw-control/?action=upload-pages`
- Edit: `/tpw-control/?action=upload-pages&sub=edit&upload_page_id=<id>`

Linked WordPress Page
- Each Upload Page automatically creates a corresponding WordPress Page when created.
- Content of the WordPress Page is a shortcode: `[tpw_upload_page slug="<upload-page-slug>"]`.
- The WordPress Page title and shortcode are kept in sync when the Upload Page is renamed; the permalink/slug of the WordPress Page is not changed to avoid breaking external links.
- Deleting an Upload Page moves the linked WordPress Page to the Trash.
- If the linked Page is deleted manually, the Upload Page edit screen shows a “Recreate Page” button to re‑generate it.

Public Shortcode
- `[tpw_upload_page slug="..."]` renders the Upload Page on a normal WordPress Page or post. Internally this calls `TPW_Control_Upload_Pages::render_page_public( $slug )` and applies the Upload Page’s visibility rules.

Visibility JSON (supported keys)
- `public` (bool): true allows all visitors (logged out users included)
- `logged_in` (bool): require a logged-in user
- `flags_any` (array<string>): any of `is_admin`, `is_committee`, `is_match_manager`, `is_noticeboard_admin`
- `flags_all` (array<string>): user must have all listed flags
- `flags_not` (array<string>): user must NOT have any listed flags
- `allowed_statuses` (array<string>): member statuses allowed (e.g., `Active`, `Honorary`)

Rules
- Admins always allowed.
- If `public` is true, access is granted immediately.
- If `logged_in` is true and user is not logged in, access is denied.
- Flags and statuses are evaluated only when a member record is present; if required but unavailable, access is denied.
- With no visibility specified, default behavior requires a valid TPW member (same as `__tpw_control_is_member__`).

Data model (internal)
- Pages table stores Title, Slug, Description, Visibility (JSON), created/updated timestamps.
- Files table stores Attachment ID, Label, Year, Sort order; displayed grouped by Year.

### Menu Manager — Section spec

Purpose
- Front-end UI to manage WordPress nav menus without wp-admin, with TPW Control-aware links and per-item visibility.

Capabilities
- Create/select a WordPress menu
- Add links:
	- TPW Control sections (registered via filters)
	- Upload Pages (from Upload Pages section)
	- Custom links (URL + label)
- Edit existing menu items (label, URL) and delete items
- Per-item metadata:
	- `_tpw_visibility_json` (LONGTEXT): Visibility JSON as above; used to filter items on front-end
	- `_tpw_requires_login` (bool): If true, item is hidden from logged-out visitors

Routing
- `/tpw-control/?action=menu-manager[&menu_id=<id>]`

Notes
- All forms/actions are nonce-protected.
- Admin-only by default. Visibility logic for item meta mirrors `tpw_control_user_has_access()`.
- Theme integration: A front-end filter/walker can use `_tpw_visibility_json` and `_tpw_requires_login` to hide items the current user shouldn’t see.
