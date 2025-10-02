<?php
if (!defined('ABSPATH')) { exit; }

class TPW_Noticeboard {
    public static function init() {
        add_action('init', [__CLASS__, 'register_cpt_and_tax']);
    }

    public static function register_cpt_and_tax() {
        // CPT: tpw_notice
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

        // Taxonomy: tpw_notice_category
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
}

TPW_Noticeboard::init();
