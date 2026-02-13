<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Only enable logout redirect when the Members module is active.
$tpw_members_active = false;
if ( defined( 'TPW_MEMBERS_ACTIVE' ) && TPW_MEMBERS_ACTIVE ) {
    $tpw_members_active = true;
} elseif ( function_exists( 'tpw_members_module_enabled' ) && true === tpw_members_module_enabled() ) {
    $tpw_members_active = true;
} else {
    // Fallback: detect by table presence
    global $wpdb;
    $table = $wpdb->prefix . 'tpw_members';
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( ! empty( $exists ) ) {
        $tpw_members_active = true;
    }
}

if ( $tpw_members_active ) {
    if ( ! function_exists('tpw_members_get_display_name') ) {
        /**
         * Build a display name for a member object based on configured format.
         *
         * @param object|array $member Member row with keys: first_name, surname, initials, title.
         * @return string
         */
        function tpw_members_get_display_name( $member ) {
            if ( is_array($member) ) { $member = (object) $member; }
            $first   = isset($member->first_name) ? trim((string)$member->first_name) : '';
            $surname = isset($member->surname) ? trim((string)$member->surname) : '';
            $initials= isset($member->initials) ? trim((string)$member->initials) : '';
            $title   = isset($member->title) ? trim((string)$member->title) : '';

            $settings = get_option('tpw_members_settings', []);
            $fmt = is_array($settings) && isset($settings['name_format']) ? $settings['name_format'] : 'surname_first';

            // Helper: collapse extra spaces and commas
            $clean = function($s){
                $s = trim(preg_replace('/\s+/', ' ', (string)$s));
                $s = preg_replace('/\s+,\s*/', ', ', $s);
                $s = preg_replace('/,\s*,+/', ', ', $s);
                return trim($s);
            };

            switch ($fmt) {
                case 'surname_initials_first_paren':
                    $out = sprintf('%s%s%s',
                        $surname,
                        ($initials !== '' ? ', ' . $initials : ''),
                        ($first !== '' ? ' (' . $first . ')' : '')
                    );
                    return $clean($out);
                case 'initials_surname':
                    return $clean(trim(($initials !== '' ? $initials . ' ' : '') . $surname));
                case 'surname_initials':
                    return $clean(trim($surname . ($initials !== '' ? ' ' . $initials : '')));
                case 'first_surname':
                    return $clean(trim($first . ' ' . $surname));
                case 'surname_first':
                    return $clean(trim(($surname !== '' ? $surname : '') . ($first !== '' ? ', ' . $first : '')));
                case 'surname_first_initials_paren':
                    $out = sprintf('%s%s%s',
                        $surname,
                        ($first !== '' ? ', ' . $first : ''),
                        ($initials !== '' ? ' (' . $initials . ')' : '')
                    );
                    return $clean($out);
                case 'title_first_surname':
                    return $clean(trim(($title !== '' ? $title . ' ' : '') . trim($first . ' ' . $surname)));
                case 'surname_title_initials':
                    $right = trim(($title !== '' ? $title . ' ' : '') . $initials);
                    return $clean(trim($surname . ($right !== '' ? ', ' . $right : '')));
                case 'title_initials_surname':
                    return $clean(trim(($title !== '' ? $title . ' ' : '') . ($initials !== '' ? $initials . ' ' : '') . $surname));
                default:
                    return $clean(trim(($surname !== '' ? $surname : '') . ($first !== '' ? ', ' . $first : '')));
            }
        }
    }
    // Enqueue shared TPW UI styles for all public-facing Members screens
    // Priority is high so theme/global styles are enqueued first.
    add_action( 'wp_enqueue_scripts', function() {
        global $post;
        $has_members_ui = false;
        if ( $post && isset( $post->post_content ) ) {
            $content = (string) $post->post_content;
            // Core Members shortcodes and virtual page content
            if ( has_shortcode( $content, 'tpw_manage_members' ) || has_shortcode( $content, 'tpw_member_profile' ) || has_shortcode( $content, 'tpw_member_login' ) ) {
                $has_members_ui = true;
            }
        }
        // Also cover the virtual My Profile route injected by Core
        if ( get_query_var( 'tpw_my_profile' ) ) {
            $has_members_ui = true;
        }
        if ( ! $has_members_ui ) {
            return;
        }

        // Core UI helpers (tokens), scoped admin UI (layout/resets), buttons, and tabs.
        $ui_file       = TPW_CORE_PATH . 'assets/css/tpw-ui.css';
        $admin_ui_file = TPW_CORE_PATH . 'assets/css/tpw-admin-ui.css';
        $buttons_file  = TPW_CORE_PATH . 'assets/css/tpw-buttons.css';
        $tabs_file     = TPW_CORE_PATH . 'assets/css/tpw-admin-tabs.css';

        $ui_ver       = file_exists( $ui_file ) ? filemtime( $ui_file ) : null;
        $admin_ui_ver = file_exists( $admin_ui_file ) ? filemtime( $admin_ui_file ) : null;
        $buttons_ver  = file_exists( $buttons_file ) ? filemtime( $buttons_file ) : null;
        $tabs_ver     = file_exists( $tabs_file ) ? filemtime( $tabs_file ) : null;

        if ( function_exists( 'wp_style_is' ) ) {
            if ( ! wp_style_is( 'tpw-ui', 'enqueued' ) ) {
                wp_enqueue_style( 'tpw-ui', TPW_CORE_URL . 'assets/css/tpw-ui.css', [], $ui_ver );
            }
            if ( ! wp_style_is( 'tpw-admin-ui', 'enqueued' ) ) {
                wp_enqueue_style( 'tpw-admin-ui', TPW_CORE_URL . 'assets/css/tpw-admin-ui.css', [ 'tpw-ui' ], $admin_ui_ver );
            }
            if ( ! wp_style_is( 'tpw-buttons', 'enqueued' ) ) {
                wp_enqueue_style( 'tpw-buttons', TPW_CORE_URL . 'assets/css/tpw-buttons.css', [ 'tpw-ui' ], $buttons_ver );
            }
            if ( ! wp_style_is( 'tpw-admin-tabs', 'enqueued' ) ) {
                wp_enqueue_style( 'tpw-admin-tabs', TPW_CORE_URL . 'assets/css/tpw-admin-tabs.css', [ 'tpw-ui' ], $tabs_ver );
            }
        }
    }, 999 );

    add_action( 'wp_logout', function() {
        wp_safe_redirect( home_url() );
        exit;
    } );

    // Ensure Member Clubs UI renders inside the profile container when viewing the Profile page
    add_filter( 'the_content', function( $content ) {
        if ( is_admin() ) return $content;
        if ( empty( $content ) || strpos( $content, 'TPW_MEMBER_PROFILE_AFTER' ) === false ) return $content;

        // Try to identify a clubs block by common markers: heading text and/or a known field class
        $has_clubs_field = ( strpos( $content, 'tpw-member-clubs-field' ) !== false );
        $has_clubs_heading = ( stripos( $content, '>Member Clubs<' ) !== false );
        if ( ! $has_clubs_field && ! $has_clubs_heading ) {
            return $content; // nothing to move
        }

        // Simple DOM manipulation with regex-safe approach: extract a heading+form group if present
        $moved = '';
        $updated = $content;

        // 1) Prefer to grab an immediate heading (h1-h6) with text Member Clubs and the next form
        if ( $has_clubs_heading ) {
            $pattern = '/(<h[1-6][^>]*>\s*Member\s+Clubs\s*<\/h[1-6]>)([\s\S]*?<form[^>]*>.*?<\/form>)/iU';
            if ( preg_match( $pattern, $updated, $matches ) ) {
                $moved = $matches[1] . $matches[2];
                $updated = str_replace( $matches[0], '', $updated );
            }
        }

        // 2) If not found, try to grab a form containing the clubs field class
        if ( $moved === '' && $has_clubs_field ) {
            $pattern2 = '/(<form[^>]*class=["\']?[^>]*tpw-member-clubs-field[^>]*>.*?<\/form>)/isU';
            if ( preg_match( $pattern2, $updated, $matches2 ) ) {
                $moved = '<h3>Member Clubs</h3>' . $matches2[1];
                $updated = str_replace( $matches2[1], '', $updated );
            }
        }

        if ( $moved === '' ) {
            return $content; // could not safely extract
        }

        // Insert right after the profile block marker. Keep any surrounding whitespace intact.
        $marker = '<!-- TPW_MEMBER_PROFILE_AFTER -->';
        $pos = strpos( $updated, $marker );
        if ( $pos === false ) {
            return $content;
        }
        $injected = substr($updated, 0, $pos + strlen($marker)) . "\n" . $moved . "\n" . substr($updated, $pos + strlen($marker));
        return $injected;
    }, 50 );

    // Hide WP admin bar for regular members (non-admins)
    add_filter( 'show_admin_bar', function( $show ) {
        if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return $show;
    }, 1000 );

    // Shortcode: [tpw_logout_link] -> outputs a logout link redirecting to homepage
    add_shortcode( 'tpw_logout_link', function( $atts = [], $content = null ) {
        $url = wp_logout_url( home_url() );
        $label = $content ? wp_kses_post( $content ) : esc_html__( 'Logout', 'tpw-core' );
        return '<a class="tpw-logout-link" href="' . esc_url( $url ) . '">' . $label . '</a>';
    } );

    // For block themes using the Navigation block, append a logout item dynamically
    add_filter( 'render_block', function( $block_content, $block ) {
        if ( empty( $block['blockName'] ) || 'core/navigation' !== $block['blockName'] ) {
            return $block_content;
        }
        if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
            return $block_content;
        }
        $logout_url = esc_url( wp_logout_url( home_url() ) );
        $li = '<li class="wp-block-navigation-item menu-item-tpw-logout"><a href="' . $logout_url . '">' . esc_html__( 'Logout', 'tpw-core' ) . '</a></li>';
        // Insert before the first closing </ul> in the navigation container
        $updated = preg_replace( '/(<\/ul>)/i', $li . '$1', $block_content, 1 );
        return $updated ? $updated : $block_content;
    }, 10, 2 );
}
