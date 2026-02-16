<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * TPW Member Payments — Phase 1 skeleton
 *
 * - Registers a "My Payments" profile section via filter (future-friendly)
 * - Renders a minimal skeleton inside the member profile via the server-side insert point
 * - No changes to existing profile logic or payments manager
 */
class TPW_Member_Payments {
    public static function init() : void {
        // Future-friendly: allow a tabs/sections registry to pick up this section
        add_filter( 'tpw_core_register_profile_sections', [ __CLASS__, 'register_profile_section' ] );
        // Register a safe, built-in default source so the hub always has content
        add_filter( 'tpw_core_register_payment_sources', [ __CLASS__, 'register_default_sources' ], 5 );
        // Current implementation: render after core profile block
        add_action( 'tpw_member_profile_after', [ __CLASS__, 'render_profile_payments' ], 20, 1 );
    }

    /**
     * Register a Payments section in a hypothetical profile sections registry.
     * Safe/no-op when the registry isn't used elsewhere yet.
     */
    public static function register_profile_section( array $sections ) : array {
        $active_methods = class_exists( 'TPW_Payments_Manager' ) ? (array) TPW_Payments_Manager::get_active_methods() : [];
        if ( ! empty( $active_methods ) ) {
            $sections['payments'] = [
                'label'    => __( 'My Payments', 'tpw-core' ),
                'template' => 'members/profile/payments/index.php',
                'icon'     => 'credit-card',
                'priority' => 40,
            ];
        }
        return $sections;
    }

    /**
     * Retrieve registered payment sources and normalize.
     *
     * @return array<string,array{label:string,callback:mixed,icon?:string,priority?:int}>
     */
    public static function get_registered_sources() : array {
        $sources = apply_filters( 'tpw_core_register_payment_sources', [] );
        if ( ! is_array( $sources ) ) {
            return [];
        }
        // Normalize entries; keep only those with a callable callback and label
        $norm = [];
        foreach ( $sources as $slug => $src ) {
            if ( ! is_string( $slug ) || $slug === '' ) { continue; }
            if ( ! is_array( $src ) ) { continue; }
            $label = isset( $src['label'] ) ? (string) $src['label'] : '';
            $cb    = $src['callback'] ?? null;
            if ( $label === '' || ! $cb ) { continue; }
            $prio  = isset( $src['priority'] ) ? (int) $src['priority'] : 50;
            $icon  = isset( $src['icon'] ) ? (string) $src['icon'] : '';
            $norm[$slug] = [
                'label'    => $label,
                'callback' => $cb,
                'icon'     => $icon,
                'priority' => $prio,
            ];
        }
        // Sort by priority ASC, then by label
        uasort( $norm, function( $a, $b ) {
            $pa = $a['priority'] ?? 50; $pb = $b['priority'] ?? 50;
            if ( $pa === $pb ) { return strcasecmp( (string) $a['label'], (string) $b['label'] ); }
            return ( $pa < $pb ) ? -1 : 1;
        } );
        return $norm;
    }

    /**
     * Ensure a minimal default source exists so admins can verify setup without add-ons.
     * Provides a simple "Payment Methods" panel listing active methods from the DB table.
     */
    public static function register_default_sources( $sources ) {
        if ( ! is_array( $sources ) ) { $sources = []; }
        if ( ! isset( $sources['methods'] ) ) {
            $sources['methods'] = [
                'label'    => __( 'Payment Methods', 'tpw-core' ),
                'priority' => 10,
                'callback' => [ __CLASS__, 'render_source_methods' ],
                'icon'     => 'list',
            ];
        }
        return $sources;
    }

    /**
     * Render callback for the default "Payment Methods" source.
     * Queries the tpw_payment_methods table (slug + active, or legacy method_key + enabled).
     */
    public static function render_source_methods() : void {
        global $wpdb;
    $active_slugs = [];
    $table = $wpdb->prefix . 'tpw_payment_methods';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) {
            $has_active      = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'active'" );
            $has_enabled     = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'enabled'" );
            $has_slug        = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'slug'" );
            $has_method_key  = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'method_key'" );
            $has_sort        = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'sort_order'" );
            $col_key   = $has_slug ? 'slug' : ( $has_method_key ? 'method_key' : '' );
            $col_flag  = $has_active ? 'active' : ( $has_enabled ? 'enabled' : '' );
            if ( $col_key && $col_flag ) {
                $order_by = $has_sort ? 'ORDER BY sort_order ASC' : '';
                $rows = (array) $wpdb->get_results( "SELECT {$col_key} AS slug, {$col_flag} AS enabled FROM {$table} WHERE {$col_flag} IN (1,'1','yes','on','true','enabled') {$order_by}" );
                foreach ( $rows as $r ) {
                    $slug = (string) $r->slug;
                    if ( $slug !== '' ) { $active_slugs[] = $slug; }
                }
            }
        }
        echo '<div class="tpw-card">';
        echo '  <h3>' . esc_html__( 'Active payment methods', 'tpw-core' ) . '</h3>';
        if ( ! empty( $active_slugs ) ) {
            echo '<ul class="tpw-list">';
            foreach ( $active_slugs as $s ) {
                echo '<li>' . esc_html( $s ) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'No active methods detected.', 'tpw-core' ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Server-side renderer for Phase 1+2 — inject Payments Hub after the profile when requested.
     *
     * @param object $member Current member object
     * @return void
     */
    public static function render_profile_payments( $member ) : void {
        // Only for logged-in visitors — shortcode/route already enforces, keep defensive here
        if ( ! is_user_logged_in() ) {
            return;
        }
        // Only render when explicitly on the payments section
        $section = isset($_GET['section']) ? sanitize_key( (string) $_GET['section'] ) : '';
        if ( $section !== 'payments' ) {
            return;
        }
        // Show only when at least one payment method is active
        if ( ! self::has_active_methods() ) {
            return;
        }
        // Prepare dynamic sources
        $sources = self::get_registered_sources();

        // Member-facing hub: do not expose the default "Payment Methods" panel.
        // This keeps gateway processing + admin Payment Methods management unchanged.
        if ( isset( $sources['methods'] ) ) {
            unset( $sources['methods'] );
        }
        $active_type_raw = isset($_GET['type']) ? (string) $_GET['type'] : '';
        $active_type = $active_type_raw !== '' ? sanitize_key( $active_type_raw ) : '';
        // Robust lookup: try common variants (dash/underscore, lowercased)
        $chosen_key = '';
        if ( $active_type !== '' ) {
            $candidates = array_unique([
                $active_type,
                strtolower($active_type),
                str_replace('-', '_', strtolower($active_type)),
                str_replace('_', '-', strtolower($active_type)),
            ]);
            foreach ( $candidates as $cand ) {
                if ( isset( $sources[$cand] ) ) { $chosen_key = $cand; break; }
            }
        }
        if ( $chosen_key === '' ) {
            // default to first source when available
            $first_key = $sources ? array_key_first( $sources ) : '';
            $chosen_key = is_string($first_key) ? $first_key : '';
        }
        $active_type = $chosen_key;
        $active_source = ( $active_type && isset($sources[$active_type]) ) ? $sources[$active_type] : null;

        // Render template (front-end safe include), variables available to template scope
        $tpl = trailingslashit( TPW_CORE_PATH ) . 'templates/members/profile/payments/index.php';
        if ( file_exists( $tpl ) ) {
            include $tpl;
        }
    }

    /**
     * Minimal CTA link under the default profile to access the Payments Hub.
     * Non-intrusive and only shown when at least one method is active.
     */
    public static function maybe_render_payments_link( $member ) : void {
        if ( ! is_user_logged_in() ) { return; }
        $section = isset($_GET['section']) ? sanitize_key( (string) $_GET['section'] ) : '';
        if ( $section === 'payments' ) { return; }
        if ( ! self::has_active_methods() ) { return; }
        $url = add_query_arg( 'section', 'payments' );
        echo '<div class="tpw-section" style="margin-top:16px;">';
        echo '  <a class="tpw-btn tpw-btn-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'My Payments', 'tpw-core' ) . '</a>';
        echo '</div>';
    }

    /**
     * Helper: determine if there are any active payment methods using manager, table, or options fallback.
     */
    public static function has_active_methods() : bool {
        if ( class_exists( 'TPW_Payments_Manager' ) && method_exists( 'TPW_Payments_Manager', 'has_active_methods' ) ) {
            return TPW_Payments_Manager::has_active_methods();
        }
        $active_methods = class_exists( 'TPW_Payments_Manager' ) ? (array) TPW_Payments_Manager::get_active_methods() : [];
        return ! empty( $active_methods );
    }
}
