<?php
class TPW_Menus {

    public static function init() {
        // Hook into the FlexiEvent event page (after main event content)
        add_action( 'tpw_event_details_after_meta', [ __CLASS__, 'hook_after_event_content' ], 5 );
        // Back-compat: also listen to the older hook
        add_action( 'tpw_after_event_content', [ __CLASS__, 'hook_after_event_content' ], 5 );
    }

    protected static function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( $message );
        }
    }

    public static function hook_after_event_content( $event ) {
        self::log('[TPW_Menus] hook_after_event_content() received: ' . print_r($event, true));

        $event_id = 0;
        // Accept multiple shapes: array, object, or direct int
        if ( is_numeric( $event ) ) {
            $event_id = (int) $event;
        } elseif ( is_array( $event ) ) {
            if ( isset( $event['event_id'] ) ) {
                $event_id = (int) $event['event_id'];
            } elseif ( isset( $event['id'] ) ) {
                $event_id = (int) $event['id'];
            }
        } elseif ( is_object( $event ) ) {
            if ( isset( $event->event_id ) ) {
                $event_id = (int) $event->event_id;
            } elseif ( isset( $event->id ) ) {
                $event_id = (int) $event->id;
            }
        }

        if ( $event_id === 0 && ( ( is_array( $event ) && isset( $event['post_id'] ) ) || ( is_object( $event ) && isset( $event->post_id ) ) ) ) {
            global $wpdb;
            $post_id = is_array( $event ) ? (int) $event['post_id'] : (int) $event->post_id;
            $event_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT event_id FROM {$wpdb->prefix}tpw_events WHERE post_id = %d LIMIT 1",
                $post_id
            ));
        }

        self::log('[TPW_Menus] Resolved event_id: ' . $event_id);

        static $rendered = [];
        if ( $event_id > 0 ) {
            if ( isset( $rendered[ $event_id ] ) ) {
                self::log('[TPW_Menus] Duplicate render skipped for event_id: ' . $event_id);
                return;
            }
            $rendered[ $event_id ] = true;
            self::render_menu_modal_trigger( $event_id );
        }
    }

    public static function event_has_menu( $event_id ) {
        global $wpdb;
        self::log('[TPW_Menus] event_has_menu() checking for event_id: ' . $event_id . ' in table: ' . $wpdb->prefix . 'tpw_event_menu_relationship');
        $result = (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT menu_id FROM {$wpdb->prefix}tpw_event_menu_relationship WHERE event_id = %d LIMIT 1",
            $event_id
        ));
        self::log('[TPW_Menus] event_has_menu() result: ' . ( $result ? 'true' : 'false' ));
        return $result;
    }

    public static function get_menu_payload( $event_id ) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $menu_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT menu_id FROM {$prefix}tpw_event_menu_relationship WHERE event_id = %d LIMIT 1",
            $event_id
        ));
        if ( ! $menu_id ) return null;

        $menu = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, description, number_of_courses, price
             FROM {$prefix}tpw_menus
             WHERE id = %d",
            $menu_id
        ), ARRAY_A );

        if ( ! $menu ) return null;

        $courses = [];
        $course_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT course_number, course_name
             FROM {$prefix}tpw_menu_courses
             WHERE menu_id = %d
             ORDER BY course_number ASC",
            $menu_id
        ), ARRAY_A );

        foreach ( $course_rows as $row ) {
            $courses[ (int) $row['course_number'] ] = [
                'course_name' => $row['course_name'],
                'choices'     => [],
            ];
        }

        $choice_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT course_number, label, description
             FROM {$prefix}tpw_menu_choices
             WHERE menu_id = %d
             ORDER BY course_number ASC, id ASC",
            $menu_id
        ), ARRAY_A );

        foreach ( $choice_rows as $choice ) {
            $num = (int) $choice['course_number'];
            if ( ! isset( $courses[ $num ] ) ) {
                $courses[ $num ] = [ 'course_name' => '', 'choices' => [] ];
            }
            $courses[ $num ]['choices'][] = [
                'label'       => $choice['label'],
                'description' => $choice['description'],
            ];
        }

        return [
            'menu'    => $menu,
            'courses' => $courses,
        ];
    }

    public static function flag_need_ui_assets() {
        static $enqueued = false;
        if ( $enqueued ) {
            return;
        }
        $enqueued = true;

        $enqueue = function () {
            // Register + enqueue module UI assets
            $css = trailingslashit( TPW_CORE_URL ) . 'modules/menus/css/tpw-menu-ui.css';
            $js  = trailingslashit( TPW_CORE_URL ) . 'modules/menus/js/tpw-menu-ui.js';

            wp_register_style( 'tpw-menu-ui', $css, [], defined( 'TPW_CORE_VERSION' ) ? TPW_CORE_VERSION : null );
            wp_register_script( 'tpw-menu-ui', $js, [], defined( 'TPW_CORE_VERSION' ) ? TPW_CORE_VERSION : null, true );

            wp_enqueue_style( 'tpw-menu-ui' );
            wp_enqueue_script( 'tpw-menu-ui' );
        };

        if ( did_action( 'wp_enqueue_scripts' ) ) {
            // We are late in the lifecycle; enqueue immediately.
            $enqueue();
        } else {
            add_action( 'wp_enqueue_scripts', $enqueue );
        }
    }

    public static function render_menu_modal_trigger( $event_id ) {
        self::log('[TPW_Menus] render_menu_modal_trigger() called for event_id: ' . $event_id);
        if ( ! self::event_has_menu( $event_id ) ) {
            self::log('[TPW_Menus] event_has_menu() returned false for event_id: ' . $event_id . ' - aborting render.');
            return;
        }

        // Ensure modal UI assets are available
        self::flag_need_ui_assets();

        $template = locate_template( 'tpw-core/menus/menu-modal.php' );
        if ( ! $template ) {
            $template = TPW_CORE_PATH . 'modules/menus/templates/menu-modal.php';
        }
        self::log('[TPW_Menus] Including menu modal template: ' . $template);
        $event_id = (int) $event_id;
        // Wrap the modal in the same row structure as event details
        echo '<div class="tpw-event-row tpw-menu-row"><div class="tpw-event-column-full">';
        include $template;
        echo '</div></div>';
    }

    // --- Optional shim methods for template compatibility ---
    public static function tpw_core_event_has_menu( $event_id ) { return self::event_has_menu( $event_id ); }
    public static function tpw_core_get_menu_payload( $event_id ) { return self::get_menu_payload( $event_id ); }
    public static function tpw_core_flag_need_ui_assets() { self::flag_need_ui_assets(); }
}
// Global helper wrappers (only if not already defined)
if ( ! function_exists( 'tpw_core_event_has_menu' ) ) {
    function tpw_core_event_has_menu( $event_id ) { return TPW_Menus::event_has_menu( $event_id ); }
}
if ( ! function_exists( 'tpw_core_get_menu_payload' ) ) {
    function tpw_core_get_menu_payload( $event_id ) { return TPW_Menus::get_menu_payload( $event_id ); }
}
if ( ! function_exists( 'tpw_core_flag_need_ui_assets' ) ) {
    function tpw_core_flag_need_ui_assets() { TPW_Menus::flag_need_ui_assets(); }
}