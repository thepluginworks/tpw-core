<?php
/**
 * TPW Core Settings and Member Menu swapper.
 *
 * Registers the Settings → TPW Core page with tabbed content for Branding,
 * Member Menu, Features, Email, Email Templates, and System Pages.
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1) Register new menu location early
add_action( 'after_setup_theme', function () {
    // Avoid fatal if function unavailable very early
    if ( function_exists( 'register_nav_menu' ) ) {
        register_nav_menu( 'tpw_member_menu', __( 'TPW Member Menu', 'tpw-core' ) );
    }
}, 5 );

// 2) Add Settings page under Settings
add_action( 'admin_menu', function () {
    add_options_page(
        __( 'TPW Core Settings', 'tpw-core' ),
        __( 'TPW Core', 'tpw-core' ),
        'manage_options',
        'tpw-core-settings',
        'tpw_core_render_settings_page'
    );
} );

// Ensure media library scripts are available on our settings page
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook === 'settings_page_tpw-core-settings' ) {
        // Load WordPress media modal and dependencies
        if ( function_exists( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }
    }
} );

// 3) Render the settings page (tabbed)
if ( ! function_exists( 'tpw_core_render_settings_page' ) ) {
    /**
     * Render the TPW Core settings page wrapper and tabs.
     *
     * @since 1.0.0
     * @return void
     */
    function tpw_core_render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Build tabs (extensible)
        $tabs = apply_filters( 'tpw_core_settings_tabs', [
            'branding'    => __( 'Branding', 'tpw-core' ),
            'member-menu' => __( 'Member Menu', 'tpw-core' ),
            'features'    => __( 'Features', 'tpw-core' ),
            'email'       => __( 'Email Settings', 'tpw-core' ),
            'email-templates' => __( 'Email Templates', 'tpw-core' ),
            'payment-methods' => __( 'Payment Methods', 'tpw-core' ),
            'system-pages' => __( 'System Pages', 'tpw-core' ),
        ] );
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'member-menu';
        if ( $current_tab === '' ) {
            $current_tab = 'member-menu';
        }

        $base_url = admin_url( 'options-general.php?page=tpw-core-settings' );
    ?>
        <?php
        if ( function_exists( 'tpw_core_render_settings_header' ) ) {
            tpw_core_render_settings_header(
                __( 'TPW Core Settings', 'tpw-core' ),
                __( 'Configure branding, menus, email, payment methods, and system pages.', 'tpw-core' )
            );
        }
        ?>

    <div class="tpw-admin-ui" style="<?php echo esc_attr( function_exists('tpw_core_build_ui_theme_style_attr') ? tpw_core_build_ui_theme_style_attr() : '' ); ?>">
        <div class="wrap">

            <h2 class="nav-tab-wrapper">
                <?php foreach ( $tabs as $slug => $label ):
                    $url = esc_url( add_query_arg( 'tab', $slug, $base_url ) );
                    $active = $slug === $current_tab ? ' nav-tab-active' : '';
                ?>
                    <a href="<?php echo $url; ?>" class="nav-tab<?php echo esc_attr($active); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </h2>

            <?php
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'TPW CORE: settings_errors() START – ' . __FILE__ . ':' . __LINE__ );
            }

            settings_errors();

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'TPW CORE: settings_errors() END – ' . __FILE__ . ':' . __LINE__ );
            }
            ?>

            <?php $tpw_core_builtin_tab_rendered = false; ?>

            <?php if ( 'branding' === $current_tab ) : ?>
                <?php $tpw_core_builtin_tab_rendered = true; ?>
                <?php tpw_core_render_branding_tab(); ?>
            <?php elseif ( 'email' === $current_tab ) : ?>
                <?php $tpw_core_builtin_tab_rendered = true; ?>
                <?php tpw_core_render_email_settings_tab(); ?>
            <?php elseif ( 'email-templates' === $current_tab ) : ?>
                <?php $tpw_core_builtin_tab_rendered = true; ?>
                <?php tpw_core_render_email_templates_tab(); ?>
            <?php elseif ( 'branding' === $current_tab ) : ?>
                <?php
                $tpw_core_builtin_tab_rendered = true;
                // UI Theme settings form inside Branding tab
                $ui_defaults = function_exists('tpw_core_get_ui_theme_defaults') ? tpw_core_get_ui_theme_defaults() : [];
                $ui = function_exists('tpw_core_get_ui_theme_settings') ? tpw_core_get_ui_theme_settings(true) : $ui_defaults;
                // Handle POST back for UI theme fields
                if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tpw_core_branding_nonce']) && wp_verify_nonce( $_POST['tpw_core_branding_nonce'], 'tpw_core_save_branding' ) ) {
                    $font  = isset($_POST['tpw_ui_font_family']) ? wp_unslash( (string) $_POST['tpw_ui_font_family'] ) : '';
                    $fontw = isset($_POST['tpw_ui_font_weight']) ? preg_replace('/[^0-9a-zA-Z-]/', '', (string) $_POST['tpw_ui_font_weight'] ) : '';
                    $ttrans= isset($_POST['tpw_ui_text_transform']) ? preg_replace('/[^a-z-]/', '', strtolower( (string) $_POST['tpw_ui_text_transform'] ) ) : '';
                    $lsp   = isset($_POST['tpw_ui_letter_spacing']) ? wp_unslash( (string) $_POST['tpw_ui_letter_spacing'] ) : '';
                    $tsh   = isset($_POST['tpw_ui_text_shadow']) ? wp_unslash( (string) $_POST['tpw_ui_text_shadow'] ) : '';
                    $btnbg = isset($_POST['tpw_ui_btn_bg']) ? sanitize_hex_color( (string) $_POST['tpw_ui_btn_bg'] ) : '';
                    $btntx = isset($_POST['tpw_ui_btn_text']) ? sanitize_hex_color( (string) $_POST['tpw_ui_btn_text'] ) : '';
                    $acc   = isset($_POST['tpw_ui_accent']) ? sanitize_hex_color( (string) $_POST['tpw_ui_accent'] ) : '';
                    $save = [];
                    if ( $font !== '' ) { $save['font_family'] = $font; }
                    if ( $fontw !== '' ) { $save['font_weight'] = $fontw; }
                    if ( in_array( $ttrans, ['none','uppercase','lowercase','capitalize'], true ) ) { $save['text_transform'] = $ttrans; }
                    if ( $lsp !== '' ) { $save['letter_spacing'] = $lsp; }
                    if ( $tsh !== '' ) { $save['text_shadow'] = $tsh; }
                    if ( $btnbg ) { $save['btn_bg'] = $btnbg; }
                    if ( $btntx ) { $save['btn_text'] = $btntx; }
                    if ( $acc )   { $save['accent_color'] = $acc; }
                    if ( ! empty( $save ) ) {
                        $existing = get_option( 'tpw_ui_theme_settings', [] );
                        if ( ! is_array( $existing ) ) { $existing = []; }
                        $merged = array_merge( $existing, $save );
                        update_option( 'tpw_ui_theme_settings', $merged );
                        add_settings_error( 'tpw_core_branding', 'tpw_ui_theme_saved', __( 'UI Theme settings saved.', 'tpw-core' ), 'updated' );
                        $ui = wp_parse_args( $merged, $ui_defaults );
                    }
                }
                ?>
                <form method="post">
                    <?php wp_nonce_field( 'tpw_core_save_branding', 'tpw_core_branding_nonce' ); ?>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr><th colspan="2"><h2 style="margin:6px 0;">UI Theme (applies to .tpw-admin-ui)</h2></th></tr>
                            <tr>
                                <th scope="row"><label for="tpw_ui_font_family"><?php esc_html_e('Font family', 'tpw-core'); ?></label></th>
                                <td>
                                    <input type="text" class="regular-text" id="tpw_ui_font_family" name="tpw_ui_font_family" value="<?php echo esc_attr( (string) ($ui['font_family'] ?? '') ); ?>" placeholder="system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial" />
                                    <p class="description"><?php esc_html_e('Applies within .tpw-admin-ui only.', 'tpw-core'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tpw_ui_font_weight"><?php esc_html_e('Font weight', 'tpw-core'); ?></label></th>
                                <td>
                                    <?php $fw = (string) ($ui['font_weight'] ?? '600'); ?>
                                    <select id="tpw_ui_font_weight" name="tpw_ui_font_weight">
                                        <?php foreach ( ['normal','500','600','700'] as $opt ): ?>
                                            <option value="<?php echo esc_attr($opt); ?>" <?php selected( $fw, $opt ); ?>><?php echo esc_html($opt); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tpw_ui_text_transform"><?php esc_html_e('Text transform', 'tpw-core'); ?></label></th>
                                <td>
                                    <?php $tt = (string) ($ui['text_transform'] ?? 'none'); ?>
                                    <select id="tpw_ui_text_transform" name="tpw_ui_text_transform">
                                        <?php foreach ( ['none','uppercase','lowercase','capitalize'] as $opt ): ?>
                                            <option value="<?php echo esc_attr($opt); ?>" <?php selected( $tt, $opt ); ?>><?php echo esc_html( ucfirst($opt) ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tpw_ui_letter_spacing"><?php esc_html_e('Letter spacing', 'tpw-core'); ?></label></th>
                                <td>
                                    <input type="text" class="regular-text" id="tpw_ui_letter_spacing" name="tpw_ui_letter_spacing" value="<?php echo esc_attr( (string) ($ui['letter_spacing'] ?? 'normal') ); ?>" placeholder="normal | 0.03em | 1px" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tpw_ui_text_shadow"><?php esc_html_e('Text shadow', 'tpw-core'); ?></label></th>
                                <td>
                                    <input type="text" class="regular-text" id="tpw_ui_text_shadow" name="tpw_ui_text_shadow" value="<?php echo esc_attr( (string) ($ui['text_shadow'] ?? 'none') ); ?>" placeholder="none | 0 0 0 rgba(0,0,0,0.3)" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tpw_ui_btn_bg"><?php esc_html_e('Button background colour', 'tpw-core'); ?></label></th>
                                <td>
                                    <input type="text" class="regular-text" id="tpw_ui_btn_bg" name="tpw_ui_btn_bg" value="<?php echo esc_attr( (string) ($ui['btn_bg'] ?? '#0b6cad') ); ?>" placeholder="#0b6cad" />
                                    <input type="color" value="<?php echo esc_attr( (string) ($ui['btn_bg'] ?? '#0b6cad') ); ?>" oninput="document.getElementById('tpw_ui_btn_bg').value=this.value" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tpw_ui_btn_text"><?php esc_html_e('Button text colour', 'tpw-core'); ?></label></th>
                                <td>
                                    <input type="text" class="regular-text" id="tpw_ui_btn_text" name="tpw_ui_btn_text" value="<?php echo esc_attr( (string) ($ui['btn_text'] ?? '#ffffff') ); ?>" placeholder="#ffffff" />
                                    <input type="color" value="<?php echo esc_attr( (string) ($ui['btn_text'] ?? '#ffffff') ); ?>" oninput="document.getElementById('tpw_ui_btn_text').value=this.value" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tpw_ui_accent"><?php esc_html_e('Accent colour', 'tpw-core'); ?></label></th>
                                <td>
                                    <input type="text" class="regular-text" id="tpw_ui_accent" name="tpw_ui_accent" value="<?php echo esc_attr( (string) ($ui['accent_color'] ?? '#2271b1') ); ?>" placeholder="#2271b1" />
                                    <input type="color" value="<?php echo esc_attr( (string) ($ui['accent_color'] ?? '#2271b1') ); ?>" oninput="document.getElementById('tpw_ui_accent').value=this.value" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php submit_button( __( 'Save UI Theme', 'tpw-core' ) ); ?>
                </form>
            <?php elseif ( 'features' === $current_tab ) : ?>
                <?php $tpw_core_builtin_tab_rendered = true; ?>
                <?php if ( function_exists( 'tpw_core_render_features_tab' ) ) { tpw_core_render_features_tab(); } ?>
            <?php elseif ( 'member-menu' === $current_tab ) : ?>
                <?php $tpw_core_builtin_tab_rendered = true; ?>
                <?php if ( function_exists( 'tpw_core_render_member_menu_tab' ) ) { tpw_core_render_member_menu_tab(); } ?>
            <?php elseif ( 'system-pages' === $current_tab ) : ?>
                <?php $tpw_core_builtin_tab_rendered = true; ?>
                <?php if ( function_exists( 'tpw_core_render_system_pages_tab' ) ) { tpw_core_render_system_pages_tab(); } ?>
            <?php endif; ?>

            <?php
            // Extensible tab content mechanism: allow modules/add-ons to render tab content.
            ob_start();
            do_action( 'tpw_core_settings_tab_content', $current_tab );
            do_action( "tpw_core_settings_tab_content_{$current_tab}", $current_tab );
            $tpw_core_hooked_tab_output = (string) ob_get_clean();

            if ( trim( $tpw_core_hooked_tab_output ) !== '' ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tab content is responsible for escaping.
                echo $tpw_core_hooked_tab_output;
            } elseif ( ! $tpw_core_builtin_tab_rendered ) {
                echo '<p>' . esc_html__( 'No content registered for this tab.', 'tpw-core' ) . '</p>';
            }
            ?>
        </div></div>
        <?php
    }
}

// Render Features tab: Login redirect target page
if ( ! function_exists( 'tpw_core_render_features_tab' ) ) {
    /**
     * Render Features tab content (login target and redirect pages).
     *
     * @since 1.0.0
     * @return void
     */
    function tpw_core_render_features_tab() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $selected_redirect = (int) get_option( 'tpw_login_redirect_page_id', 0 );
        $selected_login    = (int) get_option( 'tpw_core_default_login_page', 0 );
        $action = esc_url( admin_url( 'admin-post.php' ) );
        ?>
        <form method="post" action="<?php echo $action; ?>">
            <?php wp_nonce_field( 'tpw_core_save_features', 'tpw_core_features_nonce' ); ?>
            <input type="hidden" name="action" value="tpw_core_save_features" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="tpw_core_default_login_page"><?php esc_html_e( 'Default Login Page', 'tpw-core' ); ?></label></th>
                        <td>
                            <?php
                            echo wp_dropdown_pages( [
                                'name'              => 'tpw_core_default_login_page',
                                'id'                => 'tpw_core_default_login_page',
                                'selected'          => $selected_login,
                                'show_option_none'  => '— Use TPW default —',
                                'option_none_value' => '0',
                                'echo'              => 0,
                                'post_status'       => 'publish',
                            ] ) ?: '<em>' . esc_html__( 'No published pages found.', 'tpw-core' ) . '</em>';
                            ?>
                            <p class="description"><?php esc_html_e( 'Select the page members should be redirected to when login is required. Used by FlexiEvent and other TPW modules.', 'tpw-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_login_redirect_page_id"><?php esc_html_e( 'Redirect After Login', 'tpw-core' ); ?></label></th>
                        <td>
                            <?php
                            echo wp_dropdown_pages( [
                                'name'              => 'tpw_login_redirect_page_id',
                                'id'                => 'tpw_login_redirect_page_id',
                                'selected'          => $selected_redirect,
                                'show_option_none'  => '— No redirect (default) —',
                                'option_none_value' => '0',
                                'echo'              => 0,
                                'post_status'       => 'publish',
                            ] ) ?: '<em>' . esc_html__( 'No published pages found.', 'tpw-core' ) . '</em>';
                            ?>
                            <p class="description"><?php esc_html_e( 'Choose a page to send members to immediately after they log in. Leave as “No redirect” to go to the site home.', 'tpw-core' ); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button( __( 'Save Features', 'tpw-core' ) ); ?>
        </form>
        <?php
    }
}

// Member Menu tab renderer (previously used generic settings API form)
if ( ! function_exists( 'tpw_core_render_member_menu_tab' ) ) {
    function tpw_core_render_member_menu_tab() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $selected = get_option( 'tpw_member_menu_location', 'primary' );
        $locations = function_exists( 'get_registered_nav_menus' ) ? get_registered_nav_menus() : [];
        $action = esc_url( admin_url( 'admin-post.php' ) );
        ?>
        <form method="post" action="<?php echo $action; ?>">
            <?php wp_nonce_field( 'tpw_core_save_member_menu', 'tpw_core_member_menu_nonce' ); ?>
            <input type="hidden" name="action" value="tpw_core_save_member_menu" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="tpw_member_menu_location"><?php esc_html_e( 'Replace Which Menu Location for Members?', 'tpw-core' ); ?></label></th>
                        <td>
                            <select name="tpw_member_menu_location" id="tpw_member_menu_location">
                                <?php foreach ( $locations as $key => $label ) : ?>
                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Logged-in users will see the TPW Member Menu at this location if a menu is assigned to it.', 'tpw-core' ); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button( __( 'Save Member Menu', 'tpw-core' ) ); ?>
        </form>
        <?php
    }
}

// System Pages tab renderer
if ( ! function_exists( 'tpw_core_render_system_pages_tab' ) ) {
    /**
     * Render System Pages registry UI.
     *
     * @since 1.0.0
     * @return void
     */
    function tpw_core_render_system_pages_tab() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        // Load template
        $tpl = defined('TPW_CORE_PATH') ? TPW_CORE_PATH . 'modules/system-pages/templates/system-pages.php' : '';
        if ( $tpl && file_exists( $tpl ) ) {
            include $tpl;
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'System Pages template not found.', 'tpw-core' ) . '</p></div>';
        }
    }
}

// Render Email Settings tab content
if ( ! function_exists( 'tpw_core_render_email_settings_tab' ) ) {
    /**
     * Render Email settings tab content.
     *
     * @since 1.0.1 Exposed additional branding title and base64 embedding toggles.
     * @return void
     */
    function tpw_core_render_email_settings_tab() {
        if ( ! class_exists( 'TPW_Core_Email_Settings' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Email settings class not found. Please ensure TPW Core is fully updated.', 'tpw-core' ) . '</p></div>';
            return;
        }
        $s = TPW_Core_Email_Settings::get();
        $brand_title = get_option('tpw_brand_title', get_bloginfo('name'));
        $action = esc_url( admin_url( 'admin-post.php' ) );
        ?>
        <form method="post" action="<?php echo $action; ?>">
            <?php wp_nonce_field( 'tpw_core_save_email_settings', 'tpw_core_email_nonce' ); ?>
            <input type="hidden" name="action" value="tpw_core_save_email_settings" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="tpw_brand_title">Brand Title</label></th>
                        <td>
                            <input type="text" class="regular-text" id="tpw_brand_title" name="brand_title" value="<?php echo esc_attr( $brand_title ); ?>" placeholder="<?php echo esc_attr( get_bloginfo('name') ); ?>" />
                            <p class="description"><?php esc_html_e( 'Displayed in email headers. Leave blank to use your Site Title.', 'tpw-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Throttling', 'tpw-core' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_throttling" value="1" <?php checked( ! empty( $s['enable_throttling'] ) ); ?> />
                                <?php esc_html_e( 'Limit the rate of outgoing emails', 'tpw-core' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Max Emails Per Minute', 'tpw-core' ); ?></th>
                        <td>
                            <input type="number" class="small-text" name="max_emails_per_minute" min="1" value="<?php echo esc_attr( (int) $s['max_emails_per_minute'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Delay Between Emails (sec)', 'tpw-core' ); ?></th>
                        <td>
                            <input type="number" class="small-text" name="delay_between_emails" min="0" value="<?php echo esc_attr( (int) $s['delay_between_emails'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Logging', 'tpw-core' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_logging" value="1" <?php checked( ! empty( $s['enable_logging'] ) ); ?> />
                                <?php esc_html_e( 'Record email activity (via hooks)', 'tpw-core' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Test Mode', 'tpw-core' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="send_test_mode" value="1" <?php checked( ! empty( $s['send_test_mode'] ) ); ?> />
                                <?php esc_html_e( 'Send all emails to a single test address', 'tpw-core' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Test Email Recipient', 'tpw-core' ); ?></th>
                        <td>
                            <input type="email" class="regular-text" name="test_mode_recipient" value="<?php echo esc_attr( (string) $s['test_mode_recipient'] ); ?>" />
                            <p class="description"><?php esc_html_e( 'Used only when Test Mode is enabled.', 'tpw-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Fallback Logo URL', 'tpw-core' ); ?></th>
                        <td>
                            <input type="text" class="regular-text code" id="tpw-fallback-logo-url" name="fallback_logo_url" value="<?php echo esc_attr( (string) ( $s['fallback_logo_url'] ?: $s['default_logo_url'] ) ); ?>" />
                            <button type="button" class="button" id="tpw-select-logo"><?php esc_html_e('Select Image','tpw-core'); ?></button>
                            <button type="button" class="button" id="tpw-clear-logo"><?php esc_html_e('Clear','tpw-core'); ?></button>
                            <p class="description"><?php esc_html_e( 'Optional fallback logo displayed in email templates. PNG/JPG only for base64 copy.', 'tpw-core' ); ?></p>
                            <?php if ( ! empty( $s['fallback_logo_url'] ) || ! empty( $s['default_logo_url'] ) ) : ?>
                                <div style="margin-top:8px;">
                                    <strong><?php esc_html_e('Current Logo Preview','tpw-core'); ?>:</strong><br/>
                                    <img src="<?php echo esc_url( $s['fallback_logo_url'] ?: $s['default_logo_url'] ); ?>" alt="" style="max-height:60px; background:#fff; padding:4px; border:1px solid #eee;" />
                                </div>
                            <?php endif; ?>
                            <div style="margin-top:8px;">
                                <label>
                                    <input type="checkbox" name="embed_logo_base64" value="1" <?php checked( ! empty( $s['embed_logo_base64'] ) ); ?> />
                                    <?php esc_html_e('Embed Logo as Inline Image (base64)','tpw-core'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Use this if you want to embed the logo directly in the email for clients that block remote images. Not recommended for large images.','tpw-core'); ?></p>
                                <?php if ( ! empty( $s['fallback_logo_base64'] ) ) : ?>
                                    <p class="description" style="color:#2271b1;">
                                        <?php esc_html_e('Base64 copy is stored.','tpw-core'); ?>
                                        <button type="submit" name="reset_logo_base64" value="1" class="button-link">(<?php esc_html_e('Reset','tpw-core'); ?>)</button>
                                    </p>
                                <?php else: ?>
                                    <p class="description"><?php esc_html_e('No base64 copy stored yet. It will be created automatically when you select a compatible image (PNG/JPG, ≤50KB, ≤400px wide).','tpw-core'); ?></p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button( __( 'Save Email Settings', 'tpw-core' ) ); ?>
        </form>
        <script>
        (function(){
            var mediaFrame;
            function openPicker(){
                if (mediaFrame){ mediaFrame.open(); return; }
                if (!window.wp || !wp.media) return;
                mediaFrame = wp.media({
                    title: 'Select Logo',
                    button: { text: 'Use this image' },
                    multiple: false
                });
                mediaFrame.on('select', function(){
                    var a = mediaFrame.state().get('selection').first();
                    if (!a) return;
                    var url = a.get('url');
                    var field = document.getElementById('tpw-fallback-logo-url');
                    if (field) field.value = url;
                });
                mediaFrame.open();
            }
            var btn = document.getElementById('tpw-select-logo');
            if (btn) btn.addEventListener('click', openPicker);
            var clr = document.getElementById('tpw-clear-logo');
            if (clr) clr.addEventListener('click', function(){
                var field = document.getElementById('tpw-fallback-logo-url');
                if (field) field.value = '';
            });
        })();
        </script>
        <?php
    }
}

// Render Email Templates tab
if ( ! function_exists( 'tpw_core_render_email_templates_tab' ) ) {
    function tpw_core_render_email_templates_tab() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! class_exists('TPW_Email_Template_Registry') ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Email Template Registry not loaded.', 'tpw-core' ) . '</p></div>';
            return;
        }

    $editing = isset($_GET['edit_template']) ? strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $_GET['edit_template'] ) ) : '';
        $base_url = admin_url( 'options-general.php?page=tpw-core-settings&tab=email-templates' );

        if ( $editing ) {
            $tpl = TPW_Email_Template_Registry::get( $editing );
            if ( ! $tpl ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Unknown template key.', 'tpw-core' ) . '</p></div>';
                return;
            }

            // Load overrides
            $ov = class_exists('TPW_Email_Templates_DB') ? TPW_Email_Templates_DB::get_override( $editing ) : null;
            $subject_val = $tpl['default_subject'];
            $body_val    = $tpl['default_body'];
            $use_logo    = true;
            if ( $ov ) {
                if ( $tpl['editable_subject'] && isset($ov['subject_override']) && $ov['subject_override'] !== '' ) {
                    $subject_val = (string) $ov['subject_override'];
                }
                if ( $tpl['editable_body'] && isset($ov['body_override']) && $ov['body_override'] !== '' ) {
                    $body_val = (string) $ov['body_override'];
                }
                if ( isset($ov['use_logo']) ) {
                    $use_logo = (bool) $ov['use_logo'];
                }
            }

            $action = esc_url( admin_url( 'admin-post.php' ) );
            echo '<h2>' . esc_html( $tpl['label'] ) . ' <small style="font-weight:normal">(' . esc_html( $tpl['group'] ) . ')</small></h2>';
            echo '<p><a class="button" href="' . esc_url( $base_url ) . '">← ' . esc_html__( 'Back to list', 'tpw-core' ) . '</a></p>';

            echo '<form method="post" action="' . $action . '">';
            wp_nonce_field( 'tpw_save_email_template', 'tpw_email_tmpl_nonce' );
            echo '<input type="hidden" name="action" value="tpw_core_save_email_template" />';
            echo '<input type="hidden" name="template_key" value="' . esc_attr( $tpl['key'] ) . '" />';

            echo '<table class="form-table" role="presentation"><tbody>';
            echo '<tr><th scope="row">' . esc_html__( 'Template', 'tpw-core' ) . '</th><td>' . esc_html( $tpl['label'] ) . ' <code>(' . esc_html( $tpl['key'] ) . ')</code></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Group', 'tpw-core' ) . '</th><td>' . esc_html( $tpl['group'] ) . '</td></tr>';
            if ( $tpl['editable_subject'] ) {
                echo '<tr><th scope="row">' . esc_html__( 'Subject', 'tpw-core' ) . '</th><td>';
                echo '<input type="text" class="regular-text" name="subject_override" value="' . esc_attr( $subject_val ) . '" />';
                echo '<p class="description">' . esc_html__( 'Use placeholders below in the subject.', 'tpw-core' ) . '</p>';
                echo '</td></tr>';
            } else {
                echo '<tr><th scope="row">' . esc_html__( 'Subject', 'tpw-core' ) . '</th><td><code>' . esc_html( $subject_val ) . '</code><p class="description">' . esc_html__( 'Subject is not editable for this template.', 'tpw-core' ) . '</p></td></tr>';
            }
            if ( $tpl['editable_body'] ) {
                echo '<tr><th scope="row">' . esc_html__( 'Body', 'tpw-core' ) . '</th><td>';
                // Use wp_editor for TinyMCE
                ob_start();
                wp_editor( $body_val, 'tpw_email_template_body', [ 'textarea_name' => 'body_override', 'textarea_rows' => 12 ] );
                $editor = ob_get_clean();
                echo $editor;
                echo '<p class="description">' . esc_html__( 'You can use the placeholders below in the body.', 'tpw-core' ) . '</p>';
                echo '</td></tr>';
            } else {
                echo '<tr><th scope="row">' . esc_html__( 'Body', 'tpw-core' ) . '</th><td><div class="inside" style="background:#fff;border:1px solid #ccd0d4;padding:10px;max-width:780px;overflow:auto">' . wp_kses_post( $body_val ) . '</div><p class="description">' . esc_html__( 'Body is not editable for this template.', 'tpw-core' ) . '</p></td></tr>';
            }

            echo '<tr><th scope="row">' . esc_html__( 'Include fallback logo', 'tpw-core' ) . '</th><td>';
            echo '<label><input type="checkbox" name="use_logo" value="1" ' . checked( $use_logo, true, false ) . ' /> ' . esc_html__( 'Show the configured fallback logo at the top of this email', 'tpw-core' ) . '</label>';
            echo '</td></tr>';

            // Placeholders list
            echo '<tr><th scope="row">' . esc_html__( 'Available placeholders', 'tpw-core' ) . '</th><td>';
            if ( ! empty( $tpl['placeholders'] ) && is_array( $tpl['placeholders'] ) ) {
                echo '<ul style="margin:0;">';
                foreach ( $tpl['placeholders'] as $token => $desc ) {
                    echo '<li><code>' . esc_html( $token ) . '</code> — ' . esc_html( $desc ) . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<em>' . esc_html__( 'No placeholders defined for this template.', 'tpw-core' ) . '</em>';
            }
            echo '</td></tr>';

            echo '</tbody></table>';
            submit_button( __( 'Save Template', 'tpw-core' ) );
            echo ' <a class="button button-secondary" href="' . esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'tpw_core_reset_email_template', 'template_key' => $tpl['key'] ], admin_url( 'admin-post.php' ) ), 'tpw_reset_email_template', 'tpw_email_tmpl_nonce' ) ) . '">' . esc_html__( 'Reset to Default', 'tpw-core' ) . '</a>';
            echo '</form>';

            return;
        }

        // List view
        $grouped = TPW_Email_Template_Registry::all_grouped();
        echo '<p>' . esc_html__( 'Registered email templates from TPW plugins. Edit to override the default subject/body and choose whether to include the fallback logo.', 'tpw-core' ) . '</p>';
        if ( empty( $grouped ) ) {
            echo '<p><em>' . esc_html__( 'No templates registered yet.', 'tpw-core' ) . '</em></p>';
            return;
        }
        echo '<div class="tpw-email-templates-list">';
        foreach ( $grouped as $group => $templates ) {
            echo '<h2 style="margin-top:1.5em">' . esc_html( ucfirst( $group ) ) . '</h2>';
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>' . esc_html__( 'Label', 'tpw-core' ) . '</th>';
            echo '<th>' . esc_html__( 'Key', 'tpw-core' ) . '</th>';
            echo '<th>' . esc_html__( 'Actions', 'tpw-core' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $templates as $t ) {
                $edit_url = esc_url( add_query_arg( [ 'edit_template' => $t['key'] ], $base_url ) );
                echo '<tr>';
                echo '<td>' . esc_html( $t['label'] ) . '</td>';
                echo '<td><code>' . esc_html( $t['key'] ) . '</code></td>';
                echo '<td><a class="button" href="' . $edit_url . '">' . esc_html__( 'Edit', 'tpw-core' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}

// Handle Email Template save
add_action( 'admin_post_tpw_core_save_email_template', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Permission denied', 'tpw-core' ) );
    check_admin_referer( 'tpw_save_email_template', 'tpw_email_tmpl_nonce' );

    $key = isset($_POST['template_key']) ? strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $_POST['template_key'] ) ) : '';
    $tpl = class_exists('TPW_Email_Template_Registry') ? TPW_Email_Template_Registry::get( $key ) : null;
    if ( ! $key || ! $tpl ) {
        wp_safe_redirect( add_query_arg( [ 'page' => 'tpw-core-settings', 'tab' => 'email-templates' ], admin_url('options-general.php') ) );
        exit;
    }

    $subject = $tpl['editable_subject'] ? ( isset($_POST['subject_override']) ? wp_kses_post( wp_unslash( $_POST['subject_override'] ) ) : '' ) : '';
    // Body can be HTML; allow through with wp_kses_post
    $body    = $tpl['editable_body'] ? ( isset($_POST['body_override']) ? wp_kses_post( wp_unslash( $_POST['body_override'] ) ) : '' ) : '';
    $use_logo = isset($_POST['use_logo']) ? 1 : 0;

    if ( class_exists('TPW_Email_Templates_DB') ) {
        TPW_Email_Templates_DB::upsert_override( $key, $tpl['group'], $tpl['label'], $subject, $body, $use_logo );
        add_settings_error( 'tpw_email_templates', 'saved', __( 'Template saved.', 'tpw-core' ), 'updated' );
    }

    $errors = get_settings_errors();
    set_transient( 'settings_errors', $errors, 30 );
    $url = add_query_arg( [ 'page' => 'tpw-core-settings', 'tab' => 'email-templates', 'edit_template' => $key, 'settings-updated' => '1' ], admin_url( 'options-general.php' ) );
    wp_safe_redirect( $url );
    exit;
} );
// Helper: Build CSS variables override from Branding settings
if ( ! function_exists('tpw_core_build_branding_css') ) {
    function tpw_core_build_branding_css( $only_if_not_empty = false ) {
        $opt = get_option( 'tpw_core_branding', [] );
        if ( ! is_array($opt) ) { $opt = []; }
        $map = [
            'btn_primary'    => '--tpw-btn-primary',
            'btn_secondary'  => '--tpw-btn-secondary',
            'btn_danger'     => '--tpw-btn-danger',
            'btn_light'      => '--tpw-btn-light',
            'btn_dark'       => '--tpw-btn-dark',
            'btn_text_light' => '--tpw-btn-text-light',
            'btn_text_dark'  => '--tpw-btn-text-dark',
            // Action colours
            'action_edit'    => '--tpw-action-edit',
            // Semantic notice palette (success/info/warning/error)
            'color_success'  => '--tpw-color-success',
            'color_info'     => '--tpw-color-info',
            'color_warning'  => '--tpw-color-warning',
            'color_error'    => '--tpw-color-error',
            'btn_radius'     => '--tpw-btn-radius',
            'btn_padding'    => '--tpw-btn-padding',
            'btn_font_size'  => '--tpw-btn-font-size',
            'btn_font_family'=> '--tpw-btn-font-family',
            'btn_font_weight'=> '--tpw-btn-font-weight',
            'btn_height'     => '--tpw-btn-height',
        ];
        $lines = [];
        foreach ( $map as $key => $var ) {
            if ( ! array_key_exists( $key, $opt ) ) { continue; }
            $val = trim( (string) $opt[ $key ] );
            if ( $only_if_not_empty && $val === '' ) { continue; }
            if ( $val === '' ) { continue; }
            $lines[] = $var . ': ' . $val . ';';
        }
        // Also include UI Theme typography tokens globally so front-end .tpw-btn can inherit them
        $ui = get_option( 'tpw_ui_theme_settings', [] );
        if ( is_array( $ui ) && ! empty( $ui ) ) {
            $ui_map = [
                'font_family'    => '--tpw-font-family',
                'font_weight'    => '--tpw-font-weight',
                'text_transform' => '--tpw-text-transform',
                'letter_spacing' => '--tpw-letter-spacing',
                'text_shadow'    => '--tpw-text-shadow',
            ];
            foreach ( $ui_map as $key => $var ) {
                if ( ! array_key_exists( $key, $ui ) ) { continue; }
                $val = trim( (string) $ui[ $key ] );
                if ( $only_if_not_empty && $val === '' ) { continue; }
                if ( $val === '' ) { continue; }
                $lines[] = $var . ': ' . $val . ';';
            }
        }
        if ( empty($lines) ) { return ''; }
        return ":root{\n" . implode("\n", $lines) . "\n}";
    }
}

// Output CSS variables in admin and front-end heads
add_action( 'admin_head', function(){
    $css = tpw_core_build_branding_css( true );
    if ( $css ) { echo '<style id="tpw-core-branding-vars">' . $css . '</style>'; }
});
add_action( 'wp_head', function(){
    $css = tpw_core_build_branding_css( true );
    if ( $css ) { echo '<style id="tpw-core-branding-vars">' . $css . '</style>'; }
});

// Heading typography tokens: defaults, builder, and output
if ( ! function_exists( 'tpw_core_get_heading_defaults' ) ) {
    function tpw_core_get_heading_defaults() {
        // Defaults align with tpw-admin-ui.css fallbacks
        return [
            'font_family' => '', // inherit UI font when empty
            'h1' => [ 'color' => '', 'size' => '1.75rem', 'weight' => '700' ],
            'h2' => [ 'color' => '', 'size' => '1.5rem',  'weight' => '600' ],
            'h3' => [ 'color' => '', 'size' => '1.25rem', 'weight' => '600' ],
            'h4' => [ 'color' => '', 'size' => '1.125rem','weight' => '600' ],
            'h5' => [ 'color' => '', 'size' => '1rem',    'weight' => '600' ],
            'h6' => [ 'color' => '', 'size' => '0.875rem','weight' => '600' ],
        ];
    }
}

if ( ! function_exists( 'tpw_core_build_heading_css' ) ) {
    /**
     * Build CSS custom properties for heading typography.
     * When $only_if_not_empty is true, only outputs vars that have non-empty values (so CSS fallbacks apply).
     */
    function tpw_core_build_heading_css( $only_if_not_empty = true ) {
        $opt = get_option( 'tpw_heading_styles', [] );
        if ( ! is_array( $opt ) ) { $opt = []; }
        $defaults = tpw_core_get_heading_defaults();
        $val = wp_parse_args( $opt, $defaults );

        $lines = [];
        // Global heading font family
        $ff = isset( $val['font_family'] ) ? trim( (string) $val['font_family'] ) : '';
        if ( $ff !== '' || ! $only_if_not_empty ) {
            if ( $ff !== '' ) { $lines[] = '--tpw-heading-font: ' . $ff . ';'; }
        }
        // Per level: h1..h6
        for ( $i = 1; $i <= 6; $i++ ) {
            $k = 'h' . $i;
            $row = isset( $val[$k] ) && is_array( $val[$k] ) ? $val[$k] : [];
            $def = $defaults[$k];
            $color = isset($row['color']) ? trim((string)$row['color']) : '';
            $size  = isset($row['size'])  ? trim((string)$row['size'])  : '';
            $weight= isset($row['weight'])? trim((string)$row['weight']): '';
            if ( $color !== '' || ! $only_if_not_empty ) {
                if ( $color !== '' ) { $lines[] = '--tpw-h' . $i . '-color: ' . $color . ';'; }
            }
            if ( $size !== '' || ! $only_if_not_empty ) {
                $lines[] = '--tpw-h' . $i . '-size: ' . ( $size !== '' ? $size : $def['size'] ) . ';';
            }
            if ( $weight !== '' || ! $only_if_not_empty ) {
                $lines[] = '--tpw-h' . $i . '-weight: ' . ( $weight !== '' ? $weight : $def['weight'] ) . ';';
            }
        }
        if ( empty($lines) ) { return ''; }
        // Apply within both admin and (future) frontend UI scopes, and :root so header styles can consume tokens
        $vars = implode("\n", $lines);
        return ".tpw-admin-ui,\n.tpw-frontend-ui,\n:root{\n$vars\n}";
    }
}

// Output heading CSS variables in admin and front-end heads
add_action( 'admin_head', function(){
    $css = tpw_core_build_heading_css( true );
    if ( $css ) { echo '<style id="tpw-core-heading-vars">' . $css . '</style>'; }
});
add_action( 'wp_head', function(){
    $css = tpw_core_build_heading_css( true );
    if ( $css ) { echo '<style id="tpw-core-heading-vars">' . $css . '</style>'; }
});

// Render Branding tab
if ( ! function_exists( 'tpw_core_render_branding_tab' ) ) {
    function tpw_core_render_branding_tab() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Defaults mirror tpw-buttons.css
        $defaults = [
            'btn_primary'    => '#0b6cad',
            'btn_secondary'  => '#6c757d',
            'btn_danger'     => '#dc3545',
            'btn_light'      => '#f8f9fa',
            'btn_dark'       => '#343a40',
            'btn_text_light' => '#ffffff',
            'btn_text_dark'  => '#000000',
            // Action buttons (module UIs)
            'action_edit'    => '#2d7ff9',
            // Semantic notice colours (admin may override). Success derived via color-mix using primary by default.
            'color_success'  => 'color-mix(in oklab, var(--tpw-btn-primary) 60%, white 40%)',
            'color_info'     => 'var(--tpw-accent-color)',
            'color_warning'  => '#ed6c02',
            'color_error'    => 'var(--tpw-btn-danger)',
            'btn_radius'     => '7px',
            'btn_padding'    => '4px 8px',
            'btn_font_size'  => '0.9rem',
            'btn_font_family'=> '',
            'btn_font_weight'=> '600',
            'btn_height'     => '',
        ];
        $opt = get_option( 'tpw_core_branding', [] );
        if ( ! is_array($opt) ) { $opt = []; }
        $val = wp_parse_args( $opt, $defaults );

        // Ensure buttons CSS is available for preview
        $css_file = defined('TPW_CORE_PATH') ? TPW_CORE_PATH . 'assets/css/tpw-buttons.css' : '';
        $ver = $css_file && file_exists($css_file) ? filemtime($css_file) : ( defined('TPW_CORE_VERSION') ? TPW_CORE_VERSION : '1.0' );
        if ( defined('TPW_CORE_URL') ) {
            wp_enqueue_style( 'tpw-buttons', TPW_CORE_URL . 'assets/css/tpw-buttons.css', [], $ver );
            $inline = tpw_core_build_branding_css( true );
            if ( $inline ) { wp_add_inline_style( 'tpw-buttons', $inline ); }
        }

        $action = esc_url( admin_url( 'admin-post.php' ) );
        // Read UI Theme settings for prefill
        $ui_defaults = function_exists('tpw_core_get_ui_theme_defaults') ? tpw_core_get_ui_theme_defaults() : [];
        $ui = function_exists('tpw_core_get_ui_theme_settings') ? tpw_core_get_ui_theme_settings(true) : $ui_defaults;
        ?>
    <form method="post" action="<?php echo $action; ?>" class="tpw-branding-form">
            <?php wp_nonce_field( 'tpw_core_save_branding', 'tpw_core_branding_nonce' ); ?>
            <input type="hidden" name="action" value="tpw_core_save_branding" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr><th colspan="2"><h2 style="margin:6px 0;">UI Theme (applies to .tpw-admin-ui)</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="tpw_ui_font_family"><?php esc_html_e('Font family', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="tpw_ui_font_family" name="tpw_ui_font_family" value="<?php echo esc_attr( (string) ($ui['font_family'] ?? '') ); ?>" placeholder="system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial" />
                            <p class="description"><?php esc_html_e('Applies within .tpw-admin-ui only.', 'tpw-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_ui_font_weight"><?php esc_html_e('Font weight', 'tpw-core'); ?></label></th>
                        <td>
                            <?php $fw = (string) ($ui['font_weight'] ?? '600'); ?>
                            <select id="tpw_ui_font_weight" name="tpw_ui_font_weight">
                                <?php foreach ( ['normal','500','600','700'] as $opt ): ?>
                                    <option value="<?php echo esc_attr($opt); ?>" <?php selected( $fw, $opt ); ?>><?php echo esc_html($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_ui_text_transform"><?php esc_html_e('Text transform', 'tpw-core'); ?></label></th>
                        <td>
                            <?php $tt = (string) ($ui['text_transform'] ?? 'none'); ?>
                            <select id="tpw_ui_text_transform" name="tpw_ui_text_transform">
                                <?php foreach ( ['none','uppercase','lowercase','capitalize'] as $opt ): ?>
                                    <option value="<?php echo esc_attr($opt); ?>" <?php selected( $tt, $opt ); ?>><?php echo esc_html( ucfirst($opt) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_ui_letter_spacing"><?php esc_html_e('Letter spacing', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="tpw_ui_letter_spacing" name="tpw_ui_letter_spacing" value="<?php echo esc_attr( (string) ($ui['letter_spacing'] ?? 'normal') ); ?>" placeholder="normal | 0.03em | 1px" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_ui_text_shadow"><?php esc_html_e('Text shadow', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="tpw_ui_text_shadow" name="tpw_ui_text_shadow" value="<?php echo esc_attr( (string) ($ui['text_shadow'] ?? 'none') ); ?>" placeholder="none | 0 0 0 rgba(0,0,0,0.3)" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_ui_btn_bg"><?php esc_html_e('Button background colour', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="tpw_ui_btn_bg" name="tpw_ui_btn_bg" value="<?php echo esc_attr( (string) ($ui['btn_bg'] ?? '#0b6cad') ); ?>" placeholder="#0b6cad" />
                            <input type="color" value="<?php echo esc_attr( (string) ($ui['btn_bg'] ?? '#0b6cad') ); ?>" oninput="document.getElementById('tpw_ui_btn_bg').value=this.value" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_ui_btn_text"><?php esc_html_e('Button text colour', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="tpw_ui_btn_text" name="tpw_ui_btn_text" value="<?php echo esc_attr( (string) ($ui['btn_text'] ?? '#ffffff') ); ?>" placeholder="#ffffff" />
                            <input type="color" value="<?php echo esc_attr( (string) ($ui['btn_text'] ?? '#ffffff') ); ?>" oninput="document.getElementById('tpw_ui_btn_text').value=this.value" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_ui_accent"><?php esc_html_e('Accent colour', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="tpw_ui_accent" name="tpw_ui_accent" value="<?php echo esc_attr( (string) ($ui['accent_color'] ?? '#2271b1') ); ?>" placeholder="#2271b1" />
                            <input type="color" value="<?php echo esc_attr( (string) ($ui['accent_color'] ?? '#2271b1') ); ?>" oninput="document.getElementById('tpw_ui_accent').value=this.value" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpw_ui_inherit_global_frontend"><?php esc_html_e('Inherit global site styles on admin screens (front-end only)', 'tpw-core'); ?></label></th>
                        <td>
                            <?php $inherit_flag = ! empty( $ui['inherit_global_frontend'] ) ? 1 : 0; ?>
                            <label>
                                <input type="checkbox" id="tpw_ui_inherit_global_frontend" name="tpw_ui_inherit_global_frontend" value="1" <?php checked( 1, $inherit_flag ); ?> />
                                <?php esc_html_e('Do not apply TPW Admin UI wrapper/styles on front-end shortcodes. Useful when using Elementor/Theme global styles.', 'tpw-core'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Does not affect wp-admin. Only front-end admin-like pages (shortcodes) are affected.', 'tpw-core'); ?></p>
                        </td>
                    </tr>
                    <?php
                    // Headings section UI
                    $heading_defaults = function_exists('tpw_core_get_heading_defaults') ? tpw_core_get_heading_defaults() : [];
                    $heading_opt = get_option( 'tpw_heading_styles', [] );
                    if ( ! is_array( $heading_opt ) ) { $heading_opt = []; }
                    $heading = wp_parse_args( $heading_opt, $heading_defaults );
                    ?>
                    <tr><th colspan="2"><h2 style="margin:12px 0 6px;">Headings</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="tpw_heading_font_family"><?php esc_html_e('Heading font family', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="tpw_heading_font_family" name="tpw_heading_font_family" value="<?php echo esc_attr( (string) ($heading['font_family'] ?? '') ); ?>" placeholder='inherit UI font (leave empty) or e.g. "Inter", system-ui' />
                            <p class="description"><?php esc_html_e('Leave empty to inherit the UI Theme font. Applies to h1–h6 within TPW UIs.', 'tpw-core'); ?></p>
                        </td>
                    </tr>
                    <?php for ( $i = 1; $i <= 6; $i++ ):
                        $hk = 'h' . $i; $row = isset($heading[$hk]) && is_array($heading[$hk]) ? $heading[$hk] : ['color'=>'','size'=>'','weight'=>''];
                        $color = (string) ($row['color'] ?? '');
                        $size  = (string) ($row['size'] ?? '');
                        $weight= (string) ($row['weight'] ?? '');
                    ?>
                    <tr>
                        <th scope="row"><label for="<?php echo 'h' . $i . '_color'; ?>"><?php echo esc_html( strtoupper($hk) ); ?> <?php esc_html_e('colour / size / weight', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="text" class="small-text" id="<?php echo 'h' . $i . '_color'; ?>" name="<?php echo 'h' . $i . '_color'; ?>" value="<?php echo esc_attr( $color ); ?>" placeholder="e.g. #1d2327 or var(--brand)" />
                            <input type="color" value="<?php echo esc_attr( preg_match('/^var\(/', $color) ? '#000000' : ( $color !== '' ? $color : '#000000' ) ); ?>" oninput="document.getElementById('<?php echo 'h' . $i . '_color'; ?>').value=this.value" />
                            <input type="text" class="small-text" id="<?php echo 'h' . $i . '_size'; ?>" name="<?php echo 'h' . $i . '_size'; ?>" value="<?php echo esc_attr( $size ); ?>" placeholder="e.g. <?php echo esc_attr( $heading_defaults[$hk]['size'] ); ?>" />
                            <?php $fw = $weight !== '' ? $weight : $heading_defaults[$hk]['weight']; ?>
                            <select id="<?php echo 'h' . $i . '_weight'; ?>" name="<?php echo 'h' . $i . '_weight'; ?>">
                                <?php foreach ( ['','normal','500','600','700'] as $wopt ): ?>
                                    <option value="<?php echo esc_attr($wopt); ?>" <?php selected( $fw, $wopt ); ?>><?php echo $wopt === '' ? esc_html__('Default','tpw-core') : esc_html($wopt); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description" style="margin-top:4px;">
                                <?php esc_html_e('Leave colour empty to inherit. Size accepts px, rem, or em. Weight: choose Default to use the built-in scale.', 'tpw-core'); ?>
                            </p>
                        </td>
                    </tr>
                    <?php endfor; ?>
                    <tr><th colspan="2"><h2 style="margin:6px 0;">Buttons</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="btn_primary">Primary</label></th>
                        <td><input type="text" class="regular-text" id="btn_primary" name="btn_primary" value="<?php echo esc_attr($val['btn_primary']); ?>" placeholder="#0b6cad" /> <input type="color" value="<?php echo esc_attr($val['btn_primary']); ?>" oninput="document.getElementById('btn_primary').value=this.value" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_secondary">Secondary</label></th>
                        <td><input type="text" class="regular-text" id="btn_secondary" name="btn_secondary" value="<?php echo esc_attr($val['btn_secondary']); ?>" placeholder="#6c757d" /> <input type="color" value="<?php echo esc_attr($val['btn_secondary']); ?>" oninput="document.getElementById('btn_secondary').value=this.value" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_danger">Danger</label></th>
                        <td><input type="text" class="regular-text" id="btn_danger" name="btn_danger" value="<?php echo esc_attr($val['btn_danger']); ?>" placeholder="#dc3545" /> <input type="color" value="<?php echo esc_attr($val['btn_danger']); ?>" oninput="document.getElementById('btn_danger').value=this.value" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_light">Light</label></th>
                        <td><input type="text" class="regular-text" id="btn_light" name="btn_light" value="<?php echo esc_attr($val['btn_light']); ?>" placeholder="#f8f9fa" /> <input type="color" value="<?php echo esc_attr($val['btn_light']); ?>" oninput="document.getElementById('btn_light').value=this.value" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_dark">Dark</label></th>
                        <td><input type="text" class="regular-text" id="btn_dark" name="btn_dark" value="<?php echo esc_attr($val['btn_dark']); ?>" placeholder="#343a40" /> <input type="color" value="<?php echo esc_attr($val['btn_dark']); ?>" oninput="document.getElementById('btn_dark').value=this.value" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_text_light">Text (light)</label></th>
                        <td><input type="text" class="regular-text" id="btn_text_light" name="btn_text_light" value="<?php echo esc_attr($val['btn_text_light']); ?>" placeholder="#ffffff" /> <input type="color" value="<?php echo esc_attr($val['btn_text_light']); ?>" oninput="document.getElementById('btn_text_light').value=this.value" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_text_dark">Text (dark)</label></th>
                        <td><input type="text" class="regular-text" id="btn_text_dark" name="btn_text_dark" value="<?php echo esc_attr($val['btn_text_dark']); ?>" placeholder="#000000" /> <input type="color" value="<?php echo esc_attr($val['btn_text_dark']); ?>" oninput="document.getElementById('btn_text_dark').value=this.value" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="action_edit">Action: Edit</label></th>
                        <td>
                            <input type="text" class="regular-text" id="action_edit" name="action_edit" value="<?php echo esc_attr($val['action_edit']); ?>" placeholder="#2d7ff9" />
                            <input type="color" value="<?php echo esc_attr($val['action_edit']); ?>" oninput="document.getElementById('action_edit').value=this.value" />
                            <p class="description"><?php esc_html_e('Used for .tpw-action-edit buttons in admin module lists.', 'tpw-core'); ?></p>
                        </td>
                    </tr>
                    <tr><th colspan="2"><h2 style="margin:12px 0 6px;">Semantic Notice Colours</h2></th></tr>
                    <tr>
                        <th scope="row"><label for="color_success">Success</label></th>
                        <td>
                            <input type="text" class="regular-text" id="color_success" name="color_success" value="<?php echo esc_attr($val['color_success']); ?>" placeholder="color-mix(in oklab, var(--tpw-btn-primary) 60%, white 40%)" />
                            <p class="description"><?php esc_html_e('Background/base colour for success states. Supports CSS functions (color-mix, var()).', 'tpw-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="color_info">Info</label></th>
                        <td>
                            <input type="text" class="regular-text" id="color_info" name="color_info" value="<?php echo esc_attr($val['color_info']); ?>" placeholder="var(--tpw-accent-color)" />
                            <p class="description"><?php esc_html_e('Base colour for informational notices. Typically uses the UI accent.', 'tpw-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="color_warning">Warning</label></th>
                        <td>
                            <input type="text" class="regular-text" id="color_warning" name="color_warning" value="<?php echo esc_attr($val['color_warning']); ?>" placeholder="#ed6c02" />
                            <input type="color" value="<?php echo esc_attr(preg_match('/^#/', $val['color_warning']) ? $val['color_warning'] : '#ed6c02'); ?>" oninput="document.getElementById('color_warning').value=this.value" />
                            <p class="description"><?php esc_html_e('High-attention but non-fatal states.', 'tpw-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="color_error">Error</label></th>
                        <td>
                            <input type="text" class="regular-text" id="color_error" name="color_error" value="<?php echo esc_attr($val['color_error']); ?>" placeholder="var(--tpw-btn-danger)" />
                            <p class="description"><?php esc_html_e('Critical / failure state colour, usually matches Danger button.', 'tpw-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_radius">Border radius</label></th>
                        <td><input type="text" class="regular-text" id="btn_radius" name="btn_radius" value="<?php echo esc_attr($val['btn_radius']); ?>" placeholder="7px" /> <span class="description">e.g., 7px, 0.5rem</span></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_padding">Padding</label></th>
                        <td><input type="text" class="regular-text" id="btn_padding" name="btn_padding" value="<?php echo esc_attr($val['btn_padding']); ?>" placeholder="4px 8px" /> <span class="description">CSS shorthand accepted</span></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_font_size">Font size</label></th>
                        <td><input type="text" class="regular-text" id="btn_font_size" name="btn_font_size" value="<?php echo esc_attr($val['btn_font_size']); ?>" placeholder="0.9rem" /> <span class="description">e.g., 14px, 0.9rem</span></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_height"><?php esc_html_e('Buttons Height', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="btn_height" name="btn_height" value="<?php echo esc_attr($val['btn_height']); ?>" placeholder="auto | 32px" />
                            <span class="description"><?php esc_html_e('Optional. Sets a fixed height for TPW buttons. Use a unit (e.g., 32px). Leave blank or set to “auto” to size by padding.', 'tpw-core'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_font_weight"><?php esc_html_e('Buttons Font Weight', 'tpw-core'); ?></label></th>
                        <td>
                            <?php $bfw = isset($val['btn_font_weight']) ? (string) $val['btn_font_weight'] : '600'; ?>
                            <select id="btn_font_weight" name="btn_font_weight">
                                <?php foreach (['normal','500','600','700'] as $opt): ?>
                                    <option value="<?php echo esc_attr($opt); ?>" <?php selected($bfw, $opt); ?>><?php echo esc_html($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="description"><?php esc_html_e('Overrides UI weight for TPW buttons only. Leave as 600 for the default.', 'tpw-core'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="btn_font_family"><?php esc_html_e('Buttons Font Family', 'tpw-core'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="btn_font_family" name="btn_font_family" value="<?php echo esc_attr($val['btn_font_family']); ?>" placeholder='"Inter", system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial' />
                            <span class="description"><?php esc_html_e('Optional. Overrides UI font for TPW buttons only. If empty, buttons use the UI Theme font or the system stack.', 'tpw-core'); ?></span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <h2 style="margin:16px 0 6px;">UI Theme</h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="tpw_ui_inherit_global_frontend"><?php esc_html_e('Inherit global site styles on admin screens (front-end only)', 'tpw-core'); ?></label></th>
                        <td>
                            <?php 
                            $ui_theme = get_option( 'tpw_ui_theme_settings', [] );
                            if ( ! is_array( $ui_theme ) ) { $ui_theme = []; }
                            $inherit_flag = ! empty( $ui_theme['inherit_global_frontend'] ) ? 1 : 0;
                            ?>
                            <label>
                                <input type="checkbox" id="tpw_ui_inherit_global_frontend" name="tpw_ui_inherit_global_frontend" value="1" <?php checked( 1, $inherit_flag ); ?> />
                                <?php esc_html_e('Do not apply TPW Admin UI wrapper/styles on front-end shortcodes. Useful when using Elementor/Theme global styles.', 'tpw-core'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('This does not affect wp-admin. It applies only to front-end admin-like pages powered by TPW shortcodes.', 'tpw-core'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button( __( 'Save Branding', 'tpw-core' ) ); ?>
            <button type="submit" name="tpw_branding_reset" value="1" class="button button-secondary" onclick="return confirm('Reset all branding values to defaults?');">Reset to Defaults</button>
        </form>

        <h2 style="margin-top:18px;">Preview</h2>
        <p>These reflect your current settings:</p>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <a href="#" class="tpw-btn tpw-btn-primary">Primary</a>
            <a href="#" class="tpw-btn tpw-btn-secondary">Secondary</a>
            <a href="#" class="tpw-btn tpw-btn-danger">Danger</a>
            <a href="#" class="tpw-btn tpw-btn-light">Light</a>
            <a href="#" class="tpw-btn tpw-btn-dark">Dark</a>
            <a href="#" class="tpw-btn tpw-btn-outline">Outline</a>
        </div>
        <?php
    }
}

// Save Branding handler
add_action( 'admin_post_tpw_core_save_branding', function(){
    if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Permission denied', 'tpw-core' ) );
    check_admin_referer( 'tpw_core_save_branding', 'tpw_core_branding_nonce' );

    $defaults = [
        'btn_primary'    => '#0b6cad',
        'btn_secondary'  => '#6c757d',
        'btn_danger'     => '#dc3545',
        'btn_light'      => '#f8f9fa',
        'btn_dark'       => '#343a40',
        'btn_text_light' => '#ffffff',
        'btn_text_dark'  => '#000000',
        // Action buttons (module UIs)
        'action_edit'    => '#2d7ff9',
        // Semantic notice colours (same defaults as form)
        'color_success'  => 'color-mix(in oklab, var(--tpw-btn-primary) 60%, white 40%)',
        'color_info'     => 'var(--tpw-accent-color)',
        'color_warning'  => '#ed6c02',
        'color_error'    => 'var(--tpw-btn-danger)',
        'btn_radius'     => '7px',
        'btn_padding'    => '4px 8px',
        'btn_font_size'  => '0.9rem',
        'btn_font_family'=> '',
        'btn_font_weight'=> '600',
        'btn_height'     => '',
    ];

    if ( isset($_POST['tpw_branding_reset']) && $_POST['tpw_branding_reset'] == '1' ) {
        update_option( 'tpw_core_branding', $defaults );
        add_settings_error( 'tpw_core_branding', 'tpw_branding_reset', __( 'Branding reset to defaults.', 'tpw-core' ), 'updated' );
        // Also reset heading styles to defaults
        if ( function_exists('tpw_core_get_heading_defaults') ) {
            update_option( 'tpw_heading_styles', tpw_core_get_heading_defaults() );
        }
    } else {
        $in = [];
    $fields = array_keys( $defaults );
        foreach ( $fields as $k ) { $in[$k] = isset($_POST[$k]) ? wp_unslash( (string) $_POST[$k] ) : ''; }

        // Sanitize colors
        foreach ( ['btn_primary','btn_secondary','btn_danger','btn_light','btn_dark','btn_text_light','btn_text_dark','action_edit'] as $ck ) {
            $v = trim( (string) $in[$ck] );
            $hex = sanitize_hex_color( $v );
            // allow #fff shorthand also
            if ( ! $hex && preg_match('/^#([0-9a-fA-F]{3})$/', $v) ) { $hex = $v; }
            $in[$ck] = $hex ?: $defaults[$ck];
        }
        // Warning is a hex by default; sanitize as color (supports override)
        if ( array_key_exists('color_warning', $in ) ) {
            $v = trim( (string) $in['color_warning'] );
            $hex = sanitize_hex_color( $v );
            if ( ! $hex && preg_match('/^#([0-9a-fA-F]{3})$/', $v) ) { $hex = $v; }
            $in['color_warning'] = $hex ?: $defaults['color_warning'];
        }
        // Preserve functional values for success/info/error when using CSS functions or var() references.
        foreach ( ['color_success','color_info','color_error'] as $fk ) {
            if ( array_key_exists( $fk, $in ) ) {
                $val = trim( (string) $in[$fk] );
                // If plain hex, sanitize; otherwise allow vetted patterns.
                $hex = sanitize_hex_color( $val );
                if ( $hex ) {
                    $in[$fk] = $hex;
                } else {
                    // Allow color-mix() and var(--tpw-*) forms; basic safety check.
                    if ( preg_match( '/^(color-mix\(|var\(--tpw-[a-z0-9-]+\))/', $val ) ) {
                        $in[$fk] = $val;
                    } else {
                        // Fallback to default if pattern not recognized.
                        $in[$fk] = $defaults[$fk];
                    }
                }
            }
        }
        // Sanitize sizes (allow px, rem, em, %)
        $sanitize_size = function($s, $fallback){
            $t = trim((string)$s);
            if ($t === '') return $fallback;
            if ( preg_match('/^\d+(?:\.\d+)?(px|rem|em|%)?$/', $t) ) {
                // If no unit and not zero, append px
                if ( ! preg_match('/[a-z%]$/i', $t) ) { $t .= 'px'; }
                return $t;
            }
            // Allow CSS shorthand for padding like "4px 8px"
            if ( strpos($fallback, ' ') !== false ) {
                $parts = preg_split('/\s+/', $t);
                $ok = true; foreach ($parts as $p){ if ($p==='') continue; if (!preg_match('/^\d+(?:\.\d+)?(px|rem|em|%)?$/',$p)){ $ok=false; break; } }
                if ($ok) return $t;
            }
            return $fallback;
        };
        $in['btn_radius']    = $sanitize_size( $in['btn_radius'], $defaults['btn_radius'] );
        $in['btn_padding']   = $sanitize_size( $in['btn_padding'], $defaults['btn_padding'] );
    $in['btn_font_size'] = $sanitize_size( $in['btn_font_size'], $defaults['btn_font_size'] );

    // Font family is free text; trim only (quotes allowed)
    $in['btn_font_family'] = trim( (string) $in['btn_font_family'] );

        // Font weight: allow normal or numeric tokens 100–900; default fallback
        $w = trim( (string ) ( $in['btn_font_weight'] ?? '' ) );
        if ( $w === '' ) {
            $in['btn_font_weight'] = $defaults['btn_font_weight'];
        } else {
            if ( $w === 'normal' || preg_match('/^(100|200|300|400|500|600|700|800|900)$/', $w) ) {
                $in['btn_font_weight'] = $w;
            } else {
                $in['btn_font_weight'] = $defaults['btn_font_weight'];
            }
        }

        // Button height: allow empty (means omit), or a size with unit; 'auto' allowed
        $h = trim( (string ) ( $in['btn_height'] ?? '' ) );
        if ( $h === '' || strtolower($h) === 'auto' ) {
            $in['btn_height'] = $h === '' ? '' : 'auto';
        } else {
            if ( preg_match('/^\d+(?:\.\d+)?(px|rem|em|%)$/', $h) ) {
                $in['btn_height'] = $h;
            } else {
                // invalid value, clear it so it won’t be output
                $in['btn_height'] = '';
            }
        }

        update_option( 'tpw_core_branding', $in );
        add_settings_error( 'tpw_core_branding', 'tpw_branding_saved', __( 'Branding saved.', 'tpw-core' ), 'updated' );

        // Also save the UI Theme fields and frontend inheritance toggle into tpw_ui_theme_settings
        $inherit_ui = isset($_POST['tpw_ui_inherit_global_frontend']) ? 1 : 0;
        $ui_theme = get_option( 'tpw_ui_theme_settings', [] );
        if ( ! is_array( $ui_theme ) ) { $ui_theme = []; }
        $ui_theme['inherit_global_frontend'] = $inherit_ui ? 1 : 0;
        // UI Theme fields
        $font  = isset($_POST['tpw_ui_font_family']) ? wp_unslash( (string) $_POST['tpw_ui_font_family'] ) : '';
    $fontw = isset($_POST['tpw_ui_font_weight']) ? preg_replace('/[^0-9a-zA-Z-]/', '', (string) $_POST['tpw_ui_font_weight'] ) : '';
    $ttrans= isset($_POST['tpw_ui_text_transform']) ? preg_replace('/[^a-z-]/', '', strtolower( (string) $_POST['tpw_ui_text_transform'] ) ) : '';
    $lsp   = isset($_POST['tpw_ui_letter_spacing']) ? wp_unslash( (string) $_POST['tpw_ui_letter_spacing'] ) : '';
    $tsh   = isset($_POST['tpw_ui_text_shadow']) ? wp_unslash( (string) $_POST['tpw_ui_text_shadow'] ) : '';
        $btnbg = isset($_POST['tpw_ui_btn_bg']) ? sanitize_hex_color( (string) $_POST['tpw_ui_btn_bg'] ) : '';
        $btntx = isset($_POST['tpw_ui_btn_text']) ? sanitize_hex_color( (string) $_POST['tpw_ui_btn_text'] ) : '';
        $acc   = isset($_POST['tpw_ui_accent']) ? sanitize_hex_color( (string) $_POST['tpw_ui_accent'] ) : '';
        if ( $font !== '' ) { $ui_theme['font_family'] = $font; }
    if ( $fontw !== '' ) { $ui_theme['font_weight'] = $fontw; }
    if ( in_array( $ttrans, ['none','uppercase','lowercase','capitalize'], true ) ) { $ui_theme['text_transform'] = $ttrans; }
    if ( $lsp !== '' ) { $ui_theme['letter_spacing'] = $lsp; }
    if ( $tsh !== '' ) { $ui_theme['text_shadow'] = $tsh; }
        if ( $btnbg ) { $ui_theme['btn_bg'] = $btnbg; }
        if ( $btntx ) { $ui_theme['btn_text'] = $btntx; }
        if ( $acc )   { $ui_theme['accent_color'] = $acc; }
        update_option( 'tpw_ui_theme_settings', $ui_theme );

        // Save Headings fields into tpw_heading_styles
        $h_defaults = function_exists('tpw_core_get_heading_defaults') ? tpw_core_get_heading_defaults() : [];
        $h_in = [];
        // Font family: free text
        $h_in['font_family'] = isset($_POST['tpw_heading_font_family']) ? trim( (string) wp_unslash( $_POST['tpw_heading_font_family'] ) ) : '';
        // Per level
        $sanitize_size = function($s, $fallback){
            $t = trim((string)$s);
            if ($t === '') return '';
            if ( preg_match('/^\d+(?:\.\d+)?(px|rem|em|%)?$/', $t) ) {
                if ( ! preg_match('/[a-z%]$/i', $t) ) { $t .= 'px'; }
                return $t;
            }
            return $fallback;
        };
        for ( $i = 1; $i <= 6; $i++ ) {
            $hk = 'h' . $i;
            $color = isset($_POST[$hk . '_color']) ? trim( (string) wp_unslash( $_POST[$hk . '_color'] ) ) : '';
            // allow hex and var() or empty
            if ( $color !== '' ) {
                $hex = sanitize_hex_color( $color );
                if ( ! $hex && preg_match('/^var\([^)]+\)$/', $color) ) { /* keep var(...) */ }
                elseif ( ! $hex && preg_match('/^#([0-9a-fA-F]{3})$/', $color) ) { $hex = $color; }
                $color = $hex ? $hex : $color; // keep var() or valid hex
            }
            $size  = isset($_POST[$hk . '_size']) ? $sanitize_size( wp_unslash( $_POST[$hk . '_size'] ), '' ) : '';
            $wraw  = isset($_POST[$hk . '_weight']) ? trim( (string) wp_unslash( $_POST[$hk . '_weight'] ) ) : '';
            $weight= '';
            if ( $wraw !== '' ) {
                if ( $wraw === 'normal' || preg_match('/^(100|200|300|400|500|600|700|800|900)$/', $wraw) ) {
                    $weight = $wraw;
                }
            }
            $h_in[$hk] = [ 'color' => $color, 'size' => $size, 'weight' => $weight ];
        }
        // Merge with existing, then defaults
        $existing_h = get_option( 'tpw_heading_styles', [] );
        if ( ! is_array( $existing_h ) ) { $existing_h = []; }
        $merged_h = array_merge( $existing_h, $h_in );
        // Ensure per-level arrays exist
        foreach ( ['h1','h2','h3','h4','h5','h6'] as $hk ) {
            if ( ! isset( $merged_h[$hk] ) || ! is_array( $merged_h[$hk] ) ) { $merged_h[$hk] = $h_defaults[$hk]; }
            $merged_h[$hk] = wp_parse_args( $merged_h[$hk], $h_defaults[$hk] );
        }
        if ( isset($h_in['font_family']) ) { $merged_h['font_family'] = $h_in['font_family']; }
        update_option( 'tpw_heading_styles', $merged_h );
    }

    $errors = get_settings_errors();
    set_transient( 'settings_errors', $errors, 30 );
    $url = add_query_arg( [ 'page' => 'tpw-core-settings', 'tab' => 'branding', 'settings-updated' => '1' ], admin_url( 'options-general.php' ) );
    wp_safe_redirect( $url );
    exit;
});

// Handle Email Template reset
add_action( 'admin_post_tpw_core_reset_email_template', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Permission denied', 'tpw-core' ) );
    check_admin_referer( 'tpw_reset_email_template', 'tpw_email_tmpl_nonce' );

    $key = isset($_GET['template_key']) ? strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $_GET['template_key'] ) ) : '';
    if ( $key && class_exists('TPW_Email_Templates_DB') ) {
        TPW_Email_Templates_DB::delete_override( $key );
        add_settings_error( 'tpw_email_templates', 'reset', __( 'Template reset to default.', 'tpw-core' ), 'updated' );
    }
    $errors = get_settings_errors();
    set_transient( 'settings_errors', $errors, 30 );
    $url = add_query_arg( [ 'page' => 'tpw-core-settings', 'tab' => 'email-templates', 'settings-updated' => '1' ], admin_url( 'options-general.php' ) );
    wp_safe_redirect( $url );
    exit;
} );

// Old Settings API registration for Features & Member Menu removed (now using dedicated handlers)

// New save handler: Features tab
add_action( 'admin_post_tpw_core_save_features', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Permission denied', 'tpw-core' ) );
    check_admin_referer( 'tpw_core_save_features', 'tpw_core_features_nonce' );

    $login_page   = isset( $_POST['tpw_core_default_login_page'] ) ? (int) $_POST['tpw_core_default_login_page'] : 0;
    $redirect_page= isset( $_POST['tpw_login_redirect_page_id'] ) ? (int) $_POST['tpw_login_redirect_page_id'] : 0;

    // Validate published status
    $validate_page = function( $id ) {
        $id = (int) $id;
        if ( $id <= 0 ) return 0;
        $status = get_post_status( $id );
        return ( $status === 'publish' ) ? $id : 0;
    };
    $login_page    = $validate_page( $login_page );
    $redirect_page = $validate_page( $redirect_page );

    update_option( 'tpw_core_default_login_page', $login_page );
    update_option( 'tpw_login_redirect_page_id', $redirect_page );

    add_settings_error( 'tpw_core_features', 'tpw_features_saved', __( 'Features settings saved.', 'tpw-core' ), 'updated' );
    $errors = get_settings_errors();
    set_transient( 'settings_errors', $errors, 30 );
    wp_safe_redirect( add_query_arg( [ 'page' => 'tpw-core-settings', 'tab' => 'features', 'settings-updated' => '1' ], admin_url( 'options-general.php' ) ) );
    exit;
} );

// New save handler: Member Menu tab
add_action( 'admin_post_tpw_core_save_member_menu', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Permission denied', 'tpw-core' ) );
    check_admin_referer( 'tpw_core_save_member_menu', 'tpw_core_member_menu_nonce' );

    $location = isset( $_POST['tpw_member_menu_location'] ) ? sanitize_key( $_POST['tpw_member_menu_location'] ) : 'primary';
    if ( $location === '' ) { $location = 'primary'; }
    update_option( 'tpw_member_menu_location', $location );

    add_settings_error( 'tpw_core_member_menu', 'tpw_member_menu_saved', __( 'Member Menu settings saved.', 'tpw-core' ), 'updated' );
    $errors = get_settings_errors();
    set_transient( 'settings_errors', $errors, 30 );
    wp_safe_redirect( add_query_arg( [ 'page' => 'tpw-core-settings', 'tab' => 'member-menu', 'settings-updated' => '1' ], admin_url( 'options-general.php' ) ) );
    exit;
} );

// Migrate legacy FlexiGolf option to Core on admin_init (one-way)
add_action( 'admin_init', function() {
    // If Core option not set but legacy exists, copy across
    $core = (int) get_option( 'tpw_login_redirect_page_id', 0 );
    if ( $core > 0 ) return;
    $legacy = (int) get_option( 'flexigolf_login_redirect_page_id', 0 );
    if ( $legacy > 0 && get_post_status( $legacy ) === 'publish' ) {
        update_option( 'tpw_login_redirect_page_id', $legacy );
    }
} );

// Handle Email Settings save (POST)
add_action( 'admin_post_tpw_core_save_email_settings', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to manage settings.', 'tpw-core' ) );
    }
    check_admin_referer( 'tpw_core_save_email_settings', 'tpw_core_email_nonce' );

    // Gather inputs
    $incoming = [
        'enable_throttling'     => isset($_POST['enable_throttling']) ? 1 : 0,
        'max_emails_per_minute' => isset($_POST['max_emails_per_minute']) ? (int) $_POST['max_emails_per_minute'] : null,
        'delay_between_emails'  => isset($_POST['delay_between_emails']) ? (int) $_POST['delay_between_emails'] : null,
        'enable_logging'        => isset($_POST['enable_logging']) ? 1 : 0,
        'send_test_mode'        => isset($_POST['send_test_mode']) ? 1 : 0,
        'test_mode_recipient'   => isset($_POST['test_mode_recipient']) ? sanitize_email( wp_unslash( $_POST['test_mode_recipient'] ) ) : '',
        'fallback_logo_url'     => isset($_POST['fallback_logo_url']) ? esc_url_raw( wp_unslash( $_POST['fallback_logo_url'] ) ) : '',
        'embed_logo_base64'     => isset($_POST['embed_logo_base64']) ? 1 : 0,
    ];
    $brand_title = isset($_POST['brand_title']) ? sanitize_text_field( wp_unslash( $_POST['brand_title'] ) ) : '';

    $reset_b64 = isset($_POST['reset_logo_base64']) && $_POST['reset_logo_base64'] == '1';

    // Attempt base64 generation when a logo URL is provided and reset not requested
    $b64 = '';
    if ( ! $reset_b64 && ! empty( $incoming['fallback_logo_url'] ) && class_exists('TPW_Email_Logo_Helper') ) {
        $b64 = TPW_Email_Logo_Helper::generate_base64( $incoming['fallback_logo_url'] );
        if ( $b64 === '' ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 6 );
                $frames = [];
                foreach ( $bt as $f ) {
                    $frames[] = ( $f['function'] ?? 'unknown' ) . ' @ ' . ( $f['file'] ?? 'unknown' ) . ':' . ( $f['line'] ?? 0 );
                }
                error_log( 'TPW CORE: add_settings_error(tpw_email_logo_b64_skipped) – ' . __FILE__ . ':' . __LINE__ . ' – ' . implode( ' | ', array_slice( $frames, 0, 5 ) ) );
            }
            add_settings_error( 'tpw_core_email_settings', 'tpw_email_logo_b64_skipped', __( 'Base64 copy not created – image too large or incompatible format.', 'tpw-core' ), 'warning' );
        }
    }

    if ( $reset_b64 ) {
        $incoming['fallback_logo_base64'] = '';
    } elseif ( $b64 !== '' ) {
        $incoming['fallback_logo_base64'] = $b64;
    }

    if ( class_exists( 'TPW_Core_Email_Settings' ) ) {
        TPW_Core_Email_Settings::update( $incoming );
        // Save brand title separately under its own option
        if ( $brand_title !== '' ) {
            update_option( 'tpw_brand_title', $brand_title );
        } else {
            // If empty, clear to fall back to Site Title
            delete_option( 'tpw_brand_title' );
        }
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 6 );
            $frames = [];
            foreach ( $bt as $f ) {
                $frames[] = ( $f['function'] ?? 'unknown' ) . ' @ ' . ( $f['file'] ?? 'unknown' ) . ':' . ( $f['line'] ?? 0 );
            }
            error_log( 'TPW CORE: add_settings_error(tpw_email_saved) – ' . __FILE__ . ':' . __LINE__ . ' – ' . implode( ' | ', array_slice( $frames, 0, 5 ) ) );
        }

        add_settings_error( 'tpw_core_email_settings', 'tpw_email_saved', __( 'Email settings saved.', 'tpw-core' ), 'updated' );
    } else {
        add_settings_error( 'tpw_core_email_settings', 'tpw_email_missing', __( 'Could not save. Email settings class missing.', 'tpw-core' ), 'error' );
    }

    // Persist messages and redirect back to Email tab
    $errors = get_settings_errors();
    // De-dupe messages defensively (some environments may register the same error twice).
    $seen = [];
    $unique = [];
    foreach ( $errors as $e ) {
        $key = (string) ( $e['setting'] ?? '' ) . '|' . (string) ( $e['code'] ?? '' ) . '|' . (string) ( $e['message'] ?? '' );
        if ( isset( $seen[ $key ] ) ) {
            continue;
        }
        $seen[ $key ] = true;
        $unique[] = $e;
    }
    set_transient( 'settings_errors', $unique, 30 );
    $url = add_query_arg( [ 'page' => 'tpw-core-settings', 'tab' => 'email', 'settings-updated' => '1' ], admin_url( 'options-general.php' ) );
    wp_safe_redirect( $url );
    exit;
} );

if ( ! function_exists( 'tpw_member_menu_location_dropdown' ) ) {
    function tpw_member_menu_location_dropdown() {
        $locations = function_exists( 'get_registered_nav_menus' ) ? get_registered_nav_menus() : [];
        $selected  = get_option( 'tpw_member_menu_location', 'primary' );

        echo '<select name="tpw_member_menu_location">';
        foreach ( $locations as $key => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $key ),
                selected( $selected, $key, false ),
                esc_html( $label )
            );
        }
        echo '</select>';

        // Helpful note
        echo '<p class="description">' . esc_html__( 'Logged-in users will see the TPW Member Menu at this location if a menu is assigned to it.', 'tpw-core' ) . '</p>';
    }
}

// 5) Intercept wp_nav_menu_args to swap locations for logged-in users
add_filter( 'wp_nav_menu_args', function ( $args ) {
    if ( is_admin() ) return $args; // front-end only
    if ( ! is_user_logged_in() ) return $args;

    // Ensure we don't affect REST or feeds
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return $args;

    $target_location = get_option( 'tpw_member_menu_location', 'primary' );

    // Some themes pass theme_location as empty; only proceed if it's set and matches
    if ( ! empty( $args['theme_location'] ) && $args['theme_location'] === $target_location ) {
        if ( has_nav_menu( 'tpw_member_menu' ) ) {
            $args['theme_location'] = 'tpw_member_menu';

            // Add a helper class so site owners can confirm it's active
            if ( isset( $args['menu_class'] ) && is_string( $args['menu_class'] ) ) {
                $args['menu_class'] .= ' tpw-member-menu-active';
            } else {
                $args['menu_class'] = 'tpw-member-menu-active';
            }
        }
    }

    return $args;
}, 10 );

// Helper: Resolve the member profile page URL. If the saved option is missing/invalid,
// try to find a published page containing the [tpw_member_profile] shortcode.
if ( ! function_exists( 'tpw_core_resolve_profile_page_url' ) ) {
    function tpw_core_resolve_profile_page_url() {
        // Optional future-proofing: Prefer System Pages permalink when available
        if ( class_exists( 'TPW_Core_System_Pages' ) ) {
            $sys_url = TPW_Core_System_Pages::get_permalink( 'my-profile' );
            if ( $sys_url ) {
                return $sys_url;
            }
        }

        $profile_page_id = (int) get_option( 'tpw_member_profile_page_id', 0 );
        if ( $profile_page_id > 0 ) {
            $p = get_post( $profile_page_id );
            if ( $p && 'publish' === $p->post_status ) {
                return get_permalink( $p );
            }
        }

        // Attempt to auto-detect by searching for the shortcode in published pages.
        $found_id = 0;
        if ( class_exists( 'WP_Query' ) ) {
            // First, try a lightweight text search
            $q = new WP_Query( [
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                's'              => '[tpw_member_profile',
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ] );
            if ( $q->have_posts() && ! empty( $q->posts[0] ) ) {
                $found_id = (int) $q->posts[0];
            }
            wp_reset_postdata();

            // If not found, scan a small set of recent pages' content for the shortcode
            if ( ! $found_id ) {
                $q2 = new WP_Query( [
                    'post_type'      => 'page',
                    'post_status'    => 'publish',
                    'posts_per_page' => 10,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'fields'         => 'ids',
                ] );
                if ( $q2->have_posts() ) {
                    foreach ( $q2->posts as $pid ) {
                        $content = get_post_field( 'post_content', $pid );
                        if ( is_string( $content ) && false !== strpos( $content, '[tpw_member_profile' ) ) {
                            $found_id = (int) $pid;
                            break;
                        }
                    }
                }
                wp_reset_postdata();
            }
        }

        if ( $found_id ) {
            // Cache for future use
            update_option( 'tpw_member_profile_page_id', $found_id );
            return get_permalink( $found_id );
        }

        // No valid page found
        return '';
    }
}

if ( ! function_exists( 'tpw_core_profile_page_is_configured' ) ) {
    function tpw_core_profile_page_is_configured() {
        return (bool) tpw_core_resolve_profile_page_url();
    }
}

// Append a profile avatar + dropdown with My Profile link for logged-in members on classic menus
add_filter( 'wp_nav_menu_items', function( $items, $args ) {
    if ( is_admin() ) return $items;
    if ( ! is_user_logged_in() ) return $items;

    // Only add to the tpw_member_menu output
    if ( empty($args->theme_location) || $args->theme_location !== 'tpw_member_menu' ) {
        return $items;
    }

    // Resolve profile page URL. If not configured, fall back to a front-end route.
    $profile_url = tpw_core_resolve_profile_page_url();
    $profile_configured = ! empty( $profile_url );
    if ( ! $profile_configured ) {
        // Add the query var to guarantee routing even if rewrite rules aren't flushed
        $profile_url = add_query_arg( 'tpw_my_profile', '1', home_url( '/my-profile/' ) );
    }

    // Compute avatar: member photo or initials
    $avatar_html = '';
    $user = wp_get_current_user();
    if ( $user && class_exists('TPW_Member_Access') ) {
        require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-controller.php';
        $controller = new TPW_Member_Controller();
        $m = $controller->get_member_by_user_id( (int) $user->ID );
        if ( $m && ! empty( $m->member_photo ) ) {
            $url = $m->member_photo;
            if ( ! preg_match('#^https?://#i', $url) ) {
                $uploads = wp_get_upload_dir();
                if ( ! empty( $uploads['baseurl'] ) ) {
                    $url = trailingslashit( $uploads['baseurl'] ) . ltrim( $url, '/' );
                }
            }
            $avatar_html = '<img class="tpw-nav-avatar" src="' . esc_url( $url ) . '" alt="" width="28" height="28" />';
        } else {
            $fi = $user->user_firstname ? strtoupper( $user->user_firstname[0] ) : '';
            $li = $user->user_lastname ? strtoupper( $user->user_lastname[0] ) : '';
            $initials = esc_html( trim( $fi . $li ) ?: strtoupper( substr( $user->display_name, 0, 1 ) ) );
            $avatar_html = '<span class="tpw-nav-initials">' . $initials . '</span>';
        }
    }

    // If no profile page is configured, we will still point to the front-end /my-profile/ route so members can access their profile without wp-admin.

    $profile_li  = '<li class="menu-item tpw-member-profile-menu">';
    $profile_li .= '<a href="' . esc_url( $profile_url ) . '" class="tpw-member-profile-link">' . $avatar_html . '</a>';
    // Build submenu: My Profile + Logout (logout redirects to Home)
    $logout_url = function_exists( 'wp_logout_url' ) ? wp_logout_url( home_url( '/' ) ) : home_url( '/?logout=1' );
    $profile_li .= '<ul class="sub-menu tpw-member-profile-sub">'
                . '<li class="menu-item"><a href="' . esc_url( $profile_url ) . '">' . esc_html__( 'My Profile', 'tpw-core' ) . '</a></li>'
                . '<li class="menu-item"><a href="' . esc_url( $logout_url ) . '">' . esc_html__( 'Logout', 'tpw-core' ) . '</a></li>'
                . '</ul>';
    $profile_li .= '</li>';

    // Append right after items (before logout ideally, but order depends on theme). We append at end.
    return $items . $profile_li;
}, 10, 2 );

// 6) Apply TPW visibility rules on front-end menus and cascade parent hiding to children
add_filter( 'wp_nav_menu_objects', function( $items, $args ) {
    if ( is_admin() ) return $items;
    if ( empty( $items ) || ! is_array( $items ) ) return $items;
    // Avoid affecting REST or feeds
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return $items;
    // Only apply if at least one item in this menu carries TPW visibility meta.
    $has_rules = false;
    foreach ( $items as $probe ) {
        $pid = (int) $probe->ID;
        if ( get_post_meta( $pid, '_tpw_requires_login', true ) || get_post_meta( $pid, '_tpw_visibility_json', true ) ) { $has_rules = true; break; }
    }
    if ( ! $has_rules ) return $items;

    // Build a quick lookup of items by ID and parent relationships
    $by_id = [];
    $children_of = [];
    foreach ( $items as $it ) {
        $by_id[ (int) $it->ID ] = $it;
        $pid = (int) ( $it->menu_item_parent ?? 0 );
        if ( ! isset( $children_of[ $pid ] ) ) $children_of[ $pid ] = [];
        $children_of[ $pid ][] = (int) $it->ID;
    }

    $is_hidden = [];
    $has_any_rules = function( $vis ) {
        return ( ! empty( $vis['is_admin'] ) || ! empty( $vis['is_committee'] ) || ! empty( $vis['is_match_manager'] ) || ! empty( $vis['is_noticeboard_admin'] ) || ( ! empty( $vis['status'] ) && is_array( $vis['status'] ) ) );
    };
    $convert_vis = function( $vis ) {
        $flags = [];
        foreach ( [ 'is_admin', 'is_committee', 'is_match_manager', 'is_noticeboard_admin' ] as $k ) {
            if ( ! empty( $vis[ $k ] ) ) $flags[] = $k;
        }
        $out = [];
        if ( ! empty( $flags ) ) $out['flags_any'] = $flags;
        if ( ! empty( $vis['status'] ) && is_array( $vis['status'] ) ) $out['allowed_statuses'] = $vis['status'];
        return $out;
    };

    // First pass: evaluate each item's own rules
    foreach ( $items as $it ) {
        $id = (int) $it->ID;
        $hide = false;
        // requires_login meta
        $requires_login = (bool) get_post_meta( $id, '_tpw_requires_login', true );
        if ( $requires_login && ! is_user_logged_in() ) {
            $hide = true;
        }
        // visibility json rules (flat model)
        if ( ! $hide ) {
            $raw = get_post_meta( $id, '_tpw_visibility_json', true );
            $vis = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
            if ( is_array( $vis ) && $has_any_rules( $vis ) ) {
                // Convert to advanced model and defer to TPW_Control_UI
                if ( ! class_exists( 'TPW_Control_UI' ) ) {
                    $ui = defined('TPW_CORE_PATH') ? TPW_CORE_PATH . 'modules/tpw-control/class-tpw-control-ui.php' : '';
                    if ( $ui && file_exists( $ui ) ) require_once $ui;
                }
                $adv = $convert_vis( $vis );
                if ( ! empty( $adv ) && class_exists( 'TPW_Control_UI' ) ) {
                    if ( ! TPW_Control_UI::user_has_access( $adv ) ) {
                        $hide = true;
                    }
                }
            }
        }
        if ( $hide ) $is_hidden[ $id ] = true;
    }

    // Second pass: cascade hiding to descendants if a parent is hidden
    $check_ancestor_hidden = function( $id ) use ( &$check_ancestor_hidden, $is_hidden, $by_id ) {
        $cur = $by_id[ $id ] ?? null;
        while ( $cur ) {
            $pid = (int) ( $cur->menu_item_parent ?? 0 );
            if ( $pid === 0 ) return false;
            if ( ! empty( $is_hidden[ $pid ] ) ) return true;
            $cur = $by_id[ $pid ] ?? null;
        }
        return false;
    };

    $out = [];
    foreach ( $items as $it ) {
        $id = (int) $it->ID;
        if ( ! empty( $is_hidden[ $id ] ) ) continue;
        if ( $check_ancestor_hidden( $id ) ) continue;
        $out[] = $it;
    }
    return $out;
}, 10, 2 );

// Logout URL contract (public): /?tpw_action=logout
//
// We use a stable placeholder URL and do NOT persist nonce-bearing WordPress logout URLs in the database.
// WordPress logout links include a security nonce that expires; if saved into menus, users eventually see
// the WP confirmation screen instead of being logged out.
//
// This filter rewrites the placeholder at render-time into wp_logout_url( home_url('/') ) so a fresh nonce
// is generated per user/session and logout happens immediately.
//
// This is a public contract; do not change the placeholder without backward-compatibility consideration.
// Placeholder (exact match when normalised): /?tpw_action=logout (allow absolute or relative)
add_filter( 'wp_nav_menu_objects', function( $items, $args ) {
    if ( is_admin() ) return $items;
    if ( ! is_user_logged_in() ) return $items;
    if ( empty( $items ) || ! is_array( $items ) ) return $items;

    foreach ( $items as $it ) {
        if ( empty( $it->url ) ) {
            continue;
        }

        $raw = html_entity_decode( trim( (string) $it->url ), ENT_QUOTES, 'UTF-8' );
        if ( $raw === '' ) {
            continue;
        }

        $parsed = function_exists( 'wp_parse_url' ) ? wp_parse_url( $raw ) : parse_url( $raw );
        if ( ! is_array( $parsed ) ) {
            continue;
        }

        $path  = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';
        $query = isset( $parsed['query'] ) ? (string) $parsed['query'] : '';

        // Treat a bare domain + query as root path.
        if ( $path === '' ) {
            $path = '/';
        }

        if ( $path !== '/' || $query === '' ) {
            continue;
        }

        parse_str( $query, $qv );
        if ( ! is_array( $qv ) || count( $qv ) !== 1 ) {
            continue;
        }

        if ( ! isset( $qv['tpw_action'] ) || (string) $qv['tpw_action'] !== 'logout' ) {
            continue;
        }

        $it->url = wp_logout_url( home_url( '/' ) );
    }

    return $items;
}, 20, 2 );

// Admin notice: prompt to configure profile page if feature is being used but not configured
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    // Only show on relevant admin screens once in a while
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    $allowed_screens = [ 'settings_page_tpw-core-settings', 'nav-menus' ];
    if ( $screen && ! in_array( $screen->id, $allowed_screens, true ) ) return;

    // Warn if missing
    if ( ! tpw_core_profile_page_is_configured() ) {
        $url = admin_url( 'options-general.php?page=tpw-core-settings' );
        $url = add_query_arg( 'tab', 'profile', $url );
        echo '<div class="notice notice-warning"><p>'
            . esc_html__( 'TPW Core: The Member Profile page is not configured. ', 'tpw-core' )
            . '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Select a Profile page now', 'tpw-core' ) . '</a>'
            . '</p></div>';
        return;
    }

    // Also warn if selected page isn’t published (admins see it, members don’t)
    $pid = (int) get_option('tpw_member_profile_page_id', 0);
    if ( $pid > 0 ) {
        $p = get_post( $pid );
        if ( $p && 'page' === $p->post_type && 'publish' !== $p->post_status ) {
            $edit = get_edit_post_link( $pid, '' );
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'TPW Core: The selected Member Profile page is not published. Members cannot view it until it is Public and Published.', 'tpw-core' )
                . ( $edit ? ' <a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit page', 'tpw-core' ) . '</a>' : '' )
                . '</p></div>';
        }
    }
} );

// One-time fallback seeder: create and assign a default "Members Menu" if the
// tpw_member_menu location has no assignment yet (e.g., activation ran before
// this file existed). Runs for admins in wp-admin once, then sets an option flag.
add_action( 'admin_init', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( is_network_admin() || defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return; // avoid background/ajax contexts
    }
    if ( get_option( 'tpw_core_member_menu_seeded' ) ) {
        return;
    }

    // Make sure the location exists and check if it already has a menu
    if ( ! function_exists( 'has_nav_menu' ) ) {
        return;
    }

    if ( has_nav_menu( 'tpw_member_menu' ) ) {
        update_option( 'tpw_core_member_menu_seeded', time() );
        return; // already assigned
    }

    // Load helpers if needed
    if ( ! function_exists( 'get_nav_menu_locations' ) && defined( 'ABSPATH' ) ) {
        @require_once ABSPATH . 'wp-includes/nav-menu.php';
    }
    if ( ! function_exists( 'wp_create_nav_menu' ) && defined( 'ABSPATH' ) ) {
        @require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
    }

    if ( ! function_exists( 'get_nav_menu_locations' ) || ! function_exists( 'wp_create_nav_menu' ) ) {
        return;
    }

    $locations = get_nav_menu_locations();
    if ( ! is_array( $locations ) ) {
        $locations = [];
    }

    $has_assignment = isset( $locations['tpw_member_menu'] ) && ! empty( $locations['tpw_member_menu'] );
    if ( $has_assignment ) {
        update_option( 'tpw_core_member_menu_seeded', time() );
        return;
    }

    // Create or reuse a "Members Menu"
    $menu_name = __( 'Members Menu', 'tpw-core' );
    $menu_obj  = function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $menu_name ) : null;
    $menu_id   = $menu_obj && isset( $menu_obj->term_id ) ? (int) $menu_obj->term_id : 0;

    if ( ! $menu_id ) {
        $menu_id = (int) wp_create_nav_menu( $menu_name );
    }

    if ( $menu_id > 0 && function_exists( 'wp_update_nav_menu_item' ) ) {
        // Store a placeholder (not a nonce URL) so it can be rewritten at render-time.
        $logout_url = '/?tpw_action=logout';
        wp_update_nav_menu_item( $menu_id, 0, [
            'menu-item-title'  => __( 'Logout', 'tpw-core' ),
            'menu-item-url'    => esc_url_raw( $logout_url ),
            'menu-item-status' => 'publish',
            'menu-item-type'   => 'custom',
        ] );

        $locations['tpw_member_menu'] = $menu_id;
        set_theme_mod( 'nav_menu_locations', $locations );
    }

    update_option( 'tpw_core_member_menu_seeded', time() );
} );

// One-time fallback: ensure a "My Profile" page exists even if the site didn't re-activate the plugin
add_action( 'admin_init', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( is_network_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
        return;
    }
    if ( get_option( 'tpw_core_profile_page_seeded' ) ) {
        return;
    }

    $configured_id = (int) get_option( 'tpw_member_profile_page_id', 0 );
    if ( $configured_id > 0 ) {
        $p = get_post( $configured_id );
        if ( $p && 'publish' === $p->post_status && 'page' === $p->post_type ) {
            update_option( 'tpw_core_profile_page_seeded', time() );
            return; // already configured with a valid page
        }
    }

    // Try to find an existing page containing the shortcode
    $found_id = 0;
    if ( class_exists( 'WP_Query' ) ) {
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
                if ( is_string( $content ) && false !== strpos( $content, '[tpw_member_profile' ) ) {
                    $found_id = (int) $pid;
                    break;
                }
            }
        }
        wp_reset_postdata();
    }

    if ( $found_id ) {
        update_option( 'tpw_member_profile_page_id', $found_id );
        update_option( 'tpw_core_profile_page_seeded', time() );
        return;
    }

    // Create a new My Profile page with the shortcode
    $author = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
    $post_id = wp_insert_post( [
        'post_title'     => __( 'My Profile', 'tpw-core' ),
        'post_name'      => 'my-profile',
        'post_status'    => 'publish',
        'post_type'      => 'page',
        'post_author'    => $author,
        'post_content'   => '[tpw_member_profile]',
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
    ] );
    if ( $post_id && ! is_wp_error( $post_id ) ) {
        update_option( 'tpw_member_profile_page_id', (int) $post_id );
    }
    update_option( 'tpw_core_profile_page_seeded', time() );
} );
