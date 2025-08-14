<?php
class TPW_Menus_Admin_Edit {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
    }

    public static function add_menu_page() {
        add_submenu_page(
            'tpw-core',
            'Edit Menu',
            '', // Hidden from the sidebar
            'manage_options',
            'tpw-core-dining-menus-edit',
            [__CLASS__, 'render_edit_menu_page']
        );
    }

    public static function render_edit_menu_page() {
        global $wpdb;

        $menu_id = isset($_GET['menu_id']) ? intval($_GET['menu_id']) : 0;
        $menu = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}tpw_menus WHERE id = $menu_id");

        if (!$menu) {
            echo '<div class="notice notice-error"><p>Invalid menu ID.</p></div>';
            return;
        }

        $header_title = __( 'Edit Menu', 'tpw-core' );
        $header_desc  = __( 'Update menu details, courses, and pricing for your events.', 'tpw-core' );

        if ( function_exists( 'tpw_admin_output_header' ) ) {
            tpw_admin_output_header( $header_title, $header_desc );
            echo '<div class="wrap">';
        } elseif ( function_exists( 'flexievent_output_header' ) ) {
            flexievent_output_header( $header_title, $header_desc );
            echo '<div class="wrap">';
        } else {
            echo '<div class="wrap"><h1>' . esc_html( $header_title ) . '</h1>';
        }
                echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=tpw-core-dining-menus' ) ) . '" class="button button-secondary">&larr; Back to Manage Menus</a></p>';
        echo '<form method="post">';
        echo '<input type="hidden" name="menu_id" value="' . esc_attr($menu->id) . '" class="tpw-menu-id" />';
        echo '<input type="text" name="menu_name" placeholder="Menu Name" value="' . esc_attr($menu->name) . '" required class="widefat tpw-menu-name" />';
        echo '<br/><textarea name="menu_description" placeholder="Description" class="widefat tpw-menu-description" rows="3">' . esc_textarea($menu->description) . '</textarea>';
        echo '<br/><label class="tpw-menu-courses-label">Number of Courses: <input type="number" name="number_of_courses" min="1" max="30" value="' . intval($menu->number_of_courses) . '" required class="tpw-menu-courses" /></label>';
        echo '<br/><label class="tpw-menu-price-label">Price: <input type="text" name="price" pattern="^\\d+(\\.\\d{1,2})?$" value="' . esc_attr(number_format((float) $menu->price, 2)) . '" required class="tpw-menu-price" /></label>';
        echo '<br/><input type="submit" name="submit_menu" class="button button-primary tpw-menu-submit" value="Update Menu" />';
        echo '</form>';
        echo '</div>';
    }
}
