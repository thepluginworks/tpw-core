<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Optionally fetch submission data here using $_GET['submission_id']
$submission_id = isset($_GET['submission_id']) ? intval($_GET['submission_id']) : 0;

?>
<div class="tpw-thank-you-wrapper">
    <h1>Thank You for Your RSVP</h1>
    <p>Your submission has been received<?php if ($submission_id) echo " for submission ID: $submission_id"; ?>.</p>

    <p>If you selected Bank Transfer, please use the bank details provided to complete your payment.</p>

    <p>We look forward to seeing you at the event.</p>
</div>
