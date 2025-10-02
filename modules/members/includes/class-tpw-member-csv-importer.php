<?php

class TPW_Member_CSV_Importer {

    private array $notices = [];

    private function add_notice($message, $type = 'info') {
        $this->notices[] = ['type' => $type, 'message' => $message];
    }

    private function render_notices() {
        foreach ($this->notices as $notice) {
            $class = 'notice notice-' . esc_attr($notice['type']);
            echo '<div class="' . $class . '"><p>' . esc_html($notice['message']) . '</p></div>';
        }
    }

    public function handle_csv_upload() {
        if ( isset( $_POST['field_map'] ) ) {
            $this->process_mapped_import();
            return;
        }

        if ( ! isset( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            $this->render_upload_form();
            return;
        }

        $temp_file = wp_upload_dir()['basedir'] . '/tpw-temp-import.csv';
        move_uploaded_file($_FILES['csv_file']['tmp_name'], $temp_file);

        $csv_data = [];
        if (($handle = fopen($temp_file, 'r')) !== false) {
            $line_num = 0;
            while (($row = fgetcsv($handle)) !== false) {
                // Remove BOM from first cell if present
                if ($line_num === 0 && isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                }
                // Sanitize all fields in the row
                $row = array_map(function($field) {
                    // Remove control characters
                    $field = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $field);

                    // Convert to valid UTF-8
                    $field = mb_convert_encoding($field, 'UTF-8', 'UTF-8');

                    // Replace common problematic characters
                    $field = strtr($field, [
                        '‘' => "'", '’' => "'", '“' => '"', '”' => '"',
                        '–' => '-', '—' => '-', '…' => '...',
                    ]);

                    // Optional: Strip emojis (if needed for legacy DBs)
                    // $field = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $field);

                    return $field;
                }, $row);
                $csv_data[] = $row;
                $line_num++;
            }
            fclose($handle);
        }
        $has_headers = true;
        if ( isset($_POST['has_headers']) ) {
            $has_headers = true;
        }

        if ( $has_headers ) {
            $headers = array_map( fn($h) => strtolower(trim($h)), $csv_data[0] );
            $rows    = array_slice( $csv_data, 1 );
        } else {
            $column_count = count($csv_data[0]);
            $headers = array_map(fn($i) => 'Column ' . ($i + 1), range(0, $column_count - 1));
            $rows = $csv_data;
        }

        if ( empty( $csv_data ) || count( $csv_data ) < 2 ) {
            echo '<div class="notice notice-warning"><p>No data found in CSV file.</p></div>';
            return;
        }

        echo '<form method="post" action="" class="tpw-csv-mapping-form">';
        wp_nonce_field('tpw_csv_upload', 'tpw_csv_nonce');
        echo '<input type="hidden" name="csv_uploaded" value="1">';
        echo '<input type="hidden" name="temp_csv_file" value="' . esc_attr($temp_file) . '">';
        echo '<h2>Map CSV Columns to Member Fields</h2>';
        echo '<h3>Preview CSV Data</h3>';
        echo '<div style="max-height: 300px; overflow: auto; border: 1px solid #ccc; margin-bottom: 1em;">';
        echo '<table class="widefat" style="min-width: 1000px;"><thead><tr>';
        foreach ($headers as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach (array_slice($rows, 0, 3) as $preview_row) {
            echo '<tr>';
            foreach ($preview_row as $cell) {
                echo '<td>' . esc_html($cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '<table class="widefat fixed">';
        echo '<thead><tr><th>CSV Column</th><th>Map To Field</th></tr></thead><tbody>';

        global $wpdb;
        $fields_table = $wpdb->prefix . 'tpw_field_settings';
        $enabled_fields = $wpdb->get_results( "SELECT field_key, custom_label FROM $fields_table WHERE is_enabled = 1 ORDER BY sort_order ASC" );

        // Define required fields that are always included
        $required_fields = [
            (object) [ 'field_key' => 'username', 'custom_label' => 'Username' ],
            (object) [ 'field_key' => 'first_name', 'custom_label' => 'First Name' ],
            (object) [ 'field_key' => 'surname', 'custom_label' => 'Surname' ],
            (object) [ 'field_key' => 'status', 'custom_label' => 'Status' ],
        ];

        // Merge required fields with enabled fields, ensuring no duplicates
        $field_keys = [];
        $fields = [];

        foreach ( array_merge( $required_fields, $enabled_fields ) as $field ) {
            if ( ! in_array( $field->field_key, $field_keys ) ) {
                $fields[] = $field;
                $field_keys[] = $field->field_key;
            }
        }

        foreach ( $headers as $index => $header ) {
            echo '<tr>';
            echo '<td>' . esc_html( $header ) . '</td>';
            echo '<td>';
            $last_map = get_transient('tpw_last_csv_mapping_' . get_current_user_id());
            echo '<select name="field_map[' . $index . ']">';
            echo '<option value="">-- Select Field --</option>';

            foreach ( $fields as $field ) {
                $locked_fields = ['username', 'first_name', 'surname', 'status'];
                $title = in_array($field->field_key, $locked_fields)
                    ? ' title="This is a required field. Please ensure it is correctly mapped."'
                    : '';
                $disabled = ''; // No longer disabling options
                $selected = '';
                if ( isset($last_map[$index]) && $last_map[$index] === $field->field_key ) {
                    $selected = ' selected';
                } elseif ( strtolower($field->field_key) === strtolower($header) ) {
                    $selected = ' selected';
                }
                echo '<option value="' . esc_attr( $field->field_key ) . '"' . $selected . $title . '>' . esc_html( $field->custom_label ) . '</option>';
            }

            echo '</select>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    // Dedupe behavior controls
    echo '<p style="margin-top:12px;">';
    echo '<strong>On duplicate users:</strong><br>';
    echo '<label style="display:block; margin:4px 0;"><input type="radio" name="dedupe_action" value="skip" checked> Skip record if user exists (default)</label>';
    echo '<label style="display:block; margin:4px 0;"><input type="radio" name="dedupe_action" value="update"> Update user if exists</label>';
    echo '<span class="description">Duplicates are detected by email. For rows without email, username is used (fallback to matching First Name + Surname).</span>';
    echo '</p>';
        echo '<p>';
        echo '<label><input type="checkbox" name="dry_run" value="1"> Simulation mode – don’t actually import</label>';
        echo '</p>';
        echo '<p><button type="submit" class="button button-primary">Import Members</button></p>';
        echo '</form>';

        $this->render_notices();
    }

    public function process_mapped_import() {
        if ( ! isset($_POST['tpw_csv_nonce']) || ! wp_verify_nonce($_POST['tpw_csv_nonce'], 'tpw_csv_upload') ) {
            echo '<div class="notice notice-error"><p>Security check failed. Please reload the page and try again.</p></div>';
            return;
        }
        echo '<div class="notice notice-info"><p>Starting import process...</p></div>';

        $is_dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
        $dedupe_action = (isset($_POST['dedupe_action']) && $_POST['dedupe_action'] === 'update') ? 'update' : 'skip';
        if ( $is_dry_run ) {
            $this->add_notice('Simulation mode is ON. No members will be imported.', 'info');
            error_log('🟡 DRY RUN ENABLED: No database inserts will be made.');
        }

        if ( ! isset( $_POST['field_map'] ) || ! isset( $_POST['temp_csv_file'] ) || ! file_exists( $_POST['temp_csv_file'] ) ) {
            error_log('Import not proceeding: either field_map or temp_csv_file missing, or file does not exist.');
            error_log('field_map present: ' . (isset($_POST['field_map']) ? 'yes' : 'no'));
            error_log('temp_csv_file present: ' . (isset($_POST['temp_csv_file']) ? 'yes' : 'no'));
            error_log('temp_csv_file exists: ' . (file_exists($_POST['temp_csv_file']) ? 'yes' : 'no'));
            echo '<div class="notice notice-error"><p>CSV file not found or upload expired. Please try again.</p></div>';
            return;
        }

        $file = $_POST['temp_csv_file'];
        if (!is_readable($file)) {
            echo '<div class="notice notice-error"><p>CSV file is not readable.</p></div>';
            error_log('CSV file is not readable at: ' . $file);
            return;
        }
        if ( ! is_readable( $file ) ) {
            echo '<div class="notice notice-error"><p>Unable to read the CSV file.</p></div>';
            error_log('CSV file not readable: ' . $file);
            return;
        }

        $csv_data = [];
        if (($handle = fopen($file, 'r')) !== false) {
            $line_num = 0;
            while (($row = fgetcsv($handle)) !== false) {
                // Remove BOM from first cell if present
                if ($line_num === 0 && isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                }
                // Sanitize all fields in the row
                $row = array_map(function($field) {
                    // Remove control characters
                    $field = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $field);

                    // Convert to valid UTF-8
                    $field = mb_convert_encoding($field, 'UTF-8', 'UTF-8');

                    // Replace common problematic characters
                    $field = strtr($field, [
                        '‘' => "'", '’' => "'", '“' => '"', '”' => '"',
                        '–' => '-', '—' => '-', '…' => '...',
                    ]);

                    // Optional: Strip emojis (if needed for legacy DBs)
                    // $field = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $field);

                    return $field;
                }, $row);
                $csv_data[] = $row;
                $line_num++;
            }
            fclose($handle);
        }
        $has_headers = true;
        if ( isset($_POST['has_headers']) ) {
            $has_headers = true;
        }
        if ( $has_headers ) {
            $headers = array_map( fn($h) => strtolower(trim($h)), $csv_data[0] );
            $rows    = array_slice( $csv_data, 1 );
        } else {
            $column_count = count($csv_data[0]);
            $headers = array_map(fn($i) => 'Column ' . ($i + 1), range(0, $column_count - 1));
            $rows = $csv_data;
        }
        @unlink( $file );

        if ( empty( $csv_data ) || count( $csv_data ) < 2 ) {
            echo '<div class="notice notice-warning"><p>No data found in CSV file.</p></div>';
            return;
        }

        foreach ( $rows as $row_index => $row ) {
            if ( count($row) !== count($headers) ) {
                $this->add_notice("Row " . ($row_index + 2) . " has " . count($row) . " columns, expected " . count($headers) . ".", 'warning');
                error_log("⚠️ Row " . ($row_index + 2) . " column mismatch: found " . count($row) . ", expected " . count($headers));
            }
        }

        $field_map = $_POST['field_map'];

        $imported = 0;
        $import_summary = [];

        foreach ( $rows as $row ) {
            $member_data = [];
            if ( empty($row) || count(array_filter($row)) === 0 ) {
                error_log('⚠️ Skipping empty row');
                continue;
            }

            foreach ( $field_map as $index => $field_name ) {
                if ( ! empty( $field_name ) && isset( $row[ $index ] ) ) {
                    $member_data[ $field_name ] = sanitize_text_field( $row[ $index ] );
                }
            }

            $email = isset($member_data['email']) ? trim($member_data['email']) : '';
            $has_valid_email = ($email !== '' && is_email($email));

            // If email is present and valid, attempt to create WP user; otherwise import member only
            if ( $has_valid_email ) {
                $existing_user = get_user_by('email', $email);
                if ( $existing_user ) {
                    if ( $dedupe_action === 'skip' ) {
                        $import_summary[] = [ 'email' => $email, 'status' => '⚠️ Skipped – Email exists' ];
                        continue;
                    }
                    // Update path: Update WP user names if provided, then upsert into tpw_members by user_id
                    if ( $is_dry_run ) {
                        error_log('🟡 DRY RUN: Would UPDATE existing user and member for email: ' . $email);
                        continue;
                    }
                    // Optionally update user's first/last name
                    $upd = [ 'ID' => $existing_user->ID ];
                    $has_name_change = false;
                    if ( !empty($member_data['first_name']) ) { $upd['first_name'] = $member_data['first_name']; $has_name_change = true; }
                    if ( !empty($member_data['surname']) )     { $upd['last_name']  = $member_data['surname'];  $has_name_change = true; }
                    if ( $has_name_change ) { wp_update_user( $upd ); }

                    global $wpdb;
                    $table = $wpdb->prefix . 'tpw_members';
                    // Build data set
                    $set = [];
                    $expected_fields = [
                        'username', 'first_name', 'surname', 'initials', 'title', 'decoration',
                        'email', 'mobile', 'landline', 'address1', 'address2',
                        'town', 'county', 'postcode', 'country', 'date_joined', 'status'
                    ];
                    foreach ($expected_fields as $field) {
                        if (!empty($member_data[$field])) { $set[$field] = $member_data[$field]; }
                    }
                    // Ensure we set email field explicitly
                    $set['email'] = $email;

                    // Does a member row exist for this user_id?
                    $existing_member_id = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE user_id = %d LIMIT 1",
                        (int) $existing_user->ID
                    ) );
                    if ( $existing_member_id > 0 ) {
                        // Update existing member
                        $wpdb->update( $table, $set, [ 'id' => $existing_member_id ] );
                        $imported++;
                        $import_summary[] = [ 'email' => $email, 'status' => '🛠️ Updated existing member (by email)' ];
                    } else {
                        // Insert new member row linked to the existing user
                        $set['user_id'] = (int) $existing_user->ID;
                        $wpdb->insert( $table, $set );
                        $imported++;
                        $import_summary[] = [ 'email' => $email, 'status' => '✅ Inserted member (linked existing user)' ];
                    }
                    continue; // handled update/insert for existing user
                }
                if ( $is_dry_run ) {
                    error_log('🟡 DRY RUN: Would create user and member for email: ' . $email);
                    continue;
                }

                $desired_login = isset($member_data['username']) ? trim($member_data['username']) : '';
                $user_login = $desired_login;

                // Validate and sanitize user_login
                if ( empty($user_login) || username_exists($user_login) || strlen($user_login) > 60 ) {
                    $user_login = sanitize_user( current( explode( '@', $email ) ), true );
                }

                // Set display_name if first_name and/or surname are available
                $display_name = '';
                if ( !empty($member_data['first_name']) && !empty($member_data['surname']) ) {
                    $display_name = $member_data['first_name'] . ' ' . $member_data['surname'];
                } elseif ( !empty($member_data['first_name']) ) {
                    $display_name = $member_data['first_name'];
                } elseif ( !empty($member_data['surname']) ) {
                    $display_name = $member_data['surname'];
                }

                // Create WP User and also set first_name / last_name
                $user_id = wp_insert_user( [
                    'user_login'   => $user_login,
                    'user_email'   => $email,
                    'user_pass'    => wp_generate_password(),
                    'role'         => 'member',
                    'display_name' => $display_name,
                    'first_name'   => $member_data['first_name'] ?? '',
                    'last_name'    => $member_data['surname'] ?? '',
                ] );

                if ( ! is_wp_error( $user_id ) ) {
                    global $wpdb;
                    $table = $wpdb->prefix . 'tpw_members';

                    $insert_data = [];
                    if ( $user_id ) { $insert_data['user_id'] = $user_id; }
                    $expected_fields = [
                        'username', 'first_name', 'surname', 'initials', 'title', 'decoration',
                        'email', 'mobile', 'landline', 'address1', 'address2',
                        'town', 'county', 'postcode', 'country', 'date_joined', 'status'
                    ];
                    foreach ($expected_fields as $field) {
                        if (!empty($member_data[$field])) {
                            $insert_data[$field] = $member_data[$field];
                        }
                    }

                    $insert_result = $wpdb->insert( $table, $insert_data );
                    if ( false === $insert_result ) {
                        error_log('❌ DB Insert failed. Error: ' . $wpdb->last_error);
                        error_log('❌ Data tried to insert: ' . print_r($insert_data, true));
                    } else {
                        $imported++;
                        $import_summary[] = [ 'email' => $email, 'status' => '✅ Imported' ];

                        // Save custom fields into meta table
                        $meta_table = $wpdb->prefix . 'tpw_member_meta';
                        foreach ( $member_data as $key => $value ) {
                            if ( ! in_array( $key, $expected_fields ) && ! empty( $key ) && ! empty( $value ) ) {
                                if ( $is_dry_run ) {
                                    error_log("🟡 DRY RUN: Would save custom meta: $key = $value for member_id $user_id");
                                    continue;
                                }
                                $wpdb->insert( $meta_table, [
                                    'member_id'  => $user_id,
                                    'meta_key'   => sanitize_key( $key ),
                                    'meta_value' => sanitize_text_field( $value )
                                ] );
                            }
                        }
                    }
                }
            } else {
                // No valid email: dedupe using username (fallback to First+Surname)
                if ( $is_dry_run ) {
                    $nm = trim( ($member_data['first_name'] ?? '') . ' ' . ($member_data['surname'] ?? '') );
                    error_log('🟡 DRY RUN: Would process member WITHOUT WP user: ' . $nm);
                    continue;
                }
                global $wpdb;
                $table = $wpdb->prefix . 'tpw_members';
                $expected_fields = [
                    'username', 'first_name', 'surname', 'initials', 'title', 'decoration',
                    'email', 'mobile', 'landline', 'address1', 'address2',
                    'town', 'county', 'postcode', 'country', 'date_joined', 'status'
                ];
                $data_set = [];
                foreach ($expected_fields as $field) { if (!empty($member_data[$field])) { $data_set[$field] = $member_data[$field]; } }

                // Try find existing row by username
                $existing_member_id = 0;
                if ( !empty($member_data['username']) ) {
                    $existing_member_id = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE username = %s LIMIT 1",
                        $member_data['username']
                    ) );
                }
                // Fallback match by First Name + Surname if no username match
                if ( $existing_member_id === 0 && (!empty($member_data['first_name']) || !empty($member_data['surname'])) ) {
                    $existing_member_id = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE first_name = %s AND surname = %s LIMIT 1",
                        $member_data['first_name'] ?? '',
                        $member_data['surname'] ?? ''
                    ) );
                }

                if ( $existing_member_id > 0 ) {
                    if ( $dedupe_action === 'skip' ) {
                        $import_summary[] = [ 'email' => '', 'status' => '⚠️ Skipped – Member exists (by username/name)' ];
                    } else {
                        $wpdb->update( $table, $data_set, [ 'id' => $existing_member_id ] );
                        $imported++;
                        $import_summary[] = [ 'email' => '', 'status' => '🛠️ Updated existing member (no email)' ];
                    }
                } else {
                    $insert_result = $wpdb->insert( $table, $data_set );
                    if ( false === $insert_result ) {
                        error_log('❌ DB Insert (no email) failed. Error: ' . $wpdb->last_error);
                        error_log('❌ Data tried to insert: ' . print_r($data_set, true));
                    } else {
                        $imported++;
                        $import_summary[] = [ 'email' => '', 'status' => '✅ Imported (no WP user)' ];
                    }
                }
            }
        }

        echo '<div class="notice notice-success"><p>Imported ' . $imported . ' members successfully.</p></div>';
        if ( ! empty($import_summary) ) {
            echo '<h3>Import Summary</h3>';
            echo '<table class="widefat"><thead><tr><th>Email</th><th>Status</th></tr></thead><tbody>';
            foreach ($import_summary as $summary) {
                echo '<tr>';
                echo '<td>' . esc_html($summary['email']) . '</td>';
                echo '<td>' . esc_html($summary['status']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table><br>';
        }
        error_log('✅ Import complete. Total imported: ' . $imported);

        set_transient('tpw_last_csv_mapping_' . get_current_user_id(), $_POST['field_map'], HOUR_IN_SECONDS);

        $this->render_notices();
    }

    private function render_upload_form() {
        echo '<h2>Import Members from CSV</h2>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('tpw_csv_upload', 'tpw_csv_nonce');
        echo '<p>';
        echo '<label for="csv_file">Choose CSV File:</label><br>';
        echo '<input type="file" name="csv_file" id="csv_file" required>';
        echo '</p>';
        echo '<p>';
        echo '<label><input type="checkbox" name="has_headers" checked> First row contains column headers</label>';
        echo '</p>';
        echo '<p><button type="submit" class="button button-primary">Upload and Continue</button></p>';
        echo '<p>';
        echo '<a href="' . esc_url( plugins_url( 'modules/members/assets/sample-members-template.csv', TPW_CORE_FILE ) ) . '" class="button">Download Sample CSV Template</a>';
        echo '</p>';
        echo '</form>';
    }
}