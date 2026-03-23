<?php

class TPW_Member_Controller {

    /**
     * Get all members, optionally filtered.
    *
    * @since 1.0.0
    * @param array $args Query args (status, search, adv_* filters, paging)
    * @return array<object> Rows from tpw_members
     */
    public function get_members( $args = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';

    // IMPORTANT: select only base member columns to avoid meta JOIN 'id' columns overwriting $member->id
    $sql = "SELECT {$table}.* FROM $table WHERE 1=1";
    $meta_joins = [];

    // Directory safety: optionally restrict results to primary household members only.
    // Also hard-exclude household children when enabled.
    if ( ! empty( $args['directory_primary_only'] ) ) {
        $hm_table = $wpdb->prefix . 'tpw_members_household_member';
        $meta_joins[] = " LEFT JOIN {$hm_table} AS hm_dir ON hm_dir.member_id = {$table}.id ";
        // Include non-household members OR household primary.
        $sql .= " AND ( hm_dir.member_id IS NULL OR hm_dir.is_primary = 1 )";
        // Hard rule: never include household children in member-facing lists/search.
        $sql .= " AND ( hm_dir.member_id IS NULL OR hm_dir.role IS NULL OR hm_dir.role <> 'child' )";
    }

        // Optional filters
        if ( isset( $args['society_id'] ) ) {
            $sql .= $wpdb->prepare( " AND society_id = %d", $args['society_id'] );
        }

        // Exact status filter (admin list)
        if ( isset( $args['status'] ) && $args['status'] !== '' ) {
            $sql .= $wpdb->prepare( " AND status = %s", $args['status'] );
        }

        // Status filter for member directory view
        if ( isset( $args['status_in'] ) && is_array( $args['status_in'] ) && ! empty( $args['status_in'] ) ) {
            // Prepare placeholders
            $placeholders = implode( ',', array_fill( 0, count( $args['status_in'] ), '%s' ) );
            $sql .= $wpdb->prepare( " AND status IN ($placeholders)", $args['status_in'] );
        }

        // Search filter
        if ( isset( $args['search'] ) && ! empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $sql .= $wpdb->prepare( " AND (
                CONCAT(first_name, ' ', surname) LIKE %s
                OR email LIKE %s
                OR status LIKE %s
            )", $like, $like, $like );
        }

        // Advanced text filters (column LIKE %value%)
        if ( ! empty($args['adv_text']) && is_array($args['adv_text']) ) {
            $cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            foreach ($args['adv_text'] as $col => $val) {
                $col = sanitize_key($col);
                if (!in_array($col, $cols, true)) continue;
                if ($val === '') continue;
                $like = '%' . $wpdb->esc_like( $val ) . '%';
                $sql .= $wpdb->prepare( " AND `{$col}` LIKE %s", $like );
            }
        }

        // Advanced select filters (column = value)
        if ( ! empty($args['adv_select']) && is_array($args['adv_select']) ) {
            $cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            foreach ($args['adv_select'] as $col => $val) {
                $col = sanitize_key($col);
                if (!in_array($col, $cols, true)) continue;
                if ($val === '') continue;
                $sql .= $wpdb->prepare( " AND `{$col}` = %s", $val );
            }
        }

        // Advanced select filters for custom meta fields: requires JOINs
        if ( ! empty($args['adv_select_meta']) && is_array($args['adv_select_meta']) ) {
            $i = 0;
            foreach ( $args['adv_select_meta'] as $mkey => $mval ) {
                $i++;
                $alias = 'mm_' . $i;
                $mkey = sanitize_key( $mkey );
                $meta_joins[] = $wpdb->prepare(
                    " INNER JOIN {$wpdb->prefix}tpw_member_meta AS {$alias} ON {$alias}.member_id = {$table}.id AND {$alias}.meta_key = %s AND {$alias}.meta_value = %s ",
                    $mkey, $mval
                );
            }
        }

        // Advanced date range filters (column >= from, <= to)
        if ( ! empty($args['adv_date_range']) && is_array($args['adv_date_range']) ) {
            $cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            foreach ($args['adv_date_range'] as $col => $range) {
                $col = sanitize_key($col);
                if (!in_array($col, $cols, true)) continue;
                $from = isset($range['from']) ? $range['from'] : '';
                $to   = isset($range['to']) ? $range['to'] : '';
                // Exclude empty/zero dates when applying any bound
                if ($from !== '' || $to !== '') {
                    $sql .= " AND `{$col}` IS NOT NULL AND `{$col}` <> '' AND `{$col}` <> '0000-00-00'";
                }
                if ($from !== '') {
                    $sql .= $wpdb->prepare( " AND `{$col}` >= %s", $from );
                }
                if ($to !== '') {
                    $sql .= $wpdb->prepare( " AND `{$col}` <= %s", $to );
                }
            }
        }

        // Has value filters (support for core columns and custom meta)
        if ( isset($args['has_value']) && is_array($args['has_value']) && ! empty($args['has_value']) ) {
            $cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            // Track how many meta joins already exist to avoid alias collisions
            $meta_index = count($meta_joins);
            foreach ( $args['has_value'] as $key ) {
                $sanekey = sanitize_key( $key );
                if ( in_array( $sanekey, $cols, true ) ) {
                    // Core column: non-empty check
                    $sql .= " AND `{$sanekey}` IS NOT NULL AND `{$sanekey}` <> ''";
                } else {
                    // Custom field stored in meta: join on presence of non-empty meta_value
                    $meta_index++;
                    $alias = 'mm_hv_' . $meta_index;
                    // Support known alias mapping (e.g., tpw_subcode historically stored as tpw_subpaid)
                    $keys_to_match = [$sanekey];
                    if ($sanekey === 'tpw_subcode') { $keys_to_match[] = 'tpw_subpaid'; }
                    if (count($keys_to_match) > 1) {
                        $placeholders = implode(',', array_fill(0, count($keys_to_match), '%s'));
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic placeholders are prepared below
                        $meta_joins[] = $wpdb->prepare(
                            " INNER JOIN {$wpdb->prefix}tpw_member_meta AS {$alias} ON {$alias}.member_id = {$table}.id AND {$alias}.meta_key IN ($placeholders) AND {$alias}.meta_value <> '' ",
                            $keys_to_match
                        );
                    } else {
                        $meta_joins[] = $wpdb->prepare(
                            " INNER JOIN {$wpdb->prefix}tpw_member_meta AS {$alias} ON {$alias}.member_id = {$table}.id AND {$alias}.meta_key = %s AND {$alias}.meta_value <> '' ",
                            $sanekey
                        );
                    }
                }
            }
        }

        // Checkbox filters (boolean-style columns equal to 1 when checked)
        if ( isset($args['adv_checkbox']) && is_array($args['adv_checkbox']) && ! empty($args['adv_checkbox']) ) {
            $cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            foreach ( $args['adv_checkbox'] as $col ) {
                $col = sanitize_key( $col );
                if ( in_array( $col, $cols, true ) ) {
                    $sql .= " AND `{$col}` = 1";
                }
            }
        }

        // Append meta joins (if any)
        if ( ! empty($meta_joins) ) {
            $sql = str_replace( "FROM $table", "FROM $table" . implode('', $meta_joins), $sql );
        }

        $sql .= " ORDER BY surname ASC, first_name ASC";

        if ( isset( $args['per_page'] ) && isset( $args['page'] ) ) {
            $offset = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
            $sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", (int) $args['per_page'], $offset );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * Get a member by linked WP user ID
     *
     * @since 1.0.0
     * @param int $user_id
     * @return object|null
     */
    public function get_member_by_user_id( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", (int)$user_id ) );
    }

    /**
     * Get a single member by ID.
     *
     * @since 1.0.0
     * @param int $id
     * @return object|null
     */
    public function get_member( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id )
        );
    }

    /**
     * Add a new member (core fields only).
     *
     * @since 1.0.0
     * @param array $data Column => value map for tpw_members
     * @return int|false Inserted member ID or false on failure
     */
    public function add_member( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';

        $society_id = tpw_core_resolve_entity_society_id( isset( $data['society_id'] ) ? $data['society_id'] : 0 );

        // Base insert data
        $insert = [
            'society_id'            => $society_id,
            'user_id'               => $data['user_id'],
            'first_name'            => isset($data['first_name']) ? $data['first_name'] : '',
            'surname'               => isset($data['surname']) ? $data['surname'] : '',
            'initials'              => isset($data['initials']) ? $data['initials'] : '',
            'title'                 => isset($data['title']) ? $data['title'] : '',
            'decoration'            => isset($data['decoration']) ? $data['decoration'] : '',
            'email'                 => isset($data['email']) ? $data['email'] : '',
            'mobile'                => isset($data['mobile']) ? $data['mobile'] : '',
            'landline'              => isset($data['landline']) ? $data['landline'] : '',
            'member_photo'          => isset($data['member_photo']) ? $data['member_photo'] : '',
            'address1'              => isset($data['address1']) ? $data['address1'] : '',
            'address2'              => isset($data['address2']) ? $data['address2'] : '',
            'town'                  => isset($data['town']) ? $data['town'] : '',
            'county'                => isset($data['county']) ? $data['county'] : '',
            'postcode'              => isset($data['postcode']) ? $data['postcode'] : '',
            'country'               => isset($data['country']) ? $data['country'] : '',
            'dob'                   => isset($data['dob']) ? $data['dob'] : '',
            'date_joined'           => isset($data['date_joined']) ? $data['date_joined'] : '',
            'status'                => isset($data['status']) ? $data['status'] : '',
            'is_committee'          => isset($data['is_committee']) ? $data['is_committee'] : 0,
            'is_match_manager'      => isset($data['is_match_manager']) ? $data['is_match_manager'] : 0,
            'is_admin'              => isset($data['is_admin']) ? $data['is_admin'] : 0,
            'is_noticeboard_admin'  => isset($data['is_noticeboard_admin']) ? $data['is_noticeboard_admin'] : 0,
            'is_gallery_admin'      => isset($data['is_gallery_admin']) ? $data['is_gallery_admin'] : 0,
            'is_manage_members'     => isset($data['is_manage_members']) ? $data['is_manage_members'] : 0,
            'is_volunteer'          => isset($data['is_volunteer']) ? $data['is_volunteer'] : 0,
            'username'              => isset($data['username']) ? $data['username'] : '',
            'password_hash'         => isset($data['password_hash']) ? $data['password_hash'] : '',
            'created_at'            => current_time( 'mysql' ),
            'updated_at'            => current_time( 'mysql' ),
        ];

        // Add FlexiGolf fields if plugin active and columns exist
        if ( method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active() ) {
            $cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            if ( in_array( 'whi', (array) $cols, true ) ) {
                $insert['whi'] = isset($data['whi']) ? $data['whi'] : '';
            }
            if ( in_array( 'whi_updated', (array) $cols, true ) ) {
                $insert['whi_updated'] = isset($data['whi_updated']) ? $data['whi_updated'] : '';
            }
            if ( in_array( 'cdh_id', (array) $cols, true ) ) {
                $insert['cdh_id'] = isset($data['cdh_id']) ? $data['cdh_id'] : '';
            }
        }

        $result = $wpdb->insert( $table, $insert );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update an existing member.
     *
     * @since 1.0.0
     * @param int   $id Member ID
     * @param array $data Partial column => value map
     * @return int|false Rows affected or false on error
     */
    public function update_member( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';

        // Partial update: only update fields explicitly provided in $data
        $allowed_columns = [
            'society_id',
            'user_id',
            'first_name',
            'surname',
            'initials',
            'title',
            'decoration',
            'email',
            'mobile',
            'landline',
            'member_photo',
            'address1',
            'address2',
            'town',
            'county',
            'postcode',
            'country',
            'dob',
            'date_joined',
            'status',
            'is_committee',
            'is_match_manager',
            'is_admin',
            'is_noticeboard_admin',
            'is_gallery_admin',
            'is_manage_members',
            'is_volunteer',
            'username',
            'password_hash',
        ];

        // If FlexiGolf is active and columns exist, allow updating them
        if ( method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active() ) {
            $cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            foreach ( ['whi','whi_updated','cdh_id'] as $fg_col ) {
                if ( in_array( $fg_col, (array) $cols, true ) ) {
                    $allowed_columns[] = $fg_col;
                }
            }
        }

        $update = [];
        foreach ( $allowed_columns as $col ) {
            if ( array_key_exists( $col, $data ) ) {
                $update[ $col ] = $data[ $col ];
            }
        }

        // Normalise user_id so blank values become NULL (avoid UNIQUE '0' collisions)
        if ( array_key_exists( 'user_id', $update ) ) {
            $uid = $update['user_id'];
            // Treat empty string, null, or 0 (int or string) as no linkage
            if ( $uid === '' || $uid === null || $uid === 0 || $uid === '0' ) {
                $update['user_id'] = null; // wpdb->update will persist NULL correctly
            } else {
                $update['user_id'] = (int) $uid;
            }
        }

        if ( array_key_exists( 'society_id', $update ) ) {
            $update['society_id'] = tpw_core_resolve_entity_society_id( $update['society_id'] );
        }

        // Always bump the updated_at timestamp
        $update['updated_at'] = current_time( 'mysql' );

    $res = $wpdb->update( $table, $update, [ 'id' => $id ] );
        if ( $res !== false ) {
            // Bust dependent option caches for any updated columns
            if ( ! empty($update) ) {
                $changed = array_keys($update);
                $searchable = get_option('tpw_member_searchable_fields', []);
                if ( is_array($searchable) ) {
                    foreach ($searchable as $fkey => $conf) {
                        if ( ! empty($conf['depends_on']) && in_array($conf['depends_on'], $changed, true ) ) {
                            // Delete transients matching child-parent pattern
                            // We cannot wildcard delete easily; store a mini index option of dependency caches? Simpler: brute force keys for last parent values not known.
                            // Minimal approach: delete all transients for this child (scan wp_options). Only if feasible.
                            global $wpdb;
                            $like = '%tpw_dep_opts_%';
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                            $transient_rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_'.esc_sql( 'tpw_dep_opts_' ).'%'" );
                            if ( $transient_rows ) {
                                foreach ($transient_rows as $on) {
                                    if ( strpos( $on, md5($fkey.'|') ) !== false ) { delete_option( $on ); }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $res;
    }

    /**
     * Delete a member.
     *
     * @since 1.0.0
     * @param int $id Member ID
     * @return int|false Rows affected or false on error
     */
    public function delete_member( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';

        return $wpdb->delete( $table, [ 'id' => $id ] );
    }
    /**
     * Render CSV import form.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_import_csv_page() {
        include plugin_dir_path( __FILE__ ) . '../templates/admin/import-csv.php';
    }

    /**
     * Get total count for members matching filters (for pagination).
     *
     * @since 1.0.0
     * @param array $args Same shape as get_members() filters
     * @return int
     */
    public function get_total_members_count( $args = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';

    $sql = "SELECT COUNT(*) FROM $table WHERE 1=1";
    $meta_joins = [];

    // Directory safety: optionally restrict results to primary household members only.
    // Also hard-exclude household children when enabled.
    if ( ! empty( $args['directory_primary_only'] ) ) {
        $hm_table = $wpdb->prefix . 'tpw_members_household_member';
        $meta_joins[] = " LEFT JOIN {$hm_table} AS hm_dir ON hm_dir.member_id = {$table}.id ";
        $sql .= " AND ( hm_dir.member_id IS NULL OR hm_dir.is_primary = 1 )";
        $sql .= " AND ( hm_dir.member_id IS NULL OR hm_dir.role IS NULL OR hm_dir.role <> 'child' )";
    }

        // Optional filters
        if ( isset( $args['society_id'] ) ) {
            $sql .= $wpdb->prepare( " AND society_id = %d", $args['society_id'] );
        }

        // Exact status filter (admin list)
        if ( isset( $args['status'] ) && $args['status'] !== '' ) {
            $sql .= $wpdb->prepare( " AND status = %s", $args['status'] );
        }

        // Status IN filter (member directory or multi-select scenarios)
        if ( isset( $args['status_in'] ) && is_array( $args['status_in'] ) && ! empty( $args['status_in'] ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $args['status_in'] ), '%s' ) );
            $sql .= $wpdb->prepare( " AND status IN ($placeholders)", $args['status_in'] );
        }

        if ( isset( $args['search'] ) && ! empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $sql .= $wpdb->prepare( " AND (
                CONCAT(first_name, ' ', surname) LIKE %s
                OR email LIKE %s
                OR status LIKE %s
            )", $like, $like, $like );
        }

        // Advanced text filters
        if ( ! empty($args['adv_text']) && is_array($args['adv_text']) ) {
            $cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            foreach ($args['adv_text'] as $col => $val) {
                $col = sanitize_key($col);
                if (!in_array($col, $cols, true)) continue;
                if ($val === '') continue;
                $like = '%' . $wpdb->esc_like( $val ) . '%';
                $sql .= $wpdb->prepare( " AND `{$col}` LIKE %s", $like );
            }
        }

        // Advanced select filters
        if ( ! empty($args['adv_select']) && is_array($args['adv_select']) ) {
            $cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            foreach ($args['adv_select'] as $col => $val) {
                $col = sanitize_key($col);
                if (!in_array($col, $cols, true)) continue;
                if ($val === '') continue;
                $sql .= $wpdb->prepare( " AND `{$col}` = %s", $val );
            }
        }

        // Advanced select filters for custom meta fields
        if ( ! empty($args['adv_select_meta']) && is_array($args['adv_select_meta']) ) {
            $i = 0;
            foreach ( $args['adv_select_meta'] as $mkey => $mval ) {
                $i++;
                $alias = 'mm_' . $i;
                $mkey = sanitize_key( $mkey );
                $meta_joins[] = $wpdb->prepare(
                    " INNER JOIN {$wpdb->prefix}tpw_member_meta AS {$alias} ON {$alias}.member_id = {$table}.id AND {$alias}.meta_key = %s AND {$alias}.meta_value = %s ",
                    $mkey, $mval
                );
            }
        }

        // Advanced date range filters
        if ( ! empty($args['adv_date_range']) && is_array($args['adv_date_range']) ) {
            $cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            foreach ($args['adv_date_range'] as $col => $range) {
                $col = sanitize_key($col);
                if (!in_array($col, $cols, true)) continue;
                $from = isset($range['from']) ? $range['from'] : '';
                $to   = isset($range['to']) ? $range['to'] : '';
                if ($from !== '' || $to !== '') {
                    $sql .= " AND `{$col}` IS NOT NULL AND `{$col}` <> '' AND `{$col}` <> '0000-00-00'";
                }
                if ($from !== '') {
                    $sql .= $wpdb->prepare( " AND `{$col}` >= %s", $from );
                }
                if ($to !== '') {
                    $sql .= $wpdb->prepare( " AND `{$col}` <= %s", $to );
                }
            }
        }

        // Has value filters (support for core columns and custom meta)
        if ( isset($args['has_value']) && is_array($args['has_value']) && ! empty($args['has_value']) ) {
            $cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            $meta_index = count($meta_joins);
            foreach ( $args['has_value'] as $key ) {
                $sanekey = sanitize_key( $key );
                if ( in_array( $sanekey, $cols, true ) ) {
                    $sql .= " AND `{$sanekey}` IS NOT NULL AND `{$sanekey}` <> ''";
                } else {
                    $meta_index++;
                    $alias = 'mm_hv_' . $meta_index;
                    $keys_to_match = [$sanekey];
                    if ($sanekey === 'tpw_subcode') { $keys_to_match[] = 'tpw_subpaid'; }
                    if (count($keys_to_match) > 1) {
                        $placeholders = implode(',', array_fill(0, count($keys_to_match), '%s'));
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                        $meta_joins[] = $wpdb->prepare(
                            " INNER JOIN {$wpdb->prefix}tpw_member_meta AS {$alias} ON {$alias}.member_id = {$table}.id AND {$alias}.meta_key IN ($placeholders) AND {$alias}.meta_value <> '' ",
                            $keys_to_match
                        );
                    } else {
                        $meta_joins[] = $wpdb->prepare(
                            " INNER JOIN {$wpdb->prefix}tpw_member_meta AS {$alias} ON {$alias}.member_id = {$table}.id AND {$alias}.meta_key = %s AND {$alias}.meta_value <> '' ",
                            $sanekey
                        );
                    }
                }
            }
        }

        // Checkbox filters for count query
        if ( isset($args['adv_checkbox']) && is_array($args['adv_checkbox']) && ! empty($args['adv_checkbox']) ) {
            $cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
            foreach ( $args['adv_checkbox'] as $col ) {
                $col = sanitize_key( $col );
                if ( in_array( $col, $cols, true ) ) {
                    $sql .= " AND `{$col}` = 1";
                }
            }
        }

        // Append meta joins (if any)
        if ( ! empty($meta_joins) ) {
            $sql = str_replace( "FROM $table", "FROM $table" . implode('', $meta_joins), $sql );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Get distinct current statuses present in the table.
     * Useful for building admin filters.
     *
     * @since 1.0.0
     * @return string[]
     */
    public function get_statuses() {
        global $wpdb;
        $table = $wpdb->prefix . 'tpw_members';
        $sql = "SELECT DISTINCT status FROM $table WHERE status IS NOT NULL AND status <> '' ORDER BY status ASC";
        $rows = $wpdb->get_col( $sql );
        // Ensure array of strings
        return array_values( array_filter( array_map( 'strval', (array) $rows ) ) );
    }
}