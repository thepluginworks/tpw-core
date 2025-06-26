<?php

class TPW_Course_Choice_Form_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_submenu_page']);
        add_action('admin_init', [__CLASS__, 'handle_form_submit']);
    }

    public static function register_submenu_page() {
        add_submenu_page(
            'tools.php', // hidden from menu but safe
            'Course Choice Form',
            'Course Choice Form',
            'manage_options',
            'tpw-course-choice-form',
            [__CLASS__, 'render_page']
        );
    }

    public static function handle_form_submit() {
        if (!isset($_POST['submit_course_choice'])) {
            return;
        }

        $label = sanitize_textarea_field( wp_unslash( $_POST['label'] ) );
        $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ) );
        $menu_id = intval($_POST['menu_id']);
        $course_number = intval($_POST['course_number']);
        $choice_id = isset($_POST['choice_id']) ? intval($_POST['choice_id']) : 0;

        if ($choice_id) {
            TPW_Course_Choices_Manager::update_choice($choice_id, $label, $description);
        } else {
            TPW_Course_Choices_Manager::insert_choice($menu_id, $course_number, $label, $description);
        }

        wp_redirect(admin_url('admin.php?page=tpw-course-choices&menu_id=' . $menu_id));
        exit;
    }

    public static function render_page() {
        echo '<div class="wrap"><h1>Course Choice</h1>';

        $choice_id = isset($_GET['choice_id']) ? intval($_GET['choice_id']) : 0;
        $menu_id = isset($_GET['menu_id']) ? intval($_GET['menu_id']) : 0;
        $course_number = isset($_GET['course_number']) ? intval($_GET['course_number']) : 0;

        $choice = $choice_id ? TPW_Course_Choices_Manager::get_choice_by_id($choice_id) : null;

        $label_value = $choice ? esc_attr( wp_unslash( $choice->label ) ) : '';
        $desc_value = $choice ? esc_textarea( wp_unslash( $choice->description ) ) : '';

        echo '<form method="post">';
        echo '<table class="form-table">';
        echo '<tr><th><label for="label">Label</label></th><td><input name="label" id="label" type="text" value="' . $label_value . '" required /></td></tr>';
        echo '<tr><th><label for="description">Description</label></th><td><textarea name="description" id="description">' . $desc_value . '</textarea></td></tr>';
        echo '</table>';

        echo '<input type="hidden" name="menu_id" value="' . esc_attr($menu_id) . '">';
        echo '<input type="hidden" name="course_number" value="' . esc_attr($course_number) . '">';
        if ($choice_id) {
            echo '<input type="hidden" name="choice_id" value="' . esc_attr($choice_id) . '">';
        }

        echo '<p><input type="submit" name="submit_course_choice" class="button-primary" value="' . ($choice_id ? 'Update' : 'Add') . ' Choice"></p>';
        echo '</form></div>';
    }
}

TPW_Course_Choice_Form_Admin::init();