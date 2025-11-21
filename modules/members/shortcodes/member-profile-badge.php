<?php
/**
 * Shortcode: tpw_profile_badge
 * Lightweight member profile/login badge for placement in headers, page builders and templates.
 *
 * Behaviour:
 * - Logged out: circular login badge linking to System Page 'member-login'.
 * - Logged in: shows member photo when available (and photos enabled), else real WP avatar, else initials.
 *   The default grey placeholder (Gravatar mystery man) is treated as "no avatar" and skipped in favour of initials.
 * - Links to System Page 'my-profile'.
 * - Suppressed in wp-admin.
 * - Independent of existing menu-injection logic (no filters touched).
 *
 * Markup (always):
 * <div class="tpw-profile-badge">
 *   <a href="...">
 *     <img class="tpw-profile-avatar" src="..." alt="" />
 *     OR
 *     <span class="tpw-profile-initials">AB</span>
 *   </a>
 * </div>
 *
 * @since 1.1.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Register shortcode early on init like other member shortcodes.
add_shortcode( 'tpw_profile_badge', function( $atts = [] ) {
    // Do not render inside wp-admin screens.
    if ( is_admin() ) {
        return '';
    }

    $atts = is_array( $atts ) ? $atts : [];
    $dropdown_enabled = isset( $atts['dropdown'] ) && in_array( strtolower( (string) $atts['dropdown'] ), [ 'yes','true','1','on' ], true );

    // Resolve system page URLs using central helper (fallback to conventional path when class missing).
    $login_url   = class_exists( 'TPW_Core_System_Pages' ) ? TPW_Core_System_Pages::get( 'member-login' ) : site_url( '/member-login/' );
    $profile_url = class_exists( 'TPW_Core_System_Pages' ) ? TPW_Core_System_Pages::get( 'my-profile' )    : site_url( '/my-profile/' );

    // Logged‑out badge → link to login page with accessible text.
    if ( ! is_user_logged_in() ) {
        $html  = '<div class="tpw-profile-badge">';
        $html .= '<a href="' . esc_url( $login_url ) . '" class="tpw-profile-badge__link" aria-label="Member Login">';
        $html .= '<span class="tpw-profile-initials" aria-hidden="true">Login</span>';
        $html .= '</a></div>';
        return $html;
    }

    // Logged‑in flow: attempt member photo → real WP avatar (non-placeholder) → initials.
    require_once plugin_dir_path( __FILE__ ) . '../includes/class-tpw-member-controller.php';
    $user       = wp_get_current_user();
    $controller = class_exists( 'TPW_Member_Controller' ) ? new TPW_Member_Controller() : null;
    $member     = $controller ? $controller->get_member_by_user_id( (int) $user->ID ) : null;

    $photo_url = '';
    $photos_enabled = get_option( 'tpw_members_use_photos', '0' ) === '1';
    if ( $photos_enabled && $member && isset( $member->member_photo ) ) {
        $rel = trim( (string) $member->member_photo );
        if ( $rel !== '' ) {
            if ( preg_match( '#^https?://#i', $rel ) ) {
                $photo_url = $rel; // already absolute
            } else {
                $uploads = wp_get_upload_dir();
                if ( ! empty( $uploads['baseurl'] ) ) {
                    $photo_url = rtrim( $uploads['baseurl'], '/' ) . '/' . ltrim( $rel, '/' );
                }
            }
        }
    }

    // Fallback to WP avatar when no member photo – exclude grey placeholder (d=mm).
    $avatar_url = '';
    $has_real_avatar = false;
    if ( $photo_url === '' && function_exists( 'get_avatar_url' ) ) {
        $maybe_avatar = (string) get_avatar_url( $user->ID, [ 'size' => 96, 'default' => 'mm' ] );
        if ( $maybe_avatar !== '' ) {
            // Treat as placeholder if query contains d=mm (Gravatar mystery man)
            if ( ! preg_match( '/[?&]d=mm(&|$)/', $maybe_avatar ) ) {
                $avatar_url = $maybe_avatar;
                $has_real_avatar = true;
            }
        }
    }

    // Final fallback: initials when no member photo and no real avatar.
    // Initials generation (member first_name + surname preferred; fallback chain as specified).
    $initials = '';
    if ( $photo_url === '' && ! $has_real_avatar ) {
        $first = '';
        $sur   = '';
        if ( $member && isset( $member->first_name ) && isset( $member->surname ) ) {
            $first = trim( (string) $member->first_name );
            $sur   = trim( (string) $member->surname );
        }
        if ( $first !== '' || $sur !== '' ) {
            $f_char = $first !== '' ? mb_substr( $first, 0, 1, 'UTF-8' ) : '';
            $s_char = $sur !== '' ? mb_substr( $sur, 0, 1, 'UTF-8' ) : '';
            $initials = mb_strtoupper( $f_char . $s_char, 'UTF-8' );
        }
        if ( $initials === '' ) {
            $name = trim( (string) ( $user->display_name ?: '' ) );
            if ( $name === '' ) { $name = (string) $user->user_login; }
            $parts = preg_split( '/[\s\-]+/', $name );
            if ( is_array( $parts ) ) {
                foreach ( $parts as $p ) {
                    $c = mb_substr( $p, 0, 1, 'UTF-8' );
                    if ( $c !== '' ) { $initials .= mb_strtoupper( $c, 'UTF-8' ); }
                    if ( mb_strlen( $initials, 'UTF-8' ) >= 2 ) { break; }
                }
            }
            if ( $initials === '' && $name !== '' ) {
                $initials = mb_strtoupper( mb_substr( $name, 0, 1, 'UTF-8' ), 'UTF-8' );
            }
        }
        if ( $initials === '' ) { $initials = '?'; }
    }

    // Ensure public UI stylesheet (tpw-ui.css) is enqueued for sizing variables if not already.
    if ( ! is_admin() && ! wp_style_is( 'tpw-ui', 'enqueued' ) ) {
        $ui_file = trailingslashit( TPW_CORE_PATH ) . 'assets/css/tpw-ui.css';
        if ( file_exists( $ui_file ) ) {
            $ver = (string) @filemtime( $ui_file );
            wp_enqueue_style( 'tpw-ui', trailingslashit( TPW_CORE_URL ) . 'assets/css/tpw-ui.css', [], $ver ?: '1.0' );
        }
    }

    $html  = '<div class="tpw-profile-badge"' . ( $dropdown_enabled ? ' data-has-dropdown="1"' : '' ) . '>';    
    $html .= '<a href="' . esc_url( $profile_url ) . '" class="tpw-profile-badge__link" aria-label="My Profile" aria-haspopup="' . ( $dropdown_enabled ? 'true' : 'false' ) . '" aria-expanded="false">';
    if ( $photo_url !== '' ) {
        $html .= '<img class="tpw-profile-avatar" src="' . esc_url( $photo_url ) . '" alt="" />';
    } elseif ( $has_real_avatar && $avatar_url !== '' ) {
        $html .= '<img class="tpw-profile-avatar" src="' . esc_url( $avatar_url ) . '" alt="" />';
    } else {
        $html .= '<span class="tpw-profile-initials" aria-hidden="true">' . esc_html( $initials ) . '</span>';
    }
    $html .= '</a>';

    // Dropdown (only for logged-in and when attribute enabled)
    if ( $dropdown_enabled ) {
        $logout_url = function_exists( 'wp_logout_url' ) ? wp_logout_url( home_url( '/' ) ) : home_url( '/?logout=1' );
        $logout_url = esc_url( $logout_url );
        $html .= '<div class="tpw-profile-badge__dropdown" role="menu" aria-label="Profile menu">';
        $html .= '  <a class="tpw-profile-badge__item" role="menuitem" href="' . esc_url( $profile_url ) . '">My Profile</a>';
        $html .= '  <a class="tpw-profile-badge__item" role="menuitem" href="' . $logout_url . '">Logout</a>';
        $html .= '</div>';
    }

    $html .= '</div>';

    // Enqueue dropdown interaction JS only when dropdown requested (touch devices only will act on it)
    if ( $dropdown_enabled && ! is_admin() ) {
        $js_handle = 'tpw-profile-badge';
        $js_file   = trailingslashit( TPW_CORE_PATH ) . 'assets/js/tpw-profile-badge.js';
        if ( file_exists( $js_file ) ) {
            $ver = (string) @filemtime( $js_file );
            wp_enqueue_script( $js_handle, trailingslashit( TPW_CORE_URL ) . 'assets/js/tpw-profile-badge.js', [], $ver ?: '1.0', true );
        }
    }
    return $html;
} );
