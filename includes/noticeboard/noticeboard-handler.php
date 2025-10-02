<?php
if (!defined('ABSPATH')) { exit; }

class TPW_Noticeboard_Handler {
    public static function init() {
        add_action('wp_ajax_tpw_notice_save', [__CLASS__, 'save_notice']);
        add_action('wp_ajax_tpw_notice_delete', [__CLASS__, 'delete_notice']);
        add_action('wp_ajax_tpw_notice_duplicate', [__CLASS__, 'duplicate_notice']);
        add_action('wp_ajax_tpw_notice_add_category', [__CLASS__, 'add_category']);
    }

    private static function check_caps_and_nonce($action) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'tpw-core')], 403);
        }
        $nonce = $_POST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, $action)) {
            wp_send_json_error(['message' => __('Security check failed.', 'tpw-core')], 400);
        }
    }

    public static function save_notice() {
        self::check_caps_and_nonce('tpw_notice_save');
        $notice_id = isset($_POST['notice_id']) ? intval($_POST['notice_id']) : 0;
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $excerpt = sanitize_textarea_field($_POST['excerpt'] ?? '');
        $category = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $thumb_id = isset($_POST['thumbnail_id']) ? intval($_POST['thumbnail_id']) : 0;

        $postarr = [
            'post_type'   => 'tpw_notice',
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_content'=> $content,
            'post_excerpt'=> $excerpt,
        ];
        if ($notice_id) { $postarr['ID'] = $notice_id; }
        $id = $notice_id ? wp_update_post($postarr, true) : wp_insert_post($postarr, true);
        if (is_wp_error($id)) {
            wp_send_json_error(['message' => $id->get_error_message()], 400);
        }
        if ($category) { wp_set_object_terms($id, [$category], 'tpw_notice_category', false); }
        if ($thumb_id) { set_post_thumbnail($id, $thumb_id); }

        wp_send_json_success(['message' => __('Notice saved.', 'tpw-core'), 'id' => $id]);
    }

    public static function delete_notice() {
        self::check_caps_and_nonce('tpw_notice_delete');
        $notice_id = isset($_POST['notice_id']) ? intval($_POST['notice_id']) : 0;
        if (!$notice_id) wp_send_json_error(['message' => __('Invalid notice ID.', 'tpw-core')], 400);
        $res = wp_trash_post($notice_id);
        if (!$res) wp_send_json_error(['message' => __('Delete failed.', 'tpw-core')], 400);
        wp_send_json_success(['message' => __('Notice deleted.', 'tpw-core')]);
    }

    public static function duplicate_notice() {
        self::check_caps_and_nonce('tpw_notice_duplicate');
        $notice_id = isset($_POST['notice_id']) ? intval($_POST['notice_id']) : 0;
        if (!$notice_id) wp_send_json_error(['message' => __('Invalid notice ID.', 'tpw-core')], 400);
        $post = get_post($notice_id);
        if (!$post || $post->post_type !== 'tpw_notice') wp_send_json_error(['message' => __('Not found.', 'tpw-core')], 404);

        $new_postarr = [
            'post_type'   => 'tpw_notice',
            'post_status' => 'draft',
            'post_title'  => $post->post_title . ' (Copy)',
            'post_content'=> $post->post_content,
            'post_excerpt'=> $post->post_excerpt,
        ];
        $new_id = wp_insert_post($new_postarr, true);
        if (is_wp_error($new_id)) wp_send_json_error(['message' => $new_id->get_error_message()], 400);

        // Copy terms
        $terms = wp_get_object_terms($notice_id, 'tpw_notice_category', ['fields' => 'ids']);
        if (!is_wp_error($terms)) { wp_set_object_terms($new_id, $terms, 'tpw_notice_category', false); }
        // Copy thumbnail
        $thumb_id = get_post_thumbnail_id($notice_id);
        if ($thumb_id) set_post_thumbnail($new_id, $thumb_id);

        wp_send_json_success(['message' => __('Notice duplicated.', 'tpw-core'), 'id' => $new_id]);
    }

    public static function add_category() {
        self::check_caps_and_nonce('tpw_notice_add_category');
        $name = sanitize_text_field($_POST['name'] ?? '');
        if ($name === '') {
            wp_send_json_error(['message' => __('Category name is required.','tpw-core')], 400);
        }
        $result = wp_insert_term($name, 'tpw_notice_category');
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }
        $term = get_term($result['term_id'], 'tpw_notice_category');
        wp_send_json_success(['message' => __('Category added.','tpw-core'), 'term' => [ 'id' => $term->term_id, 'name' => $term->name ]]);
    }
}

TPW_Noticeboard_Handler::init();
