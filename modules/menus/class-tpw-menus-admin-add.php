<?php
class TPW_Menus_Admin_Add {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
    }

    public static function add_menu_page() {
        add_submenu_page(
            'tpw-core',
            'Add New Menu',
            'Add New Menu',
            'manage_options',
            'tpw-core-dining-menus-add',
            [__CLASS__, 'render_add_menu_page']
        );
    }

    public static function render_add_menu_page() {
        echo '<div class="tpw-admin-ui"><div class="wrap">';
        echo '<h1>Add New Menu</h1>';
        echo '<form method="post">';
        echo '<input type="text" name="menu_name" placeholder="Menu Name" required class="widefat" />';
        echo '<br/><textarea name="menu_description" placeholder="Description" class="widefat" rows="3"></textarea>';
        echo '<br/><label>Number of Courses: <input type="number" name="number_of_courses" min="1" max="30" value="3" required /></label>';
        echo '<br/><label>Price: <input type="text" name="price" pattern="^\\d+(\\.\\d{1,2})?$" value="0.00" required /></label>';
        echo '<br/><input type="submit" name="submit_menu" class="button button-primary" value="Save Menu" />';
        echo '</form>';
        echo '</div></div>';
    }
}