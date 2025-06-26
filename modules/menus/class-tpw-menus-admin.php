<?php

class TPW_Menus_Admin {

    public static function init() {
        error_log('TPW_Menus_Admin::init() called');
        add_action('admin_menu', [__CLASS__, 'maybe_register']);
        add_action('admin_init', [__CLASS__, 'handle_menu_form']);
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
            // Ensure all 4 arguments are passed to save_menu
            //error_log('Calling save_menu() with: ' . json_encode([$name, $description, $number_of_courses, $price]));
            $inserted_id = TPW_Menus_Saver::save_menu($name, $description, $number_of_courses, $price);
            wp_redirect(admin_url('admin.php?page=tpw-core-dining-menus&saved=1'));
        }
        exit;
    }

    public static function render_menu_page() {
        //error_log('TPW_Menus_Admin::render_menu_page() called');
        global $wpdb;

        $menus = TPW_Menus_Manager::get_all_menus();

        $edit_menu_id = isset($_GET['edit_menu']) ? intval($_GET['edit_menu']) : 0;
        $menu_to_edit = $edit_menu_id ? TPW_Menus_Manager::get_menu_by_id($edit_menu_id) : null;

        echo '<div class="wrap">';
        echo '<h1>Manage Menus</h1>';

        echo '<form method="post">';
        echo '<h2>' . ($menu_to_edit ? 'Edit Menu' : 'Add New Menu') . '</h2>';

        $name_value = $menu_to_edit ? esc_attr($menu_to_edit->name) : '';
        $desc_value = $menu_to_edit ? esc_textarea($menu_to_edit->description) : '';
        $courses_value = (isset($menu_to_edit->number_of_courses)) ? intval($menu_to_edit->number_of_courses) : 3;
        $price_value = isset($menu_to_edit->price) ? esc_attr(number_format((float) $menu_to_edit->price, 2)) : '0.00';

        echo '<input type="text" name="menu_name" placeholder="Menu Name" value="' . $name_value . '" required />';
        echo '<br/><textarea name="menu_description" placeholder="Description">' . $desc_value . '</textarea>';
        echo '<br/><label>Number of Courses: <input type="number" name="number_of_courses" min="1" max="30" value="' . $courses_value . '" required /></label>';
        echo '<br/><label>Price: <input type="text" name="price" pattern="^\d+(\.\d{1,2})?$" value="' . $price_value . '" required /></label>';

        if ($menu_to_edit) {
            echo '<input type="hidden" name="menu_id" value="' . esc_attr($menu_to_edit->id) . '" />';
        }

        echo '<br/><input type="submit" name="submit_menu" class="button button-primary" value="' . ($menu_to_edit ? 'Update Menu' : 'Save Menu') . '" />';
        echo '</form>';

        echo '<h2>Existing Menus</h2>';
        if ($menus) {
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Courses</th><th>Price</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            foreach ($menus as $menu) {
                echo '<tr>';
                echo '<td>' . esc_html($menu->id) . '</td>';
                echo '<td><a href="?page=tpw-core-dining-menus&edit_menu=' . intval($menu->id) . '">' . esc_html($menu->name) . '</a></td>';
                echo '<td>' . esc_html($menu->description) . '</td>';
                echo '<td>' . esc_html($menu->number_of_courses) . '</td>';
                echo '<td>' . esc_html(number_format($menu->price, 2)) . '</td>';
                $courses_url = admin_url('admin.php?page=tpw-course-choices&menu_id=' . intval($menu->id));
                $delete_url = wp_nonce_url(admin_url('admin.php?page=tpw-core-dining-menus&delete_menu=' . intval($menu->id)), 'delete_menu_' . intval($menu->id));

                echo '<td>';
                echo '<a href="' . esc_url($courses_url) . '" class="button">Courses</a> ';
                echo '<a href="' . esc_url($delete_url) . '" class="button delete" onclick="return confirm(\'Are you sure you want to delete this menu?\')">Delete</a>';
                echo '</td>';

                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No menus found.</p>';
        }

        echo '</div>';
    }

    public static function maybe_register() {
        // This method only exists to comply with admin_menu hook structure
        // Submenu is now registered through core menu system
        //error_log('TPW_Menus_Admin::maybe_register() was called');
    }
}
