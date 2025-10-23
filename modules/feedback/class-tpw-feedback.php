<?php
/**
 * TPW Core – Feedback Module
 *
 * Provides a centralised RSVP process feedback form and storage.
 * Table: {$wpdb->prefix}tpw_rsvp_feedback
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'TPW_Feedback' ) ) {
    class TPW_Feedback {
        const DB_VERSION = '1.0.0';
        const OPTION_KEY = 'tpw_feedback_db_version';
        const TABLE_SLUG = 'tpw_rsvp_feedback';
        const NONCE_ACTION = 'tpw_feedback_submit';
        const NONCE_FIELD  = 'tpw_feedback_nonce';

        /**
         * Boot the module. Call from Core loader after plugins_loaded.
         */
        public static function init() {
            // Ensure table exists / is upgraded lazily.
            add_action( 'init', [ __CLASS__, 'maybe_install_table' ] );

            // Hook point for RSVP confirmation pages to render the form automatically.
            // Signature expected: ($submission_id, $event_id, $module_slug, $origin)
            add_action( 'tpw_rsvp_confirmation_after', [ __CLASS__, 'render_form_hook' ], 10, 4 );

            // AJAX endpoints.
            add_action( 'wp_ajax_tpw_feedback_submit', [ __CLASS__, 'ajax_save' ] );
            add_action( 'wp_ajax_nopriv_tpw_feedback_submit', [ __CLASS__, 'ajax_save' ] );

            // Assets (kept lightweight; only enqueue when rendering form).
            add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
        }

        /**
         * Table name helper.
         */
        protected static function table_name() {
            global $wpdb;
            return $wpdb->prefix . self::TABLE_SLUG;
        }

        /**
         * Install/upgrade feedback table via dbDelta.
         */
        public static function maybe_install_table() {
            $installed = get_option( self::OPTION_KEY );
            if ( $installed === self::DB_VERSION ) {
                return;
            }

            global $wpdb;
            $table = self::table_name();
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            // phpcs:disable WordPress.DB.RestrictedFunctions.mysql_create_db
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table} (
                feedback_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                submission_id BIGINT UNSIGNED NOT NULL,
                event_id BIGINT UNSIGNED NOT NULL,
                member_id BIGINT UNSIGNED NULL,
                ease_rating TINYINT UNSIGNED NULL,
                clarity_ok TINYINT(1) NULL,
                time_under_2min TINYINT(1) NULL,
                suggestions TEXT NULL,
                origin VARCHAR(32) NOT NULL DEFAULT 'thankyou',
                module_slug VARCHAR(64) NOT NULL,
                user_agent VARCHAR(255) NULL,
                client_platform VARCHAR(64) NULL,
                lang VARCHAR(10) NULL,
                extra JSON NULL,
                is_anonymous TINYINT(1) NOT NULL DEFAULT 1,
                ip_hash CHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (feedback_id),
                KEY idx_submission (submission_id),
                KEY idx_event (event_id),
                KEY idx_module_time (module_slug, created_at),
                KEY idx_origin (origin)
            ) {$charset_collate};";

            dbDelta( $sql );
            update_option( self::OPTION_KEY, self::DB_VERSION );
        }

        /**
         * Register assets used by the feedback form. Enqueued on demand in render_form().
         */
        public static function register_assets() {
            $base = plugin_dir_url( dirname( __FILE__, 2 ) ); // /tpw-core/

            // Expect assets under modules/feedback/assets/
            wp_register_style(
                'tpw-feedback',
                $base . 'modules/feedback/assets/feedback.css',
                [],
                defined( 'TPW_CORE_VERSION' ) ? TPW_CORE_VERSION : self::DB_VERSION
            );

            wp_register_script(
                'tpw-feedback',
                $base . 'modules/feedback/assets/feedback.js',
                [ 'jquery' ],
                defined( 'TPW_CORE_VERSION' ) ? TPW_CORE_VERSION : self::DB_VERSION,
                true
            );

            wp_localize_script( 'tpw-feedback', 'TPW_FEEDBACK', [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
                'i18n'    => [
                    'thanks' => __( 'Thanks for your feedback!', 'tpw-core' ),
                    'error'  => __( 'Sorry, something went wrong. Please try again.', 'tpw-core' ),
                ],
            ] );
        }

        /**
         * Convenience hook wrapper to match the confirmation action signature.
         */
        public static function render_form_hook( $submission_id, $event_id, $module_slug, $origin = 'thankyou' ) {
            self::render_form( (int) $submission_id, (int) $event_id, sanitize_text_field( $module_slug ), sanitize_text_field( $origin ) );
        }

        /**
         * Render the compact RSVP process feedback form.
         * Safe to call multiple times; will enqueue assets once.
         */
        public static function render_form( $submission_id, $event_id, $module_slug, $origin = 'thankyou', $args = [] ) {
            if ( empty( $submission_id ) || empty( $event_id ) || empty( $module_slug ) ) {
                return; // Not enough context.
            }

            // Enqueue minimal assets.
            wp_enqueue_style( 'tpw-feedback' );
            wp_enqueue_script( 'tpw-feedback' );

            $member_id = get_current_user_id();
            $nonce     = wp_create_nonce( self::NONCE_ACTION );

            // Basic labels (British English tone).
            $labels = wp_parse_args( $args['labels'] ?? [], [
                'title'        => __( 'Help us improve the RSVP form', 'tpw-core' ),
                'ease'         => __( 'How easy was it to complete the RSVP?', 'tpw-core' ),
                'clarity'      => __( 'Did you find all the options you needed?', 'tpw-core' ),
                'time'         => __( 'About how long did it take?', 'tpw-core' ),
                'suggestions'  => __( 'Any suggestions to improve the form?', 'tpw-core' ),
                'submit'       => __( 'Send feedback', 'tpw-core' ),
            ] );
            ?>
            <div class="tpw-feedback-panel" data-tpw-feedback>
                <h3 class="tpw-feedback-title"><?php echo esc_html( $labels['title'] ); ?></h3>

                <form class="tpw-feedback-form" method="post" action="#" novalidate>
                    <input type="hidden" name="action" value="tpw_feedback_submit" />
                    <input type="hidden" name="<?php echo esc_attr( self::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $nonce ); ?>" />

                    <input type="hidden" name="submission_id" value="<?php echo (int) $submission_id; ?>" />
                    <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                    <input type="hidden" name="module_slug" value="<?php echo esc_attr( $module_slug ); ?>" />
                    <input type="hidden" name="origin" value="<?php echo esc_attr( $origin ); ?>" />
                    <input type="hidden" name="member_id" value="<?php echo (int) $member_id; ?>" />

                    <fieldset class="tpw-field tpw-ease">
                        <label><?php echo esc_html( $labels['ease'] ); ?></label>
                        <div class="tpw-rating-group">
                            <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                <label><input type="radio" name="ease_rating" value="<?php echo (int) $i; ?>"> <?php echo (int) $i; ?></label>
                            <?php endfor; ?>
                        </div>
                    </fieldset>

                    <fieldset class="tpw-field tpw-clarity">
                        <label><?php echo esc_html( $labels['clarity'] ); ?></label>
                        <label><input type="radio" name="clarity_ok" value="1"> <?php esc_html_e( 'Yes', 'tpw-core' ); ?></label>
                        <label><input type="radio" name="clarity_ok" value="0"> <?php esc_html_e( 'No', 'tpw-core' ); ?></label>
                        <input type="text" name="missing_or_unclear" class="tpw-inline-text" placeholder="<?php esc_attr_e( 'If no, what was missing or unclear?', 'tpw-core' ); ?>">
                    </fieldset>

                    <fieldset class="tpw-field tpw-time">
                        <label><?php echo esc_html( $labels['time'] ); ?></label>
                        <label><input type="radio" name="time_bucket" value="under2"> <?php esc_html_e( 'Under 2 minutes', 'tpw-core' ); ?></label>
                        <label><input type="radio" name="time_bucket" value="2-5"> <?php esc_html_e( '2–5 minutes', 'tpw-core' ); ?></label>
                        <label><input type="radio" name="time_bucket" value=">5"> <?php esc_html_e( 'Over 5 minutes', 'tpw-core' ); ?></label>
                    </fieldset>

                    <fieldset class="tpw-field tpw-suggestions">
                        <label for="tpw_suggestions"><?php echo esc_html( $labels['suggestions'] ); ?></label>
                        <textarea id="tpw_suggestions" name="suggestions" rows="3" maxlength="1000" placeholder="<?php esc_attr_e( 'Optional', 'tpw-core' ); ?>"></textarea>
                    </fieldset>

                    <div class="tpw-actions">
                        <button type="submit" class="tpw-btn tpw-btn-primary"><?php echo esc_html( $labels['submit'] ); ?></button>
                        <span class="tpw-feedback-status" aria-live="polite"></span>
                    </div>
                </form>
                <p class="tpw-feedback-powered">
                    Powered by <a href="https://thepluginworks.com" target="_blank" rel="noopener">ThePluginWorks</a>
                </p>
            </div>
            <?php
        }

        /**
         * AJAX handler to save feedback.
         */
        public static function ajax_save() {
            // Basic nonce check
            $nonce = $_POST[ self::NONCE_FIELD ] ?? '';
            if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
                wp_send_json_error( [ 'message' => __( 'Bad nonce.', 'tpw-core' ) ], 403 );
            }

            $submission_id = isset( $_POST['submission_id'] ) ? (int) $_POST['submission_id'] : 0;
            $event_id      = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
            $module_slug   = isset( $_POST['module_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['module_slug'] ) ) : '';
            $origin        = isset( $_POST['origin'] ) ? sanitize_text_field( wp_unslash( $_POST['origin'] ) ) : 'thankyou';
            $member_id     = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;

            if ( ! $submission_id || ! $event_id || ! $module_slug ) {
                wp_send_json_error( [ 'message' => __( 'Missing context.', 'tpw-core' ) ], 400 );
            }

            $ease_rating   = isset( $_POST['ease_rating'] ) ? (int) $_POST['ease_rating'] : null;
            $clarity_ok    = isset( $_POST['clarity_ok'] ) ? (int) $_POST['clarity_ok'] : null;
            $time_bucket   = isset( $_POST['time_bucket'] ) ? sanitize_text_field( wp_unslash( $_POST['time_bucket'] ) ) : '';
            $suggestions   = isset( $_POST['suggestions'] ) ? wp_kses_post( wp_unslash( $_POST['suggestions'] ) ) : '';
            $missing_unc   = isset( $_POST['missing_or_unclear'] ) ? sanitize_text_field( wp_unslash( $_POST['missing_or_unclear'] ) ) : '';

            // Capture the clean referring URL.
            $submitted_url = wp_get_referer() ? esc_url_raw( strtok( wp_get_referer(), '?' ) ) : null;

            // Derive time_under_2min boolean for core column.
            $time_under_2  = ( $time_bucket === 'under2' ) ? 1 : ( $time_bucket ? 0 : null );

            // Build extras JSON to keep core table lean.
            $extra = [
                'time_bucket'        => $time_bucket ?: null,
                'missing_or_unclear' => $missing_unc ?: null,
                'submitted_url'      => $submitted_url,
            ];

            // Telemetry (no PII beyond IDs; anonymous by default).
            $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';
            $client_platform = wp_is_mobile() ? 'mobile' : 'desktop';
            $lang = get_locale();

            // Privacy: store only a salted hash of IP to allow dedupe.
            $ip_raw  = self::client_ip();
            $ip_hash = $ip_raw ? hash( 'sha256', $ip_raw . wp_salt( 'auth' ) ) : null;

            $data = [
                'submission_id'   => $submission_id,
                'event_id'        => $event_id,
                'member_id'       => $member_id ?: null,
                'ease_rating'     => ( $ease_rating > 0 && $ease_rating <= 5 ) ? $ease_rating : null,
                'clarity_ok'      => ( $clarity_ok === 0 || $clarity_ok === 1 ) ? $clarity_ok : null,
                'time_under_2min' => $time_under_2,
                'suggestions'     => $suggestions,
                'origin'          => $origin,
                'module_slug'     => $module_slug,
                'user_agent'      => $user_agent,
                'client_platform' => $client_platform,
                'lang'            => $lang,
                'extra'           => self::maybe_json_encode( array_filter( $extra, fn( $v ) => ! is_null( $v ) ) ),
                'is_anonymous'    => 1,
                'ip_hash'         => $ip_hash,
                'created_at'      => current_time( 'mysql' ),
            ];

            $result = self::save( $data );

            if ( $result ) {
                // Send email notification after successful save.
                $to = 'clovissa@thepluginworks.com';
                $subject = 'New TPW Feedback Submitted';
                $body_lines = [
                    'A new RSVP feedback has been submitted:',
                    '',
                    'Submission ID: ' . html_entity_decode( esc_html( $data['submission_id'] ) ),
                    'Event ID: ' . html_entity_decode( esc_html( $data['event_id'] ) ),
                    'Module Slug: ' . html_entity_decode( esc_html( $data['module_slug'] ) ),
                    'Origin: ' . html_entity_decode( esc_html( $data['origin'] ) ),
                    'Submitted From: ' . ( ! empty( $data['client_platform'] ) ? html_entity_decode( esc_html( $data['client_platform'] ) ) : 'N/A' ),
                    'Page URL: ' . ( $submitted_url ? esc_url( $submitted_url ) : 'N/A' ),
                    '',
                    'Ease Rating: ' . ( isset( $data['ease_rating'] ) && $data['ease_rating'] !== null ? html_entity_decode( esc_html( $data['ease_rating'] ) ) : 'N/A' ),
                    
                    // Additional form responses:
                    'Clarity OK: ' . (
                        ( $clarity_ok === 1 ) ? 'Yes'
                        : ( $clarity_ok === 0 ? 'No' : 'N/A' )
                    ),
                    'Missing or Unclear: ' . ( ! empty( $missing_unc ) ? html_entity_decode( esc_html( $missing_unc ) ) : 'None' ),
                    'Time Bucket: ' . ( ! empty( $time_bucket ) ? html_entity_decode( esc_html( $time_bucket ) ) : 'N/A' ),
                    'Suggestions: ' . ( ! empty( $suggestions ) ? html_entity_decode( esc_html( $suggestions ) ) : 'None' ),
                    
                ];
                $body = implode( "\n", $body_lines );
                $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
                wp_mail( $to, $subject, $body, $headers );

                wp_send_json_success( [ 'message' => __( 'Saved', 'tpw-core' ) ] );
            } else {
                wp_send_json_error( [ 'message' => __( 'Database error', 'tpw-core' ) ], 500 );
            }
        }

        /**
         * Insert row (can be used by non-AJAX flows as well).
         */
        public static function save( array $data ) {
            global $wpdb;
            $table = self::table_name();

            // Normalise JSON field for insert.
            if ( isset( $data['extra'] ) && is_array( $data['extra'] ) ) {
                $data['extra'] = wp_json_encode( $data['extra'] );
            }

            $formats = [
                '%d', // submission_id
                '%d', // event_id
                '%d', // member_id
                '%d', // ease_rating
                '%d', // clarity_ok
                '%d', // time_under_2min
                '%s', // suggestions
                '%s', // origin
                '%s', // module_slug
                '%s', // user_agent
                '%s', // client_platform
                '%s', // lang
                '%s', // extra JSON
                '%d', // is_anonymous
                '%s', // ip_hash
                '%s', // created_at
            ];

            $ordered = [
                'submission_id',
                'event_id',
                'member_id',
                'ease_rating',
                'clarity_ok',
                'time_under_2min',
                'suggestions',
                'origin',
                'module_slug',
                'user_agent',
                'client_platform',
                'lang',
                'extra',
                'is_anonymous',
                'ip_hash',
                'created_at',
            ];

            $insert_data = [];
            foreach ( $ordered as $key ) {
                $insert_data[] = $data[ $key ] ?? null;
            }

            $ok = $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$table}
                (submission_id, event_id, member_id, ease_rating, clarity_ok, time_under_2min, suggestions, origin, module_slug, user_agent, client_platform, lang, extra, is_anonymous, ip_hash, created_at)
                VALUES (%d,%d,%d,%d,%d,%d,%s,%s,%s,%s,%s,%s,%s,%d,%s,%s)",
                $insert_data
            ) );

            return (bool) $ok;
        }

        /**
         * Utility: best-effort client IP (never stored raw, only hashed).
         */
        protected static function client_ip() {
            $keys = [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
            foreach ( $keys as $key ) {
                if ( ! empty( $_SERVER[ $key ] ) ) {
                    $ip = explode( ',', $_SERVER[ $key ] );
                    $ip = trim( $ip[0] );
                    if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                        return $ip;
                    }
                }
            }
            return null;
        }

        /**
         * JSON encoding helper handling NULL values.
         */
        protected static function maybe_json_encode( $data ) {
            if ( is_null( $data ) ) {
                return null;
            }
            if ( is_array( $data ) ) {
                return wp_json_encode( $data );
            }
            return (string) $data;
        }
    }

    // Auto-init when Core loads modules.
    add_action( 'plugins_loaded', [ 'TPW_Feedback', 'init' ] );
}