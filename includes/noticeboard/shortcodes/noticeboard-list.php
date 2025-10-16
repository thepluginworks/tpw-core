<?php
if (!defined('ABSPATH')) { exit; }

class TPW_Noticeboard_List_Shortcode {
    public static function init() {
        add_shortcode('tpw_noticeboard_list', [__CLASS__, 'render']);
        // Load after most theme/page‑builder styles so our button rules can win on specificity + order
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue'], 100);
    }

    public static function maybe_enqueue() {
        if (!is_singular()) return;
        global $post; if (!$post) return;
        if (has_shortcode($post->post_content, 'tpw_noticeboard_list')) {
            wp_enqueue_media();
            wp_enqueue_script('tpw-noticeboard', TPW_CORE_URL . 'assets/js/noticeboard.js', ['jquery'], '1.1', true);
            // Determine Noticeboard management capability strictly via members flags: is_admin OR is_noticeboard_admin
            $can_manage = false;
            // Check Noticeboard Admin flag first
            if ( ! class_exists('TPW_Control_UI') ) {
                $ui_path = TPW_CORE_PATH . 'modules/tpw-control/class-tpw-control-ui.php';
                if ( file_exists( $ui_path ) ) { require_once $ui_path; }
            }
            if ( class_exists('TPW_Control_UI') && TPW_Control_UI::is_noticeboard_admin() ) {
                $can_manage = true;
            } else {
                // Check Members is_admin flag directly, without WP capability fallback
                $ma_path = TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-access.php';
                if ( file_exists( $ma_path ) ) { require_once $ma_path; }
                if ( class_exists('TPW_Member_Access') && is_user_logged_in() ) {
                    $user = wp_get_current_user();
                    $member = TPW_Member_Access::get_member_by_user_id( (int) $user->ID );
                    if ( $member && isset($member->is_admin) && (int)$member->is_admin === 1 ) {
                        $can_manage = true;
                    }
                }
            }
            wp_localize_script('tpw-noticeboard', 'TPWNoticeboard', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonces' => [
                    'save' => wp_create_nonce('tpw_notice_save'),
                    'delete' => wp_create_nonce('tpw_notice_delete'),
                    'duplicate' => wp_create_nonce('tpw_notice_duplicate'),
                    'addCategory' => wp_create_nonce('tpw_notice_add_category'),
                ],
                'caps' => [ 'canManage' => $can_manage ],
            ]);
            wp_enqueue_style('tpw-noticeboard', TPW_CORE_URL . 'assets/css/noticeboard.css', [], '1.0');
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
        // Determine if current user can manage notices strictly via members flags
        $can_manage = false;
        if ( ! class_exists('TPW_Control_UI') ) {
            $ui_path = TPW_CORE_PATH . 'modules/tpw-control/class-tpw-control-ui.php';
            if ( file_exists( $ui_path ) ) { require_once $ui_path; }
        }
        if ( class_exists('TPW_Control_UI') && TPW_Control_UI::is_noticeboard_admin() ) {
            $can_manage = true;
        } else {
            $ma_path = TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-access.php';
            if ( file_exists( $ma_path ) ) { require_once $ma_path; }
            if ( class_exists('TPW_Member_Access') && is_user_logged_in() ) {
                $user = wp_get_current_user();
                $member = TPW_Member_Access::get_member_by_user_id( (int) $user->ID );
                if ( $member && isset($member->is_admin) && (int)$member->is_admin === 1 ) {
                    $can_manage = true;
                }
            }
        }
        if ($can_manage) {
            echo '<div class="tpw-notice-admin-actions">';
            echo '<button class="button button-primary tpw-notice-add">' . esc_html__('Add New Notice', 'tpw-core') . '</button>';
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
                if ($can_manage) {
                    echo '<div class="tpw-notice-actions">';
                    echo '<button class="button tpw-notice-edit">' . esc_html__('Edit', 'tpw-core') . '</button>';
                    echo '<button class="button tpw-notice-duplicate">' . esc_html__('Duplicate', 'tpw-core') . '</button>';
                    echo '<button class="button button-link-delete tpw-notice-delete">' . esc_html__('Delete', 'tpw-core') . '</button>';
                    echo '</div>';
                }
                echo '</div>';
            }
            wp_reset_postdata();
        } else {
            echo '<p>' . esc_html__('No notices found.', 'tpw-core') . '</p>';
        }
        echo '</div>';

        if ($can_manage) {
            // Modal container
            include TPW_CORE_PATH . 'templates/noticeboard/form.php';
        }

        return ob_get_clean();
    }
}

TPW_Noticeboard_List_Shortcode::init();
