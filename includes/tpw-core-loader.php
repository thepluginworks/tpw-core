<?php
/**
 * TPW Core Loader
 *
 * Central include file that wires TPW modules, routes and settings into WordPress.
 * Avoid putting business logic here; prefer to include classes and call their
 * own initializers. This file may register rewrite rules and light routing for
 * virtual pages where required.
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Core includes
require_once TPW_CORE_PATH . 'includes/class-tpw-core-activator.php';
require_once TPW_CORE_PATH . 'includes/class-tpw-core-deactivator.php';
require_once TPW_CORE_PATH . 'includes/class-tpw-core.php';
require_once TPW_CORE_PATH . 'includes/class-tpw-core-updater.php';
require_once TPW_CORE_PATH . 'includes/scheduler/class-tpw-core-scheduler.php';
require_once TPW_CORE_PATH . 'includes/tpw-core-functions.php';
require_once TPW_CORE_PATH . 'includes/class-tpw-core-create-menu.php';
require_once TPW_CORE_PATH . 'modules/costs/class-tpw-costs-save.php';
require_once TPW_CORE_PATH . 'modules/costs/class-tpw-costs.php';
require_once TPW_CORE_PATH . 'includes/admin-functions.php';

TPW_Core_Updater::init();

// Load WP-CLI command if in CLI context (safe to include; will noop outside WP_CLI)
if ( file_exists( TPW_CORE_PATH . 'modules/system-pages/class-tpw-core-system-pages-cli.php' ) ) {
    require_once TPW_CORE_PATH . 'modules/system-pages/class-tpw-core-system-pages-cli.php';
}

// Must run on/after init to avoid WP 6.7+ early textdomain JIT notices.
// Register the Members "My Profile" page in the System Pages table (keeps existing logic intact)
add_action( 'init', function() {
    if ( class_exists( 'TPW_Core_System_Pages' ) ) {
        TPW_Core_System_Pages::register_page( 'my-profile', [
            'title'     => 'My Profile',
            'shortcode' => '[tpw_member_profile]',
            'plugin'    => 'tpw-core',
            'required'  => 1,
        ] );
        // Register the login page so sites can have a front-end login form
        TPW_Core_System_Pages::register_page( 'member-login', [
            'title'     => 'Member Login',
            'shortcode' => '[tpw_member_login]',
            'plugin'    => 'tpw-core',
            'required'  => 1,
        ] );
    }
} );

// Module includes
//require_once TPW_CORE_PATH . 'modules/guests/class-tpw-guests-cpt.php';
//require_once TPW_CORE_PATH . 'modules/guests/class-tpw-guests-meta.php';
//require_once TPW_CORE_PATH . 'modules/guests/class-tpw-guests-admin.php';
//require_once TPW_CORE_PATH . 'modules/guests/class-tpw-guests-table.php';

//require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-cpt.php';
//require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-meta.php';
require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-manager.php';
require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-saver.php';
require_once TPW_CORE_PATH . 'modules/menus/class-tpw-event-menu-rel.php';
// Front-end Menus API (modal rendering, public helpers)
require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus.php';
TPW_Menus::init();
//member modules
require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-identity.php';
require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-identity-compat.php';
require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-username-generator.php';
require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-household-repository.php';
require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-fields.php';
require_once TPW_CORE_PATH . 'modules/members/admin/class-tpw-identity-audit-admin.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-sections.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-field-schema.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-attempts-db.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-attempts-service.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-field-mapper.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-finalizer.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-completion-bridge.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-completion-actions.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-attempts-admin.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-join-page.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-form-validator.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-payload-builder.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-form-renderer.php';
require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-form-controller.php';
add_action( 'admin_init', [ 'TPW_Signup_Attempts_DB', 'ensure_core_schema' ], 5 );
TPW_Join_Page::init();
TPW_Signup_Form_Controller::init();
TPW_Signup_Completion_Actions::init();
TPW_Signup_Attempts_Admin::init();
new TPW_Member_Fields();
require_once TPW_CORE_PATH . 'modules/members/shortcodes/members-admin.php';
require_once TPW_CORE_PATH . 'modules/members/shortcodes/member-login.php';
require_once TPW_CORE_PATH . 'modules/members/shortcodes/member-profile.php';
require_once TPW_CORE_PATH . 'modules/members/shortcodes/member-profile-badge.php';
require_once TPW_CORE_PATH . 'modules/members/members-init.php';
// Members admin actions (admin-post handlers)
if ( file_exists( TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-admin-actions.php' ) ) {
    require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-admin-actions.php';
    if ( class_exists( 'TPW_Member_Admin_Actions' ) ) {
        add_action( 'init', [ 'TPW_Member_Admin_Actions', 'init' ] );
    }
}

//require_once TPW_CORE_PATH . 'modules/choices/class-tpw-choices-handler.php';
//require_once TPW_CORE_PATH . 'modules/choices/class-tpw-choices-utils.php';

// API
//require_once TPW_CORE_PATH . 'modules/api/class-tpw-api-init.php';
//require_once TPW_CORE_PATH . 'modules/api/endpoints/class-tpw-api-guests.php';
//require_once TPW_CORE_PATH . 'modules/api/endpoints/class-tpw-api-menus.php';
//require_once TPW_CORE_PATH . 'modules/api/endpoints/class-tpw-api-choices.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payment-logger.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payment-logs-admin.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-core-payments.php';
require_once TPW_CORE_PATH . 'modules/payments/admin-settings.php';
TPW_Payment_Logs_Admin::init();
require_once TPW_CORE_PATH . 'modules/payments/gateways/class-tpw-sumup-gateway.php';
// Load WooCommerce display overrides only when Lodge Meetings plugin is active
if ( file_exists( TPW_CORE_PATH . 'modules/postcodes/enqueue.php' ) ) {
    require_once TPW_CORE_PATH . 'modules/postcodes/enqueue.php';
}

require_once TPW_CORE_PATH . 'modules/payments/gateways/class-tpw-woocommerce-display.php';

require_once TPW_CORE_PATH . 'modules/payments/gateways/sumup-oauth-callback.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-core-woocommerce-hooks.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payments-settings.php';
TPW_Payments_Settings::init();
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-bacs-settings.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-cheque-settings.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-cash-settings.php';
require_once TPW_CORE_PATH . 'modules/payments/class-tpw-card-on-the-day-settings.php';
require_once TPW_CORE_PATH . 'modules/payments/views/thank-you-shortcode.php';

require_once TPW_CORE_PATH . 'modules/feedback/class-tpw-feedback.php';
require_once TPW_CORE_PATH . 'modules/feedback/admin/class-tpw-admin.php';
new TPW_Feedback_Admin();
require_once TPW_CORE_PATH . 'modules/feedback/includes/class-tpw-feedback-model.php';

// Noticeboard (moved to modules/notices)
require_once TPW_CORE_PATH . 'modules/notices/class-tpw-noticeboard.php';
require_once TPW_CORE_PATH . 'modules/notices/noticeboard-handler.php';
require_once TPW_CORE_PATH . 'modules/notices/shortcodes/noticeboard-list.php';
// Email module (reusable across plugins)
require_once TPW_CORE_PATH . 'modules/email/class-tpw-core-email-settings.php';
require_once TPW_CORE_PATH . 'modules/email/class-tpw-email-logs.php';
require_once TPW_CORE_PATH . 'modules/email/class-tpw-email-logo-helper.php';
require_once TPW_CORE_PATH . 'modules/email/class-tpw-email-templates-db.php';
require_once TPW_CORE_PATH . 'modules/email/class-tpw-email-template-registry.php';
require_once TPW_CORE_PATH . 'modules/email/class-tpw-email-template-manager.php';
require_once TPW_CORE_PATH . 'modules/email/class-tpw-email.php';
require_once TPW_CORE_PATH . 'modules/email/class-tpw-email-form.php';
TPW_Email_Logs::init();
TPW_Email_Form::init();

// Register Members email templates
add_action( 'plugins_loaded', function(){
    if ( class_exists( 'TPW_Email_Template_Registry' ) ) {
        TPW_Email_Template_Registry::register_template( [
            'key'               => 'member_new_wp_user_created',
            'group'             => 'members',
            'label'             => 'Member: New WP User Created',
            'default_subject'   => 'Your Member Login Has Been Created',
            'default_body'      => "Dear {member_first_name},\n\nA member login has now been created for you on {site_name}.\n\nYou can access the members’ area here:\n{member_login_url}\n\nBefore you can log in for the first time, please set your password using the Reset Password option on the login page below:\n{password_reset_url}\n\nKind regards,\n{organisation_name}",
            'editable_subject'  => true,
            'editable_body'     => true,
            'placeholders'      => [
                '{member_first_name}',
                '{member_last_name}',
                '{site_name}',
                '{member_login_url}',
                '{password_reset_url}',
                '{organisation_name}',
            ],
        ] );
        TPW_Email_Template_Registry::register_template( [
            'key'               => 'member_password_setup',
            'group'             => 'members',
            'label'             => 'Member: Password Setup',
            'default_subject'   => 'Set up your member password',
            'default_body'      => "Hello {member_name},\n\nA member account has been created or prepared for you on {site_name}.\n\nUse the secure link below to set up your password:\n{password_setup_url}\n\nThis link is time-limited for security. If it expires, you can request a new link from the member login page:\n{member_login_url}\n\nKind regards,\n{organisation_name}",
            'editable_subject'  => true,
            'editable_body'     => true,
            'placeholders'      => [
                '{member_name}'        => 'Member display name',
                '{member_first_name}'  => 'Member first name when available',
                '{password_setup_url}' => 'Secure password setup/reset URL',
                '{setup_reset_url}'    => 'Alias of the secure password setup/reset URL',
                '{member_login_url}'   => 'Front-end member login page URL',
                '{site_name}'          => 'WordPress site name',
                '{organisation_name}'  => 'TPW brand title or site name',
            ],
        ] );
    }
}, 20 );

// Member Payments (Phase 1 skeleton)
if ( file_exists( TPW_CORE_PATH . 'includes/class-tpw-member-payments.php' ) ) {
    require_once TPW_CORE_PATH . 'includes/class-tpw-member-payments.php';
    if ( class_exists( 'TPW_Member_Payments' ) ) {
        add_action( 'init', [ 'TPW_Member_Payments', 'init' ] );
    }
}

// Postcodes module (global)
require_once TPW_CORE_PATH . 'modules/postcodes/class-tpw-postcode-provider-registry.php';
require_once TPW_CORE_PATH . 'modules/postcodes/providers/class-tpw-postcode-provider-abstract.php';
require_once TPW_CORE_PATH . 'modules/postcodes/providers/class-tpw-postcode-provider-ideal-postcodes.php';
require_once TPW_CORE_PATH . 'modules/postcodes/providers/class-tpw-postcode-provider-fetchify.php';
require_once TPW_CORE_PATH . 'modules/postcodes/class-tpw-postcode-helper.php';
require_once TPW_CORE_PATH . 'modules/postcodes/postcode-ajax.php';

// Gallery module (Phase 1 scaffold only: register in module registry, no UI)
if ( file_exists( TPW_CORE_PATH . 'modules/gallery/gallery-loader.php' ) ) {
    require_once TPW_CORE_PATH . 'modules/gallery/gallery-loader.php';
}

// Core Settings (member menu location & swapper)
$__tpw_settings_file = TPW_CORE_PATH . 'includes/tpw-core-settings.php';
if ( file_exists( $__tpw_settings_file ) ) {
    require_once $__tpw_settings_file;
} else {
    // Avoid fatal if file not yet deployed; log for visibility
    if ( function_exists( 'error_log' ) ) {
        error_log( 'TPW Core: Missing includes/tpw-core-settings.php. Settings page and member menu swapper will be unavailable until the file is deployed.' );
    }
}

// TPW Control (front-end admin hub)
if ( file_exists( TPW_CORE_PATH . 'modules/tpw-control/class-tpw-control.php' ) ) {
    // Ensure Upload Pages class is available early so its public shortcode is registered site-wide
    if ( file_exists( TPW_CORE_PATH . 'modules/tpw-control/class-tpw-control-upload-pages.php' ) ) {
        require_once TPW_CORE_PATH . 'modules/tpw-control/class-tpw-control-upload-pages.php';
    }
    require_once TPW_CORE_PATH . 'modules/tpw-control/class-tpw-control.php';
    add_action( 'init', [ 'TPW_Control', 'init' ] );
}

add_action('init', 'tpw_core_load_optional_modules', 20);

/**
 * Conditionally load optional TPW modules based on filters.
 *
 * Filters (booleans):
 * - tpw_show_dining_menu — load menu admin UIs
 * - tpw_show_payment_settings — expose Payments settings page
 * - tpw_enable_create_menu — enable legacy Create Menu tools
 *
 * @since 1.0.0
 * @return void
 */
function tpw_core_load_optional_modules() {
    if ( apply_filters('tpw_show_dining_menu', false) ) {
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menu-courses-manager.php';
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-admin.php';
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-admin-add.php';
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-admin-edit.php';
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-frontend-admin.php';
        TPW_Menus_Admin_Edit::init();
        TPW_Menus_Admin_Add::init();
        TPW_Menus_Admin::init();
        TPW_Menus_Frontend_Admin::init();

        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-course-choices-manager.php';
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-course-choices-admin.php';
        TPW_Course_Choices_Admin::init();

        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-course-choice-form-admin.php';
        TPW_Course_Choice_Form_Admin::init();
    }

    if ( apply_filters('tpw_show_payment_settings', false) ) {
        require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payments-admin.php';
        TPW_Payments_Admin::init();
    }

    if ( apply_filters('tpw_enable_create_menu', false) ) {
        TPW_Core_Create_Menu::init();
    }
}
// Register thank-you page endpoint
/**
 * Rewrite rules for shared front‑end endpoints.
 *
 * - /rsvp-thank-you/ — shared thank‑you endpoint
 * - /my-profile/ — optional virtual route when no real page exists
 *
 * @since 1.0.0
 */
add_action('init', function() {
    add_rewrite_rule('^rsvp-thank-you/?$', 'index.php?tpw_thank_you=1', 'top');

    // Conditionally register the front-end fallback for My Profile only when a real page doesn't exist.
    // This avoids hijacking a real Elementor page and prevents missing elementorFrontendConfig on /my-profile/.
    $should_add_virtual = true;
    $configured_id = (int) get_option( 'tpw_member_profile_page_id', 0 );
    if ( $configured_id > 0 ) {
        $p = get_post( $configured_id );
        if ( $p && 'page' === $p->post_type && 'publish' === $p->post_status ) {
            $should_add_virtual = false;
        }
    } else {
        $by_path = function_exists( 'get_page_by_path' ) ? get_page_by_path( 'my-profile' ) : null;
        if ( $by_path && 'page' === $by_path->post_type && 'publish' === $by_path->post_status ) {
            $should_add_virtual = false;
        }
    }

    if ( $should_add_virtual ) {
        add_rewrite_rule('^my-profile/?$', 'index.php?tpw_my_profile=1', 'top');
    }
});

/**
 * Register public query vars used by Core routes.
 *
 * @since 1.0.0
 */
add_filter('query_vars', function($vars) {
    $vars[] = 'tpw_thank_you';
    $vars[] = 'tpw_my_profile';
    return $vars;
});

// Protect real WP pages that render the profile shortcode and prevent caching
/**
 * Protect real profile pages and enforce login/no‑cache.
 *
 * Ensures pages containing [tpw_member_profile] are uncached and require login.
 * Sites can override login URL via the tpw_core/login_url filter.
 *
 * @since 1.0.0
 */
add_action('template_redirect', function() {
    if ( is_admin() ) {
        return;
    }
    // If the request is for a singular post/page and it is the configured My Profile page,
    // or its content contains the [tpw_member_profile] shortcode, enforce login and no-cache.
    if ( is_singular() ) {
        global $post;
        if ( $post instanceof WP_Post ) {
            $is_configured_profile_page = false;
            $configured_id = (int) get_option( 'tpw_member_profile_page_id', 0 );
            if ( $configured_id > 0 && (int) $post->ID === $configured_id ) {
                $is_configured_profile_page = true;
            }

            $has_profile_shortcode = false;
            if ( isset( $post->post_content ) && is_string( $post->post_content ) ) {
                $has_profile_shortcode = has_shortcode( $post->post_content, 'tpw_member_profile' );
            }

            if ( $is_configured_profile_page || $has_profile_shortcode ) {
                // Prevent full-page caching of this sensitive page
                if ( ! defined( 'DONOTCACHEPAGE' ) ) {
                    define( 'DONOTCACHEPAGE', true );
                }
                nocache_headers();

                // Require login for access
                if ( ! is_user_logged_in() ) {
                    $redirect_to = get_permalink( $post );
                    // Resolve via central filter so plugins/sites can override
                    $login_url = apply_filters( 'tpw_core/login_url', '', $redirect_to );
                    if ( ! is_string( $login_url ) || $login_url === '' ) {
                        $login_url = wp_login_url( $redirect_to );
                    }
                    wp_safe_redirect( $login_url );
                    exit;
                }
            }
        }
    }
}, 5);

/**
 * Handle virtual routes for thank‑you and profile when applicable.
 *
 * @since 1.0.0
 */
add_action('template_redirect', function() {
    if (get_query_var('tpw_thank_you')) {
        include TPW_CORE_PATH . 'modules/payments/views/thank-you.php';
        exit;
    }
    if (get_query_var('tpw_my_profile')) {
        // Defensive: if a real, published Profile page exists, render it directly instead of the virtual route.
        // This covers sites that still have stale rewrite rules pointing to tpw_my_profile.
        $configured_id = (int) get_option( 'tpw_member_profile_page_id', 0 );
        if ( $configured_id > 0 ) {
            $real = get_post( $configured_id );
            if ( $real && 'page' === $real->post_type && 'publish' === $real->post_status ) {
                // Prevent caching like we do for the virtual route
                if ( ! defined( 'DONOTCACHEPAGE' ) ) {
                    define( 'DONOTCACHEPAGE', true );
                }
                nocache_headers();

                global $wp_query, $post;
                $post = $real;
                // Prime the main query to the real page
                $wp_query->posts = [ $post ];
                $wp_query->post = $post;
                $wp_query->post_count = 1;
                $wp_query->is_page = true;
                $wp_query->is_singular = true;
                $wp_query->is_single = false;
                $wp_query->is_home = false;
                $wp_query->is_archive = false;
                $wp_query->is_404 = false;
                $wp_query->queried_object = $post;
                $wp_query->queried_object_id = $post->ID;
                setup_postdata( $post );

                // Load the theme template normally for the real page
                $candidates = [];
                if ( ! empty( $post->post_name ) ) {
                    $candidates[] = 'page-' . $post->post_name . '.php';
                }
                $candidates[] = 'page-' . (int) $post->ID . '.php';
                $candidates[] = 'page.php';
                $candidates[] = 'singular.php';
                $candidates[] = 'index.php';
                $template = locate_template( $candidates );
                if ( ! $template ) {
                    echo apply_filters('the_content', do_shortcode('[tpw_member_profile]') );
                    exit;
                }
                include $template;
                exit;
            }
        }

        // Render through the theme pipeline by creating a virtual page and replacing the main query
        global $wp_query, $post;

        // Access rules aligned with shortcode logic
        $admin_can_view = apply_filters('tpw_members/wp_admin_can_view_profile', true);
        $allow_all_statuses = apply_filters('tpw_members/profile_allow_all_statuses', true);

        // Prevent caching of virtual profile page
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        nocache_headers();

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( home_url('/') );
            exit;
        }

        // Load member record
        require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-controller.php';
        $controller = new TPW_Member_Controller();
        $user = wp_get_current_user();
        $member = $controller->get_member_by_user_id( (int) $user->ID );

        if ( ! $member ) {
            // Allow admins to preview the page even without a member record; the shortcode will show a helpful message.
            if ( ! current_user_can('manage_options') ) {
                wp_safe_redirect( home_url('/') );
                exit;
            }
            // else proceed and let the shortcode output the notice
        }

        $allowed = true;
        if ( ! ( current_user_can('manage_options') && $admin_can_view ) ) {
            if ( ! $allow_all_statuses && class_exists('TPW_Member_Access') ) {
                require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-access.php';
                $allowed = TPW_Member_Access::is_member_current();
            }
        }
        if ( ! $allowed ) {
            wp_safe_redirect( home_url('/') );
            exit;
        }

        // Build a virtual page post so the theme loop shows correct title/content
        $virtual = (object) [
            'ID'                    => -987654, // negative to avoid clashing
            'post_author'           => get_current_user_id(),
            'post_date'             => current_time('mysql'),
            'post_date_gmt'         => current_time('mysql', 1),
            'post_content'          => '[tpw_member_profile]',
            'post_title'            => apply_filters('tpw_members/profile_virtual_title', __('My Profile', 'tpw-core')),
            'post_excerpt'          => '',
            'post_status'           => 'publish',
            'comment_status'        => 'closed',
            'ping_status'           => 'closed',
            'post_password'         => '',
            'post_name'             => 'my-profile',
            'to_ping'               => '',
            'pinged'                => '',
            'post_modified'         => current_time('mysql'),
            'post_modified_gmt'     => current_time('mysql', 1),
            'post_content_filtered'  => '',
            'post_parent'           => 0,
            'guid'                  => home_url('/my-profile/'),
            'menu_order'            => 0,
            'post_type'             => 'page',
            'post_mime_type'        => '',
            'filter'                => 'raw',
        ];
        $post = new WP_Post( $virtual );
        // Replace the main query
        $wp_query->posts = [ $post ];
        $wp_query->post = $post;
        $wp_query->post_count = 1;
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        $wp_query->is_single = false;
        $wp_query->is_home = false;
        $wp_query->is_archive = false;
        $wp_query->is_404 = false;
        $wp_query->queried_object = $post;
        $wp_query->queried_object_id = $post->ID;
        setup_postdata( $post );

        // Ensure content and title render as expected for the virtual page
        add_filter('the_content', function($c) use ($post){
            if ( isset($post->ID) && (int) $post->ID === -987654 ) {
                // Guarantee shortcodes run for the virtual page content
                return do_shortcode( $post->post_content );
            }
            return $c;
        }, 1);
        add_filter('the_title', function($t, $pid) use ($post){
            if ( isset($post->ID) && (int) $post->ID === -987654 && (int) $pid === -987654 ) {
                return $post->post_title;
            }
            return $t;
        }, 10, 2);

        // Load the theme template normally
        $template = locate_template( ['page.php', 'singular.php', 'index.php'] );
        if ( ! $template ) {
            // Hard fallback if theme has no templates (rare)
            echo apply_filters('the_content', do_shortcode('[tpw_member_profile]') );
            exit;
        }
        include $template;
        exit;
    }
});

// One-time flush for new pretty permalink routes (e.g., /my-profile/)
/**
 * One‑time flush for new pretty permalink routes (v1).
 *
 * @since 1.0.0
 */
add_action('admin_init', function() {
    if ( ! current_user_can('manage_options') ) return;
    $flag = get_option('tpw_core_rewrite_flushed_v1');
    if ( ! $flag ) {
        flush_rewrite_rules(false);
        update_option('tpw_core_rewrite_flushed_v1', time());
    }
});

// One-time flush v2 to remove the /my-profile/ virtual route when a real page exists and the rule was previously saved.
/**
 * One‑time flush to remove the /my-profile/ virtual route when a real page exists (v2).
 *
 * @since 1.0.1
 */
add_action('admin_init', function() {
    if ( ! current_user_can('manage_options') ) return;
    $flag2 = get_option('tpw_core_rewrite_flushed_v2');
    if ( ! $flag2 ) {
        flush_rewrite_rules(false);
        update_option('tpw_core_rewrite_flushed_v2', time());
    }
});