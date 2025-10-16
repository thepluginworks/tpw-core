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
     * When true, echo extra debug information during operations.
     * Enabled via define('TPW_DEBUG_SYSTEM_PAGES', true);
     */
    protected static function debug_enabled() {
        return ( defined('TPW_DEBUG_SYSTEM_PAGES') && TPW_DEBUG_SYSTEM_PAGES );
    }

    /** Parse shortcode tag name from a shortcode string like "[tpw-control]" */
    protected static function parse_shortcode_tag( $shortcode ) {
        if ( ! is_string( $shortcode ) ) return '';
        if ( preg_match( '/\[(\w+)/', $shortcode, $m ) ) {
            return strtolower( $m[1] );
        }
        return '';
    }

    /**
     * Check if a content string contains a shortcode tag.
     * Uses WP's has_shortcode when possible, falling back to regex.
     */
    protected static function content_has_shortcode_tag( $content, $tag ) {
        if ( ! is_string( $content ) || '' === $content || '' === $tag ) return false;
        if ( function_exists('has_shortcode') ) {
            return has_shortcode( $content, $tag );
        }
        $pattern = '/\[' . preg_quote( $tag, '/' ) . '(?:\s|\])/i';
        return (bool) preg_match( $pattern, $content );
    }

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

        // Then: try to find a published page whose content contains the shortcode tag (exact tag match)
        $shortcode = is_string( $shortcode ) ? trim( $shortcode ) : '';
        $tag = self::parse_shortcode_tag( $shortcode );
        if ( $tag && function_exists('shortcode_exists') && ! shortcode_exists( $tag ) ) {
            // Shortcode isn't registered; skip content scan to avoid false positives
            if ( self::debug_enabled() && function_exists('error_log') ) {
                error_log( '[TPW System Pages] Skipping content scan for slug ' . $slug . ' since shortcode tag [' . $tag . '] is not registered.' );
            }
            return 0;
        }

        if ( $tag && class_exists( 'WP_Query' ) ) {
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
                    if ( self::content_has_shortcode_tag( $content, $tag ) ) {
                        wp_reset_postdata();
                        return (int) $pid;
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
            // Do not allow a different plugin/shortcode to overwrite an existing slug
            $same_plugin     = ( strtolower( (string) $existing->plugin ) === strtolower( (string) $data['plugin'] ) );
            $same_shortcode  = ( trim( (string) $existing->shortcode ) === trim( (string) $data['shortcode'] ) );
            if ( $same_plugin && $same_shortcode ) {
                $ok = $wpdb->update( $table, $data, [ 'id' => (int) $existing->id ], [ '%s','%s','%s','%s','%d' ], [ '%d' ] );
                $row_id = $ok !== false ? (int) $existing->id : 0;
            } else {
                // Keep existing row unchanged
                if ( function_exists('error_log') && ( defined('WP_DEBUG') && WP_DEBUG ) ) {
                    error_log( sprintf('[TPW System Pages] register_page("%s") ignored update because of plugin/shortcode mismatch. Existing plugin="%s" shortcode="%s"; incoming plugin="%s" shortcode="%s".',
                        $slug, (string)$existing->plugin, (string)$existing->shortcode, (string)$data['plugin'], (string)$data['shortcode']
                    ) );
                }
                $row_id = (int) $existing->id;
            }

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
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, wp_page_id, shortcode, title FROM {$table} WHERE slug = %s", $slug ) );
        if ( ! $row ) return 0;

        $pid = (int) $row->wp_page_id;
        if ( $pid > 0 ) {
            $p = get_post( $pid );
            $ok = ( $p && 'page' === $p->post_type && 'trash' !== $p->post_status );

            // Validate expected shortcode exists in content
            $tag = self::parse_shortcode_tag( (string) $row->shortcode );
            $has_sc = false;
            if ( $ok ) {
                $content = (string) get_post_field( 'post_content', $pid );
                $has_sc = $tag ? self::content_has_shortcode_tag( $content, $tag ) : true;
            }

            if ( ! $ok || ! $has_sc ) {
                if ( function_exists('error_log') && ( defined('WP_DEBUG') && WP_DEBUG ) ) {
                    error_log( sprintf('[TPW System Pages] Self-heal: slug=%s linked to invalid page id=%d (ok=%s, shortcode=%s, has_sc=%s). Recreating...', $slug, $pid, $ok?'1':'0', (string)$row->shortcode, $has_sc?'1':'0') );
                }
                // Unlink and recreate
                $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET wp_page_id = NULL WHERE id = %d", (int) $row->id ) );
                return (int) self::ensure_page( $slug );
            }
        }

        return max( 0, $pid );
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
     * Explicitly unlink a slug from its wp_page_id
     */
    public static function unlink( $slug ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_system_pages';
        $slug = sanitize_key( $slug );
        if ( '' === $slug ) return false;
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET wp_page_id = NULL WHERE slug = %s", $slug ) );
        return true;
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

// AJAX: Unlink a system page mapping
add_action( 'wp_ajax_tpw_system_page_unlink', function(){
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ), 403 );
    $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'tpw_system_pages_ajax' ) ) wp_send_json_error( __( 'Bad nonce', 'tpw-core' ), 400 );
    $slug = isset($_POST['slug']) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
    if ( ! $slug ) wp_send_json_error( __( 'Missing slug', 'tpw-core' ), 400 );
    $ok = TPW_Core_System_Pages::unlink( $slug );
    if ( ! $ok ) wp_send_json_error( __( 'Failed to unlink', 'tpw-core' ), 500 );

    // Return updated row HTML
    ob_start();
    TPW_Core_System_Pages_Render::render_row_by_slug( $slug );
    $html = ob_get_clean();
    wp_send_json_success( [ 'rowHtml' => $html ] );
} );

// AJAX: Recreate a system page immediately
add_action( 'wp_ajax_tpw_system_page_recreate', function(){
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'tpw-core' ), 403 );
    $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'tpw_system_pages_ajax' ) ) wp_send_json_error( __( 'Bad nonce', 'tpw-core' ), 400 );
    $slug = isset($_POST['slug']) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
    if ( ! $slug ) wp_send_json_error( __( 'Missing slug', 'tpw-core' ), 400 );
    // Unlink and recreate
    TPW_Core_System_Pages::unlink( $slug );
    $new = TPW_Core_System_Pages::ensure_page( $slug );
    if ( $new <= 0 ) wp_send_json_error( __( 'Failed to recreate page', 'tpw-core' ), 500 );

    ob_start();
    TPW_Core_System_Pages_Render::render_row_by_slug( $slug );
    $html = ob_get_clean();
    wp_send_json_success( [ 'rowHtml' => $html ] );
} );

/**
 * Lightweight helper to render a <tr> for a given slug, used by AJAX responses.
 * Split out to avoid duplicating row rendering logic in multiple places.
 */
if ( ! class_exists( 'TPW_Core_System_Pages_Render' ) ) {
class TPW_Core_System_Pages_Render {
    public static function render_row_by_slug( $slug ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_system_pages';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ) );
        if ( ! $row ) return;
        $title = $row->title ?: $row->slug;
        $slug  = $row->slug;
        $pid   = (int) $row->wp_page_id;
        $plugin = $row->plugin;
        $required = (int) $row->required;

        $page_obj = $pid ? get_post( $pid ) : null;
        $is_live = false;
        $perm = '';
        if ( $page_obj && $page_obj->post_type === 'page' && $page_obj->post_status === 'publish' ) {
            $is_live = true;
            $perm = get_permalink( $pid );
        }
        $nonce = wp_create_nonce('tpw_system_pages_ajax');
        ?>
        <tr data-slug="<?php echo esc_attr($slug); ?>">
            <td><?php echo esc_html( $title ); ?></td>
            <td><code><?php echo esc_html( $slug ); ?></code></td>
            <td>
                <?php if ( $pid > 0 ) : ?>
                    <div>
                        <strong>#<?php echo (int) $pid; ?></strong>
                        <?php if ( $perm ) : ?>
                            <br /><a href="<?php echo esc_url( $perm ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $perm ); ?></a>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top:4px;">
                        <?php if ( $perm ) : ?>
                            <a class="button button-small" href="<?php echo esc_url( $perm ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'tpw-core' ); ?></a>
                        <?php endif; ?>
                        <?php if ( $page_obj ) : ?>
                            <a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $pid, '' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Edit', 'tpw-core' ); ?></a>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <em><?php esc_html_e( 'Not linked', 'tpw-core' ); ?></em>
                <?php endif; ?>
            </td>
            <td>
                <?php if ( $is_live ) : ?>
                    <span style="color:#008a20;">✅ <?php esc_html_e( 'Exists', 'tpw-core' ); ?></span>
                <?php else : ?>
                    <span style="color:#b52727;">❌ <?php esc_html_e( 'Missing', 'tpw-core' ); ?></span>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html( $plugin ); ?></td>
            <td>
                <?php if ( $required ) : ?>
                    <span class="tpw-badge tpw-badge-required" style="background:#0b6cad;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;">Required</span>
                <?php else : ?>
                    <span class="tpw-badge" style="background:#e1e1e1;color:#333;padding:2px 6px;border-radius:4px;font-size:11px;">Optional</span>
                <?php endif; ?>
            </td>
            <td>
                <button class="tpw-btn tpw-btn-secondary js-tpw-sp-unlink" data-nonce="<?php echo esc_attr($nonce); ?>" data-slug="<?php echo esc_attr($slug); ?>"><?php esc_html_e('Unlink','tpw-core'); ?></button>
                <button class="tpw-btn tpw-btn-primary js-tpw-sp-recreate" data-nonce="<?php echo esc_attr($nonce); ?>" data-slug="<?php echo esc_attr($slug); ?>"><?php esc_html_e('Recreate','tpw-core'); ?></button>
                <?php if ( $is_live && $perm ) : ?>
                    <a class="tpw-btn" href="<?php echo esc_url( $perm ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'tpw-core' ); ?></a>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
}
}
