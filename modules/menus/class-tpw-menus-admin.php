<?php

class TPW_Menus_Admin {

    public static function init() {
        error_log('TPW_Menus_Admin::init() called');
        add_action('admin_menu', [__CLASS__, 'maybe_register']);
        add_action('admin_init', [__CLASS__, 'handle_menu_form']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets($hook) {
        if (!isset($_GET['page']) || strpos($_GET['page'], 'tpw-core-dining-menus') !== 0) {
            return;
        }

        wp_enqueue_style(
            'tpw-menus-css',
            plugin_dir_url(__FILE__) . 'css/admin-menus.css',
            [],
            null
        );
    }

    public static function handle_menu_form() {
        // Handle secure menu deletion via GET
        if (isset($_GET['delete_menu']) && current_user_can('manage_options')) {
            $menu_id = intval($_GET['delete_menu']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_menu_' . $menu_id)) {
                TPW_Menus_Manager::delete_menu($menu_id);
                wp_redirect(admin_url('admin.php?page=tpw-core-dining-menus&deleted=1'));
                exit;
            }
        }
        if (!isset($_POST['submit_menu'])) {
            return;
        }
        error_log('Submitted Menu Form: ' . print_r($_POST, true));

        $name = sanitize_text_field($_POST['menu_name']);
        $description = sanitize_textarea_field($_POST['menu_description']);
        $number_of_courses = intval($_POST['number_of_courses']);
        $price = floatval($_POST['price']);
        $menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : 0;

        if ($menu_id) {
            TPW_Menus_Manager::update_menu($menu_id, $name, $description, $number_of_courses, $price);
            wp_redirect(admin_url('admin.php?page=tpw-core-dining-menus&updated=1'));
        } else {
            // Create a new menu, then redirect to Course Choices for that menu
            $inserted_id = TPW_Menus_Saver::save_menu( $name, $description, $number_of_courses, $price );
            if ( ! $inserted_id ) {
                // Fallback to list with error flag if insert failed
                error_log( '[TPW Menus] save_menu() failed; redirecting back to list.' );
                wp_safe_redirect( admin_url( 'admin.php?page=tpw-core-dining-menus&saved=0' ) );
                exit;
            }
            // Build target URL robustly
            $target = admin_url( 'admin.php?page=tpw-course-choices' );
            $url    = add_query_arg( 'menu_id', intval( $inserted_id ), $target );
            wp_safe_redirect( $url );
        }
        exit;
    }

    public static function render_menu_page() {
        //error_log('TPW_Menus_Admin::render_menu_page() called');
        global $wpdb;

        $menus = TPW_Menus_Manager::get_all_menus();

        if ( function_exists( 'tpw_admin_output_header' ) ) {
            tpw_admin_output_header(
                __( 'Manage Menus', 'tpw-core' ),
                __( 'Add, edit, and delete dining menus for your events. For Admins and Secretaries.', 'tpw-core' )
            );
            echo '<div class="tpw-admin-ui"><div class="wrap">';
        } elseif ( function_exists( 'flexievent_output_header' ) ) {
            flexievent_output_header(
                __( 'Manage Menus', 'tpw-core' ),
                __( 'Add, edit, and delete dining menus for your events. For Admins and Secretaries.', 'tpw-core' )
            );
            echo '<div class="tpw-admin-ui"><div class="wrap">';
        } else {
            echo '<div class="tpw-admin-ui"><div class="wrap"><h1>' . esc_html__( 'Manage Menus', 'tpw-core' ) . '</h1>';
        }

        echo '<a href="' . esc_url(admin_url('admin.php?page=tpw-core-dining-menus-add')) . '" class="page-title-action">Add New Menu</a>';

        echo '<h2>Existing Menus</h2>';
        if ($menus) {
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Courses</th><th>Price</th></tr></thead>';
            echo '<tbody>';
            foreach ($menus as $menu) {
                echo '<tr>';
                echo '<td>' . esc_html($menu->id) . '</td>';
                $courses_url = admin_url('admin.php?page=tpw-course-choices&menu_id=' . intval($menu->id));
                $edit_url = admin_url('admin.php?page=tpw-core-dining-menus-edit&menu_id=' . intval($menu->id));
                $delete_url = wp_nonce_url(admin_url('admin.php?page=tpw-core-dining-menus&delete_menu=' . intval($menu->id)), 'delete_menu_' . intval($menu->id));
                echo '<td>';
                echo '<strong>' . esc_html($menu->name) . '</strong>';
                echo '<div class="row-actions">';
                echo '<span class="edit"><a href="' . esc_url($edit_url) . '">Edit</a></span> | ';
                echo '<span class="courses"><a href="' . esc_url($courses_url) . '">Courses</a></span> | ';
                echo '<span class="delete"><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Are you sure you want to delete this menu?\')">Delete</a></span>';
                echo '</div>';
                echo '</td>';
                echo '<td>' . esc_html($menu->description) . '</td>';
                echo '<td>' . esc_html($menu->number_of_courses) . '</td>';
                echo '<td>' . esc_html(number_format($menu->price, 2)) . '</td>';

                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No menus found.</p>';
        }

        echo '</div></div>';
    }

    public static function maybe_register() {
        // This method only exists to comply with admin_menu hook structure
        // Submenu is now registered through core menu system
        //error_log('TPW_Menus_Admin::maybe_register() was called');
    }
}
