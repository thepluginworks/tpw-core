<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class TPW_Email {
    const THROTTLE_STATE_OPTION = 'tpw_core_email_throttle_state';
    const THROTTLE_LOCK_OPTION  = 'tpw_core_email_throttle_lock';

    // Security hygiene: In wrap_html(), the variables $logo_html, $title_html, and $body
    // are constructed from sanitized sources (logo src via esc_url/esc_attr, title via esc_html,
    // and message body via wp_kses_post + wpautop). They are therefore safe to echo in the
    // template block without additional escaping.
    /**
     * Central low-level dispatcher for TPW emails.
     *
     * This is the single point where TPW Core enforces outbound email throttling
     * before handing off to WordPress' wp_mail().
     *
     * @param string|array $to
     * @param string       $subject
     * @param string       $message
    * @param string|array $headers
    * @param array        $attachments
    * @param string|array $context
    * @return bool
     */
    public static function dispatch_mail( $to, $subject, $message, $headers = [], $attachments = [], $context = [] ) {
        $subject_clean    = wp_strip_all_tags( (string) $subject );
        $message_body     = (string) $message;
        $headers_for_send = is_array( $headers ) ? $headers : (string) $headers;
        $attachments_list = is_array( $attachments ) ? array_values( array_filter( $attachments ) ) : [];
        $dispatch_context = self::normalise_dispatch_context( $context );

        $slot = self::reserve_dispatch_slot();
        if ( ! empty( $slot['wait_seconds'] ) ) {
            self::sleep_for_seconds( (float) $slot['wait_seconds'] );
        }

        $attempted_at = gmdate( 'Y-m-d H:i:s' );
        $started_at   = microtime( true );
        $mail_error   = null;
        $capture_failure = static function( $wp_error ) use ( &$mail_error ) {
            if ( is_wp_error( $wp_error ) ) {
                $mail_error = implode( '; ', $wp_error->get_error_messages() );
            }
        };

        add_action( 'wp_mail_failed', $capture_failure, 10, 1 );
        try {
            $sent = wp_mail( $to, $subject_clean, $message_body, $headers_for_send, $attachments_list );
        } finally {
            remove_action( 'wp_mail_failed', $capture_failure, 10 );
        }

        $duration_ms = max( 0, (int) round( ( microtime( true ) - $started_at ) * 1000 ) );
        if ( ! $sent && empty( $mail_error ) ) {
            $mail_error = __( 'wp_mail() returned false.', 'tpw-core' );
        }

        self::log_email(
            array_merge(
                [
                    'dispatcher'            => 'TPW_Email::dispatch_mail',
                    'timestamp'             => $attempted_at,
                    'to'                    => $to,
                    'subject'               => $subject_clean,
                    'context'               => self::extract_log_context_label( $dispatch_context ),
                    'headers'               => is_array( $headers_for_send ) ? $headers_for_send : preg_split( '/\r\n|\r|\n/', (string) $headers_for_send ),
                    'attachments'           => $attachments_list,
                    'sent'                  => (bool) $sent,
                    'status'                => $sent ? 'sent' : 'failed',
                    'error_message'         => $mail_error,
                    'duration_ms'           => $duration_ms,
                    'throttling_enabled'    => ! empty( $slot['applied'] ),
                    'throttle_wait_seconds' => isset( $slot['wait_seconds'] ) ? (float) $slot['wait_seconds'] : 0.0,
                    'scheduled_send_at'     => isset( $slot['scheduled_at'] ) ? (float) $slot['scheduled_at'] : microtime( true ),
                ],
                $dispatch_context
            )
        );

        return (bool) $sent;
    }

    /**
     * Send an HTML email with optional attachments and optional copy to sender.
     *
     * @param string $to           Recipient email address.
     * @param array  $from         Assoc: ['name' => string, 'email' => string]
     * @param string $subject      Subject line.
     * @param string $message      HTML message (will be sanitized and wrapped).
     * @param array  $attachments  Array of file paths to attach (validated beforehand or raw $_FILES handled elsewhere).
     * @param bool   $send_copy    Whether to send a copy to the sender. Default true.
     * @return array { success: bool, message: string }
     */
    public static function send_email( $to, $from, $subject, $message, $attachments = [], $send_copy = true, $use_logo = true ) {
        // Sanitize inputs
        $to_email      = sanitize_email( (string) $to );
        $from_name     = sanitize_text_field( isset($from['name']) ? (string) $from['name'] : '' );
        $from_email    = sanitize_email( isset($from['email']) ? (string) $from['email'] : '' );
        $subject_clean = wp_strip_all_tags( (string) $subject );
        $html_message  = wp_kses_post( (string) $message );

        if ( empty($to_email) || ! is_email($to_email) ) {
            return [ 'success' => false, 'message' => __( 'Invalid recipient email.', 'tpw-core' ) ];
        }
        if ( empty($from_email) || ! is_email($from_email) ) {
            return [ 'success' => false, 'message' => __( 'Invalid sender email.', 'tpw-core' ) ];
        }
        if ( '' === $subject_clean ) {
            return [ 'success' => false, 'message' => __( 'Subject is required.', 'tpw-core' ) ];
        }
        if ( '' === trim( wp_strip_all_tags( $html_message ) ) ) {
            return [ 'success' => false, 'message' => __( 'Message is required.', 'tpw-core' ) ];
        }

        // Validate attachments (paths) if any
        $validated_paths = [];
        if ( ! empty( $attachments ) ) {
            $res = self::validate_attachments( $attachments );
            if ( ! $res['success'] ) {
                return $res; // contains error message
            }
            $validated_paths = $res['paths'];
        }

    // Build HTML layout wrapper (with optional logo)
    $wrapped = self::wrap_html( $html_message, $from_name, (bool) $use_logo );

        // Build headers: HTML content and reply-to to sender
        $headers   = [];
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Reply-To: ' . ( $from_name ? ( $from_name . ' ' ) : '' ) . '<' . $from_email . '>';

        // Send main email through the shared dispatcher so throttling is enforced.
        $sent = self::dispatch_mail( $to_email, $subject_clean, $wrapped, $headers, $validated_paths, [
            'direction'  => 'outbound',
            'from_email' => $from_email,
            'from_name'  => $from_name,
            'message_type' => 'html',
            'source'     => 'TPW_Email::send_email',
        ] );

        if ( ! $sent ) {
            return [ 'success' => false, 'message' => __( 'Failed to send email.', 'tpw-core' ) ];
        }

        // Optionally send a copy to sender
        if ( $send_copy ) {
            $copy_subject = '[Copy] ' . $subject_clean;
            $copy_body    = self::wrap_html( '<p>' . esc_html__( 'This is a copy of your message.', 'tpw-core' ) . '</p>' . $html_message, $from_name, (bool) $use_logo );
            // Sender copy: no attachments by default for safety
            self::dispatch_mail( $from_email, $copy_subject, $copy_body, $headers, [], [
                'direction'    => 'copy',
                'from_email'   => $from_email,
                'from_name'    => $from_name,
                'message_type' => 'html',
                'source'       => 'TPW_Email::send_email',
            ] );
        }

        return [ 'success' => true, 'message' => __( 'Email sent successfully.', 'tpw-core' ) ];
    }

    /**
     * Reserve the next available outbound email slot.
     *
     * The reservation is made under a short-lived lock so concurrent requests
     * share one rolling schedule without introducing a separate queue system.
     *
     * @return array<string, mixed>
     */
    protected static function reserve_dispatch_slot() {
        $settings = self::get_dispatch_settings();
        $now      = microtime( true );

        if ( empty( $settings['enable_throttling'] ) ) {
            return [
                'applied'      => false,
                'wait_seconds' => 0.0,
                'scheduled_at' => $now,
                'settings'     => $settings,
            ];
        }

        $lock_token = self::acquire_dispatch_lock();
        if ( '' === $lock_token ) {
            return [
                'applied'      => false,
                'wait_seconds' => 0.0,
                'scheduled_at' => $now,
                'settings'     => $settings,
                'reason'       => 'lock_timeout',
            ];
        }

        try {
            $state      = get_option( self::THROTTLE_STATE_OPTION, [] );
            $timestamps = self::normalise_dispatch_timestamps( isset( $state['scheduled'] ) ? $state['scheduled'] : [] );
            $timestamps = array_values( array_filter( $timestamps, function( $timestamp ) use ( $now ) {
                return $timestamp >= ( $now - 60 );
            } ) );
            sort( $timestamps, SORT_NUMERIC );

            $last_scheduled_at = isset( $state['last_scheduled_at'] ) ? (float) $state['last_scheduled_at'] : 0.0;
            $candidate         = max( $now, $last_scheduled_at );
            $delay_seconds     = max( 0, (int) $settings['delay_between_emails'] );
            $max_per_minute    = max( 1, (int) $settings['max_emails_per_minute'] );

            if ( $delay_seconds > 0 ) {
                $candidate = max( $candidate, $last_scheduled_at + (float) $delay_seconds );
            }

            while ( true ) {
                $window = array_values( array_filter( $timestamps, function( $timestamp ) use ( $candidate ) {
                    return $timestamp > ( $candidate - 60 ) && $timestamp <= $candidate;
                } ) );

                if ( count( $window ) < $max_per_minute ) {
                    break;
                }

                $candidate = max( $candidate, (float) reset( $window ) + 60.0 );
            }

            $timestamps[] = $candidate;
            sort( $timestamps, SORT_NUMERIC );

            update_option( self::THROTTLE_STATE_OPTION, [
                'scheduled'         => $timestamps,
                'last_scheduled_at' => $candidate,
                'updated_at'        => $now,
            ] );

            return [
                'applied'      => true,
                'wait_seconds' => max( 0.0, $candidate - $now ),
                'scheduled_at' => $candidate,
                'settings'     => $settings,
            ];
        } finally {
            self::release_dispatch_lock( $lock_token );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected static function get_dispatch_settings() {
        $settings = class_exists( 'TPW_Core_Email_Settings' ) ? TPW_Core_Email_Settings::get() : [];

        $enable_throttling = ! empty( $settings['enable_throttling'] );
        $max_per_minute    = isset( $settings['max_emails_per_minute'] ) ? (int) $settings['max_emails_per_minute'] : 60;
        $delay_seconds     = isset( $settings['delay_between_emails'] ) ? (int) $settings['delay_between_emails'] : 0;

        return [
            'enable_throttling'    => (bool) apply_filters( 'tpw_email/enable_throttling', $enable_throttling, $settings ),
            'max_emails_per_minute'=> max( 1, (int) apply_filters( 'tpw_email/max_emails_per_minute', $max_per_minute, $settings ) ),
            'delay_between_emails' => max( 0, (int) apply_filters( 'tpw_email/delay_between_emails', $delay_seconds, $settings ) ),
        ];
    }

    /**
     * @param mixed $timestamps
     * @return array<int, float>
     */
    protected static function normalise_dispatch_timestamps( $timestamps ) {
        if ( ! is_array( $timestamps ) ) {
            return [];
        }

        $normalised = [];
        foreach ( $timestamps as $timestamp ) {
            if ( is_numeric( $timestamp ) ) {
                $normalised[] = (float) $timestamp;
            }
        }

        sort( $normalised, SORT_NUMERIC );
        return $normalised;
    }

    /**
     * @param float $timeout_seconds
     * @return string
     */
    protected static function acquire_dispatch_lock( $timeout_seconds = 5.0 ) {
        $token    = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'tpw-email-', true );
        $deadline = microtime( true ) + max( 0.1, (float) $timeout_seconds );

        while ( microtime( true ) < $deadline ) {
            $now  = microtime( true );
            $lock = [
                'token'      => $token,
                'expires_at' => $now + 15.0,
            ];

            if ( add_option( self::THROTTLE_LOCK_OPTION, $lock, '', false ) ) {
                return $token;
            }

            $existing = get_option( self::THROTTLE_LOCK_OPTION, [] );
            $expired  = ! is_array( $existing ) || empty( $existing['expires_at'] ) || (float) $existing['expires_at'] < $now;

            if ( $expired ) {
                delete_option( self::THROTTLE_LOCK_OPTION );
                continue;
            }

            usleep( 100000 );
        }

        return '';
    }

    /**
     * @param string $token
     * @return void
     */
    protected static function release_dispatch_lock( $token ) {
        $existing = get_option( self::THROTTLE_LOCK_OPTION, [] );
        if ( is_array( $existing ) && isset( $existing['token'] ) && (string) $existing['token'] === (string) $token ) {
            delete_option( self::THROTTLE_LOCK_OPTION );
        }
    }

    /**
     * @param float $seconds
     * @return void
     */
    protected static function sleep_for_seconds( $seconds ) {
        $seconds = max( 0.0, (float) $seconds );
        if ( $seconds <= 0 ) {
            return;
        }

        $whole_seconds = (int) floor( $seconds );
        $microseconds  = (int) round( ( $seconds - $whole_seconds ) * 1000000 );

        if ( $whole_seconds > 0 ) {
            sleep( $whole_seconds );
        }

        if ( $microseconds > 0 ) {
            usleep( $microseconds );
        }
    }

    /**
     * Validate attachments. Accepts either file paths or an array similar to $_FILES list.
     * Returns array: [ success => bool, message => string, paths => [] ]
     */
    public static function validate_attachments( $files ) {
    $allowed_exts = [ 'pdf', 'docx', 'jpg', 'jpeg', 'png' ];
    $policy_max   = 5 * 1024 * 1024; // 5MB policy cap
    $server_max   = (int) wp_max_upload_size();
    $max_bytes    = ( $server_max > 0 ) ? min( $policy_max, $server_max ) : $policy_max;

        $paths = [];

        // Normalize to an array of file path strings; handle $_FILES structure if present
        if ( isset( $files['name'] ) && is_array( $files['name'] ) ) {
            // Multiple files via $_FILES['field'] structure
            $count = count( $files['name'] );
            for ( $i = 0; $i < $count; $i++ ) {
                $name = (string) $files['name'][$i];
                $tmp  = (string) $files['tmp_name'][$i];
                $size = (int) $files['size'][$i];
                $error= (int) $files['error'][$i];
                // If no file was selected for this index, skip it silently
                if ( $error === UPLOAD_ERR_NO_FILE ) {
                    continue;
                }
                if ( $error !== UPLOAD_ERR_OK ) {
                    $err_map = [
                        UPLOAD_ERR_INI_SIZE   => __( 'The uploaded file exceeds the upload_max_filesize directive.', 'tpw-core' ),
                        UPLOAD_ERR_FORM_SIZE  => __( 'The uploaded file exceeds the MAX_FILE_SIZE directive.', 'tpw-core' ),
                        UPLOAD_ERR_PARTIAL    => __( 'The uploaded file was only partially uploaded.', 'tpw-core' ),
                        UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'tpw-core' ),
                        UPLOAD_ERR_NO_TMP_DIR => __( 'Missing a temporary folder on the server.', 'tpw-core' ),
                        UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'tpw-core' ),
                        UPLOAD_ERR_EXTENSION  => __( 'A PHP extension stopped the file upload.', 'tpw-core' ),
                    ];
                    $msg = isset($err_map[$error]) ? $err_map[$error] : __( 'Upload error.', 'tpw-core' );
                    return [ 'success' => false, 'message' => $msg ];
                }
                $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
                if ( ! in_array( $ext, $allowed_exts, true ) ) {
                    return [ 'success' => false, 'message' => sprintf( __( 'File type not allowed: %s', 'tpw-core' ), esc_html( $name ) ) ];
                }
                if ( $size > $max_bytes ) {
                    return [ 'success' => false, 'message' => sprintf( __( 'File too large: %s', 'tpw-core' ), esc_html( $name ) ) ];
                }
                $moved = self::move_to_temp( $tmp, $name );
                if ( empty( $moved ) ) {
                    return [ 'success' => false, 'message' => __( 'Unable to store uploaded file. Please try again or contact support.', 'tpw-core' ) ];
                }
                $paths[] = $moved;
            }
        } elseif ( is_array( $files ) ) {
            // Possibly array of file paths
            foreach ( $files as $f ) {
                if ( is_string( $f ) && file_exists( $f ) ) {
                    $ext = strtolower( pathinfo( $f, PATHINFO_EXTENSION ) );
                    if ( ! in_array( $ext, $allowed_exts, true ) ) {
                        return [ 'success' => false, 'message' => sprintf( __( 'File type not allowed: %s', 'tpw-core' ), esc_html( basename($f) ) ) ];
                    }
                    if ( filesize( $f ) > $max_bytes ) {
                        return [ 'success' => false, 'message' => sprintf( __( 'File too large: %s', 'tpw-core' ), esc_html( basename($f) ) ) ];
                    }
                    $paths[] = $f;
                }
            }
        } elseif ( isset( $files['name'] ) && is_string( $files['name'] ) ) {
            // Single file input not using []
            $name = (string) $files['name'];
            $tmp  = (string) $files['tmp_name'];
            $size = (int) $files['size'];
            $error= (int) $files['error'];
            if ( $error === UPLOAD_ERR_NO_FILE ) {
                // no attachments
                return [ 'success' => true, 'message' => 'OK', 'paths' => [] ];
            }
            if ( $error !== UPLOAD_ERR_OK ) {
                $err_map = [
                    UPLOAD_ERR_INI_SIZE   => __( 'The uploaded file exceeds the upload_max_filesize directive.', 'tpw-core' ),
                    UPLOAD_ERR_FORM_SIZE  => __( 'The uploaded file exceeds the MAX_FILE_SIZE directive.', 'tpw-core' ),
                    UPLOAD_ERR_PARTIAL    => __( 'The uploaded file was only partially uploaded.', 'tpw-core' ),
                    UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'tpw-core' ),
                    UPLOAD_ERR_NO_TMP_DIR => __( 'Missing a temporary folder on the server.', 'tpw-core' ),
                    UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'tpw-core' ),
                    UPLOAD_ERR_EXTENSION  => __( 'A PHP extension stopped the file upload.', 'tpw-core' ),
                ];
                $msg = isset($err_map[$error]) ? $err_map[$error] : __( 'Upload error.', 'tpw-core' );
                return [ 'success' => false, 'message' => $msg ];
            }
            $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, $allowed_exts, true ) ) {
                return [ 'success' => false, 'message' => sprintf( __( 'File type not allowed: %s', 'tpw-core' ), esc_html( $name ) ) ];
            }
            if ( $size > $max_bytes ) {
                return [ 'success' => false, 'message' => sprintf( __( 'File too large: %s', 'tpw-core' ), esc_html( $name ) ) ];
            }
            $moved = self::move_to_temp( $tmp, $name );
            if ( empty( $moved ) ) {
                return [ 'success' => false, 'message' => __( 'Unable to store uploaded file. Please try again or contact support.', 'tpw-core' ) ];
            }
            $paths[] = $moved;
        }

        return [ 'success' => true, 'message' => 'OK', 'paths' => array_filter( $paths ) ];
    }

    /**
     * Move uploaded file to a temp directory under uploads for email attachments.
     */
    protected static function move_to_temp( $tmp_path, $orig_name ) {
        $uploads = wp_get_upload_dir();
        $dir = trailingslashit( $uploads['basedir'] ) . 'tpw-email-temp';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $safe_name = sanitize_file_name( $orig_name );
        $dest = trailingslashit( $dir ) . wp_unique_filename( $dir, $safe_name );
        // Prefer move_uploaded_file for uploads; fallback to rename. If both fail, return empty string so validator can flag.
        $moved = false;
        if ( function_exists('is_uploaded_file') && @is_uploaded_file( $tmp_path ) ) {
            $moved = @move_uploaded_file( $tmp_path, $dest );
        }
        if ( ! $moved ) {
            $moved = @rename( $tmp_path, $dest );
        }
        if ( ! $moved || ! file_exists( $dest ) ) {
            return '';
        }
        return $dest;
    }

    /**
     * Wrap message with a minimal HTML template.
     */
    protected static function wrap_html( $html, $from_name = '', $use_logo = true ) {
        // Determine brand assets from Core email settings and shared options
        $logo_img = '';
        if ( $use_logo && class_exists('TPW_Core_Email_Settings') ) {
            $es = TPW_Core_Email_Settings::get();
            $inline_requested = ! empty( $es['embed_logo_base64'] );
            $has_b64 = ! empty( $es['fallback_logo_base64'] );
            $has_url = ! empty( $es['fallback_logo_url'] );

            // Generate base64 on the fly once if requested
            if ( $inline_requested && ! $has_b64 && $has_url && class_exists('TPW_Email_Logo_Helper') ) {
                try {
                    $generated = TPW_Email_Logo_Helper::generate_base64( $es['fallback_logo_url'] );
                    if ( $generated ) {
                        $es['fallback_logo_base64'] = $generated;
                        $has_b64 = true;
                        if ( class_exists('TPW_Core_Email_Settings') ) {
                            TPW_Core_Email_Settings::update( [ 'fallback_logo_base64' => $generated ] );
                        }
                    }
                } catch ( \Throwable $e ) {
                    // ignore and fall back to URL
                }
            }

            if ( $inline_requested && $has_b64 ) {
                $src = $es['fallback_logo_base64'];
                $logo_img = '<img src="' . esc_attr( $src ) . '" alt="" style="max-height:50px;display:inline-block" />';
            } elseif ( $has_url ) {
                $src = esc_url( $es['fallback_logo_url'] );
                $logo_img = '<img src="' . $src . '" alt="" style="max-height:50px;display:inline-block" />';
            }
        }

        $site        = wp_specialchars_decode( get_bloginfo('name'), ENT_QUOTES );
        $brand_title = trim( (string) get_option( 'tpw_brand_title', '' ) );
        $title_text  = $brand_title !== '' ? $brand_title : $site;
        $brand_color = (string) get_option( 'tpw_brand_primary_color', '#0b3b2e' );

        // Build centered logo and title header (inside card)
        $logo_html  = $logo_img ? '<div style="margin-bottom:12px;text-align:center;line-height:1">' . $logo_img . '</div>' : '';
        $title_html = '<h2 style="margin:0 0 10px;color:' . esc_attr( $brand_color ) . ';text-align:center">' . esc_html( $title_text ) . '</h2>';

        // Sanitize message HTML
        $raw  = (string) $html;
        $safe = wp_kses_post( $raw );
        $body = wpautop( $safe );

        // Compose wrapper: bordered card + outside Powered by footer (to match FlexiGolf)
        ob_start();
        ?>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
            <style>body{background:#f6f7f9;color:#222;margin:0;padding:20px;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;} a{color:#2563eb}</style>
        </head>
        <body>
            <div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;padding:16px;border:1px solid #eee;border-radius:8px;background:#fff">
                <?php echo $logo_html; ?>
                <?php echo $title_html; ?>
                <div><?php echo $body; ?></div>
            </div>
            <div style="font-family:Arial,sans-serif;max-width:640px;margin:10px auto 0;font-size:11px;color:#9ca3af;text-align:center;">
                Powered by <a href="https://thepluginworks.com/" target="_blank" rel="noopener noreferrer" style="color:#9ca3af;text-decoration:underline">ThePluginWorks</a>
            </div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Public helper to render branded HTML wrapper.
     * Other modules should call this to ensure a consistent wrapper.
     *
     * @param string $html      The inner HTML body (will be sanitized similarly to send_email())
     * @param bool   $use_logo  Whether to include the brand header (logo/title)
     * @return string           Full HTML document ready to send
     */
    public static function render_branded_html( $html, $use_logo = true ) {
        return self::wrap_html( (string) $html, '', (bool) $use_logo );
    }

    /**
     * Placeholder for future logging.
     */
    public static function log_email( $details ) {
        if ( class_exists( 'TPW_Core_Email_Settings' ) && ! TPW_Core_Email_Settings::get( 'enable_logging' ) ) {
            return;
        }
        do_action( 'tpw_email/log', $details );
    }

    /**
     * Convenience: render a registered template by key and send.
     *
     * @param string $to
     * @param array  $from ['name'=>..., 'email'=>...]
     * @param string $template_key
     * @param array  $token_values
     * @param array  $attachments
     * @param bool   $send_copy
     * @return array { success: bool, message: string }
     */
    public static function send_with_template( $to, $from, $template_key, $token_values = [], $attachments = [], $send_copy = true ) {
        if ( ! class_exists('TPW_Email_Template_Manager') ) {
            return [ 'success' => false, 'message' => __( 'Email template manager unavailable.', 'tpw-core' ) ];
        }
        $tpl = TPW_Email_Template_Manager::get_rendered_template( $template_key, is_array($token_values) ? $token_values : [] );
        $res = self::send_email( $to, $from, (string) $tpl['subject'], (string) $tpl['body'], $attachments, $send_copy, ! empty( $tpl['use_logo'] ) );
        return $res;
    }

    /**
     * @param mixed $context
     * @return array<string, mixed>
     */
    protected static function normalise_dispatch_context( $context ) {
        if ( is_string( $context ) ) {
            $context = trim( $context );
            return $context === '' ? [] : [ 'context' => $context ];
        }

        return is_array( $context ) ? $context : [];
    }

    /**
     * @param array<string, mixed> $context
     * @return string
     */
    protected static function extract_log_context_label( array $context ) {
        if ( isset( $context['context'] ) && is_string( $context['context'] ) ) {
            return sanitize_text_field( $context['context'] );
        }

        if ( isset( $context['source'] ) && is_string( $context['source'] ) ) {
            return sanitize_text_field( $context['source'] );
        }

        return '';
    }
}
