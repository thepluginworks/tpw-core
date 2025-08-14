<?php

class TPW_Course_Choices_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_submenu_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function register_submenu_page() {
        add_submenu_page(
            '',
            'Course Choices',
            'Course Choices',
            'manage_options',
            'tpw-course-choices',
            [__CLASS__, 'render_page']
        );
    }

    public static function hide_hidden_pages() {
        // Hide these from the FlexiEvent submenu but keep them associated with it
        remove_submenu_page('flexievent', 'tpw-course-choices');
    }

    public static function render_page() {
        if (isset($_POST['rename_course']) && check_admin_referer('rename_course_action')) {
            $menu_id = intval($_POST['menu_id']);
            $course_number = intval($_POST['course_number']);
            $new_name = sanitize_text_field($_POST['course_name']);
            TPW_Menu_Courses_Manager::set_course_name($menu_id, $course_number, $new_name);
            echo '<div class="updated"><p>Course name updated.</p></div>';
        }

        if (isset($_GET['delete_choice']) && isset($_GET['_wpnonce'])) {
            $delete_id = intval($_GET['delete_choice']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_choice_' . $delete_id)) {
                TPW_Course_Choices_Manager::delete_choice($delete_id);
                echo '<div class="updated"><p>Choice deleted.</p></div>';
            } else {
                echo '<div class="error"><p>Security check failed.</p></div>';
            }
        }

        if ( function_exists( 'tpw_admin_output_header' ) ) {
            tpw_admin_output_header(
                __( 'Manage Course Options', 'tpw-core' ),
                __( 'View and edit available choices for each course in your dining menus. For Admins and Secretaries.', 'tpw-core' )
            );
        } elseif ( function_exists( 'flexievent_output_header' ) ) {
            flexievent_output_header(
                __( 'Manage Course Options', 'tpw-core' ),
                __( 'View and edit available choices for each course in your dining menus. For Admins and Secretaries.', 'tpw-core' )
            );
        } else {
            echo '<div class="wrap"><h1>' . esc_html__( 'Manage Course Options', 'tpw-core' ) . '</h1>';
        }
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=tpw-core-dining-menus')) . '" class="button button-secondary">&larr; Back to Dining Menus</a></p>';
        echo '<div class="wrap">';

        $menu_id = isset($_GET['menu_id']) ? intval($_GET['menu_id']) : 0;

        if ($menu_id) {
            $menu = TPW_Menus_Manager::get_menu_by_id($menu_id);
            echo '<h2>Course options for menu: <strong>' . esc_html($menu->name) . '</strong> <span style="font-size:0.7em;color:gray;">(id:' . esc_html($menu_id) . ')</span></h2>';
            if (!$menu) {
                echo '<div class="error"><p>Menu not found.</p></div>';
                echo '</div>';
                return;
            }
            
            for ($i = 1; $i <= intval($menu->number_of_courses); $i++) {
                echo '<div class="course-section">';
                $course_name = TPW_Menu_Courses_Manager::get_course_name($menu_id, $i);
                $heading = $course_name ? esc_html($course_name) : 'Course ' . $i;

                echo '<div class="course-header">';
                echo '<h2 id="course_heading_' . esc_attr($i) . '">';
                echo '<span class="course-name">' . $heading . '</span> ';
                echo '<button class="rename-trigger button-link small">Rename</button>';
                echo '</h2>';

                echo '<form method="post" class="rename-form" style="display: none; margin-bottom: 1em;">';
                wp_nonce_field('rename_course_action');
                echo '<input type="hidden" name="menu_id" value="' . esc_attr($menu_id) . '">';
                echo '<input type="hidden" name="course_number" value="' . esc_attr($i) . '">';
                echo '<input type="text" name="course_name" value="' . esc_attr($heading) . '" />';
                echo '<button type="submit" name="rename_course" class="button button-primary">Confirm</button> ';
                echo '<button type="button" class="button cancel-rename">Cancel</button>';
                echo '</form>';
                echo '</div>';

                $choices = TPW_Course_Choices_Manager::get_choices_for_course($menu_id, $i);
                if ($choices) {
                    echo '<p><em>' . count($choices) . ' choice(s)</em></p>';
                    echo '<ul>';
                    foreach ($choices as $choice) {
                        $edit_url = admin_url('admin.php?page=tpw-course-choice-form&choice_id=' . intval($choice->id) . '&menu_id=' . intval($menu_id) . '&course_number=' . intval($i));
                        $delete_url = wp_nonce_url(
                            admin_url('admin.php?page=tpw-course-choices&menu_id=' . intval($menu_id) . '&delete_choice=' . intval($choice->id)),
                            'delete_choice_' . $choice->id
                        );
                        echo '<li><strong>' . esc_html( wp_unslash( $choice->label ) ) . '</strong> ';
                        echo '<a href="' . esc_url($edit_url) . '">Edit</a> | ';
                        echo '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Are you sure you want to delete this choice?\')">Delete</a>';
                        if ($choice->description) {
                            echo '<br/><em>' . esc_html( wp_unslash( $choice->description ) ) . '</em>';
                        }
                        echo '</li>';
                    }
                    echo '</ul>';
                    $add_url = admin_url('admin.php?page=tpw-course-choice-form&menu_id=' . intval($menu_id) . '&course_number=' . $i);
                    echo '<p><a href="' . esc_url($add_url) . '" class="button add-new-option">Add New Dish</a></p>';
                } else {
                    echo '<p>No options added yet for this course.</p>';
                    $add_url = admin_url('admin.php?page=tpw-course-choice-form&menu_id=' . intval($menu_id) . '&course_number=' . $i);
                    echo '<p><a href="' . esc_url($add_url) . '" class="button add-new-option">Add New Dish</a></p>';
                }
                echo '</div>';
            }
        } else {
            echo '<p>Please select a menu to manage its course options.</p>';
        }

        echo '</div>';
    }
    public static function enqueue_scripts($hook) {
        if (!isset($_GET['page']) || $_GET['page'] !== 'tpw-course-choices') {
            return;
        }

        wp_enqueue_script(
            'tpw-course-choices-js',
            plugin_dir_url(__FILE__) . 'js/admin-course-choices.js',
            ['jquery'],
            null,
            true
        );

        wp_enqueue_style(
            'tpw-menus-css',
            plugin_dir_url(__FILE__) . 'css/admin-menus.css',
            [],
            filemtime(__DIR__ . '/css/admin-menus.css')
        );
    }
}

TPW_Course_Choices_Admin::init();