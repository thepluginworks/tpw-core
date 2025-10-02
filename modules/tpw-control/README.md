# TPW Control (Front-end Admin Hub)

Shortcode: [tpw-control]
Route format: /tpw-control/?action=
Default page: Dashboard (when no action provided)

Sections included:
- Dashboard
- Upload Pages (bridge)
- Front-end Menu Manager (bridge)

Hookable registry:
- Use the `tpw_control/sections` filter to add sections to the sidebar and router.
- Use `tpw_control/register_sections` action to prepare registrations early if needed.

Capability markers accepted in section definitions:
- __tpw_control_is_member__
- __tpw_control_is_admin__
- __tpw_control_is_committee_or_admin__

Templates:
- templates/layout.php (sidebar + content area)
- templates/dashboard.php
- templates/sections/*.php

Assets:
- assets/css/tpw-control.css
- assets/js/tpw-control.js

Notes:
- TPW plugins should auto-create a WP Page titled "TPW Control" with content [tpw-control].
