<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * TPW Core System Pages Manager
 *
 * Provides a central registry and lifecycle for front-end WordPress pages used by TPW plugins.
 *
 * Table: {$wpdb->prefix}tpw_system_pages
 */
class TPW_Core_System_Pages {

    /**
     * Try to find an existing published WP Page matching the given slug or containing the shortcode.
     * Returns 0 if not found.
     *
     * @param string $slug       Expected page slug (post_name)
     * @param string $shortcode  Expected shortcode string, e.g. "[tpw_member_profile]"
     * @return int Page ID or 0
     */
    protected static function find_existing_page_id( $slug, $shortcode ) {
        // First: try by slug (post_name) using get_page_by_path.
        $clean = sanitize_title( $slug );
        if ( $clean ) {
            $p = get_page_by_path( $clean, OBJECT, 'page' );
            if ( $p && 'page' === $p->post_type && 'publish' === $p->post_status ) {
                return (int) $p->ID;
            }
        }

        // Then: try to find a published page whose content contains the shortcode.
        $needles = [];
        $shortcode = is_string( $shortcode ) ? trim( $shortcode ) : '';
        if ( $shortcode !== '' ) {
            $needles[] = $shortcode; // exact match (e.g., [tpw_member_profile])
            if ( preg_match( '/\[(\w+)/', $shortcode, $m ) ) {
                // Also search for the tag opening to match variations with attributes, e.g., [tpw_member_profile foo="bar"]
                $tag = $m[1];
                if ( $tag ) {
                    $needles[] = '[' . $tag;
                }
            }
        }

        if ( ! empty( $needles ) && class_exists( 'WP_Query' ) ) {
            $q = new WP_Query( [
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 25,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
            ] );
            if ( $q->have_posts() ) {
                foreach ( $q->posts as $pid ) {
                    $content = get_post_field( 'post_content', $pid );
                    if ( ! is_string( $content ) || $content === '' ) { continue; }
                    foreach ( $needles as $n ) {
                        if ( false !== strpos( $content, $n ) ) {
                            wp_reset_postdata();
                            return (int) $pid;
                        }
                    }
                }
            }
            wp_reset_postdata();
        }

        return 0;
    }

    /**
     * Ensure DB tables exist. Safe to call multiple times (dbDelta aware).
     */
    public static function ensure_tables() {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_system_pages';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            wp_page_id BIGINT(20) UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            shortcode VARCHAR(255) NOT NULL,
            plugin VARCHAR(100) NOT NULL,
            required TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY plugin (plugin)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Get all registered system pages.
     *
     * @return array List of row objects.
     */
    public static function get_all() {
        global $wpdb;
        self::ensure_tables();
        $table = $wpdb->prefix . 'tpw_system_pages';
        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY required DESC, plugin ASC, slug ASC" );
        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Register or update a system page record.
     *
     * @param string $slug Unique machine key (e.g., 'control', 'fixtures').
     * @param array $args  [title, shortcode, plugin, required]
     * @return int|false   Inserted/updated row ID on success, false on failure.
     */
    public static function register_page( $slug, $args ) {
        global $wpdb;
        self::ensure_tables();

        $table = $wpdb->prefix . 'tpw_system_pages';
        $slug = sanitize_key( $slug );
        if ( '' === $slug ) return false;

        $defaults = [
            'title'     => '',
            'shortcode' => '',
            'plugin'    => 'tpw-core',
            'required'  => 1,
        ];
        $args = wp_parse_args( $args, $defaults );

        $data = [
            'slug'      => $slug,
            'title'     => sanitize_text_field( $args['title'] ),
            'shortcode' => sanitize_text_field( $args['shortcode'] ),
            'plugin'    => sanitize_key( $args['plugin'] ),
            'required'  => (int) !! $args['required'],
        ];

        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ) );
        if ( $existing ) {
            $ok = $wpdb->update( $table, $data, [ 'id' => (int) $existing->id ], [ '%s','%s','%s','%s','%d' ], [ '%d' ] );
            $row_id = $ok !== false ? (int) $existing->id : 0;

            // Auto-link an existing WP page if we aren't already linked
            if ( $row_id && ( (int) $existing->wp_page_id ) === 0 ) {
                $found_id = self::find_existing_page_id( $slug, $args['shortcode'] );
                if ( $found_id > 0 ) {
                    $wpdb->update( $table, [ 'wp_page_id' => (int) $found_id ], [ 'id' => $row_id ], [ '%d' ], [ '%d' ] );
                }
            }
            return $row_id ?: false;
        }

        $ok = $wpdb->insert( $table, $data, [ '%s','%s','%s','%s','%d' ] );
        if ( false === $ok ) return false;
        $row_id = (int) $wpdb->insert_id;

        // Newly inserted row: attempt to auto-link an existing WP page
        $found_id = self::find_existing_page_id( $slug, $args['shortcode'] );
        if ( $row_id && $found_id > 0 ) {
            $wpdb->update( $table, [ 'wp_page_id' => (int) $found_id ], [ 'id' => $row_id ], [ '%d' ], [ '%d' ] );
        }
        return $row_id;
    }

    /**
     * Get linked WP Page ID for a slug.
     *
     * @param string $slug
     * @return int 0 if not set
     */
    public static function get_page_id( $slug ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_system_pages';
        $slug = sanitize_key( $slug );
        $id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT wp_page_id FROM {$table} WHERE slug = %s", $slug ) );
        return max( 0, $id );
    }

    /**
     * Get permalink for a linked WP page by slug.
     * @param string $slug
     * @return string|false
     */
    public static function get_permalink( $slug ) {
        $pid = self::get_page_id( $slug );
        if ( $pid > 0 ) {
            $p = get_post( $pid );
            if ( $p && 'page' === $p->post_type && 'trash' !== $p->post_status ) {
                $url = get_permalink( $pid );
                if ( $url ) return $url;
            }
        }
        return false;
    }

    /**
     * Ensure a WP page exists for the slug; if missing, (re)create it and update wp_page_id.
     * @param string $slug
     * @return int The current/created page ID, or 0 on failure.
     */
    public static function ensure_page( $slug ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_system_pages';
        $slug = sanitize_key( $slug );
        if ( '' === $slug ) return 0;

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ) );
        if ( ! $row ) return 0;

        $pid = (int) $row->wp_page_id;
        $needs_create = true;
        if ( $pid > 0 ) {
            $p = get_post( $pid );
            if ( $p && 'page' === $p->post_type && 'trash' !== $p->post_status ) {
                $needs_create = false;
            }
        }

        if ( ! $needs_create ) return $pid;

        // Before creating, try to auto-link an existing page by slug or shortcode.
        $found_id = self::find_existing_page_id( $slug, (string) $row->shortcode );
        if ( $found_id > 0 ) {
            $wpdb->update( $table, [ 'wp_page_id' => (int) $found_id ], [ 'id' => (int) $row->id ], [ '%d' ], [ '%d' ] );
            return (int) $found_id;
        }

        $author = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $postarr = [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $row->title,
            'post_name'    => sanitize_title( $slug ),
            'post_author'  => $author,
            'post_content' => $row->shortcode,
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ];
        $new_id = wp_insert_post( $postarr, true );
        if ( is_wp_error( $new_id ) || ! $new_id ) return 0;

        $wpdb->update( $table, [ 'wp_page_id' => (int) $new_id ], [ 'id' => (int) $row->id ], [ '%d' ], [ '%d' ] );
        return (int) $new_id;
    }

    /**
     * Trash the linked WP page and nullify wp_page_id in DB.
     * @param string $slug
     * @return bool True on success.
     */
    public static function delete_page( $slug ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_system_pages';
        $slug = sanitize_key( $slug );
        if ( '' === $slug ) return false;

        $pid = self::get_page_id( $slug );
        if ( $pid > 0 && function_exists('wp_trash_post') ) {
            $trashed = wp_trash_post( $pid );
            if ( ! $trashed ) {
                // if couldn't trash, return false but do not unlink
                return false;
            }
        }
        // Set to SQL NULL explicitly to avoid casting to 0
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET wp_page_id = NULL WHERE slug = %s", $slug ) );
        return true;
    }
}

// Auto-ensure table early in the load so registration from other plugins is safe
add_action( 'plugins_loaded', [ 'TPW_Core_System_Pages', 'ensure_tables' ], 1 );

// Handle System Pages admin actions (recreate)
add_action( 'admin_post_tpw_system_pages_action', function(){
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Permission denied', 'tpw-core' ) );
    }
    // Nonce
    $nonce_ok = isset($_POST['tpw_sys_pages_nonce']) && wp_verify_nonce( $_POST['tpw_sys_pages_nonce'], 'tpw_system_pages_action' );
    if ( ! $nonce_ok ) {
        wp_die( __( 'Security check failed', 'tpw-core' ) );
    }

    $op   = isset($_POST['op']) ? sanitize_key( $_POST['op'] ) : '';
    $slug = isset($_POST['slug']) ? sanitize_key( $_POST['slug'] ) : '';
    $tab_url = add_query_arg( [ 'page' => 'tpw-core-settings', 'tab' => 'system-pages' ], admin_url( 'options-general.php' ) );

    if ( $op === 'recreate' && $slug ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_system_pages';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ) );
        $label = $row ? ( $row->title ?: $slug ) : $slug;

        $new_id = TPW_Core_System_Pages::ensure_page( $slug );
        if ( $new_id > 0 ) {
            // After recreation, update any nav menu items that point to this slug
            $new_url = get_permalink( $new_id );
            if ( $new_url ) {
                // Find all nav_menu_item posts with matching _tpw_page_slug meta
                $q = new WP_Query( [
                    'post_type'   => 'nav_menu_item',
                    'post_status' => 'any',
                    'nopaging'    => true,
                    'fields'      => 'ids',
                    'meta_query'  => [
                        [ 'key' => '_tpw_page_slug', 'value' => $slug, 'compare' => '=' ],
                    ],
                ] );
                if ( $q->have_posts() ) {
                    foreach ( $q->posts as $item_id ) {
                        // Update the menu item URL to the new permalink
                        $args = [ 'menu-item-url' => esc_url_raw( $new_url ), 'menu-item-status' => 'publish' ];
                        // We need the parent menu term to call wp_update_nav_menu_item; fallback to wp_update_post meta if unknown
                        $menu_terms = get_the_terms( $item_id, 'nav_menu' );
                        $first_menu = is_array($menu_terms) && ! empty($menu_terms) ? (int) $menu_terms[0]->term_id : 0;
                        if ( $first_menu ) {
                            wp_update_nav_menu_item( $first_menu, $item_id, $args );
                        } else {
                            // Fallback: directly update the _menu_item_url meta
                            update_post_meta( $item_id, '_menu_item_url', esc_url_raw( $new_url ) );
                        }
                    }
                }
                wp_reset_postdata();
            }
            add_settings_error( 'tpw_system_pages', 'tpw_sp_recreated', sprintf( __( "Page '%s' successfully recreated.", 'tpw-core' ), esc_html( $label ) ), 'updated' );
        } else {
            add_settings_error( 'tpw_system_pages', 'tpw_sp_recreate_failed', sprintf( __( "Failed to recreate page '%s'.", 'tpw-core' ), esc_html( $label ) ), 'error' );
        }
        // Persist for redirect
        $errs = get_settings_errors();
        set_transient( 'settings_errors', $errs, 30 );
        wp_safe_redirect( add_query_arg( 'settings-updated', '1', $tab_url ) );
        exit;
    }

    // Default redirect back
    wp_safe_redirect( $tab_url );
    exit;
} );
