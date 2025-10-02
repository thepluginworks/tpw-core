<?php
if (!defined('ABSPATH')) { exit; }

class TPW_Noticeboard {
    public static function init() {
        add_action('init', [__CLASS__, 'register_cpt_and_tax']);
        add_filter('single_template', [__CLASS__, 'filter_single_template']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_single_assets']);
    }

    public static function register_cpt_and_tax() {
        $labels = [
            'name'               => __('Noticeboard', 'tpw-core'),
            'singular_name'      => __('Notice', 'tpw-core'),
            'add_new'            => __('Add New Notice', 'tpw-core'),
            'add_new_item'       => __('Add New Notice', 'tpw-core'),
            'edit_item'          => __('Edit Notice', 'tpw-core'),
            'new_item'           => __('New Notice', 'tpw-core'),
            'view_item'          => __('View Notice', 'tpw-core'),
            'search_items'       => __('Search Notices', 'tpw-core'),
            'not_found'          => __('No notices found', 'tpw-core'),
            'not_found_in_trash' => __('No notices found in Trash', 'tpw-core'),
            'menu_name'          => __('Noticeboard', 'tpw-core'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'show_in_rest'       => true,
            'has_archive'        => false,
            'exclude_from_search'=> true,
            'menu_icon'          => 'dashicons-megaphone',
            'supports'           => ['title','editor','excerpt','thumbnail','custom-fields'],
            'capability_type'    => 'post',
        ];
        register_post_type('tpw_notice', $args);

        $tax_labels = [
            'name'              => __('Notice Categories', 'tpw-core'),
            'singular_name'     => __('Notice Category', 'tpw-core'),
            'search_items'      => __('Search Notice Categories', 'tpw-core'),
            'all_items'         => __('All Notice Categories', 'tpw-core'),
            'parent_item'       => __('Parent Category', 'tpw-core'),
            'parent_item_colon' => __('Parent Category:', 'tpw-core'),
            'edit_item'         => __('Edit Category', 'tpw-core'),
            'update_item'       => __('Update Category', 'tpw-core'),
            'add_new_item'      => __('Add New Category', 'tpw-core'),
            'new_item_name'     => __('New Category Name', 'tpw-core'),
            'menu_name'         => __('Notice Categories', 'tpw-core'),
        ];
        $tax_args = [
            'hierarchical' => true,
            'labels'       => $tax_labels,
            'show_ui'      => true,
            'show_admin_column' => true,
            'query_var'    => true,
            'rewrite'      => [ 'slug' => 'notice-category' ],
            'show_in_rest' => true,
        ];
        register_taxonomy('tpw_notice_category', ['tpw_notice'], $tax_args);
    }

    public static function filter_single_template($template) {
        if (is_singular('tpw_notice')) {
            // Respect theme override if present
            $theme_template = locate_template(['single-tpw_notice.php']);
            if (empty($theme_template)) {
                $plugin_template = TPW_CORE_PATH . 'modules/notices/templates/single-tpw_notice.php';
                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
        }
        return $template;
    }

    public static function enqueue_single_assets() {
        if (is_singular('tpw_notice')) {
            wp_enqueue_style('tpw-notice-single', TPW_CORE_URL . 'modules/notices/assets/css/notice-single.css', [], '1.0');
        }
    }
}

TPW_Noticeboard::init();
