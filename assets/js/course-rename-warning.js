function normalise(s) {
    return String(s || '')
        .replace(/^\s+|\s+$/g, '') // trim
        .replace(/\s+/g, ' ');      // collapse whitespace
}

document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('tpw-course-choice-form');
    if (!form) return;

    // Only run when editing an existing choice (choice_id field present)
    var isEdit = !!form.querySelector('input[name="choice_id"]');
    if (!isEdit) return;

    var input = form.querySelector('input[name="label"]');
    if (!input) return;

    var original = normalise((window.TPW_COURSE_RENAME && TPW_COURSE_RENAME.original) || '');
    var message  = (window.TPW_COURSE_RENAME && TPW_COURSE_RENAME.message) || "Changing this menu item's name will remove the preselected value from existing RSVPs. Are you sure you want to continue?";

    form.addEventListener('submit', function(e) {
        var current = normalise(input.value);
        if (original !== current) {
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        }
    });
});
