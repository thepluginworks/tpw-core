<?php

class TPW_Member_Form_Handler {
    /**
     * Determine if the current user is allowed to manage members.
     * Aligns with AJAX permissions (admins always, optionally committee based on setting).
     */
    protected static function user_can_manage() {
        // WP admins always allowed
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        // Optional: allow committee members to manage if setting enabled
        $setting = get_option( 'tpw_members_manage_access', 'admins_only' );
        if ( $setting === 'admins_committee' && is_user_logged_in() ) {
            if ( ! class_exists( 'TPW_Member_Access' ) ) {
                require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-access.php';
            }
            $m = TPW_Member_Access::get_member_by_user_id( get_current_user_id() );
            if ( $m && ! empty( $m->is_committee ) && (int) $m->is_committee === 1 ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if the current request is within the Manage Members page context.
     * Uses presence of the [tpw_manage_members] shortcode on the current page content
     * instead of relying on a hardcoded slug.
     */
    protected static function is_manage_members_context() {
        if ( function_exists('is_page') && ! is_page() ) {
            return false;
        }
        global $post;
        if ( ! $post || ! isset( $post->post_content ) ) {
            return false;
        }
        $content = (string) $post->post_content;
        if ( function_exists( 'has_shortcode' ) ) {
            return has_shortcode( $content, 'tpw_manage_members' );
        }
        // Fallback: simple substring check if has_shortcode is not available
        return ( strpos( $content, '[tpw_manage_members' ) !== false );
    }

    protected static function normalize_status( $status ) {
        $map = [
            'active'      => 'Active',
            'inactive'    => 'Inactive',
            'deceased'    => 'Deceased',
            'honorary'    => 'Honorary',
            'resigned'    => 'Resigned',
            'suspended'   => 'Suspended',
            'pending'     => 'Pending',
            'life'        => 'Life Member',
            'life member' => 'Life Member',
        ];
        if ( ! is_string( $status ) ) return '';
        $key = strtolower( trim( $status ) );
        return $map[ $key ] ?? $status; // if already canonical, keep as-is
    }

    public static function handle_add_form() {
        if ( ! isset($_POST['tpw_add_member_nonce']) || ! wp_verify_nonce($_POST['tpw_add_member_nonce'], 'tpw_add_member_action') ) {
            error_log('[TPW Members] Add form blocked: invalid or missing nonce.');
            wp_die( 'Invalid or expired form submission. Please refresh the page and try again.', 403 );
        }

        if ( ! self::user_can_manage() ) {
            error_log('[TPW Members] Add form blocked: insufficient capabilities for current user.');
            wp_die( 'You do not have permission to perform this action.', 403 );
        }

        $enabled_fields = TPW_Member_Field_Loader::get_all_enabled_fields();

        $core_data = [];
        $meta_data = [];

        // Core boolean flags that should always be normalized to 0/1
        $known_core_checkboxes = [ 'is_committee', 'is_match_manager', 'is_admin', 'is_noticeboard_admin' ];

        foreach ( $enabled_fields as $field ) {
            $key = $field['key'];
            // Always unslash data from superglobals before sanitizing to avoid saving backslashes
            $raw   = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
            $value = ($raw !== '') ? sanitize_text_field($raw) : '';

            // Normalize core checkbox flags to explicit 0/1 so unchecked persists as 0
            if ( $field['is_core'] && ( ($field['type'] ?? '') === 'checkbox' || in_array( $key, $known_core_checkboxes, true ) ) ) {
                $core_data[$key] = isset($_POST[$key]) ? 1 : 0;
                continue;
            }

            if ( $field['is_core'] ) {
                // Prevent overwriting user_id with empty string or NULL
                if ( $key === 'user_id' && empty($value) ) {
                    continue;
                }
                if ( ($field['type'] ?? '') === 'date' && $value !== '' ) {
                    $value = self::normalize_date_ddmmyyyy_to_mysql($value);
                }
                $core_data[$key] = $value;
            } else {
                $meta_data[$key] = $value;
            }
        }

        // If WHI is provided on add, set whi_updated to today
        if ( method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active() ) {
            if ( isset($core_data['whi']) && $core_data['whi'] !== '' ) {
                $core_data['whi_updated'] = current_time('Y-m-d');
            }
        }

        // Fallback in case username or email are not enabled fields
    $core_data['username'] = $core_data['username'] ?? sanitize_user( isset($_POST['username']) ? wp_unslash($_POST['username']) : '' );
    $core_data['email'] = $core_data['email'] ?? sanitize_email( isset($_POST['email']) ? wp_unslash($_POST['email']) : '' );

        error_log('[TPW DEBUG] handle_add_form() triggered');
        if ( empty($core_data['username']) || empty($core_data['email']) ) {
            wp_die('Username and email are required to create a new user.');
        }

        // Create WP User
        $username = sanitize_user($core_data['username']);
        $password = wp_generate_password();
        $email    = sanitize_email($core_data['email']);

        $user_id = wp_create_user($username, $password, $email);
        if ( is_wp_error($user_id) ) {
            wp_die( 'Error creating user: ' . esc_html($user_id->get_error_message()) );
        }

        // Assign administrator role if requested
        if ( isset($core_data['is_admin']) && intval($core_data['is_admin']) === 1 ) {
            wp_update_user([
                'ID'   => $user_id,
                'role' => 'administrator',
            ]);
        }

        // Update first and last name in WP user meta
        wp_update_user([
            'ID'         => $user_id,
            'first_name' => $core_data['first_name'] ?? '',
            'last_name'  => $core_data['surname'] ?? '',
        ]);

    $core_data['user_id'] = $user_id;
    $core_data['society_id'] = 1; // Placeholder — set dynamically as needed

        // Normalize and assign WP Role based on Status
        $status_role_map = [
            'Active'      => 'member',
            'Life Member' => 'member',
            'Inactive'    => 'inactive_member',
            'Deceased'    => 'deceased',
            'Honorary'    => 'honorary_member',
            'Resigned'    => 'former_member',
            'Suspended'   => 'suspended',
            'Pending'     => 'pending_member',
        ];

        // Ensure canonical stored value (e.g., 'life' -> 'Life Member')
        $core_data['status'] = self::normalize_status( $core_data['status'] ?? 'Active' );
        $status = $core_data['status'];
        $role = $status_role_map[$status] ?? 'subscriber';

        $user = new WP_User($user_id);
        // Non-destructively add the mapped role (in case it differs) and ensure 'member' capability
        if ( $role && $role !== '' ) {
            $user->add_role( $role ); // add_role keeps existing roles
        }
        if ( class_exists('TPW_Member_Roles') ) {
            TPW_Member_Roles::ensure_member_cap( $user_id );
        }

        // Handle photo upload if enabled (same constraints as edit form)
        $photos_enabled = get_option('tpw_members_use_photos', '0') === '1';
        if ( $photos_enabled && isset($_FILES['member_photo_file']) && is_array($_FILES['member_photo_file']) && ! empty($_FILES['member_photo_file']['name']) ) {
            $file = $_FILES['member_photo_file'];
            $max_bytes = 2 * 1024 * 1024; // 2MB
            if ( (int)$file['size'] > $max_bytes ) {
                wp_die('Uploaded photo exceeds 2MB.');
            }
            $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, ['jpg','jpeg','png'], true ) ) {
                wp_die('Invalid file type. Allowed: JPG, JPEG, PNG.');
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $overrides = [ 'test_form' => false, 'mimes' => [ 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png' ] ];
            $uploaded = wp_handle_upload( $file, $overrides );
            if ( isset($uploaded['error']) ) {
                wp_die( 'Photo upload failed: ' . esc_html($uploaded['error']) );
            }

            $uploads   = wp_get_upload_dir();
            $file_path = $uploaded['file'] ?? '';
            if ( ! $file_path || ! file_exists($file_path) ) {
                wp_die('Uploaded file missing.');
            }

            $target_dir = trailingslashit( $uploads['basedir'] ) . 'tpw-members/photos/';
            if ( ! wp_mkdir_p( $target_dir ) ) {
                wp_die('Failed to create photo directory.');
            }
            $base_name = sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) );
            $use_ext   = ($ext === 'jpeg') ? 'jpg' : $ext; // normalize jpeg -> jpg
            $target_filename = wp_unique_filename( $target_dir, $base_name . '.' . $use_ext );
            $target_path = trailingslashit($target_dir) . $target_filename;

            $editor = wp_get_image_editor( $file_path );
            if ( ! is_wp_error( $editor ) ) {
                $editor->resize( 500, 500, false );
                if ( in_array( $use_ext, ['jpg','jpeg'], true ) ) {
                    $editor->set_quality( 75 );
                }
                $saved = $editor->save( $target_path );
                if ( is_wp_error($saved) || empty($saved['path']) ) {
                    copy( $file_path, $target_path );
                }
            } else {
                copy( $file_path, $target_path );
            }
            @unlink( $file_path );

            $relative = 'tpw-members/photos/' . $target_filename;
            $core_data['member_photo'] = $relative;
        }

        $controller = new TPW_Member_Controller();
        $member_id = $controller->add_member($core_data);

        if ( ! $member_id ) {
            wp_die( 'Failed to save member record.' );
        }

        foreach ( $meta_data as $meta_key => $meta_value ) {
            if ( $meta_value === '' || is_null($meta_value) ) {
                TPW_Member_Meta::delete_meta($member_id, $meta_key);
            } else {
                TPW_Member_Meta::save_meta($member_id, $meta_key, $meta_value);
            }
        }

        // Notify extensions that the Add form has been saved so they can persist extra fields.
        // Signature: ( string $context, int $member_id )
        do_action( 'tpw_members_admin_form_after_save', 'add', $member_id );

    // Redirect to list view after successful add with a flash flag
    $redirect_url = add_query_arg( 'saved', '1', site_url( '/manage-members/' ) );
    wp_safe_redirect( $redirect_url );
        exit;
    }
    public static function handle_edit_form() {
        if ( ! isset($_POST['tpw_edit_member_nonce']) || ! wp_verify_nonce($_POST['tpw_edit_member_nonce'], 'tpw_edit_member_action') ) {
            error_log('[TPW Members] Edit form blocked: invalid or missing nonce.');
            wp_die( 'Invalid or expired form submission. Please refresh the page and try again.', 403 );
        }

        if ( ! self::user_can_manage() ) {
            error_log('[TPW Members] Edit form blocked: insufficient capabilities for current user.');
            wp_die( 'You do not have permission to perform this action.', 403 );
        }

        $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
        if ( ! $member_id ) {
            wp_die( __( 'Invalid member ID.', 'tpw-core' ) );
        }

        $enabled_fields = TPW_Member_Field_Loader::get_all_enabled_fields();

        $core_data = [];
        $meta_data = [];

    // Core boolean flags that should always be normalized to 0/1
    $known_core_checkboxes = [ 'is_committee', 'is_match_manager', 'is_admin', 'is_noticeboard_admin' ];

        // Ensure these IDs are preserved even if not part of enabled fields
        $core_data['user_id'] = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
        $core_data['society_id'] = isset($_POST['society_id']) ? intval($_POST['society_id']) : null;

        // Load existing member to detect WHI changes
        $controller = new TPW_Member_Controller();
        $member_before = $controller->get_member($member_id);

        foreach ( $enabled_fields as $field ) {
            $key = $field['key'];
            // Do not allow username changes via Edit form
            if ( $key === 'username' ) {
                continue;
            }
            if (isset($_POST[$key])) {
                // Unslash before sanitizing to prevent persisted backslashes
                $raw   = wp_unslash($_POST[$key]);
                $value = sanitize_text_field($raw);
            } else {
                $value = null;
            }

            // Normalize core checkbox flags to explicit 0/1 so unchecked persists as 0
            if ( $field['is_core'] && ( ($field['type'] ?? '') === 'checkbox' || in_array( $key, $known_core_checkboxes, true ) ) ) {
                $core_data[$key] = isset($_POST[$key]) ? 1 : 0;
                continue;
            }

            if ( $field['is_core'] ) {
                // Skip user_id and society_id to avoid overwriting them again
                if ( $key === 'user_id' || $key === 'society_id' ) {
                    continue;
                }
                if ( ($field['type'] ?? '') === 'date' && $value !== '' ) {
                    $value = self::normalize_date_ddmmyyyy_to_mysql($value);
                }
                if ($value !== null) {
                    $core_data[$key] = $value;
                }
            } else {
                if ($value !== null) {
                    $meta_data[$key] = $value ?? '';
                }
            }
        }

        // If WHI changed, set whi_updated to today
        if ( method_exists( 'TPW_Member_Field_Loader', 'is_flexigolf_active' ) && TPW_Member_Field_Loader::is_flexigolf_active() ) {
            if ( array_key_exists('whi', $core_data) ) {
                $prev = is_object($member_before) && isset($member_before->whi) ? (string)$member_before->whi : '';
                if ( $core_data['whi'] !== $prev ) {
                    $core_data['whi_updated'] = current_time('Y-m-d');
                }
            }
        }

        // Handle photo delete/upload before normalizing status
        $photos_enabled = get_option('tpw_members_use_photos', '0') === '1';
        $existing_photo_rel = '';
        if ( $member_before && isset($member_before->member_photo) && is_string($member_before->member_photo) ) {
            $existing_photo_rel = trim($member_before->member_photo);
        }
        $delete_requested = $photos_enabled && isset($_POST['member_photo_delete']) && $_POST['member_photo_delete'] === '1';
        $did_upload_new = false;
        if ( $photos_enabled && isset($_POST['member_photo_delete']) && $_POST['member_photo_delete'] === '1' ) {
            $core_data['member_photo'] = '';
        } elseif ( $photos_enabled && isset($_FILES['member_photo_file']) && is_array($_FILES['member_photo_file']) && ! empty($_FILES['member_photo_file']['name']) ) {
            $file = $_FILES['member_photo_file'];
            // Validate file size (<= 2MB) and type
            $max_bytes = 2 * 1024 * 1024;
            if ( (int)$file['size'] > $max_bytes ) {
                wp_die('Uploaded photo exceeds 2MB.');
            }
            $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, ['jpg','jpeg','png'], true ) ) {
                wp_die('Invalid file type. Allowed: JPG, JPEG, PNG.');
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            // Upload to WordPress using wp_handle_upload (no attachment created)
            $overrides = [ 'test_form' => false, 'mimes' => [ 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png' ] ];
            $uploaded = wp_handle_upload( $file, $overrides );
            if ( isset($uploaded['error']) ) {
                wp_die( 'Photo upload failed: ' . esc_html($uploaded['error']) );
            }

            $uploads   = wp_get_upload_dir();
            $file_path = $uploaded['file'] ?? '';
            if ( ! $file_path || ! file_exists($file_path) ) {
                wp_die('Uploaded file missing.');
            }

            // Prepare target directory under uploads: tpw-members/photos
            $target_dir = trailingslashit( $uploads['basedir'] ) . 'tpw-members/photos/';
            if ( ! wp_mkdir_p( $target_dir ) ) {
                wp_die('Failed to create photo directory.');
            }
            $base_name = sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) );
            $use_ext   = ($ext === 'jpeg') ? 'jpg' : $ext; // normalize jpeg -> jpg
            $target_filename = wp_unique_filename( $target_dir, $base_name . '.' . $use_ext );
            $target_path = trailingslashit($target_dir) . $target_filename;

            // Resize/compress using image editor
            $editor = wp_get_image_editor( $file_path );
            if ( ! is_wp_error( $editor ) ) {
                $editor->resize( 500, 500, false ); // max 500x500, no crop
                // Set quality for JPEGs (PNG may ignore)
                if ( in_array( $use_ext, ['jpg','jpeg'], true ) ) {
                    $editor->set_quality( 75 );
                }
                $saved = $editor->save( $target_path );
                if ( is_wp_error($saved) || empty($saved['path']) ) {
                    // Fallback to copy if save failed
                    copy( $file_path, $target_path );
                }
            } else {
                // Fallback: just copy original to target
                copy( $file_path, $target_path );
            }

            // Clean up the initially uploaded temp file to avoid duplicates
            @unlink( $file_path );

            // Store relative path from uploads base
            $relative = 'tpw-members/photos/' . $target_filename;
            $core_data['member_photo'] = $relative;
            $did_upload_new = true;
        } else {
            // If photos are disabled, ensure we don't accidentally blank or change the field
            unset($core_data['member_photo']);
        }

        // Normalize status BEFORE saving to DB
        if ( isset( $core_data['status'] ) ) {
            $core_data['status'] = self::normalize_status( $core_data['status'] );
        }

    $controller = new TPW_Member_Controller();
    $updated = $controller->update_member($member_id, $core_data);

        // After successful update, delete stale photo file from disk if needed
        $redirect_msg = '';
        if ( $updated ) {
            $uploads = wp_get_upload_dir();
            $base = isset($uploads['basedir']) ? trailingslashit($uploads['basedir']) : '';
            if ( $base ) {
                // If a new photo was uploaded, remove the previous one
                if ( $did_upload_new && $existing_photo_rel && $existing_photo_rel !== ($core_data['member_photo'] ?? '') ) {
                    $old_full = wp_normalize_path( $base . ltrim($existing_photo_rel, '/') );
                    $base_norm = wp_normalize_path( $base );
                    if ( strpos($old_full, $base_norm) === 0 && file_exists($old_full) ) {
                        @unlink($old_full);
                    }
                    $redirect_msg = 'photo_replaced';
                }
                // If delete was requested and no new upload replaced it, remove the existing file
                if ( $delete_requested && ! $did_upload_new && $existing_photo_rel ) {
                    $old_full = wp_normalize_path( $base . ltrim($existing_photo_rel, '/') );
                    $base_norm = wp_normalize_path( $base );
                    if ( strpos($old_full, $base_norm) === 0 && file_exists($old_full) ) {
                        @unlink($old_full);
                    }
                    $redirect_msg = 'photo_deleted';
                }
            }
        }

        // Update WP User info if user_id is available
        $member = $controller->get_member($member_id);
        if ( $member && ! empty($member->user_id) ) {
            wp_update_user([
                'ID'         => $member->user_id,
                'first_name' => $core_data['first_name'] ?? '',
                'last_name'  => $core_data['surname'] ?? '',
            ]);

            // Normalize and assign WP Role based on Status (fallback to subscriber)
            $status_role_map = [
                'Active'      => 'member',
                'Life Member' => 'member',
                'Inactive'    => 'inactive_member',
                'Deceased'    => 'deceased',
                'Honorary'    => 'honorary_member',
                'Resigned'    => 'former_member',
                'Suspended'   => 'suspended',
                'Pending'     => 'pending_member',
            ];

            // $core_data['status'] already normalized above; provide default if missing
            $status = $core_data['status'] ?? 'Active';
            $role = $status_role_map[$status] ?? 'subscriber';

            // Override with Administrator if checked
            if ( isset($core_data['is_admin']) && intval($core_data['is_admin']) === 1 ) {
                $role = 'administrator';
            }

            $user = new WP_User( $member->user_id );
            if ( $role && $role !== '' ) {
                $user->add_role( $role );
            }
            if ( class_exists('TPW_Member_Roles') ) {
                TPW_Member_Roles::ensure_member_cap( $member->user_id );
            }
        }

        foreach ( $meta_data as $meta_key => $meta_value ) {
            if ( $meta_value === '' || is_null($meta_value) ) {
                TPW_Member_Meta::delete_meta($member_id, $meta_key);
            } else {
                TPW_Member_Meta::save_meta($member_id, $meta_key, $meta_value);
            }
        }
        // Notify extensions that the Edit form has been saved so they can persist extra fields.
        // Signature: ( string $context, int $member_id )
        do_action( 'tpw_members_admin_form_after_save', 'edit', $member_id );
        // Redirect after successful edit, include flash message when set
        $base_url = site_url( '/manage-members/' );
        // Always indicate a successful save; also include photo message when applicable
        $args = [ 'saved' => '1' ];
        if ( $redirect_msg ) { $args['msg'] = $redirect_msg; }
        wp_safe_redirect( add_query_arg( $args, $base_url ) );
        exit;
    }

    private static function normalize_date_ddmmyyyy_to_mysql( $value ) {
        $value = trim((string)$value);
        if ( $value === '' ) return '';
        // already in mysql format
        if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ) return $value;
        // dd/mm/yyyy
        if ( preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m) ) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if ( checkdate($mo, $d, $y) ) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        // fallback using strtotime
        $ts = strtotime($value);
        if ( $ts ) return date('Y-m-d', $ts);
        return $value;
    }
    public static function handle_delete_request() {
        if (
            ! isset($_GET['action'], $_GET['id']) ||
            $_GET['action'] !== 'delete'
        ) {
            return;
        }

        if ( ! self::user_can_manage() ) {
            return;
        }

        $member_id = intval($_GET['id']);
        if ( ! $member_id ) {
            echo '<div class="tpw-error">' . esc_html__( 'Invalid member ID.', 'tpw-core' ) . '</div>';
            return;
        }

        $controller = new TPW_Member_Controller();
        $member = $controller->get_member($member_id);

        if ( ! $member ) {
            echo '<div class="tpw-error">' . esc_html__( 'Member not found.', 'tpw-core' ) . '</div>';
            return;
        }

        if ( ! empty($member->user_id) ) {
            $wp_user = get_user_by( 'id', $member->user_id );
            if ( $wp_user ) {
                wp_delete_user( $member->user_id );
            } else {
                error_log( 'User ID ' . $member->user_id . ' not found in wp_users.' );
            }
        } else {
            error_log( 'No user_id found for member ID ' . $member_id );
        }

        TPW_Member_Meta::delete_all_meta($member_id);
        $controller->delete_member($member_id);

        $base_url = remove_query_arg( [ 'action', 'id' ] );
        wp_safe_redirect( add_query_arg( 'action', 'list', $base_url ) );
        exit;
    }
    public static function maybe_handle_edit_form() {
        // Route edit handling dynamically by detecting the presence of the edit nonce
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            self::is_manage_members_context() &&
            isset($_POST['tpw_edit_member_nonce'])
        ) {
            // Let handle_edit_form() perform nonce/cap checks and detailed validation
            self::handle_edit_form();
        }
    }
    public static function maybe_handle_add_form() {
        // Route add handling dynamically by detecting the presence of the add nonce
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            self::is_manage_members_context() &&
            isset($_POST['tpw_add_member_nonce'])
        ) {
            error_log('[TPW DEBUG] maybe_handle_add_form() detected add nonce and context');
            // Let handle_add_form() perform nonce/cap checks and detailed validation
            self::handle_add_form();
        }
    }
}

add_action('template_redirect', ['TPW_Member_Form_Handler', 'maybe_handle_edit_form']);
add_action('template_redirect', ['TPW_Member_Form_Handler', 'maybe_handle_add_form']);