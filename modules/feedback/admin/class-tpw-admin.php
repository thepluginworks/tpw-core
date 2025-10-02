<?php

class TPW_Feedback_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_feedback_admin_page' ) );
    }

    public function register_feedback_admin_page() {
        add_submenu_page(
            'tools.php', // Parent menu slug
            'RSVP Feedback', // Page title
            'RSVP Feedback', // Menu title
            'manage_options', // Capability
            'tpw-feedback-admin', // Menu slug
            function () {
                include plugin_dir_path( __FILE__ ) . '/admin-feedback.php';
            }
        );
    }
}