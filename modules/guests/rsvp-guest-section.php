<?php
/**
 * Guest Fields Section for RSVP Form
 */
?>

<div id="guest_fields_wrap" class="rsvp-guest-fields">
    <label for="number_of_guests">How many guests will you bring?</label>
    <select name="number_of_guests" id="number_of_guests">
        <option value="0">0</option>
        <?php for ($i = 1; $i <= 30; $i++): ?>
            <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
        <?php endfor; ?>
    </select>

    <div id="guest_repeat_fields_container"></div>
</div>

<template id="guest_field_template">
    <div class="guest-block">
        <h4>Guest <span class="guest-number"></span></h4>
        <label>Title</label>
        <input type="text" name="guests[__index__][title]" id="guest_title___index__" />

        <label>First Name</label>
        <input type="text" name="guests[__index__][first_name]" id="guest_first_name___index__" />

        <label>Surname</label>
        <input type="text" name="guests[__index__][surname]" id="guest_surname___index__" />

        <label>Will the guest be dining?</label>
        <select name="guests[__index__][is_dining]" id="guest_dining___index__" class="guest_dining_dropdown">
            <option value="no">No</option>
            <option value="yes">Yes</option>
        </select>

        <div class="guest-dining-section" id="guest-dining-section-__index__" style="display: none;">
            <label>Dietary Requirements</label>
            <input type="text" name="guests[__index__][dietary]" id="guest_dietary___index__" />

            <label>Seating Preferences</label>
            <input type="text" name="guests[__index__][seating]" id="guest_seating___index__" />

            <div class="guest-course-fields"></div>
        </div>
    </div>
</template>
