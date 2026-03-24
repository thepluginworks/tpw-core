<?php
/**
 * Fired during plugin activation
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPW_Core_Activator {

    /**
     * Code to run on plugin activation.
     */
    public static function activate() {
        // Trigger any setup tasks here (e.g., flushing rewrite rules)
        flush_rewrite_rules();
        tpw_core_ensure_site_society_id();
        // require_once TPW_CORE_PATH . 'modules/guests/class-tpw-guests-table.php';
        // TPW_Guests_Table::create_table();
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-manager.php';
        TPW_Menus_Manager::create_table();
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-event-menu-rel.php';
        TPW_Event_Menu_Rel::create_table();
        
        require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payment-db.php';
        TPW_Payment_DB::create_table();

        require_once TPW_CORE_PATH . 'modules/costs/class-tpw-costs-db.php';
        TPW_Costs_DB::create_table();

        require_once TPW_CORE_PATH . 'modules/members/class-tpw-members-db.php';
        TPW_Members_DB::create_table();
        require_once TPW_CORE_PATH . 'modules/members/class-tpw-member-fields-installer.php';
        TPW_Member_Fields_Installer::insert_default_fields();
        require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-attempts-db.php';
        TPW_Signup_Attempts_DB::create_table();

        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-course-choices-manager.php';

        // Gallery module DB (Phase 2: create schema on activation, silent and safe)
        try {
            require_once TPW_CORE_PATH . 'modules/gallery/gallery-db.php';
            if ( class_exists( 'TPW_Gallery_DB' ) ) {
                $current = get_option( 'tpw_gallery_db_version', '' );
                // Read target schema version from class constant
                $target  = TPW_Gallery_DB::VERSION;
                if ( version_compare( (string) $current, (string) $target, '<' ) ) {
                    TPW_Gallery_DB::create_tables();
                }
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: Gallery DB setup failed - ' . $e->getMessage() );
            }
        }

        // Create Email Templates table
        try {
            require_once TPW_CORE_PATH . 'modules/email/class-tpw-email-templates-db.php';
            if ( class_exists( 'TPW_Email_Templates_DB' ) ) {
                TPW_Email_Templates_DB::create_table();
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: email templates table creation failed - ' . $e->getMessage() );
            }
        }

        // Create Email Logs table and schedule retention cleanup.
        try {
            require_once TPW_CORE_PATH . 'modules/email/class-tpw-email-logs.php';
            if ( class_exists( 'TPW_Email_Logs' ) ) {
                TPW_Email_Logs::create_table();
                TPW_Email_Logs::schedule_cleanup();
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: email logs setup failed - ' . $e->getMessage() );
            }
        }

        // Set default currency settings if not already set
        $settings = get_option( 'flexievent_settings', [] );

        if ( empty( $settings['currency_symbol'] ) ) {
            $settings['currency_symbol'] = '£';
        }

        if ( empty( $settings['currency_code'] ) ) {
            $settings['currency_code'] = 'GBP';
        }

        update_option( 'flexievent_settings', $settings );

        // Seed default surcharge options (percent and fixed) for all supported methods
        try {
            $methods = [ 'woocommerce', 'square', 'sumup', 'bacs', 'cheque', 'cash', 'card-on-the-day' ];
            foreach ( $methods as $m ) {
                $k_percent = 'tpw_surcharge_' . $m . '_percent';
                $k_fixed   = 'tpw_surcharge_' . $m . '_fixed';
                if ( false === get_option( $k_percent, false ) ) {
                    add_option( $k_percent, 0 );
                }
                if ( false === get_option( $k_fixed, false ) ) {
                    add_option( $k_fixed, 0 );
                }
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: surcharge defaults setup skipped - ' . $e->getMessage() );
            }
        }

        // Migrate legacy option 'tpw_member_viewable_fields' to new visibility table
        try {
            global $wpdb;
            $legacy_viewable = get_option( 'tpw_member_viewable_fields', null );
            if ( null !== $legacy_viewable ) {
                $vis_table = $wpdb->prefix . 'tpw_member_field_visibility';
                if ( is_array( $legacy_viewable ) && ! empty( $legacy_viewable ) ) {
                    foreach ( $legacy_viewable as $field_key ) {
                        $field_key = sanitize_key( $field_key );
                        if ( '' === $field_key ) continue;
                        // Use raw query so we can safely backtick the reserved column name `group`
                        $sql = $wpdb->prepare(
                            "INSERT INTO $vis_table (field_key, `group`, is_visible) VALUES (%s, %s, %d)",
                            $field_key, 'member', 1
                        );
                        $wpdb->query( $sql );
                    }
                }
                // Note: We keep the legacy option since it now powers the Member Profile view
                // (profile visibility is independent from directory visibility matrix).
                // If you want to reset profile visibility, you can clear it via the Member Settings UI.
            }

            // Seed default visibility rules for enabled fields if not already present
            try {
                $settings_tbl = $wpdb->prefix . 'tpw_field_settings';
                $enabled_fields = (array) $wpdb->get_col( "SELECT field_key FROM {$settings_tbl} WHERE is_enabled = 1" );
                $enabled_fields = array_values( array_filter( array_map( 'sanitize_key', $enabled_fields ) ) );
                if ( ! empty( $enabled_fields ) ) {
                    $vis_table = $wpdb->prefix . 'tpw_member_field_visibility';

                    // Helper: check if a mapping exists
                    $has_mapping = function( $group, $field_key ) use ( $wpdb, $vis_table ) {
                        $sql = $wpdb->prepare( "SELECT 1 FROM {$vis_table} WHERE `group` = %s AND field_key = %s LIMIT 1", $group, $field_key );
                        return (bool) $wpdb->get_var( $sql );
                    };

                    // Admin: sees all enabled fields
                    foreach ( $enabled_fields as $fk ) {
                        if ( ! $has_mapping( 'admin', $fk ) ) {
                            $wpdb->insert( $vis_table, [ 'field_key' => $fk, 'group' => 'admin', 'is_visible' => 1 ], [ '%s','%s','%d' ] );
                        }
                    }

                    // Member: basic fields (name + email + phone)
                    $member_defaults = [ 'first_name', 'surname', 'email', 'mobile', 'landline' ];
                    foreach ( $enabled_fields as $fk ) {
                        if ( in_array( $fk, $member_defaults, true ) ) {
                            if ( ! $has_mapping( 'member', $fk ) ) {
                                $wpdb->insert( $vis_table, [ 'field_key' => $fk, 'group' => 'member', 'is_visible' => 1 ], [ '%s','%s','%d' ] );
                            }
                        }
                    }

                    // Committee: inherits member defaults (add any committee-specific fields here if needed)
                    foreach ( $enabled_fields as $fk ) {
                        if ( in_array( $fk, $member_defaults, true ) ) {
                            if ( ! $has_mapping( 'committee', $fk ) ) {
                                $wpdb->insert( $vis_table, [ 'field_key' => $fk, 'group' => 'committee', 'is_visible' => 1 ], [ '%s','%s','%d' ] );
                            }
                        }
                    }

                    // Guest: no defaults (explicitly do nothing)
                }
            } catch ( \Throwable $e2 ) {
                if ( function_exists( 'error_log' ) ) {
                    error_log( 'TPW Core activation: default visibility seeding skipped - ' . $e2->getMessage() );
                }
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: migrating viewable fields failed - ' . $e->getMessage() );
            }
        }

        // Ensure a default Members Menu is available and assigned to the 'tpw_member_menu' location
        // Only if nothing is currently assigned for that location.
        // Note: During activation, not all theme hooks run, but we can still set the theme_mod mapping.
        try {
            if ( ! function_exists( 'get_nav_menu_locations' ) ) {
                // Safety include for nav menu functions during activation
                if ( defined( 'ABSPATH' ) ) {
                    @require_once ABSPATH . 'wp-includes/nav-menu.php';
                }
            }

            if ( ! function_exists( 'wp_create_nav_menu' ) ) {
                // Admin helper functions
                if ( defined( 'ABSPATH' ) ) {
                    @require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
                }
            }

            if ( function_exists( 'get_nav_menu_locations' ) ) {
                $locations = get_nav_menu_locations();
                if ( ! is_array( $locations ) ) {
                    $locations = [];
                }

                $has_assignment = isset( $locations['tpw_member_menu'] ) && ! empty( $locations['tpw_member_menu'] );

                if ( ! $has_assignment ) {
                    // Create or find a "Members Menu"
                    $menu_name = __( 'Members Menu', 'tpw-core' );
                    $menu_obj  = function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $menu_name ) : null;
                    $menu_id   = $menu_obj && isset( $menu_obj->term_id ) ? (int) $menu_obj->term_id : 0;

                    if ( ! $menu_id && function_exists( 'wp_create_nav_menu' ) ) {
                        $menu_id = (int) wp_create_nav_menu( $menu_name );
                    }

                    if ( $menu_id > 0 && function_exists( 'wp_update_nav_menu_item' ) ) {
                        // Add a basic Logout item for convenience if menu is new/empty
                        // Store a placeholder (not a nonce URL) so it can be rewritten at render-time.
                        $logout_url = '/?tpw_action=logout';
                        wp_update_nav_menu_item( $menu_id, 0, [
                            'menu-item-title'  => __( 'Logout', 'tpw-core' ),
                            'menu-item-url'    => esc_url_raw( $logout_url ),
                            'menu-item-status' => 'publish',
                            'menu-item-type'   => 'custom',
                        ] );

                        // Assign to the tpw_member_menu location
                        $locations['tpw_member_menu'] = $menu_id;
                        set_theme_mod( 'nav_menu_locations', $locations );
                    }
                }
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: menu setup skipped due to error - ' . $e->getMessage() );
            }
        }

        // Ensure a front-end "My Profile" page exists with the [tpw_member_profile] shortcode
        try {
            // If a valid page is already configured, keep it
            $configured_id = (int) get_option( 'tpw_member_profile_page_id', 0 );
            $configured_ok = false;
            if ( $configured_id > 0 ) {
                $p = get_post( $configured_id );
                if ( $p && 'publish' === $p->post_status && 'page' === $p->post_type ) {
                    // Consider it valid regardless of content to respect admin edits
                    $configured_ok = true;
                }
            }

            if ( ! $configured_ok ) {
                // Try to find an existing published page containing the shortcode
                $found_id = 0;
                if ( class_exists('WP_Query') ) {
                    $q = new WP_Query([
                        'post_type'      => 'page',
                        'post_status'    => 'publish',
                        'posts_per_page' => 25,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                        'fields'         => 'ids',
                    ]);
                    if ( $q->have_posts() ) {
                        foreach ( $q->posts as $pid ) {
                            $content = get_post_field( 'post_content', $pid );
                            if ( is_string($content) && false !== strpos( $content, '[tpw_member_profile' ) ) {
                                $found_id = (int) $pid;
                                break;
                            }
                        }
                    }
                    wp_reset_postdata();
                }

                if ( $found_id ) {
                    update_option( 'tpw_member_profile_page_id', $found_id );
                } else {
                    // Create a new My Profile page
                    $author = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
                    $post_id = wp_insert_post([
                        'post_title'   => __( 'My Profile', 'tpw-core' ),
                        'post_name'    => 'my-profile',
                        'post_status'  => 'publish',
                        'post_type'    => 'page',
                        'post_author'  => $author,
                        'post_content' => '[tpw_member_profile]',
                        'comment_status' => 'closed',
                        'ping_status'    => 'closed',
                    ]);
                    if ( $post_id && ! is_wp_error( $post_id ) ) {
                        update_option( 'tpw_member_profile_page_id', (int) $post_id );
                    }
                }
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: profile page setup skipped due to error - ' . $e->getMessage() );
            }
        }

        if ( class_exists( 'TPW_Core_System_Pages' ) ) {
            TPW_Core_System_Pages::ensure_tables();
            // Ensure key system pages exist
            try {
                TPW_Core_System_Pages::register_page( 'member-login', [
                    'title'     => 'Member Login',
                    'shortcode' => '[tpw_member_login]',
                    'plugin'    => 'tpw-core',
                    'required'  => 1,
                ] );
                TPW_Core_System_Pages::register_page( 'my-profile', [
                    'title'     => 'My Profile',
                    'shortcode' => '[tpw_member_profile]',
                    'plugin'    => 'tpw-core',
                    'required'  => 1,
                ] );
                TPW_Core_System_Pages::ensure_page( 'member-login' );
                TPW_Core_System_Pages::ensure_page( 'my-profile' );
            } catch ( \Throwable $e ) {
                if ( function_exists( 'error_log' ) ) {
                    error_log( 'TPW Core activation: ensuring system pages failed - ' . $e->getMessage() );
                }
            }

            // Validate the configured login redirect page option; clear if invalid
            try {
                $redir = (int) get_option( 'tpw_login_redirect_page_id', 0 );
                if ( $redir > 0 && get_post_status( $redir ) !== 'publish' ) {
                    update_option( 'tpw_login_redirect_page_id', 0 );
                }
            } catch ( \Throwable $e ) {
                // no-op
            }
        }
    }
}
