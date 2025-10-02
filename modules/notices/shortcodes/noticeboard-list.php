<?php
if (!defined('ABSPATH')) { exit; }

class TPW_Noticeboard_List_Shortcode {
    public static function init() {
        add_shortcode('tpw_noticeboard_list', [__CLASS__, 'render']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue'], 100);
    }

    public static function maybe_enqueue() {
        if (!is_singular()) return;
        global $post; if (!$post) return;
        if (has_shortcode($post->post_content, 'tpw_noticeboard_list')) {
            wp_enqueue_media();
            $base = TPW_CORE_URL . 'modules/notices/';
            wp_enqueue_script('tpw-noticeboard', $base . 'assets/js/noticeboard.js', ['jquery'], '1.0', true);
            wp_localize_script('tpw-noticeboard', 'TPWNoticeboard', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonces' => [
                    'save' => wp_create_nonce('tpw_notice_save'),
                    'delete' => wp_create_nonce('tpw_notice_delete'),
                    'duplicate' => wp_create_nonce('tpw_notice_duplicate'),
                    'addCategory' => wp_create_nonce('tpw_notice_add_category'),
                ],
                'caps' => [ 'isAdmin' => current_user_can('manage_options') ],
            ]);
            wp_enqueue_style('tpw-noticeboard', $base . 'assets/css/noticeboard.css', [], '1.0');
            // Ensure the global UI CSS from Core is available so shared components (e.g., member avatar in nav) are styled
            $ui_css = TPW_CORE_PATH . 'assets/css/tpw-ui.css';
            $ui_ver = file_exists($ui_css) ? filemtime($ui_css) : ( defined('TPW_CORE_VERSION') ? TPW_CORE_VERSION : '1.0' );
            wp_enqueue_style('tpw-ui', TPW_CORE_URL . 'assets/css/tpw-ui.css', [], $ui_ver);
            // Ensure global button styles are available
            $css_file = TPW_CORE_PATH . 'assets/css/tpw-buttons.css';
            $ver = file_exists($css_file) ? filemtime($css_file) : ( defined('TPW_CORE_VERSION') ? TPW_CORE_VERSION : '1.0' );
            wp_enqueue_style('tpw-buttons', TPW_CORE_URL . 'assets/css/tpw-buttons.css', [], $ver);
        }
    }

    public static function render($atts = []) {
        $atts = shortcode_atts([
            'category' => '',
            'read_more' => 'true',
        ], $atts, 'tpw_noticeboard_list');

        $args = [
            'post_type'      => 'tpw_notice',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ];
        if (!empty($atts['category'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'tpw_notice_category',
                'field'    => 'slug',
                'terms'    => sanitize_title($atts['category']),
            ]];
        }
        $q = new WP_Query($args);

        ob_start();
        if (current_user_can('manage_options')) {
            echo '<div class="tpw-notice-admin-actions">';
            echo '<button class="tpw-btn tpw-btn-primary tpw-notice-add">' . esc_html__('Add New Notice', 'tpw-core') . '</button>';
            echo '</div>';
        }

        echo '<div class="tpw-notice-list">';
        if ($q->have_posts()) {
            while ($q->have_posts()) { $q->the_post();
                $id = get_the_ID();
                $title = get_the_title();
                $excerpt = get_the_excerpt();
                $thumb = get_the_post_thumbnail_url($id, 'medium');
                $terms = get_the_terms($id, 'tpw_notice_category');
                $cat = (!is_wp_error($terms) && !empty($terms)) ? esc_html($terms[0]->name) : '';
                echo '<div class="tpw-notice-card" data-id="' . esc_attr($id) . '">';
                if ($thumb) echo '<div class="tpw-notice-thumb"><img src="' . esc_url($thumb) . '" alt=""/></div>';
                echo '<div class="tpw-notice-body">';
                echo '<h3 class="tpw-notice-title">' . esc_html($title) . '</h3>';
                if ($cat) echo '<div class="tpw-notice-cat">' . $cat . '</div>';
                if ($excerpt) echo '<div class="tpw-notice-excerpt">' . esc_html($excerpt) . '</div>';
                if ($atts['read_more'] === 'true') echo '<a class="tpw-notice-more" href="' . esc_url(get_permalink($id)) . '">' . esc_html__('Read More', 'tpw-core') . '</a>';
                echo '</div>';
                if (current_user_can('manage_options')) {
                    echo '<div class="tpw-notice-actions">';
                    echo '<button class="tpw-btn tpw-btn-secondary tpw-notice-edit">' . esc_html__('Edit', 'tpw-core') . '</button>';
                    echo '<button class="tpw-btn tpw-btn-secondary tpw-notice-duplicate">' . esc_html__('Duplicate', 'tpw-core') . '</button>';
                    echo '<button class="tpw-btn tpw-btn-danger tpw-notice-delete">' . esc_html__('Delete', 'tpw-core') . '</button>';
                    echo '</div>';
                }
                echo '</div>';
            }
            wp_reset_postdata();
        } else {
            echo '<p>' . esc_html__('No notices found.', 'tpw-core') . '</p>';
        }
        echo '</div>';

        if (current_user_can('manage_options')) {
            include TPW_CORE_PATH . 'modules/notices/templates/form.php';
        }

        return ob_get_clean();
    }
}

TPW_Noticeboard_List_Shortcode::init();
