<?php

class TPW_Member_Ajax {
    public static function init() {
        add_action( 'wp_ajax_tpw_member_search_users', [ __CLASS__, 'search_users' ] );
        add_action( 'wp_ajax_tpw_member_create_from_user', [ __CLASS__, 'create_from_user' ] );
        add_action( 'wp_ajax_tpw_member_get_details', [ __CLASS__, 'get_details' ] );
        add_action( 'wp_ajax_tpw_member_send_email', [ __CLASS__, 'send_email' ] );
        add_action( 'wp_ajax_tpw_member_profile_update', [ __CLASS__, 'profile_update' ] );
        // Photo management (admin only) for immediate actions on edit form
        add_action( 'wp_ajax_tpw_member_photo_delete', [ __CLASS__, 'photo_delete' ] );
        add_action( 'wp_ajax_tpw_member_photo_replace', [ __CLASS__, 'photo_replace' ] );
        // Admin settings: searchable fields
        add_action( 'wp_ajax_tpw_member_toggle_searchable', [ __CLASS__, 'toggle_searchable' ] );
        add_action( 'wp_ajax_tpw_member_save_search_config', [ __CLASS__, 'save_search_config' ] );
    }

    protected static function check_caps_and_nonce( $action ) {
        if ( ! current_user_can( 'manage_options' ) ) {
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

        // Ensure entry exists; if UI provided type/options/admin_only/options_source, respect them
        if ( ! isset($opt[$field_key]) || ! is_array($opt[$field_key]) ) {
            $label = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : $field_key;
            $stype_raw = isset($_POST['search_type']) ? sanitize_text_field($_POST['search_type']) : 'text';
            $stype = strtolower( str_replace( [ ' ', '-' ], '_', $stype_raw ) );
            $valid_types = ['text','select','date_range','has_value','checkbox'];
            if ( ! in_array( $stype, $valid_types, true ) ) { $stype = 'text'; }
            // Robust parse of admin_only similar to save handler
            $admin_only = false;
            if ( isset($_POST['admin_only']) ) {
                $ao = $_POST['admin_only'];
                if ( is_bool($ao) ) {
                    $admin_only = $ao;
                } else {
                    $admin_only = in_array( strtolower( (string) $ao ), [ '1', 'true', 'on', 'yes', 'y', 't' ], true );
                }
            }
            // Options source (for select type): 'static' or 'dynamic'
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
    // Normalize variants like "Date range" or "date-range" to "date_range"
    $search_type = strtolower( str_replace( [ ' ', '-' ], '_', $search_type_raw ) );
        $raw_options = isset($_POST['options']) ? wp_unslash($_POST['options']) : '';
        $label       = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : $field_key;
        // Accept common truthy values for robustness ("1", "true", "on", etc.)
        $admin_only  = false;
        if ( isset($_POST['admin_only']) ) {
            $ao = $_POST['admin_only'];
            if ( is_bool($ao) ) {
                $admin_only = $ao;
            } else {
                $admin_only = in_array( strtolower( (string) $ao ), [ '1', 'true', 'on', 'yes', 'y', 't' ], true );
            }
        }
        if ( $field_key === '' ) {
            wp_send_json_error(['message'=>'Missing field_key'],400);
        }
    $valid_types = ['text','select','date_range','has_value','checkbox'];
        if ( ! in_array( $search_type, $valid_types, true ) ) {
            $search_type = 'text';
        }
        // Options source (only relevant for select)
        $opt_source_raw = isset($_POST['options_source']) ? sanitize_text_field($_POST['options_source']) : 'static';
        $options_source = in_array( $opt_source_raw, ['static','dynamic'], true ) ? $opt_source_raw : 'static';
        $opt = get_option( 'tpw_member_searchable_fields', [] );
        if ( ! is_array($opt) ) { $opt = []; }

        $parsed_options = [];
        if ( $search_type === 'select' && $options_source === 'static' ) {
            // Accept comma or newline separated values
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
        ];
        update_option( 'tpw_member_searchable_fields', $opt );
        wp_send_json_success(['saved'=>true,'config'=>$opt[$field_key]]);
    }

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

    protected static function resolve_society_id() {
        global $wpdb;
        // Prefer an option if exists
        $opt = get_option( 'tpw_default_society_id' );
        if ( $opt ) return (int) $opt;
        // Fallback to first existing member's society_id
        $table = $wpdb->prefix . 'tpw_members';
        $sid = (int) $wpdb->get_var( "SELECT society_id FROM {$table} ORDER BY id ASC LIMIT 1" );
        return $sid > 0 ? $sid : 0;
    }

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

        protected static function member_or_admin_required() {
                require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-access.php';
                $is_admin  = TPW_Member_Access::is_admin_current();
                $is_member = TPW_Member_Access::is_member_current();
                if ( ! $is_admin && ! $is_member ) {
                        wp_send_json_error( [ 'message' => 'Access denied' ], 403 );
                }
        }

        public static function get_details() {
                self::member_or_admin_required();
                $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
                if ( $member_id <= 0 ) wp_send_json_error(['message'=>'Invalid member_id'],400);
                require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
                $controller = new TPW_Member_Controller();
                $m = $controller->get_member($member_id);
                if ( ! $m ) wp_send_json_error(['message'=>'Not found'],404);

        // Build safe HTML for details modal
        $settings = get_option( 'flexievent_settings', [] );
        $date_format = $settings['date_format'] ?? 'd-m-Y';
        $time_format = $settings['time_format'] ?? 'H:i';
                $is_admin = TPW_Member_Access::is_admin_current();
                $can_email = $is_admin || tpw_can_group_view_field('member','email');
                $can_mobile = $is_admin || tpw_can_group_view_field('member','mobile');
                $can_landline = $is_admin || tpw_can_group_view_field('member','landline');
                $can_address = $is_admin || (
                    tpw_can_group_view_field('member','address1') ||
                    tpw_can_group_view_field('member','address2') ||
                    tpw_can_group_view_field('member','town') ||
                    tpw_can_group_view_field('member','county') ||
                    tpw_can_group_view_field('member','postcode')
                );
                ob_start();
                ?>
                <div class="tpw-member-details">
                        <p><strong>Name:</strong> <?php echo esc_html($m->first_name . ' ' . $m->surname); ?></p>
                        <?php if ($m->initials): ?><p><strong>Initials:</strong> <?php echo esc_html($m->initials); ?></p><?php endif; ?>
                        <?php if ($can_email && $m->email): ?><p><strong>Email:</strong> <?php echo esc_html($m->email); ?></p><?php endif; ?>
                        <?php if (($can_mobile && $m->mobile) || ($can_landline && $m->landline)): ?>
                            <p><strong>Phone:</strong><br>
                                <?php if ($can_mobile && $m->mobile) echo esc_html($m->mobile) . '<br>'; ?>
                                <?php if ($can_landline && $m->landline) echo esc_html($m->landline); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($can_address && ($m->address1 || $m->town || $m->county || $m->postcode)): ?>
                            <p><strong>Address:</strong><br>
                                <?php echo esc_html(trim($m->address1.' '.$m->address2)); ?><br>
                                <?php echo esc_html(trim($m->town.' '.$m->county)); ?><br>
                                <?php echo esc_html($m->postcode); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($m->date_joined): ?><p><strong>Date Joined:</strong> <?php echo esc_html( tpw_format_date($m->date_joined) ); ?></p><?php endif; ?>

                        <?php if ( $is_admin ): ?>
                            <hr>
                            <p><strong>Status:</strong> <?php echo esc_html($m->status); ?></p>
                            <p><strong>Committee:</strong> <?php echo $m->is_committee ? 'Yes' : 'No'; ?></p>
                            <p><strong>Match Manager:</strong> <?php echo $m->is_match_manager ? 'Yes' : 'No'; ?></p>
                            <p><strong>Admin:</strong> <?php echo $m->is_admin ? 'Yes' : 'No'; ?></p>
                            <p><strong>Noticeboard Admin:</strong> <?php echo $m->is_noticeboard_admin ? 'Yes' : 'No'; ?></p>
                            <?php if ($m->username): ?><p><strong>Username:</strong> <?php echo esc_html($m->username); ?></p><?php endif; ?>
                            <?php if ($m->country): ?><p><strong>Country:</strong> <?php echo esc_html($m->country); ?></p><?php endif; ?>
                            <?php if ($m->county): ?><p><strong>County:</strong> <?php echo esc_html($m->county); ?></p><?php endif; ?>
                            <?php if ($m->address2): ?><p><strong>Address 2:</strong> <?php echo esc_html($m->address2); ?></p><?php endif; ?>
                            <?php if ($m->initials): ?><p><strong>Initials:</strong> <?php echo esc_html($m->initials); ?></p><?php endif; ?>
                            <?php if ($m->decoration): ?><p><strong>Decoration:</strong> <?php echo esc_html($m->decoration); ?></p><?php endif; ?>
                            <?php if ($m->town): ?><p><strong>Town:</strong> <?php echo esc_html($m->town); ?></p><?php endif; ?>
                            <?php if ($m->postcode): ?><p><strong>Postcode:</strong> <?php echo esc_html($m->postcode); ?></p><?php endif; ?>
                            <?php if ($m->user_id): ?><p><strong>Linked WP User ID:</strong> <?php echo (int)$m->user_id; ?></p><?php endif; ?>
                            <?php if ($m->society_id): ?><p><strong>Society ID:</strong> <?php echo (int)$m->society_id; ?></p><?php endif; ?>
                            <?php if ($m->created_at): ?><p><strong>Created:</strong> <?php echo esc_html( tpw_format_datetime($m->created_at) ); ?></p><?php endif; ?>
                            <?php if ($m->updated_at): ?><p><strong>Updated:</strong> <?php echo esc_html( tpw_format_datetime($m->updated_at) ); ?></p><?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php
                $html = ob_get_clean();
                wp_send_json_success(['html'=>$html]);
        }

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
                require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
                $controller = new TPW_Member_Controller();
                $m = $controller->get_member($member_id);
                if ( ! $m || empty($m->email) ) wp_send_json_error(['message'=>'Recipient not found'],404);

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
            $sent = wp_mail( $m->email, $subject, $body, $headers );
            if ( ! $sent ) wp_send_json_error(['message'=>'Failed to send email'],500);
            wp_send_json_success(['message'=>'Sent']);
        }

        public static function profile_update() {
            // Members can update allowed fields; admins too
            if ( ! is_user_logged_in() ) {
                wp_send_json_error(['message' => 'Access denied'], 403);
            }
            $admin_can_view = apply_filters('tpw_members/wp_admin_can_view_profile', true);
            $allow_all_statuses = apply_filters('tpw_members/profile_allow_all_statuses', true);

            require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
            $user = wp_get_current_user();
            $controller = new TPW_Member_Controller();
            $member = $controller->get_member_by_user_id( (int) $user->ID );
            if ( ! $member ) {
                wp_send_json_error(['message' => 'Member not found'], 404);
            }

            // Admins always allowed by default; enforce status only if filter requires it
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

            $editable = get_option('tpw_member_editable_fields', []);
            if ( ! is_array($editable) ) { $editable = []; }
            if ( ! in_array( $field_key, $editable, true ) ) {
                wp_send_json_error(['message'=>'Field not editable'], 403);
            }

            require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-meta.php';

            // Decide whether field is core column or meta
            $is_core = property_exists( $member, $field_key );
            $ok = false;
            if ( $is_core ) {
                // Minimal sanitize for known types
                $san = is_string($field_value) ? wp_kses_post( $field_value ) : $field_value;
                $update = [ $field_key => $san ];
                // Auto-maintain whi_updated when WHI changes (FlexiGolf only)
                if (
                    $field_key === 'whi'
                    && method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' )
                    && TPW_Member_Field_Loader::is_flexigolf_active()
                ) {
                    $update['whi_updated'] = current_time('Y-m-d');
                }
                $ok = (bool) $controller->update_member( (int) $member->id, $update );
            } else {
                $ok = (bool) TPW_Member_Meta::save_meta( (int) $member->id, $field_key, (string) $field_value );
            }

            if ( ! $ok ) {
                wp_send_json_error(['message'=>'Failed to save'], 500);
            }
            wp_send_json_success(['message'=>'Saved', 'field_key' => $field_key ]);
        }

        /**
         * Immediately delete a member's current photo and clear the DB field.
         * Expects: member_id (int), _wpnonce (tpw_member_photo_nonce)
         */
        public static function photo_delete() {
            if ( ! current_user_can( 'manage_options' ) ) {
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
            if ( ! current_user_can( 'manage_options' ) ) {
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
