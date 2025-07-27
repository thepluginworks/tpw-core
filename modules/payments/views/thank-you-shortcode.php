<?php
if (!defined('ABSPATH')) exit;

function tpw_thank_you_shortcode($atts) {
    ob_start();

    $submission_id = isset($_GET['submission_id']) ? intval($_GET['submission_id']) : 0;
    ?>
    <div class="tpw-thank-you-wrapper">
        <h2>Thank You for Your RSVP</h2>
        <p>Your RSVP has been received<?php if ($submission_id) echo " for submission ID: $submission_id"; ?>.</p>
        <p>If you selected Bank Transfer, please use the bank details provided to complete your payment.</p>
        <p>We look forward to welcoming you at the event.</p>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('tpw_thank_you', 'tpw_thank_you_shortcode');
