<?php

class TPW_Member_CSV_Importer {
    const PASSWORD_SETUP_TRANSIENT_PREFIX = 'tpw_csv_password_setup_';
    const PASSWORD_SETUP_TRANSIENT_TTL    = HOUR_IN_SECONDS;
    const PASSWORD_SETUP_BATCH_SIZE       = 10;

    private array $notices = [];

    protected static function sanitize_import_run_id( $run_id ) {
        return sanitize_key( (string) $run_id );
    }

    protected static function build_password_setup_transient_key( $run_id, $user_id = 0 ) {
        $run_id  = self::sanitize_import_run_id( $run_id );
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            $user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
        }

        if ( '' === $run_id || $user_id <= 0 ) {
            return '';
        }

        return self::PASSWORD_SETUP_TRANSIENT_PREFIX . $user_id . '_' . $run_id;
    }

    protected static function generate_import_run_id() {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            return self::sanitize_import_run_id( str_replace( '-', '', wp_generate_uuid4() ) );
        }

        return self::sanitize_import_run_id( uniqid( 'tpwimport', true ) );
    }

    public static function get_password_setup_batch_size() {
        return max( 1, (int) apply_filters( 'tpw_members/csv_password_setup_batch_size', self::PASSWORD_SETUP_BATCH_SIZE ) );
    }

    protected static function get_default_password_setup_results() {
        return [
            'sent'    => [],
            'failed'  => [],
            'skipped' => [],
        ];
    }

    public static function get_password_setup_state( $run_id, $user_id = 0 ) {
        $key = self::build_password_setup_transient_key( $run_id, $user_id );
        if ( '' === $key ) {
            return [];
        }

        $state = get_transient( $key );
        if ( ! is_array( $state ) ) {
            return [];
        }

        $state['run_id']  = self::sanitize_import_run_id( isset( $state['run_id'] ) ? $state['run_id'] : $run_id );
        $state['summary'] = isset( $state['summary'] ) && is_array( $state['summary'] ) ? $state['summary'] : [];
        $state['pending'] = isset( $state['pending'] ) && is_array( $state['pending'] ) ? array_values( $state['pending'] ) : [];
        $state['results'] = isset( $state['results'] ) && is_array( $state['results'] ) ? $state['results'] : self::get_default_password_setup_results();

        foreach ( [ 'sent', 'failed', 'skipped' ] as $bucket ) {
            if ( ! isset( $state['results'][ $bucket ] ) || ! is_array( $state['results'][ $bucket ] ) ) {
                $state['results'][ $bucket ] = [];
            }
        }

        return $state;
    }

    public static function set_password_setup_state( $run_id, array $state, $user_id = 0 ) {
        $key = self::build_password_setup_transient_key( $run_id, $user_id );
        if ( '' === $key ) {
            return false;
        }

        $state['run_id'] = self::sanitize_import_run_id( $run_id );
        return set_transient( $key, $state, self::PASSWORD_SETUP_TRANSIENT_TTL );
    }

    public static function delete_password_setup_state( $run_id, $user_id = 0 ) {
        $key = self::build_password_setup_transient_key( $run_id, $user_id );
        if ( '' === $key ) {
            return false;
        }

        return delete_transient( $key );
    }

    protected function render_password_setup_result_card( array $state ) {
        $run_id          = isset( $state['run_id'] ) ? self::sanitize_import_run_id( $state['run_id'] ) : '';
        $summary         = isset( $state['summary'] ) && is_array( $state['summary'] ) ? $state['summary'] : [];
        $results         = isset( $state['results'] ) && is_array( $state['results'] ) ? $state['results'] : self::get_default_password_setup_results();
        $pending         = isset( $state['pending'] ) && is_array( $state['pending'] ) ? array_values( $state['pending'] ) : [];
        $eligible_count  = isset( $summary['eligible_count'] ) ? (int) $summary['eligible_count'] : 0;
        $pending_count   = count( $pending );
        $sent_count      = isset( $results['sent'] ) && is_array( $results['sent'] ) ? count( $results['sent'] ) : 0;
        $failed_count    = isset( $results['failed'] ) && is_array( $results['failed'] ) ? count( $results['failed'] ) : 0;
        $skipped_count   = isset( $results['skipped'] ) && is_array( $results['skipped'] ) ? count( $results['skipped'] ) : 0;
        $processed_count = $sent_count + $failed_count + $skipped_count;

        echo '<div class="notice notice-info" style="margin:16px 0;padding:12px 14px;">';
        echo '<h3 style="margin-top:0;">' . esc_html__( 'Password Setup Emails', 'tpw-core' ) . '</h3>';
        echo '<p>' . esc_html( sprintf( __( 'Eligible newly imported linked members: %d', 'tpw-core' ), $eligible_count ) ) . '</p>';

        $excluded_reasons = [
            'existing_user_count'          => __( 'Existing WordPress users excluded', 'tpw-core' ),
            'invalid_or_missing_email_count' => __( 'Rows without a valid email excluded', 'tpw-core' ),
            'unlinked_user_count'          => __( 'Rows without a new linked WordPress user excluded', 'tpw-core' ),
            'other_skipped_count'          => __( 'Other skipped rows excluded', 'tpw-core' ),
        ];

        $has_exclusions = false;
        foreach ( $excluded_reasons as $key => $label ) {
            if ( ! empty( $summary[ $key ] ) ) {
                $has_exclusions = true;
                break;
            }
        }

        if ( $has_exclusions ) {
            echo '<ul style="margin:8px 0 12px 18px;">';
            foreach ( $excluded_reasons as $key => $label ) {
                $count = isset( $summary[ $key ] ) ? (int) $summary[ $key ] : 0;
                if ( $count <= 0 ) {
                    continue;
                }
                echo '<li>' . esc_html( sprintf( '%s: %d', $label, $count ) ) . '</li>';
            }
            echo '</ul>';
        }

        if ( $processed_count > 0 ) {
            echo '<p>' . esc_html( sprintf( __( 'Sent: %1$d, Failed: %2$d, Skipped: %3$d, Remaining: %4$d', 'tpw-core' ), $sent_count, $failed_count, $skipped_count, $pending_count ) ) . '</p>';
        }

        if ( $eligible_count <= 0 ) {
            echo '<p>' . esc_html__( 'No newly created and linked WordPress users were created in this import, so there are no password setup emails to send.', 'tpw-core' ) . '</p>';
        } elseif ( $pending_count > 0 ) {
            $button_label = ( $processed_count > 0 )
                ? __( 'Continue sending remaining password setup emails', 'tpw-core' )
                : __( 'Send password setup emails to newly imported members', 'tpw-core' );

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:12px 0 0;">';
            wp_nonce_field( 'tpw_send_import_password_setup_batch' );
            echo '<input type="hidden" name="action" value="tpw_send_import_password_setup_batch">';
            echo '<input type="hidden" name="import_run" value="' . esc_attr( $run_id ) . '">';
            echo '<button type="submit" class="button button-primary">' . esc_html( $button_label ) . '</button>';
            echo '</form>';
        } else {
            echo '<p>' . esc_html__( 'Password setup email sending is complete for this import run.', 'tpw-core' ) . '</p>';
        }

        $issue_rows = [];
        if ( ! empty( $results['failed'] ) && is_array( $results['failed'] ) ) {
            $issue_rows = array_merge( $issue_rows, $results['failed'] );
        }
        if ( ! empty( $results['skipped'] ) && is_array( $results['skipped'] ) ) {
            $issue_rows = array_merge( $issue_rows, $results['skipped'] );
        }

        if ( ! empty( $issue_rows ) ) {
            echo '<details style="margin-top:12px;"><summary>' . esc_html__( 'View failed or skipped recipients', 'tpw-core' ) . '</summary>';
            echo '<table class="widefat striped" style="margin-top:10px;"><thead><tr><th>' . esc_html__( 'Email', 'tpw-core' ) . '</th><th>' . esc_html__( 'Result', 'tpw-core' ) . '</th></tr></thead><tbody>';
            foreach ( $issue_rows as $row ) {
                $email   = isset( $row['email'] ) ? (string) $row['email'] : '';
                $message = isset( $row['message'] ) ? (string) $row['message'] : __( 'No details available.', 'tpw-core' );
                echo '<tr><td>' . esc_html( $email ) . '</td><td>' . esc_html( $message ) . '</td></tr>';
            }
            echo '</tbody></table>';
            echo '</details>';
        }

        echo '</div>';
    }

    public function render_saved_password_setup_run( $run_id ) {
        $run_id = self::sanitize_import_run_id( $run_id );
        if ( '' === $run_id ) {
            return;
        }

        $state = self::get_password_setup_state( $run_id );
        if ( empty( $state ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'The password setup email import run has expired or is no longer available.', 'tpw-core' ) . '</p></div>';
            return;
        }

        $this->render_password_setup_result_card( $state );
    }

    private function log_debug(string $message): void {
        // Log to uploads/tpw_import_debug.log; fall back to error_log if not writable
        $uploads = wp_upload_dir();
        $file = trailingslashit($uploads['basedir']) . 'tpw_import_debug.log';
        $line = '[' . date('Y-m-d H:i:s') . "] TPW_IMPORT: " . $message . "\n";
        if ( is_writable( $uploads['basedir'] ) || ( file_exists($file) && is_writable($file) ) ) {
            @error_log( $line, 3, $file );
        } else {
            // Fallback
            error_log( $line );
        }
    }

    private function save_custom_meta(int $member_id, array $member_data, array $core_fields, bool $is_update = false, string $row_info = '', bool $log_detail = false): void {
        // Save non-core fields as meta (upsert). Empty strings are skipped (no delete on update).
        global $wpdb;
        $meta_count = 0;
        if ( $log_detail ) {
            $this->log_debug(( $is_update ? 'Updating' : 'Inserting' ) . " meta for member_id {$member_id} {$row_info}");
        }
        foreach ( $member_data as $key => $value ) {
            if ( in_array( $key, $core_fields, true ) ) {
                continue;
            }
            $meta_key = sanitize_key( (string) $key );
            $meta_value = is_scalar($value) ? sanitize_text_field( (string) $value ) : '';
            if ( $meta_key === '' ) { continue; }

            // Determine existing value for detailed logs
            $old_value = null;
            $existed = false;
            $table = $wpdb->prefix . 'tpw_member_meta';
            $old_value = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$table} WHERE member_id = %d AND meta_key = %s LIMIT 1",
                $member_id,
                $meta_key
            ) );
            $existed = ($old_value !== null);

            if ( $meta_value === '' ) {
                // Skip truly empty mapped fields; do not delete. Log skip in update mode when detailed logging is on.
                if ( $log_detail ) {
                    $this->log_debug( sprintf('Skipped meta (empty): member_id=%d, key=%s, old=%s %s', $member_id, $meta_key, (string) $old_value, $row_info) );
                }
                continue;
            }

            // Prefer helper for upsert semantics
            if ( class_exists('TPW_Member_Meta') ) {
                $ok = (bool) TPW_Member_Meta::save_meta( $member_id, $meta_key, $meta_value );
            } else {
                // Fallback direct upsert
                $exists = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE member_id = %d AND meta_key = %s",
                    $member_id,
                    $meta_key
                ) );
                if ( $exists ) {
                    $ok = (bool) $wpdb->update( $table, [ 'meta_value' => $meta_value ], [ 'member_id' => $member_id, 'meta_key' => $meta_key ] );
                } else {
                    $ok = (bool) $wpdb->insert( $table, [ 'member_id' => $member_id, 'meta_key' => $meta_key, 'meta_value' => $meta_value ] );
                }
            }
            $meta_count++;
            if ( $log_detail ) {
                $action = $existed ? 'Updated' : 'Inserted';
                $this->log_debug( sprintf( '%s meta: member_id=%d, key=%s, old=%s, new=%s %s', $action, $member_id, $meta_key, (string) $old_value, (string) $meta_value, $row_info ) );
                if ( isset($ok) && ! $ok ) {
                    $this->log_debug( 'DB error on meta upsert: ' . ( isset($wpdb->last_error) ? $wpdb->last_error : '(unknown)' ) );
                }
            }
        }
        if ( $meta_count === 0 ) {
            if ( $log_detail ) {
                $this->log_debug('No custom meta fields to process for this row. ' . $row_info);
            }
        }
    }

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
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'No data found in CSV file.', 'tpw-core' ) . '</p></div>';
            return;
        }

        echo '<form method="post" action="" class="tpw-csv-mapping-form">';
        wp_nonce_field('tpw_csv_upload', 'tpw_csv_nonce');
        echo '<input type="hidden" name="csv_uploaded" value="1">';
        echo '<input type="hidden" name="temp_csv_file" value="' . esc_attr($temp_file) . '">';
    echo '<h2>' . esc_html__( 'Map CSV Columns to Member Fields', 'tpw-core' ) . '</h2>';
    echo '<h3>' . esc_html__( 'Preview CSV Data', 'tpw-core' ) . '</h3>';
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
    echo '<thead><tr><th>' . esc_html__( 'CSV Column', 'tpw-core' ) . '</th><th>' . esc_html__( 'Map To Field', 'tpw-core' ) . '</th></tr></thead><tbody>';

        global $wpdb;
        $fields_table = $wpdb->prefix . 'tpw_field_settings';
        $enabled_fields = $wpdb->get_results( "SELECT field_key, custom_label FROM $fields_table WHERE is_enabled = 1 ORDER BY sort_order ASC" );

        // Define required fields that are always included
        $required_fields = [
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
            echo '<option value="">' . esc_html__( '-- Select Field --', 'tpw-core' ) . '</option>';

            foreach ( $fields as $field ) {
                $locked_fields = ['first_name', 'surname', 'status'];
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
    echo '<strong>' . esc_html__( 'On duplicate users:', 'tpw-core' ) . '</strong><br>';
    echo '<label style="display:block; margin:4px 0;"><input type="radio" name="dedupe_action" value="skip" checked> ' . esc_html__( 'Skip record if user exists (default)', 'tpw-core' ) . '</label>';
    echo '<label style="display:block; margin:4px 0;"><input type="radio" name="dedupe_action" value="update"> ' . esc_html__( 'Update user if exists', 'tpw-core' ) . '</label>';
    echo '<span class="description">' . esc_html__( 'Duplicates are detected by email. For rows without email, username is used (fallback to matching First Name + Surname).', 'tpw-core' ) . '</span>';
    echo '</p>';
    echo '<p style="margin-top:12px;">';
    echo '<strong>' . esc_html__( 'Username handling for new WordPress users:', 'tpw-core' ) . '</strong><br>';
    echo '<label style="display:block; margin:4px 0;"><input type="radio" name="username_import_mode" value="generate" checked> ' . esc_html__( 'Generate new usernames (default)', 'tpw-core' ) . '</label>';
    echo '<label style="display:block; margin:4px 0;"><input type="radio" name="username_import_mode" value="preserve"> ' . esc_html__( 'Preserve imported usernames', 'tpw-core' ) . '</label>';
    echo '<span class="description">' . esc_html__( 'Generate mode ignores mapped username values when creating new WordPress users. Preserve mode uses the imported username as the preferred base and suffixes it until unique. Rows without email still use the member username field for member-only import matching.', 'tpw-core' ) . '</span>';
    echo '</p>';
        echo '<p>';
        echo '<label><input type="checkbox" name="dry_run" value="1"> ' . esc_html__( 'Simulation mode – don’t actually import', 'tpw-core' ) . '</label>';
        echo '</p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Import Members', 'tpw-core' ) . '</button></p>';
        echo '</form>';

        $this->render_notices();
    }

    public function process_mapped_import() {
        if ( ! isset($_POST['tpw_csv_nonce']) || ! wp_verify_nonce($_POST['tpw_csv_nonce'], 'tpw_csv_upload') ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please reload the page and try again.', 'tpw-core' ) . '</p></div>';
            return;
        }
        echo '<div class="notice notice-info"><p>' . esc_html__( 'Starting import process...', 'tpw-core' ) . '</p></div>';

        $is_dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
        $dedupe_action = (isset($_POST['dedupe_action']) && $_POST['dedupe_action'] === 'update') ? 'update' : 'skip';
        $preserve_imported_usernames = ( isset($_POST['username_import_mode']) && $_POST['username_import_mode'] === 'preserve' );
        if ( $is_dry_run ) {
            $this->add_notice('Simulation mode is ON. No members will be imported.', 'info');
        }

        if ( ! isset( $_POST['field_map'] ) || ! isset( $_POST['temp_csv_file'] ) || ! file_exists( $_POST['temp_csv_file'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'CSV file not found or upload expired. Please try again.', 'tpw-core' ) . '</p></div>';
            return;
        }

        $file = $_POST['temp_csv_file'];
        if (!is_readable($file)) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'CSV file is not readable.', 'tpw-core' ) . '</p></div>';
            error_log('CSV file is not readable at: ' . $file);
            return;
        }
        if ( ! is_readable( $file ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Unable to read the CSV file.', 'tpw-core' ) . '</p></div>';
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
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'No data found in CSV file.', 'tpw-core' ) . '</p></div>';
            return;
        }

        foreach ( $rows as $row_index => $row ) {
            if ( count($row) !== count($headers) ) {
                $this->add_notice("Row " . ($row_index + 2) . " has " . count($row) . " columns, expected " . count($headers) . ".", 'warning');
            }
        }

        $field_map = $_POST['field_map'];

        $imported = 0;
        $import_summary = [];
        $eligible_recipients = [];
        $existing_user_count = 0;
        $invalid_or_missing_email_count = 0;
        $unlinked_user_count = 0;
        $other_skipped_count = 0;

        foreach ( $rows as $row_index => $row ) {
            $member_data = [];
            if ( empty($row) || count(array_filter($row)) === 0 ) {
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
                    $existing_user_count++;
                    if ( $dedupe_action === 'skip' ) {
                        $import_summary[] = [ 'email' => $email, 'status' => '⚠️ Skipped – Email exists' ];
                        continue;
                    }
                    // Update path: Update WP user names if provided, then upsert into tpw_members by user_id
                    if ( $is_dry_run ) {
                        continue;
                    }
                    $this->log_debug('Matched existing WP user by email: ' . $email . ' (user_id ' . (int) $existing_user->ID . ')');
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
                    unset( $set['username'] );
                    // Ensure we set email field explicitly
                    $set['email'] = $email;

                    // Does a member row exist for this user_id?
                    $existing_member_id = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE user_id = %d LIMIT 1",
                        (int) $existing_user->ID
                    ) );
                    if ( $existing_member_id > 0 ) {
                        // Update existing member
                        $row_info = sprintf('(row=%d, username=%s)', $row_index + 2, $member_data['username'] ?? '');
                        $this->log_debug('Updating existing tpw_members row id ' . $existing_member_id . ' for user_id ' . (int) $existing_user->ID . ' ' . $row_info);
                        $wpdb->update( $table, $set, [ 'id' => $existing_member_id ] );
                        // Save custom fields to meta
                        $this->log_debug('Member matched (update): member_id=' . $existing_member_id . ', unique_identifier=' . ( $member_data['unique_identifier'] ?? '(none)' ) . ' ' . $row_info );
                        $this->save_custom_meta( $existing_member_id, $member_data, $expected_fields, true, $row_info, true );
                        $imported++;
                        $import_summary[] = [ 'email' => $email, 'status' => '🛠️ Updated existing member (by email)' ];
                    } else {
                        // Insert new member row linked to the existing user
                        $set['user_id'] = (int) $existing_user->ID;
                        $set['username'] = (string) $existing_user->user_login;
                        $wpdb->insert( $table, $set );
                        $member_id = (int) $wpdb->insert_id;
                        $this->log_debug('Inserted new tpw_members row id ' . $member_id . ' for existing user_id ' . (int) $existing_user->ID);
                        // Save custom fields to meta
                        $this->log_debug('Member matched (insert for existing WP user): member_id=' . $member_id . ', unique_identifier=' . ( $member_data['unique_identifier'] ?? '(none)' ) );
                        // Insert flow: keep detail logs off per requirement focus
                        $this->save_custom_meta( $member_id, $member_data, $expected_fields, false, '', false );
                        $imported++;
                        $import_summary[] = [ 'email' => $email, 'status' => '✅ Inserted member (linked existing user)' ];
                    }
                    continue; // handled update/insert for existing user
                }
                if ( $is_dry_run ) {
                    continue;
                }

                $desired_login = $preserve_imported_usernames && isset($member_data['username']) ? trim($member_data['username']) : '';
                $user_login = TPW_Member_Username_Generator::resolve_new_user_login(
                    $desired_login,
                    $preserve_imported_usernames,
                    TPW_Member_Username_Generator::MAX_USER_LOGIN_LENGTH,
                    isset($member_data['first_name']) ? $member_data['first_name'] : '',
                    isset($member_data['surname']) ? $member_data['surname'] : ''
                );
                if ( '' === $user_login ) {
                    $other_skipped_count++;
                    $import_summary[] = [ 'email' => $email, 'status' => '⚠️ Skipped – Unable to generate unique username' ];
                    continue;
                }
                $member_data['username'] = $user_login;

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

                if ( is_wp_error( $user_id ) ) {
                    $unlinked_user_count++;
                    $import_summary[] = [ 'email' => $email, 'status' => '⚠️ Skipped – WordPress user could not be created' ];
                    continue;
                }

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
                        $unlinked_user_count++;
                        $import_summary[] = [ 'email' => $email, 'status' => '⚠️ Skipped – Member could not be linked to the new WordPress user' ];
                        error_log('❌ DB Insert failed. Error: ' . $wpdb->last_error);
                        error_log('❌ Data tried to insert: ' . print_r($insert_data, true));
                    } else {
                        $imported++;
                        $import_summary[] = [ 'email' => $email, 'status' => '✅ Imported' ];
                        $member_id = (int) $wpdb->insert_id;
                        $this->log_debug('Inserted new tpw_members row id ' . $member_id . ' for new WP user_id ' . (int) $user_id);
                        $this->log_debug('Member created: member_id=' . $member_id . ', unique_identifier=' . ( $member_data['unique_identifier'] ?? '(none)' ) );
                        // Save custom fields into meta table, using correct member_id
                        // Insert flow: keep detail logs off per requirement focus
                        $this->save_custom_meta( $member_id, $member_data, $expected_fields, false, '', false );
                        $eligible_recipients[] = [
                            'member_id' => $member_id,
                            'user_id'   => (int) $user_id,
                            'email'     => $email,
                        ];
                    }
                }
            } else {
                $invalid_or_missing_email_count++;
                // No valid email: dedupe using username (fallback to First+Surname)
                if ( $is_dry_run ) {
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
                        $row_info = sprintf('(row=%d, username=%s)', $row_index + 2, $member_data['username'] ?? '');
                        $this->log_debug('Updating existing tpw_members row id ' . $existing_member_id . ' (no email flow) ' . $row_info);
                        $wpdb->update( $table, $data_set, [ 'id' => $existing_member_id ] );
                        // Save custom fields to meta for this member
                        $this->log_debug('Member matched (update, no email): member_id=' . $existing_member_id . ', unique_identifier=' . ( $member_data['unique_identifier'] ?? '(none)' ) . ' ' . $row_info );
                        $this->save_custom_meta( $existing_member_id, $member_data, $expected_fields, true, $row_info, true );
                        $imported++;
                        $import_summary[] = [ 'email' => '', 'status' => '🛠️ Updated existing member (no email)' ];
                    }
                } else {
                    $insert_result = $wpdb->insert( $table, $data_set );
                    if ( false === $insert_result ) {
                        error_log('❌ DB Insert (no email) failed. Error: ' . $wpdb->last_error);
                        error_log('❌ Data tried to insert: ' . print_r($data_set, true));
                    } else {
                        $member_id = (int) $wpdb->insert_id;
                        $this->log_debug('Inserted new tpw_members row id ' . $member_id . ' (no email flow)');
                        // Save custom fields to meta
                        $this->log_debug('Member created (no email): member_id=' . $member_id . ', unique_identifier=' . ( $member_data['unique_identifier'] ?? '(none)' ) );
                        // Insert flow: keep detail logs off per requirement focus
                        $this->save_custom_meta( $member_id, $member_data, $expected_fields, false, '', false );
                        $imported++;
                        $import_summary[] = [ 'email' => '', 'status' => '✅ Imported (no WP user)' ];
                    }
                }
            }
        }

        echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Imported %d members successfully.', 'tpw-core' ), (int) $imported ) . '</p></div>';
        if ( ! empty($import_summary) ) {
            echo '<h3>' . esc_html__( 'Import Summary', 'tpw-core' ) . '</h3>';
            echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Email', 'tpw-core' ) . '</th><th>' . esc_html__( 'Status', 'tpw-core' ) . '</th></tr></thead><tbody>';
            foreach ($import_summary as $summary) {
                echo '<tr>';
                echo '<td>' . esc_html($summary['email']) . '</td>';
                echo '<td>' . esc_html($summary['status']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table><br>';
        }

        if ( ! $is_dry_run ) {
            $run_id = self::generate_import_run_id();
            $summary = [
                'eligible_count'                 => count( $eligible_recipients ),
                'existing_user_count'            => $existing_user_count,
                'invalid_or_missing_email_count' => $invalid_or_missing_email_count,
                'unlinked_user_count'            => $unlinked_user_count,
                'other_skipped_count'            => $other_skipped_count,
                'imported_count'                 => (int) $imported,
            ];

            $state = [
                'run_id'        => $run_id,
                'owner_user_id' => get_current_user_id(),
                'created_at'    => time(),
                'summary'       => $summary,
                'pending'       => $eligible_recipients,
                'results'       => self::get_default_password_setup_results(),
            ];

            self::set_password_setup_state( $run_id, $state, get_current_user_id() );
            $this->render_password_setup_result_card( $state );
        }

        set_transient('tpw_last_csv_mapping_' . get_current_user_id(), $_POST['field_map'], HOUR_IN_SECONDS);

        $this->render_notices();
    }

    private function render_upload_form() {
    echo '<h2>' . esc_html__( 'Import Members from CSV', 'tpw-core' ) . '</h2>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('tpw_csv_upload', 'tpw_csv_nonce');
        echo '<p>';
    echo '<label for="csv_file">' . esc_html__( 'Choose CSV File:', 'tpw-core' ) . '</label><br>';
        echo '<input type="file" name="csv_file" id="csv_file" required>';
        echo '</p>';
        echo '<p>';
    echo '<label><input type="checkbox" name="has_headers" checked> ' . esc_html__( 'First row contains column headers', 'tpw-core' ) . '</label>';
        echo '</p>';
    echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Upload and Continue', 'tpw-core' ) . '</button></p>';
        echo '<p>';
    echo '<a href="' . esc_url( plugins_url( 'modules/members/assets/sample-members-template.csv', TPW_CORE_FILE ) ) . '" class="button">' . esc_html__( 'Download Sample CSV Template', 'tpw-core' ) . '</a>';
        echo '</p>';
        echo '</form>';
    }
}