<?php

/**
 * AJAX endpoints for the TPW Members module.
 *
 * Registers secured AJAX handlers for admin management (search, create, photo
 * operations) and member self‑service endpoints (profile update, photo edit).
 * All endpoints enforce nonces and capabilities.
 *
 * @since 1.0.0
 */
class TPW_Member_Ajax {
    /**
     * Register AJAX actions for admin and member endpoints.
     *
     * @since 1.0.0
     * @return void
     */
    public static function init() {
        add_action( 'wp_ajax_tpw_member_search_users', [ __CLASS__, 'search_users' ] );
        add_action( 'wp_ajax_tpw_member_create_from_user', [ __CLASS__, 'create_from_user' ] );
        add_action( 'wp_ajax_tpw_member_get_details', [ __CLASS__, 'get_details' ] );
        add_action( 'wp_ajax_tpw_member_send_email', [ __CLASS__, 'send_email' ] );
        add_action( 'wp_ajax_tpw_member_profile_update', [ __CLASS__, 'profile_update' ] );
        // Photo management (admin only) for immediate actions on edit form
        add_action( 'wp_ajax_tpw_member_photo_delete', [ __CLASS__, 'photo_delete' ] );
        add_action( 'wp_ajax_tpw_member_photo_replace', [ __CLASS__, 'photo_replace' ] );
    // Photo management for members on their own profile (respects profile photo mode)
    add_action( 'wp_ajax_tpw_member_profile_photo_delete', [ __CLASS__, 'profile_photo_delete' ] );
    add_action( 'wp_ajax_tpw_member_profile_photo_replace', [ __CLASS__, 'profile_photo_replace' ] );
        // Admin settings: searchable fields
        add_action( 'wp_ajax_tpw_member_toggle_searchable', [ __CLASS__, 'toggle_searchable' ] );
        add_action( 'wp_ajax_tpw_member_save_search_config', [ __CLASS__, 'save_search_config' ] );
        // Dependent options endpoint (frontend dynamic filtering)
        add_action( 'wp_ajax_tpw_member_dependent_options', [ __CLASS__, 'dependent_options' ] );
        add_action( 'wp_ajax_nopriv_tpw_member_dependent_options', [ __CLASS__, 'dependent_options' ] );
    }

    /**
    * Whether the current user can manage members.
     *
     * @since 1.0.0
     * @return bool
     */
    protected static function user_can_manage() {
        if ( ! class_exists( 'TPW_Member_Access' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-access.php';
        }

        return TPW_Member_Access::can_manage_members_current();
    }

    /**
     * Verify manage capability and a specific nonce for admin endpoints.
     *
     * @since 1.0.0
     * @param string $action Nonce action key
     * @return void Sends JSON error and exits on failure
     */
    protected static function check_caps_and_nonce( $action ) {
        if ( ! self::user_can_manage() ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( $_POST['_wpnonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
        }
    }

    /**
     * Toggle a field's searchable status.
     * Expects: field_key (string), searchable ("1"/"0").
     * Nonce: tpw_member_searchable_nonce
     */
    public static function toggle_searchable() {
        self::check_caps_and_nonce( 'tpw_member_searchable_nonce' );
        $field_key  = isset($_POST['field_key']) ? sanitize_key($_POST['field_key']) : '';
        $searchable = isset($_POST['searchable']) ? ( $_POST['searchable'] == '1' ) : false;
        $depends_on = isset($_POST['depends_on']) ? sanitize_key($_POST['depends_on']) : '';
        if ( $field_key === '' ) {
            wp_send_json_error(['message'=>'Missing field_key'],400);
        }
        $opt = get_option( 'tpw_member_searchable_fields', [] );
        if ( ! is_array($opt) ) { $opt = []; }

        if ( ! $searchable ) {
            if ( isset($opt[$field_key]) ) {
                unset($opt[$field_key]);
                update_option( 'tpw_member_searchable_fields', $opt );
            }
            wp_send_json_success(['removed'=>true]);
        }

        if ( ! isset($opt[$field_key]) || ! is_array($opt[$field_key]) ) {
            $label = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : $field_key;
            $stype_raw = isset($_POST['search_type']) ? sanitize_text_field($_POST['search_type']) : 'text';
            $stype = strtolower( str_replace( [ ' ', '-' ], '_', $stype_raw ) );
            $valid_types = ['text','select','date_range','has_value','checkbox'];
            if ( ! in_array( $stype, $valid_types, true ) ) { $stype = 'text'; }
            $admin_only = false;
            if ( isset($_POST['admin_only']) ) {
                $ao = $_POST['admin_only'];
                if ( is_bool($ao) ) {
                    $admin_only = $ao;
                } else {
                    $admin_only = in_array( strtolower( (string) $ao ), [ '1', 'true', 'on', 'yes', 'y', 't' ], true );
                }
            }
            $opt_source_raw = isset($_POST['options_source']) ? sanitize_text_field($_POST['options_source']) : 'static';
            $options_source = in_array( $opt_source_raw, ['static','dynamic'], true ) ? $opt_source_raw : 'static';
            $opts = [];
            if ($stype === 'select' && $options_source === 'static' && isset($_POST['options'])) {
                $raw_options = wp_unslash($_POST['options']);
                $lines = preg_split('/[\r\n,]+/', (string) $raw_options );
                foreach ( $lines as $line ) {
                    $line = trim( (string) $line );
                    if ( $line !== '' ) $opts[] = $line;
                }
            }
            $opt[$field_key] = [
                'label'       => $label,
                'searchable'  => true,
                'search_type' => $stype,
                'options'     => $opts,
                'admin_only'  => (bool) $admin_only,
                'options_source' => $options_source,
                'depends_on' => $depends_on ? $depends_on : '',
            ];
        } else {
            $opt[$field_key]['searchable']  = true;
            if ( empty($opt[$field_key]['search_type']) ) {
                $opt[$field_key]['search_type'] = 'text';
            }
            if ( ! isset($opt[$field_key]['options']) ) {
                $opt[$field_key]['options'] = [];
            }
            if ( ! isset($opt[$field_key]['admin_only']) ) {
                $opt[$field_key]['admin_only'] = false;
            }
            if ( ! isset($opt[$field_key]['options_source']) ) {
                $opt[$field_key]['options_source'] = 'static';
            }
            if ( ! isset($opt[$field_key]['depends_on']) ) {
                $opt[$field_key]['depends_on'] = $depends_on ? $depends_on : '';
            }
        }
        update_option( 'tpw_member_searchable_fields', $opt );
        wp_send_json_success(['saved'=>true,'config'=>$opt[$field_key]]);
    }

    /**
     * Save full search config from modal.
     * Expects: field_key, search_type, options (optional)
     * Nonce: tpw_member_searchable_nonce
     */
    public static function save_search_config() {
        self::check_caps_and_nonce( 'tpw_member_searchable_nonce' );
        $field_key   = isset($_POST['field_key']) ? sanitize_key($_POST['field_key']) : '';
        $search_type_raw = isset($_POST['search_type']) ? sanitize_text_field($_POST['search_type']) : 'text';
        $search_type = strtolower( str_replace( [ ' ', '-' ], '_', $search_type_raw ) );
        $raw_options = isset($_POST['options']) ? wp_unslash($_POST['options']) : '';
        $label       = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : $field_key;
        $admin_only  = false;
        if ( isset($_POST['admin_only']) ) {
            $ao = $_POST['admin_only'];
            if ( is_bool($ao) ) {
                $admin_only = $ao;
            } else {
                $admin_only = in_array( strtolower( (string) $ao ), [ '1', 'true', 'on', 'yes', 'y', 't' ], true );
            }
        }
        $depends_on = isset($_POST['depends_on']) ? sanitize_key($_POST['depends_on']) : '';
        if ( $field_key === '' ) {
            wp_send_json_error(['message'=>'Missing field_key'],400);
        }
        $valid_types = ['text','select','date_range','has_value','checkbox'];
        if ( ! in_array( $search_type, $valid_types, true ) ) {
            $search_type = 'text';
        }
        $opt_source_raw = isset($_POST['options_source']) ? sanitize_text_field($_POST['options_source']) : 'static';
        $options_source = in_array( $opt_source_raw, ['static','dynamic'], true ) ? $opt_source_raw : 'static';
        $opt = get_option( 'tpw_member_searchable_fields', [] );
        if ( ! is_array($opt) ) { $opt = []; }

        $parsed_options = [];
        if ( $search_type === 'select' && $options_source === 'static' ) {
            $lines = preg_split('/[\r\n,]+/', (string) $raw_options );
            foreach ( $lines as $line ) {
                $line = trim( (string) $line );
                if ( $line !== '' ) $parsed_options[] = $line;
            }
        }

        $opt[$field_key] = [
            'label'       => $label,
            'searchable'  => true,
            'search_type' => $search_type,
            'options'     => $parsed_options,
            'admin_only'  => (bool) $admin_only,
            'options_source' => $options_source,
            'depends_on' => $depends_on ? $depends_on : '',
        ];
        update_option( 'tpw_member_searchable_fields', $opt );
        wp_send_json_success(['saved'=>true,'config'=>$opt[$field_key]]);
    }

    /**
     * Fetch distinct child field values filtered by a parent field value.
     * Expects: child (field_key), parent (depends_on field_key), parent_value
     * Returns JSON: { options: [ { value, label } ] }
     */
    public static function dependent_options() {
        require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-access.php';
        $is_admin = TPW_Member_Access::can_manage_members_current();
        $is_member = TPW_Member_Access::is_member_current();
        if ( ! $is_admin && ! $is_member ) {
            wp_send_json_error( [ 'message' => 'Access denied' ], 403 );
        }
        global $wpdb;
        $child  = isset($_REQUEST['field']) ? sanitize_key($_REQUEST['field']) : '';
        $parent = isset($_REQUEST['depends_on_field']) ? sanitize_key($_REQUEST['depends_on_field']) : '';
        $pval_raw = isset($_REQUEST['depends_on_value']) ? wp_unslash($_REQUEST['depends_on_value']) : '';
        $parent_value = sanitize_text_field( (string) $pval_raw );
        if ( strtolower( $parent_value ) === 'any' ) {
            $parent_value = '';
        }
        if ( $child === '' || $parent === '' ) {
            wp_send_json_success( [ 'options' => [] ] );
        }
        $fs = $wpdb->prefix . 'tpw_field_settings';
        $dep_conf = $wpdb->get_var( $wpdb->prepare( "SELECT depends_on FROM {$fs} WHERE field_key = %s", $child ) );
        if ( $dep_conf !== null && $dep_conf !== '' && $dep_conf !== $parent ) {
            wp_send_json_success( [ 'options' => [] ] );
        }
        if ( ! $is_admin && function_exists('tpw_can_group_view_field') ) {
            $vis_group = isset($GLOBALS['tpw_members_vis_group']) ? sanitize_key($GLOBALS['tpw_members_vis_group']) : 'member';
            if ( ! tpw_can_group_view_field( $vis_group, $child ) ) {
                wp_send_json_success( [ 'options' => [] ] );
            }
        }
        $members_table = $wpdb->prefix . 'tpw_members';
        $cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $members_table, 0 );
        $is_child_core = in_array( $child, $cols, true );
        $is_parent_core = in_array( $parent, $cols, true );
        $fields_table = $wpdb->prefix . 'tpw_member_fields';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $fields_table ) );
        if ( $table_exists === $fields_table ) {
            $defs = $wpdb->get_results( $wpdb->prepare( "SELECT field_key, field_type FROM {$fields_table} WHERE field_key IN (%s,%s)", $parent, $child ), OBJECT_K );
            if ( is_array($defs) && ! empty($defs) ) {
                if ( isset($defs[$child]) ) {
                    $ft = strtolower( (string) $defs[$child]->field_type );
                    if ( $ft === 'core' || $ft === 'builtin' ) { $is_child_core = true; } elseif ( $ft === 'meta' ) { $is_child_core = false; }
                }
                if ( isset($defs[$parent]) ) {
                    $ft = strtolower( (string) $defs[$parent]->field_type );
                    if ( $ft === 'core' || $ft === 'builtin' ) { $is_parent_core = true; } elseif ( $ft === 'meta' ) { $is_parent_core = false; }
                }
            }
        }
        $options = [];
        $role_hash = $is_admin ? 'admin' : 'member';
        $cache_key = 'tpw_dep_opts_' . md5( $child . '|' . $parent . '|' . $parent_value . '|' . $role_hash );
        $cached = get_transient( $cache_key );
        if ( $cached !== false && is_array($cached) ) {
            wp_send_json_success( [ 'options' => $cached ] );
        }

        $meta_table = $wpdb->prefix . 'tpw_member_meta';
        $hm_table = $wpdb->prefix . 'tpw_members_household_member';
        $allowed_statuses = $is_admin ? [] : TPW_Member_Access::get_allowed_statuses();
        $status_placeholders = ! empty( $allowed_statuses ) ? implode( ',', array_fill( 0, count( $allowed_statuses ), '%s' ) ) : '';
        $member_visibility_join = '';
        $member_visibility_where = '';
        $member_visibility_params = [];
        if ( ! $is_admin ) {
            $member_visibility_join = " LEFT JOIN {$hm_table} AS hm_dir ON hm_dir.member_id = mem.id ";
            $member_visibility_where = " AND COALESCE(mem.share_with_members, 1) = 1";
            if ( $status_placeholders !== '' ) {
                $member_visibility_where .= " AND mem.status IN ({$status_placeholders})";
                $member_visibility_params = array_merge( $member_visibility_params, array_values( $allowed_statuses ) );
            }
            $member_visibility_where .= " AND ( hm_dir.member_id IS NULL OR hm_dir.is_primary = 1 )";
            $member_visibility_where .= " AND ( hm_dir.member_id IS NULL OR hm_dir.role IS NULL OR hm_dir.role <> 'child' )";
        }

        if ( $is_child_core && $is_parent_core ) {
            if ( $parent_value === '' ) {
                $sql = "SELECT DISTINCT mem.`{$child}` AS v FROM {$members_table} AS mem{$member_visibility_join} WHERE mem.`{$child}` IS NOT NULL AND mem.`{$child}` <> ''{$member_visibility_where} ORDER BY LOWER(mem.`{$child}`) ASC";
                $rows = empty( $member_visibility_params ) ? $wpdb->get_col( $sql ) : $wpdb->get_col( $wpdb->prepare( $sql, ...$member_visibility_params ) );
            } else {
                $sql = "SELECT DISTINCT mem.`{$child}` AS v FROM {$members_table} AS mem{$member_visibility_join} WHERE mem.`{$parent}` = %s AND mem.`{$child}` IS NOT NULL AND mem.`{$child}` <> ''{$member_visibility_where} ORDER BY LOWER(mem.`{$child}`) ASC";
                $params = array_merge( [ $parent_value ], $member_visibility_params );
                $rows = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) );
            }
            $options = array_map( function($v){ return [ 'value' => $v, 'label' => $v ]; }, array_values( array_filter( array_map('strval', (array)$rows ) ) ) );
        } elseif ( ! $is_child_core && ! $is_parent_core ) {
            if ( $parent_value === '' ) {
                $sql = "SELECT DISTINCT c.meta_value AS v FROM {$meta_table} AS c INNER JOIN {$members_table} AS mem ON mem.id = c.member_id{$member_visibility_join} WHERE c.meta_key = %s AND c.meta_value IS NOT NULL AND c.meta_value <> ''{$member_visibility_where} ORDER BY LOWER(c.meta_value) ASC";
                $params = array_merge( [ $child ], $member_visibility_params );
                $rows = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) );
            } else {
                $sql = "SELECT DISTINCT c.meta_value AS v FROM {$meta_table} AS m INNER JOIN {$meta_table} AS c ON m.member_id = c.member_id INNER JOIN {$members_table} AS mem ON mem.id = c.member_id{$member_visibility_join} WHERE m.meta_key = %s AND c.meta_key = %s AND LOWER(m.meta_value) = LOWER(%s) AND c.meta_value IS NOT NULL AND c.meta_value <> ''{$member_visibility_where} ORDER BY LOWER(c.meta_value) ASC";
                $params = array_merge( [ $parent, $child, $parent_value ], $member_visibility_params );
                $rows = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) );
            }
            $options = array_map( function($v){ return [ 'value' => $v, 'label' => $v ]; }, array_values( array_filter( array_map('strval', (array)$rows ) ) ) );
        } else {
            if ( $is_parent_core && ! $is_child_core ) {
                if ( $parent_value === '' ) {
                    $sql = "SELECT DISTINCT m2.meta_value AS v FROM {$meta_table} AS m2 INNER JOIN {$members_table} AS mem ON mem.id = m2.member_id{$member_visibility_join} WHERE m2.meta_key = %s AND m2.meta_value IS NOT NULL AND m2.meta_value <> ''{$member_visibility_where} ORDER BY LOWER(m2.meta_value) ASC";
                    $params = array_merge( [ $child ], $member_visibility_params );
                    $rows = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) );
                } else {
                    $sql = "SELECT DISTINCT m2.meta_value AS v FROM {$members_table} AS mem INNER JOIN {$meta_table} AS m2 ON mem.id = m2.member_id{$member_visibility_join} WHERE mem.`{$parent}` = %s AND m2.meta_key = %s AND m2.meta_value IS NOT NULL AND m2.meta_value <> ''{$member_visibility_where} ORDER BY LOWER(m2.meta_value) ASC";
                    $params = array_merge( [ $parent_value, $child ], $member_visibility_params );
                    $rows = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) );
                }
            } elseif ( ! $is_parent_core && $is_child_core ) {
                if ( $parent_value === '' ) {
                    $sql = "SELECT DISTINCT mem.`{$child}` AS v FROM {$members_table} AS mem{$member_visibility_join} WHERE mem.`{$child}` IS NOT NULL AND mem.`{$child}` <> ''{$member_visibility_where} ORDER BY LOWER(mem.`{$child}`) ASC";
                    $rows = empty( $member_visibility_params ) ? $wpdb->get_col( $sql ) : $wpdb->get_col( $wpdb->prepare( $sql, ...$member_visibility_params ) );
                } else {
                    $sql = "SELECT DISTINCT mem.`{$child}` AS v FROM {$meta_table} AS pm INNER JOIN {$members_table} AS mem ON pm.member_id = mem.id{$member_visibility_join} WHERE pm.meta_key = %s AND LOWER(pm.meta_value) = LOWER(%s) AND mem.`{$child}` IS NOT NULL AND mem.`{$child}` <> ''{$member_visibility_where} ORDER BY LOWER(mem.`{$child}`) ASC";
                    $params = array_merge( [ $parent, $parent_value ], $member_visibility_params );
                    $rows = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) );
                }
            } else {
                $rows = [];
            }
            $options = array_map( function($v){ return [ 'value' => $v, 'label' => $v ]; }, array_values( array_filter( array_map('strval', (array)$rows ) ) ) );
        }
        set_transient( $cache_key, $options, 5 * MINUTE_IN_SECONDS );
        wp_send_json_success( [ 'options' => $options ] );
    }
    /**
     * Search WordPress users not already linked to TPW members.
     *
     * Nonce: tpw_member_create_nonce
     *
     * @since 1.0.0
     * @return void JSON { results: [...] }
     */
    public static function search_users() {
        self::check_caps_and_nonce( 'tpw_member_create_nonce' );

        global $wpdb;
        $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';

        // Collect user_ids already in tpw_members
        $members_table = $wpdb->prefix . 'tpw_members';
        $existing_ids = $wpdb->get_col( "SELECT user_id FROM {$members_table} WHERE user_id IS NOT NULL AND user_id > 0" );
        $exclude_ids = array_map( 'intval', array_filter( $existing_ids ) );

        // Build meta queries for first_name/last_name
        $meta_query = [];
        if ( $term !== '' ) {
            $meta_query['relation'] = 'OR';
            $meta_query[] = [ 'key' => 'first_name', 'value' => $term, 'compare' => 'LIKE' ];
            $meta_query[] = [ 'key' => 'last_name',  'value' => $term, 'compare' => 'LIKE' ];
        }

        $query_args = [
            'exclude'      => $exclude_ids,
            'number'       => 20,
            'search'       => $term ? '*' . $term . '*' : '',
            'search_columns' => [ 'user_login', 'user_email', 'user_nicename', 'display_name' ],
            'fields'       => [ 'ID', 'user_login', 'user_email', 'display_name' ],
            'meta_query'   => $meta_query,
        ];

        $user_query = new WP_User_Query( $query_args );
        $users = [];
        foreach ( $user_query->get_results() as $u ) {
            $first = get_user_meta( $u->ID, 'first_name', true );
            $last  = get_user_meta( $u->ID, 'last_name', true );
            $users[] = [
                'id'          => (int) $u->ID,
                'user_login'  => $u->user_login,
                'user_email'  => $u->user_email,
                'display_name'=> $u->display_name,
                'first_name'  => $first,
                'last_name'   => $last,
            ];
        }

        wp_send_json_success( [ 'results' => $users ] );
    }

    /**
     * Resolve a default society_id for created members.
     *
     * @since 1.0.0
     * @return int
     */
    protected static function resolve_society_id() {
        return tpw_core_get_site_society_id();
    }

    /**
     * Create a TPW member row from an existing WP user.
     *
     * Nonce: tpw_member_create_nonce
     *
     * @since 1.0.0
     * @return void JSON { member_id }
     */
    public static function create_from_user() {
        self::check_caps_and_nonce( 'tpw_member_create_nonce' );

        global $wpdb;
        $user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
        if ( $user_id <= 0 ) {
            wp_send_json_error( [ 'message' => 'Invalid user_id' ], 400 );
        }

        // Prevent duplicates
        $table = $wpdb->prefix . 'tpw_members';
        $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) );
        if ( $exists > 0 ) {
            wp_send_json_error( [ 'message' => 'User is already a member' ], 409 );
        }

        $wp_user = get_user_by( 'ID', $user_id );
        if ( ! $wp_user ) {
            wp_send_json_error( [ 'message' => 'User not found' ], 404 );
        }

        $first = get_user_meta( $user_id, 'first_name', true );
        $last  = get_user_meta( $user_id, 'last_name', true );

        $data = [
            'society_id' => self::resolve_society_id(),
            'user_id'    => $user_id,
            'first_name' => $first ?: '',
            'surname'    => $last ?: '',
            'email'      => $wp_user->user_email ?: '',
            'username'   => $wp_user->user_login ?: '',
            'status'     => 'active',
        ];

        // Create
        require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
        $controller = new TPW_Member_Controller();
    $member_id = $controller->add_member( $data );

        if ( ! $member_id ) {
            wp_send_json_error( [ 'message' => 'Failed to create member' ], 500 );
        }

        // Ensure 'member' capability is added to this WP user non-destructively
        if ( class_exists('TPW_Member_Roles') ) {
            TPW_Member_Roles::ensure_member_cap( $user_id );
        }

        wp_send_json_success( [ 'member_id' => (int) $member_id ] );
    }

    /**
     * Gate for endpoints that accept admins or valid members.
     *
     * @since 1.0.0
     * @return void Sends JSON error and exits on failure
     */
    protected static function member_or_admin_required() {
                require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-access.php';
                $is_admin  = TPW_Member_Access::can_manage_members_current();
                $is_member = TPW_Member_Access::is_member_current();
                if ( ! $is_admin && ! $is_member ) {
                        wp_send_json_error( [ 'message' => 'Access denied' ], 403 );
                }
        }

    /**
     * Return safe HTML for a member details modal.
     *
     * Nonce: tpw_member_create_nonce
     *
     * @since 1.0.0
     * @return void JSON { html, requested_id, returned_id }
     */
    public static function get_details() {
                self::member_or_admin_required();
                $requested_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
                if ( $requested_id <= 0 ) wp_send_json_error(['message'=>'Invalid member_id','requested_id'=>$requested_id,'code'=>'invalid_member_id'],400);
                // Nonce enforcement (modal-specific)
                $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
                if ( ! wp_verify_nonce( $nonce, 'tpw_member_create_nonce' ) ) {
                    wp_send_json_error(['message'=>'Invalid or expired request. Please refresh the page.','requested_id'=>$requested_id,'code'=>'invalid_nonce'],403);
                }
                require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
                // Ensure field loader and meta helpers are available
                if ( ! class_exists( 'TPW_Member_Field_Loader' ) ) {
                    require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-field-loader.php';
                }
                if ( ! class_exists( 'TPW_Member_Meta' ) ) {
                    require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-meta.php';
                }
                global $wpdb;
                $members_table = $wpdb->prefix . 'tpw_members';
                // Direct primary key lookup (decoupled from any active filters)
                $sql = $wpdb->prepare("SELECT * FROM {$members_table} WHERE id = %d", $requested_id);
                $m = $wpdb->get_row( $sql );
                if ( ! $m ) {
                    wp_send_json_error(['message'=>'Member not found','requested_id'=>$requested_id,'code'=>'not_found'],404);
                }

                // Build safe HTML for details modal using configured field sort order
                $is_admin = TPW_Member_Access::can_manage_members_current();
                if ( ! $is_admin && ! TPW_Member_Access::member_is_visible_to_standard_members( $m ) ) {
                    wp_send_json_error(['message'=>'Member not found','requested_id'=>$requested_id,'code'=>'not_found'],404);
                }

                // Directory/profile safety: Non-admins can only view primary members.
                // Hard rule: dependants/children must never be visible to other members.
                $is_primary_household_member = false;
                $household_id_for_member = 0;
                if ( ! $is_admin ) {
                    require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-household-repository.php';
                    $repo = new TPW_Member_Household_Repository();
                    $membership = $repo->get_household_for_member( $requested_id );
                    if ( $membership && isset( $membership->household_id ) ) {
                        $household_id_for_member = (int) $membership->household_id;
                        $role = isset( $membership->role ) ? (string) $membership->role : '';
                        $is_primary_household_member = ( isset( $membership->is_primary ) && 1 === (int) $membership->is_primary );
                        // Never allow children to be viewed.
                        if ( 'child' === $role ) {
                            wp_send_json_error(['message'=>'Member not found','requested_id'=>$requested_id,'code'=>'not_found'],404);
                        }
                        // Directory is primary members only.
                        if ( ! $is_primary_household_member ) {
                            wp_send_json_error(['message'=>'Member not found','requested_id'=>$requested_id,'code'=>'not_found'],404);
                        }
                    }
                }

                $fields = TPW_Member_Field_Loader::get_all_enabled_fields();
                // Extra guard to ensure sort order is respected
                usort($fields, function($a, $b){
                    $sa = isset($a['sort_order']) ? (int)$a['sort_order'] : PHP_INT_MAX;
                    $sb = isset($b['sort_order']) ? (int)$b['sort_order'] : PHP_INT_MAX;
                    if ($sa === $sb) {
                        return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
                    }
                    return $sa <=> $sb;
                });

                // Fetch all meta for this member
                $meta = TPW_Member_Meta::get_all_meta( $requested_id );
                $known_checkbox_fields = [ 'is_committee', 'is_match_manager', 'is_admin', 'is_noticeboard_admin', 'is_gallery_admin', 'is_manage_members', 'is_volunteer' ];
                if ( ! class_exists( 'TPW_Identity_Compat' ) ) {
                    require_once plugin_dir_path( __FILE__ ) . 'class-tpw-identity-compat.php';
                }
                if ( ! class_exists( 'TPW_Identity' ) ) {
                    require_once plugin_dir_path( __FILE__ ) . 'class-tpw-identity.php';
                }
                $compat_member_user_id = isset( $m->user_id ) ? (int) $m->user_id : 0;
                $compat_member = null;
                if ( $compat_member_user_id > 0 ) {
                    $compat_member = TPW_Identity::get_member_record( $compat_member_user_id );
                }

                ob_start();
                ?>
                <div class="tpw-member-details">
                    <?php
                    // Group by section name after sorting
                    $grouped = [];
                    foreach ($fields as $f) {
                        $sec = isset($f['section']) && $f['section'] !== '' ? $f['section'] : 'General';
                        $grouped[$sec][] = $f;
                    }

                    foreach ( $grouped as $section_name => $section_fields ) {
                        $section_buf = '';
                        $section_count = 0;
                        foreach ( $section_fields as $field ) {
                            $key   = $field['key'];
                            $label = $field['label'];
                            $type  = $field['type'];
                            $is_core = !empty($field['is_core']);

                            // Non-admins must pass field visibility
                            if ( ! $is_admin ) {
                                if ( ! function_exists( 'tpw_can_group_view_field' ) || ! tpw_can_group_view_field( 'member', $key ) ) {
                                    continue;
                                }
                            }

                            // Pull value from core object or meta
                            $value = '';
                            if ( $is_core ) {
                                if (
                                    in_array( $key, $known_checkbox_fields, true )
                                    && $compat_member_user_id > 0
                                    && $compat_member
                                    && isset( $compat_member->id )
                                    && (int) $compat_member->id === (int) $m->id
                                ) {
                                    $value = TPW_Identity_Compat::has_member_flag( $compat_member_user_id, $key )
                                        ? '1'
                                        : ( isset( $compat_member->$key ) ? $compat_member->$key : '' );
                                } else {
                                    $value = isset($m->$key) ? $m->$key : '';
                                }
                            } else {
                                $value = isset($meta[$key]) ? $meta[$key] : '';
                            }

                            // Render rules by type
                            $display = '';
                            if ( $type === 'date' ) {
                                if ( ! empty($value) ) {
                                    $display = tpw_format_date( $value );
                                }
                            } elseif ( $type === 'checkbox' || in_array( $key, $known_checkbox_fields, true ) ) {
                                $display = ($value == '1' || $value === 1 || $value === true || $value === 'true') ? 'Yes' : 'No';
                            } else {
                                // Generic scalar display
                                if ( is_scalar( $value ) ) {
                                    $display = (string) $value;
                                }
                            }

                            // Skip empty non-boolean values
                            if ( $display === '' ) {
                                continue;
                            }

                            $section_buf .= '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $display ) . '</p>';
                            $section_count++;
                        }
                        // Only output the section if it has at least one field rendered
                        if ( $section_count > 0 ) {
                            echo '<fieldset class="tpw-section">';
                            echo '<legend class="tpw-section__legend">' . esc_html( $section_name ) . '</legend>';
                            echo $section_buf; // already escaped pieces
                            echo '</fieldset>';
                        }
                    }

                    // For admins, add Created/Updated at the end if available
                    if ( $is_admin ) {
                        $has_created = ! empty( $m->created_at );
                        $has_updated = ! empty( $m->updated_at );
                        if ( $has_created || $has_updated ) {
                            echo '<fieldset class="tpw-section">';
                            echo '<legend class="tpw-section__legend">' . esc_html__( 'Admin Section', 'tpw-core' ) . '</legend>';
                            if ( $has_created ) {
                                echo '<p><strong>' . esc_html__( 'Created', 'tpw-core' ) . ':</strong> ' . esc_html( tpw_format_datetime( $m->created_at ) ) . '</p>';
                            }
                            if ( $has_updated ) {
                                echo '<p><strong>' . esc_html__( 'Updated', 'tpw-core' ) . ':</strong> ' . esc_html( tpw_format_datetime( $m->updated_at ) ) . '</p>';
                            }
                            echo '</fieldset>';
                        }
                    }

                    // Family membership (optional): show adult secondary members (partner only) on primary profile.
                    $show_family = get_option( 'tpw_members_show_adult_family_on_primary_profile', '0' ) === '1';
                    if ( $show_family ) {
                        // Resolve household membership if not already known.
                        if ( 0 === $household_id_for_member ) {
                            require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-household-repository.php';
                            $repo = new TPW_Member_Household_Repository();
                            $membership = $repo->get_household_for_member( $requested_id );
                            if ( $membership && isset( $membership->household_id ) ) {
                                $household_id_for_member = (int) $membership->household_id;
                                $is_primary_household_member = ( isset( $membership->is_primary ) && 1 === (int) $membership->is_primary );
                            }
                        }

                        if ( 0 < $household_id_for_member && $is_primary_household_member ) {
                            require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-household-repository.php';
                            $repo = new TPW_Member_Household_Repository();
                            $members = (array) $repo->get_household_members( $household_id_for_member );
                            $partner_names = [];
                            foreach ( $members as $hm ) {
                                $role = isset( $hm->role ) ? (string) $hm->role : '';
                                if ( 'partner' !== $role ) {
                                    continue;
                                }
                                $first = isset( $hm->first_name ) ? trim( (string) $hm->first_name ) : '';
                                $surname = isset( $hm->surname ) ? trim( (string) $hm->surname ) : '';
                                $name = trim( $first . ( ( '' !== $first && '' !== $surname ) ? ' ' : '' ) . $surname );
                                if ( '' !== $name ) {
                                    $partner_names[] = $name;
                                }
                            }

                            if ( ! empty( $partner_names ) ) {
                                echo '<fieldset class="tpw-section">';
                                echo '<legend class="tpw-section__legend">' . esc_html__( 'Family membership', 'tpw-core' ) . '</legend>';
                                foreach ( $partner_names as $n ) {
                                    echo '<p><strong>' . esc_html__( 'Partner', 'tpw-core' ) . ':</strong> ' . esc_html( $n ) . '</p>';
                                }
                                echo '</fieldset>';
                            }
                        }
                    }
                    ?>
                </div>
                <?php
                $html = ob_get_clean();
        wp_send_json_success(['html' => $html, 'requested_id'=>$requested_id, 'returned_id'=>(int)$m->id]);
        }

    /**
     * Send an email to a member on behalf of the current user.
     *
     * Filter: tpw_members/mail_from_header to override From header.
     * Nonce: tpw_member_create_nonce
     *
     * @since 1.0.0
     * @return void JSON { message }
     */
    public static function send_email() {
            self::member_or_admin_required();
            // Verify nonce shared with directory JS
            $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
            if ( ! wp_verify_nonce( $nonce, 'tpw_member_create_nonce' ) ) {
                wp_send_json_error(['message'=>'Invalid nonce'],403);
            }

            $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
            $from_name = isset($_POST['from_name']) ? sanitize_text_field($_POST['from_name']) : '';
            $from_email = isset($_POST['from_email']) ? sanitize_email($_POST['from_email']) : '';
            $message_raw = isset($_POST['message']) ? wp_unslash($_POST['message']) : '';
            $message = wp_kses_post( $message_raw );
            if ( $member_id<=0 || empty($from_name) || empty($from_email) || empty($message) ) {
                wp_send_json_error(['message'=>'Missing fields'],400);
            }
            if ( ! is_email( $from_email ) ) {
                wp_send_json_error(['message'=>'Invalid email'],400);
            }

            // Directory safety: non-admin members can only contact primary members.
            // Hard rule: dependants/children must never be reachable via directory endpoints.
            require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-access.php';
            $is_admin = TPW_Member_Access::can_manage_members_current();
            if ( ! $is_admin ) {
                require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-household-repository.php';
                $repo = new TPW_Member_Household_Repository();
                $membership = $repo->get_household_for_member( $member_id );
                if ( $membership && isset( $membership->household_id ) ) {
                    $role = isset( $membership->role ) ? (string) $membership->role : '';
                    $is_primary = ( isset( $membership->is_primary ) && 1 === (int) $membership->is_primary );
                    if ( 'child' === $role || ! $is_primary ) {
                        wp_send_json_error(['message'=>'Recipient not found'],404);
                    }
                }
            }
                require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
                $controller = new TPW_Member_Controller();
                $m = $controller->get_member($member_id);
                if ( ! $m || empty($m->email) ) wp_send_json_error(['message'=>'Recipient not found'],404);
                if ( ! $is_admin && ! TPW_Member_Access::member_is_visible_to_standard_members( $m ) ) {
                    wp_send_json_error(['message'=>'Recipient not found'],404);
                }

            // Let WordPress / SMTP plugins control the From header by default.
            // If a site wants to force a specific, authenticated From, they can provide it via this filter.
            // We do NOT force a From here; Reply-To will direct replies to the sender.
            $maybe_from_header = apply_filters( 'tpw_members/mail_from_header', '', $m, $from_name, $from_email );

            $headers = [];
            if ( is_string( $maybe_from_header ) && '' !== trim( $maybe_from_header ) ) {
                $headers[] = $maybe_from_header;
            }
            // Reply-To routes replies to the provided sender
            $headers[] = 'Reply-To: ' . $from_name . ' <' . $from_email . '>';
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $site_name = wp_specialchars_decode( get_bloginfo('name'), ENT_QUOTES );
            $subject = sprintf( '[%s] Message from %s', $site_name, $from_name );
            $body = wp_strip_all_tags( $message );
            $sent = class_exists( 'TPW_Email' )
                ? TPW_Email::dispatch_mail( $m->email, $subject, $body, $headers, [], [
                    'source'       => 'TPW_Member_Ajax::email_member',
                    'message_type' => 'plain',
                ] )
                : wp_mail( $m->email, $subject, $body, $headers );
            if ( ! $sent ) wp_send_json_error(['message'=>'Failed to send email'],500);
            wp_send_json_success(['message'=>'Sent']);
        }

    /**
     * Member self‑service: update a single editable profile field.
     *
     * Filters:
     * - tpw_members/wp_admin_can_view_profile
     * - tpw_members/profile_allow_all_statuses
     *
     * Nonce: tpw_member_profile_update
     *
     * @since 1.0.0
     * @return void JSON { message, field_key }
     */
    public static function profile_update() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(['message' => 'Access denied'], 403);
        }
        $admin_can_view = apply_filters('tpw_members/wp_admin_can_view_profile', true);
        $allow_all_statuses = apply_filters('tpw_members/profile_allow_all_statuses', true);

        require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-email-sync.php';
        $user = wp_get_current_user();
        $controller = new TPW_Member_Controller();
        $member = $controller->get_member_by_user_id( (int) $user->ID );
        if ( ! $member ) {
            wp_send_json_error(['message' => 'Member not found'], 404);
        }

        if ( ! ( current_user_can('manage_options') && $admin_can_view ) ) {
            if ( ! $allow_all_statuses ) {
                require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-access.php';
                if ( ! TPW_Member_Access::is_member_current() ) {
                    wp_send_json_error(['message' => 'Access denied'], 403);
                }
            }
        }
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
        if ( ! wp_verify_nonce( $nonce, 'tpw_member_profile_update' ) ) {
            wp_send_json_error(['message'=>'Invalid nonce'], 403);
        }

        $field_key = isset($_POST['field_key']) ? sanitize_key($_POST['field_key']) : '';
        $field_value = isset($_POST['field_value']) ? wp_unslash($_POST['field_value']) : '';
        if ( $field_key === '' ) {
            wp_send_json_error(['message'=>'Missing field_key'], 400);
        }

        require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-access.php';
        if (
            TPW_Member_Access::is_protected_member_permission_field( $field_key )
            && ! TPW_Member_Access::can_edit_protected_member_permission_fields_current()
        ) {
            wp_send_json_error(['message'=>'You do not have permission to edit this field'], 403);
        }

        $editable = get_option('tpw_member_editable_fields', []);
        if ( ! is_array($editable) ) { $editable = []; }
        if ( ! in_array( $field_key, $editable, true ) ) {
            wp_send_json_error(['message'=>'Field not editable'], 403);
        }

        require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-meta.php';

        $is_core = property_exists( $member, $field_key );
        $old_value = '';
        if ( $is_core ) {
            $old_value = isset( $member->$field_key ) ? (string) $member->$field_key : '';
        } else {
            $old_value = (string) TPW_Member_Meta::get_meta( (int) $member->id, $field_key );
        }

        $ok = false;
        if ( $is_core ) {
            $san = is_string($field_value) ? wp_kses_post( $field_value ) : $field_value;
            if ( 'share_with_members' === $field_key ) {
                $san = in_array( strtolower( (string) $field_value ), [ '1', 'true', 'yes', 'on' ], true ) ? 1 : 0;
            }
            if ( 'email' === $field_key ) {
                $sync_result = TPW_Member_Email_Sync::sync_linked_member_email(
                    $controller,
                    $member,
                    (string) $san,
                    [ 'source' => 'self_service_ajax' ]
                );

                if ( is_wp_error( $sync_result ) ) {
                    $error_code = $sync_result->get_error_code();
                    if ( 'tpw_member_email_invalid' === $error_code ) {
                        wp_send_json_error([
                            'message'   => 'Please enter a valid email address.',
                            'code'      => 'invalid_email',
                            'field_key' => 'email',
                        ], 400);
                    }

                    if ( 'tpw_member_email_broken_link' === $error_code ) {
                        wp_send_json_error([
                            'message'   => 'Your member record is linked to a WordPress account that could not be loaded. Please contact an administrator.',
                            'code'      => 'broken_link',
                            'field_key' => 'email',
                        ], 409);
                    }

                    if ( 'tpw_member_email_conflict' === $error_code ) {
                        wp_send_json_error([
                            'message'   => 'That email address is already in use by another account.',
                            'code'      => 'email_conflict',
                            'field_key' => 'email',
                        ], 409);
                    }

                    wp_send_json_error([
                        'message'   => 'We could not update your email address. No email change was saved.',
                        'code'      => 'email_sync_failed',
                        'field_key' => 'email',
                    ], 500);
                }

                $field_value = (string) $sync_result['normalized_email'];
                $ok          = true;
            } else {
                $update = [ $field_key => $san ];
                if (
                    $field_key === 'whi'
                    && method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' )
                    && TPW_Member_Field_Loader::is_flexigolf_active()
                ) {
                    $update['whi_updated'] = current_time('Y-m-d');
                }
                $ok = (bool) $controller->update_member( (int) $member->id, $update );
            }
        } else {
            $ok = (bool) TPW_Member_Meta::save_meta( (int) $member->id, $field_key, (string) $field_value );
        }

        if ( ! $ok ) {
            wp_send_json_error(['message'=>'Failed to save'], 500);
        }

        $notify_to = get_option( 'tpw_member_change_notify_email', '' );
        if ( is_string($notify_to) ) { $notify_to = trim( $notify_to ); }
        if ( $notify_to !== '' && is_email( $notify_to ) ) {
            $new_value = is_string($field_value) ? (string) $field_value : (string) wp_json_encode( $field_value );
            if ( trim( (string) $old_value ) !== trim( (string) $new_value ) ) {
                if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
                if ( function_exists( 'nocache_headers' ) ) { nocache_headers(); }

                if ( ! class_exists( 'TPW_Member_Field_Loader' ) ) {
                    require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-field-loader.php';
                }
                $fields = TPW_Member_Field_Loader::get_all_enabled_fields();
                $label = $field_key;
                foreach ( (array) $fields as $f ) {
                    if ( isset($f['key']) && $f['key'] === $field_key ) { $label = (string) ($f['label'] ?? $field_key); break; }
                }

                $first = isset($member->first_name) ? (string) $member->first_name : '';
                $last  = isset($member->surname) ? (string) $member->surname : '';
                $member_name = trim( $first . ' ' . $last );
                $profile_url = '';
                $profile_page_id = (int) get_option( 'tpw_member_profile_page_id', 0 );
                if ( $profile_page_id > 0 ) {
                    $profile_url = get_permalink( $profile_page_id );
                }

                $settings = get_option( 'flexievent_settings', [] );
                $date_format = isset($settings['date_format']) ? (string) $settings['date_format'] : 'd-m-Y';
                $time_format = isset($settings['time_format']) ? (string) $settings['time_format'] : 'H:i';
                $stamp = date_i18n( $date_format . ' ' . $time_format, current_time('timestamp') );

                $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
                $subject = sprintf( '[%s] Profile Updated by %s', $site_name, $member_name !== '' ? $member_name : 'Member' );

                $body_lines = [];
                $body_lines[] = sprintf( 'The following profile details were updated by %s on %s:', $member_name !== '' ? $member_name : 'a member', $stamp );
                $body_lines[] = '';
                $body_lines[] = 'Field: Old → New';
                $body_lines[] = $label . ': ' . wp_strip_all_tags( (string) $old_value ) . ' → ' . wp_strip_all_tags( (string) $new_value );
                $body_lines[] = '';
                if ( $profile_url ) {
                    $body_lines[] = 'View member: ' . $profile_url;
                }
                $body = implode( "\n", $body_lines );

                $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
                $member_email = isset($member->email) ? sanitize_email( (string) $member->email ) : '';
                if ( is_email( $member_email ) ) {
                    $headers[] = 'Reply-To: ' . ( $member_name !== '' ? $member_name : 'Member' ) . ' <' . $member_email . '>';
                }

                if ( class_exists( 'TPW_Email' ) ) {
                    TPW_Email::dispatch_mail( $notify_to, $subject, $body, $headers, [], [
                        'source'       => 'TPW_Member_Ajax::profile_update',
                        'message_type' => 'plain',
                    ] );
                } else {
                    @wp_mail( $notify_to, $subject, $body, $headers );
                }
            }
        }
        wp_send_json_success(['message'=>'Saved', 'field_key' => $field_key ]);
    }

        /**
         * Member self-service: delete own photo.
         * Nonce: tpw_member_profile_update (same as other self-edits)
         */
        public static function profile_photo_delete() {
            if ( ! is_user_logged_in() ) {
                wp_send_json_error( [ 'message' => 'Access denied' ], 403 );
            }
            // Respect global photos toggle and per-profile photo mode
            if ( get_option('tpw_members_use_photos', '0') !== '1' ) {
                wp_send_json_error( [ 'message' => 'Photos are disabled' ], 403 );
            }
            $mode = get_option( 'tpw_member_profile_photo_mode', 'view' );
            if ( $mode !== 'edit' ) {
                wp_send_json_error( [ 'message' => 'Editing photo is disabled' ], 403 );
            }

            $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
            if ( ! wp_verify_nonce( $nonce, 'tpw_member_profile_update' ) ) {
                wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
            }

            require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
            $user = wp_get_current_user();
            $controller = new TPW_Member_Controller();
            $m = $controller->get_member_by_user_id( (int) $user->ID );
            if ( ! $m ) {
                wp_send_json_error( [ 'message' => 'Member not found' ], 404 );
            }

            $rel = isset($m->member_photo) ? trim((string)$m->member_photo) : '';
            $deleted = false;
            if ( $rel !== '' ) {
                $uploads = wp_get_upload_dir();
                $base = trailingslashit( $uploads['basedir'] );
                $old_full = wp_normalize_path( $base . ltrim( $rel, '/' ) );
                $base_norm = wp_normalize_path( $base );
                if ( strpos( $old_full, $base_norm ) === 0 && file_exists( $old_full ) ) {
                    $deleted = @unlink( $old_full );
                }
            }
            // Clear DB regardless
            $ok = (bool) $controller->update_member( (int) $m->id, [ 'member_photo' => '' ] );
            if ( ! $ok ) {
                wp_send_json_error( [ 'message' => 'Failed to update member' ], 500 );
            }
            wp_send_json_success( [ 'deleted' => (bool) $deleted ] );
        }

        /**
         * Member self-service: replace own photo with uploaded file.
         * Expects: file field 'photo'. Nonce: tpw_member_profile_update
         */
        public static function profile_photo_replace() {
            if ( ! is_user_logged_in() ) {
                wp_send_json_error( [ 'message' => 'Access denied' ], 403 );
            }
            if ( get_option('tpw_members_use_photos', '0') !== '1' ) {
                wp_send_json_error( [ 'message' => 'Photos are disabled' ], 403 );
            }
            $mode = get_option( 'tpw_member_profile_photo_mode', 'view' );
            if ( $mode !== 'edit' ) {
                wp_send_json_error( [ 'message' => 'Editing photo is disabled' ], 403 );
            }

            $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
            if ( ! wp_verify_nonce( $nonce, 'tpw_member_profile_update' ) ) {
                wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
            }

            if ( ! isset($_FILES['photo']) || ! is_array($_FILES['photo']) || empty($_FILES['photo']['name']) ) {
                wp_send_json_error( [ 'message' => 'Missing photo' ], 400 );
            }
            $file = $_FILES['photo'];
            $max_bytes = 2 * 1024 * 1024; // 2MB
            if ( (int) $file['size'] > $max_bytes ) {
                wp_send_json_error( [ 'message' => 'Uploaded photo exceeds 2MB.' ], 400 );
            }
            $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png' ], true ) ) {
                wp_send_json_error( [ 'message' => 'Invalid file type. Allowed: JPG, JPEG, PNG.' ], 400 );
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $overrides = [ 'test_form' => false, 'mimes' => [ 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png' ] ];
            $uploaded = wp_handle_upload( $file, $overrides );
            if ( isset( $uploaded['error'] ) ) {
                wp_send_json_error( [ 'message' => 'Photo upload failed: ' . $uploaded['error'] ], 400 );
            }
            $source_path = $uploaded['file'] ?? '';
            if ( ! $source_path || ! file_exists( $source_path ) ) {
                wp_send_json_error( [ 'message' => 'Uploaded file missing.' ], 500 );
            }

            $uploads = wp_get_upload_dir();
            $target_dir = trailingslashit( $uploads['basedir'] ) . 'tpw-members/photos/';
            if ( ! wp_mkdir_p( $target_dir ) ) {
                @unlink( $source_path );
                wp_send_json_error( [ 'message' => 'Failed to create photo directory.' ], 500 );
            }
            $base_name = sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) );
            $use_ext   = ( $ext === 'jpeg' ) ? 'jpg' : $ext;
            $target_filename = wp_unique_filename( $target_dir, $base_name . '.' . $use_ext );
            $target_path = trailingslashit( $target_dir ) . $target_filename;

            // Resize/compress similar to admin
            $editor = wp_get_image_editor( $source_path );
            if ( ! is_wp_error( $editor ) ) {
                $editor->resize( 500, 500, false );
                if ( in_array( $use_ext, [ 'jpg', 'jpeg' ], true ) && method_exists( $editor, 'set_quality' ) ) {
                    $editor->set_quality( 75 );
                }
                $saved = $editor->save( $target_path );
                if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
                    copy( $source_path, $target_path );
                }
            } else {
                copy( $source_path, $target_path );
            }
            @unlink( $source_path );

            $relative = 'tpw-members/photos/' . $target_filename;

            require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
            $controller = new TPW_Member_Controller();
            $user = wp_get_current_user();
            $m = $controller->get_member_by_user_id( (int) $user->ID );
            if ( ! $m ) {
                // Clean up file
                @unlink( $target_path );
                wp_send_json_error( [ 'message' => 'Member not found' ], 404 );
            }
            $prev_rel = isset( $m->member_photo ) ? trim( (string) $m->member_photo ) : '';
            $ok = (bool) $controller->update_member( (int) $m->id, [ 'member_photo' => $relative ] );
            if ( ! $ok ) {
                // Cleanup the new file if DB update failed
                $full_new = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . $relative );
                if ( file_exists( $full_new ) ) { @unlink( $full_new ); }
                wp_send_json_error( [ 'message' => 'Failed to update member.' ], 500 );
            }
            if ( $prev_rel ) {
                $base = trailingslashit( $uploads['basedir'] );
                $old_full = wp_normalize_path( $base . ltrim( $prev_rel, '/' ) );
                $base_norm = wp_normalize_path( $base );
                if ( strpos( $old_full, $base_norm ) === 0 && file_exists( $old_full ) ) {
                    @unlink( $old_full );
                }
            }
            $abs_url = rtrim( $uploads['baseurl'], '/' ) . '/' . ltrim( $relative, '/' );
            $abs_url = add_query_arg( 't', time(), $abs_url );
            wp_send_json_success( [ 'url' => $abs_url, 'rel' => $relative ] );
        }

        /**
         * Immediately delete a member's current photo and clear the DB field.
         * Expects: member_id (int), _wpnonce (tpw_member_photo_nonce)
         */
        public static function photo_delete() {
            if ( ! self::user_can_manage() ) {
                wp_send_json_error( [ 'message' => 'Access denied' ], 403 );
            }
            $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
            if ( ! wp_verify_nonce( $nonce, 'tpw_member_photo_nonce' ) ) {
                wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
            }
            $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
            if ( $member_id <= 0 ) {
                wp_send_json_error( [ 'message' => 'Invalid member_id' ], 400 );
            }
            require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
            $controller = new TPW_Member_Controller();
            $m = $controller->get_member( $member_id );
            if ( ! $m ) {
                wp_send_json_error( [ 'message' => 'Member not found' ], 404 );
            }
            $rel = isset($m->member_photo) ? trim((string)$m->member_photo) : '';
            $deleted = false;
            if ( $rel !== '' ) {
                $uploads = wp_get_upload_dir();
                $base = trailingslashit( $uploads['basedir'] );
                $old_full = wp_normalize_path( $base . ltrim( $rel, '/' ) );
                $base_norm = wp_normalize_path( $base );
                if ( strpos( $old_full, $base_norm ) === 0 && file_exists( $old_full ) ) {
                    $deleted = @unlink( $old_full );
                }
            }
            // Clear DB field regardless of filesystem delete result
            $ok = (bool) $controller->update_member( $member_id, [ 'member_photo' => '' ] );
            if ( ! $ok ) {
                wp_send_json_error( [ 'message' => 'Failed to update member' ], 500 );
            }
            wp_send_json_success( [ 'deleted' => (bool) $deleted ] );
        }

        /**
         * Immediately replace a member's photo with an uploaded file.
         * Expects: member_id (int), file field name 'photo', _wpnonce (tpw_member_photo_nonce)
         * Returns: { url: absolute_url, rel: relative_path }
         */
        public static function photo_replace() {
            if ( ! self::user_can_manage() ) {
                wp_send_json_error( [ 'message' => 'Access denied' ], 403 );
            }
            $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
            if ( ! wp_verify_nonce( $nonce, 'tpw_member_photo_nonce' ) ) {
                wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
            }
            $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
            if ( $member_id <= 0 ) {
                wp_send_json_error( [ 'message' => 'Invalid member_id' ], 400 );
            }
            if ( ! isset($_FILES['photo']) || ! is_array($_FILES['photo']) || empty($_FILES['photo']['name']) ) {
                wp_send_json_error( [ 'message' => 'Missing photo' ], 400 );
            }

            $file = $_FILES['photo'];
            $max_bytes = 2 * 1024 * 1024; // 2MB
            if ( (int) $file['size'] > $max_bytes ) {
                wp_send_json_error( [ 'message' => 'Uploaded photo exceeds 2MB.' ], 400 );
            }
            $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png' ], true ) ) {
                wp_send_json_error( [ 'message' => 'Invalid file type. Allowed: JPG, JPEG, PNG.' ], 400 );
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $overrides = [ 'test_form' => false, 'mimes' => [ 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png' ] ];
            $uploaded = wp_handle_upload( $file, $overrides );
            if ( isset( $uploaded['error'] ) ) {
                wp_send_json_error( [ 'message' => 'Photo upload failed: ' . $uploaded['error'] ], 400 );
            }
            $source_path = $uploaded['file'] ?? '';
            if ( ! $source_path || ! file_exists( $source_path ) ) {
                wp_send_json_error( [ 'message' => 'Uploaded file missing.' ], 500 );
            }

            // Prepare target path
            $uploads = wp_get_upload_dir();
            $target_dir = trailingslashit( $uploads['basedir'] ) . 'tpw-members/photos/';
            if ( ! wp_mkdir_p( $target_dir ) ) {
                @unlink( $source_path );
                wp_send_json_error( [ 'message' => 'Failed to create photo directory.' ], 500 );
            }
            $base_name = sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) );
            $use_ext   = ( $ext === 'jpeg' ) ? 'jpg' : $ext;
            $target_filename = wp_unique_filename( $target_dir, $base_name . '.' . $use_ext );
            $target_path = trailingslashit( $target_dir ) . $target_filename;

            // Resize/compress
            $editor = wp_get_image_editor( $source_path );
            if ( ! is_wp_error( $editor ) ) {
                $editor->resize( 500, 500, false );
                if ( in_array( $use_ext, [ 'jpg', 'jpeg' ], true ) && method_exists( $editor, 'set_quality' ) ) {
                    $editor->set_quality( 75 );
                }
                $saved = $editor->save( $target_path );
                if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
                    copy( $source_path, $target_path );
                }
            } else {
                copy( $source_path, $target_path );
            }
            @unlink( $source_path );

            $relative = 'tpw-members/photos/' . $target_filename;

            // Update DB and delete previous file
            require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
            $controller = new TPW_Member_Controller();
            $member = $controller->get_member( $member_id );
            $prev_rel = $member && isset( $member->member_photo ) ? trim( (string) $member->member_photo ) : '';
            $ok = (bool) $controller->update_member( $member_id, [ 'member_photo' => $relative ] );
            if ( ! $ok ) {
                // Cleanup the new file if DB update failed
                $full_new = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . $relative );
                if ( file_exists( $full_new ) ) { @unlink( $full_new ); }
                wp_send_json_error( [ 'message' => 'Failed to update member.' ], 500 );
            }
            if ( $prev_rel ) {
                $base = trailingslashit( $uploads['basedir'] );
                $old_full = wp_normalize_path( $base . ltrim( $prev_rel, '/' ) );
                $base_norm = wp_normalize_path( $base );
                if ( strpos( $old_full, $base_norm ) === 0 && file_exists( $old_full ) ) {
                    @unlink( $old_full );
                }
            }
            $abs_url = rtrim( $uploads['baseurl'], '/' ) . '/' . ltrim( $relative, '/' );
            // Add cache-buster
            $abs_url = add_query_arg( 't', time(), $abs_url );
            wp_send_json_success( [ 'url' => $abs_url, 'rel' => $relative ] );
        }
}
