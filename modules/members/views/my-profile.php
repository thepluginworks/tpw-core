<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Render the same output as the [tpw_member_profile] shortcode as a front-end fallback
require_once TPW_CORE_PATH . 'modules/members/shortcodes/member-profile.php';

echo do_shortcode('[tpw_member_profile]');
