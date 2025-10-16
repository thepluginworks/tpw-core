<?php
/**
 * Core admin functions: screen detection, asset enqueue, header rendering.
 */

if ( ! function_exists( 'tpw_core_is_admin_screen' ) ) {
	/**
	 * Detects whether the current screen belongs to TPW Core or one of its CPTs.
	 *
	 * @return bool True if the current screen is a TPW Core screen or CPT, false otherwise.
	 */
	function tpw_core_is_admin_screen() {
		$screen = get_current_screen();
		$is_tpw_screen = false;

		if ( $screen ) {
			// Check both the screen ID and post type for 'tpw'.
			if ( strpos( $screen->id, 'tpw' ) !== false ) {
				$is_tpw_screen = true;
			} elseif ( isset( $screen->post_type ) && strpos( $screen->post_type, 'tpw' ) !== false ) {
				$is_tpw_screen = true;
			}
		}

		return apply_filters( 'tpw_core_is_admin_screen', $is_tpw_screen, $screen );
	}
}

/**
 * Enqueues admin CSS only for TPW Core pages and CPT screens.
 */
add_action( 'admin_enqueue_scripts', function () {

    // Detect our TPW page (?page=tpw-... or tpw_...) or TPW CPT screens.
    $is_tpw = false;

    // A) Custom plugin pages by slug (allow hyphen or underscore)
    if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        // Allow-list of TPW pages (filterable)
        $allow = apply_filters( 'tpw_core_admin_pages', [
            'tpw-lodge-rsvp-submissions',
            // add more slugs here as needed
        ] );

        if ( 0 === strpos( $page, 'tpw-' ) || 0 === strpos( $page, 'tpw_' ) || in_array( $page, $allow, true ) ) {
            $is_tpw = true;
        }
    }

    // B) TPW CPTs (optional; keep if you also want CPT styling)
    if ( function_exists( 'get_current_screen' ) ) {
        $s = get_current_screen();
        if ( $s && isset( $s->post_type ) && 0 === strpos( $s->post_type, 'tpw_' ) ) {
            $is_tpw = true;
        }
    }

    if ( ! $is_tpw ) {
        return;
    }

    // Enqueue Core CSS (filename confirmed as admin-style.css)
    $css_url  = plugin_dir_url( __DIR__ ) . 'assets/css/admin-style.css';
    $css_path = plugin_dir_path( __DIR__ ) . 'assets/css/admin-style.css';
    $ver      = file_exists( $css_path ) ? filemtime( $css_path ) : null;

    wp_enqueue_style( 'tpw-core-admin-css', $css_url, [], $ver );

    // Enqueue shared TPW Admin UI stylesheet (scoped to .tpw-admin-ui)
    $ui_url  = plugin_dir_url( __DIR__ ) . 'assets/css/tpw-admin-ui.css';
    $ui_path = plugin_dir_path( __DIR__ ) . 'assets/css/tpw-admin-ui.css';
    $ui_ver  = file_exists( $ui_path ) ? filemtime( $ui_path ) : null;
    wp_enqueue_style( 'tpw-admin-ui', $ui_url, [], $ui_ver );
}, 99);

/**
 * Adds a CSS class to the <body> for styling on Core admin and CPT screens.
 *
 * @param string $classes Existing body classes.
 * @return string Modified body classes.
 */
add_filter('admin_body_class', function ($classes) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $page   = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

    $is_tpw = false;

    if ($screen) {
        $id     = $screen->id;
        $parent = (string) $screen->parent_base;

        $is_tpw =
            strpos($id, 'flexievent_page_tpw-') === 0 ||   // TPW under FlexiEvent menu
            strpos($id, 'admin_page_tpw-') === 0 ||        // TPW pages via admin.php?page=
            strpos($id, 'toplevel_page_tpw-') === 0 ||     // TPW top-level menu pages
            (isset($screen->post_type) && strpos($screen->post_type, 'tpw_') === 0) ||
            $parent === 'tpw-flexievent-dashboard';
    }

    // Fallback: page param starts with tpw-
    if (!$is_tpw && $page && strpos($page, 'tpw-') === 0) {
        $is_tpw = true;
    }

    if ($is_tpw) {
        $classes .= ' tpw-fe-embed tpw-origin';
    }

    return $classes;
}, 10);

/**
 * Returns an array of TPW Core admin page slugs.
 *
 * @return array List of TPW Core admin page slugs.
 */
function tpw_core_get_admin_pages() {
	$pages = array(
		'tpw-core-settings',
		'tpw-core-dashboard',
		'tpw-core-tools',
	);
	return apply_filters( 'tpw_core_get_admin_pages', $pages );
}

// Attach the icon filter only when we're on a Core page.
add_action('current_screen', function($screen){
    $page = isset($_GET['page']) ? sanitize_key( wp_unslash($_GET['page']) ) : '';
    if ( $page && in_array( $page, tpw_core_get_admin_pages(), true ) ) {
        $icon = plugin_dir_url( __DIR__ ) . 'assets/images/tpw-core-icon-300.png'; // <- add this file
        add_filter('tpw_core/header_icon_url', function($url) use ($icon){ return $icon; }, 10);
    }
});

/**
 * Checks if the current admin screen matches any of the TPW Core admin pages or CPT screens.
 *
 * @return bool True if on a TPW Core admin page or CPT screen, false otherwise.
 */
function tpw_core_is_plugin_admin_screen() {
	if ( is_admin() ) {
		// Check $_GET['page'] for TPW Core admin pages.
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], tpw_core_get_admin_pages(), true ) ) {
			return true;
		}

		// Check current screen for TPW Core pages or CPTs.
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen ) {
				if ( in_array( $screen->id, tpw_core_get_admin_pages(), true ) ) {
					return true;
				}
				if ( isset( $screen->post_type ) && strpos( $screen->post_type, 'tpw_' ) === 0 ) {
					return true;
				}
			}
		}
	}

	return false;
}

// Front-end: ensure TPW button styles are available on key TPW pages (late so our rules can win by order when specificity ties)
add_action( 'wp_enqueue_scripts', function(){
    // Detect if we need TPW styles on the front-end
    $is_fixtures_route = false;
    $req_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ( $req_uri && preg_match('#/fixtures-manage(/?|\?|$)#', $req_uri) ) {
        $is_fixtures_route = true;
    }

    $has_shortcodes = false;
    if ( is_singular() ) {
        global $post; if ( $post ) {
            $content = (string) ( $post->post_content ?? '' );
            $shortcodes = [ 'tpw_manage_members', 'tpw_member_profile', 'tpw_member_login', 'tpw_noticeboard_list', 'tpw-control' ];
            foreach ( $shortcodes as $sc ) {
                if ( function_exists('has_shortcode') && has_shortcode( $content, $sc ) ) { $has_shortcodes = true; break; }
            }
        }
    }

    $should_enqueue = $has_shortcodes || $is_fixtures_route;
    if ( ! $should_enqueue ) return;
    if ( ! defined('TPW_CORE_PATH') || ! defined('TPW_CORE_URL') ) return;

    // Base button system
    $btn_file = TPW_CORE_PATH . 'assets/css/tpw-buttons.css';
    $btn_url  = TPW_CORE_URL . 'assets/css/tpw-buttons.css';
    $btn_ver  = file_exists( $btn_file ) ? filemtime( $btn_file ) : null;
    if ( ! wp_style_is( 'tpw-buttons', 'enqueued' ) ) {
        wp_enqueue_style( 'tpw-buttons', $btn_url, [], $btn_ver );
    }

    // Fixtures manage stylesheet is now owned by FlexiGolf; only ensure base buttons here.
}, 100 );

/**
 * Renders a consistent header block for TPW Core admin pages,
 * with optional notice message and customisation args.
 *
 * @param string $title         Header title text.
 * @param string $notice_message Optional notice message HTML.
 * @param array  $args          Optional arguments to customize header output.
 */
if ( ! function_exists( 'tpw_core_output_header' ) ) {
    /**
     * Output a consistent TPW admin header (same structure/classes as FlexiEvent).
     *
     * @param string $title          Page title.
     * @param string $notice_message Optional small message beneath the title.
     * @param array  $args           Optional args:
     *                               - 'logo_url' (string) override the logo URL
     */
    function tpw_core_output_header( $title = 'The Plugin Works', $notice_message = '', $args = array() ) {
        $plugin_url = plugin_dir_url( __DIR__ );

        $logo_url = ! empty( $args['logo_url'] )
            ? $args['logo_url']
            : $plugin_url . 'assets/images/thepluginworks-logo-300.png';

        // Start with explicit arg; otherwise null. Let filters fill it for the current screen.
        $icon_url = ! empty( $args['icon_url'] ) ? $args['icon_url'] : null;

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $page   = isset($_GET['page']) ? sanitize_key( wp_unslash($_GET['page']) ) : '';
        $icon_url = apply_filters( 'tpw_core/header_icon_url', $icon_url, $screen, $page );
        ?>
        <div class="wrap tpw-fe-header">
            <div class="tpw-fe-header-inner">
                <div class="tpw-fe-header-left">
                    <?php if ( ! empty( $icon_url ) ) : ?>
                        <div class="tpw-fe-header-icon">
                            <img src="<?php echo esc_url( $icon_url ); ?>" alt="Icon" />
                        </div>
                    <?php endif; ?>
                    <div class="tpw-fe-title-wrap">
                        <h1 class="tpw-fe-title"><?php echo esc_html( $title ); ?></h1>
                        <?php if ( ! empty( $notice_message ) ) : ?>
                            <div class="tpw-fe-notice"><p><?php echo esc_html( $notice_message ); ?></p></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="tpw-fe-header-right">
                    <img class="tpw-fe-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="The Plugin Works" />
                </div>
            </div>
            <?php do_action( 'tpw_core/admin_header/after', $title ); ?>
        </div>
        <?php
    }
}

/**
 * UI Theme helpers: read and apply custom CSS variable tokens for .tpw-admin-ui
 */
if ( ! function_exists( 'tpw_core_get_ui_theme_defaults' ) ) {
    function tpw_core_get_ui_theme_defaults() {
        return [
            'font_family'  => 'system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"',
            'btn_bg'       => '#0b6cad',
            'btn_text'     => '#ffffff',
            'accent_color' => '#2271b1',
            // New typography defaults for .tpw-admin-ui scope
            'font_weight'     => '600',
            'text_transform'  => 'none',
            'letter_spacing'  => 'normal',
            'text_shadow'     => 'none',
        ];
    }
}

if ( ! function_exists( 'tpw_core_get_ui_theme_settings' ) ) {
    function tpw_core_get_ui_theme_settings( $with_defaults = true ) {
        $opt = get_option( 'tpw_ui_theme_settings', [] );
        if ( ! is_array( $opt ) ) { $opt = []; }
        if ( $with_defaults ) {
            $opt = wp_parse_args( $opt, tpw_core_get_ui_theme_defaults() );
        }
        return $opt;
    }
}

if ( ! function_exists( 'tpw_core_build_ui_theme_style_attr' ) ) {
    /**
     * Build a style attribute string of CSS custom properties for the .tpw-admin-ui wrapper.
     * Example output: "--tpw-font-family: ...; --tpw-btn-bg: ...; --tpw-btn-text: ...; --tpw-accent-color: ..."
     */
    function tpw_core_build_ui_theme_style_attr() {
        $ui = tpw_core_get_ui_theme_settings( true );
        $props = [];
        if ( ! empty( $ui['font_family'] ) ) {
            $props[] = '--tpw-font-family: ' . $ui['font_family'];
        }
        if ( ! empty( $ui['btn_bg'] ) ) {
            $props[] = '--tpw-btn-bg: ' . $ui['btn_bg'];
        }
        if ( ! empty( $ui['btn_text'] ) ) {
            $props[] = '--tpw-btn-text: ' . $ui['btn_text'];
        }
        if ( ! empty( $ui['accent_color'] ) ) {
            $props[] = '--tpw-accent-color: ' . $ui['accent_color'];
        }
        // New tokens
        if ( isset( $ui['font_weight'] ) && $ui['font_weight'] !== '' ) {
            $props[] = '--tpw-font-weight: ' . $ui['font_weight'];
        }
        if ( isset( $ui['text_transform'] ) && $ui['text_transform'] !== '' ) {
            $props[] = '--tpw-text-transform: ' . $ui['text_transform'];
        }
        if ( isset( $ui['letter_spacing'] ) && $ui['letter_spacing'] !== '' ) {
            $props[] = '--tpw-letter-spacing: ' . $ui['letter_spacing'];
        }
        if ( isset( $ui['text_shadow'] ) && $ui['text_shadow'] !== '' ) {
            $props[] = '--tpw-text-shadow: ' . $ui['text_shadow'];
        }
        return implode( '; ', $props );
    }
}

/**
 * Whether frontend admin-like screens should inherit the site's global styles (Elementor/theme),
 * disabling the TPW scoped admin UI layer and wrapper.
 *
 * Stored under option 'tpw_ui_theme_settings' key 'inherit_global_frontend' (boolean-like 0/1).
 * Defaults to false when not set.
 *
 * @return bool
 */
if ( ! function_exists( 'tpw_core_inherit_global_frontend_enabled' ) ) {
    function tpw_core_inherit_global_frontend_enabled() {
        $opt = get_option( 'tpw_ui_theme_settings', [] );
        if ( ! is_array( $opt ) ) { $opt = []; }
        return ! empty( $opt['inherit_global_frontend'] );
    }
}
