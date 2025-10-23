<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class TPW_Email {
    // Security hygiene: In wrap_html(), the variables $logo_html, $title_html, and $body
    // are constructed from sanitized sources (logo src via esc_url/esc_attr, title via esc_html,
    // and message body via wp_kses_post + wpautop). They are therefore safe to echo in the
    // template block without additional escaping.
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

        // Send main email
        $sent = wp_mail( $to_email, $subject_clean, $wrapped, $headers, $validated_paths );
        self::log_email( [
            'direction'  => 'outbound',
            'to'         => $to_email,
            'from_email' => $from_email,
            'from_name'  => $from_name,
            'subject'    => $subject_clean,
            'attachments'=> $validated_paths,
            'sent'       => (bool) $sent,
        ] );

        if ( ! $sent ) {
            return [ 'success' => false, 'message' => __( 'Failed to send email.', 'tpw-core' ) ];
        }

        // Optionally send a copy to sender
        if ( $send_copy ) {
            $copy_subject = '[Copy] ' . $subject_clean;
            $copy_body    = self::wrap_html( '<p>' . esc_html__( 'This is a copy of your message.', 'tpw-core' ) . '</p>' . $html_message, $from_name, (bool) $use_logo );
            // Sender copy: no attachments by default for safety
            $copy_sent = wp_mail( $from_email, $copy_subject, $copy_body, $headers );
            self::log_email( [
                'direction'  => 'copy',
                'to'         => $from_email,
                'from_email' => $from_email,
                'from_name'  => $from_name,
                'subject'    => $copy_subject,
                'attachments'=> [],
                'sent'       => (bool) $copy_sent,
            ] );
        }

        return [ 'success' => true, 'message' => __( 'Email sent successfully.', 'tpw-core' ) ];
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
        do_action( 'tpw_email/log', $details );
        // Optionally write to a log table/file later via hooked listeners.
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
        // Annotate log with template key via action for listeners if needed
        if ( has_action( 'tpw_email/log' ) ) {
            self::log_email( [ 'template_key' => (string) $template_key, 'note' => 'template_send' ] );
        }
        return $res;
    }
}
