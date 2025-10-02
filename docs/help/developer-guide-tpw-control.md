# TPW Control — Developer Guide (Plugin Authors)

TPW Control provides a front‑end admin hub other plugins can extend.

## Quick reference

- Shortcode: `[tpw-control]`
- Route: `/tpw-control/?action=<slug>`
- Section registry filters:
  - Preferred: `tpw_control_register_sections`
  - Legacy: `tpw_control/sections`
- Dynamic render action: `tpw_control_render_section_{slug}`
- Helpers:
  - `tpw_control_section_url( $slug )`
  - `tpw_control_user_has_access( $visibility, $member = null )`

## Register a section

```php
add_filter( 'tpw_control_register_sections', function( array $sections ) {
	$sections['my-plugin'] = [
		'key'        => 'my-plugin',
		'label'      => 'My Plugin',
		'visibility' => [ 'logged_in' => true, 'flags_any' => ['is_admin'] ],
		// Leave out callback to render via dynamic action
		'position'   => 50,
		'icon'       => 'dashicons-admin-generic',
	];
	return $sections;
});
```

If you prefer, you can provide a callable `callback` instead of using the dynamic action.

## Render your section (dynamic action)

```php
add_action( 'tpw_control_render_section_my-plugin', function( array $section ) {
	// Output your UI directly
	echo '<h2>My Plugin</h2>';
	// For links back to your section:
	echo '<a href="' . esc_url( tpw_control_section_url('my-plugin') ) . '">Back</a>';
});
```

## Visibility JSON

Visibility constraints for both sections and menu items are evaluated with `tpw_control_user_has_access()`.

Supported keys:
- `public` (bool)
- `logged_in` (bool)
- `flags_any` (array<string>): any of `is_admin`, `is_committee`, `is_match_manager`, `is_noticeboard_admin`
- `flags_all` (array<string>)
- `flags_not` (array<string>)
- `allowed_statuses` (array<string>)

Rules:
- Admins always pass.
- If `public` is true, access is granted.
- If `logged_in` is true and user is not logged in, access is denied.
- Flags and statuses require a member record; if unavailable, access is denied.

## URL helper

```php
$url = tpw_control_section_url( 'upload-pages' );
```

Builds `/tpw-control/?action=upload-pages` for the current page.

## Best practices

- Always add nonces to forms and validate POSTs.
- Use the UI’s permission checks to gate mutations (admins are always allowed).
- Avoid nested forms; use separate forms for separate actions.
- Prefer saving JSON in LONGTEXT for broad DB compatibility.
