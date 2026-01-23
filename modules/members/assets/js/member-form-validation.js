document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.tpw-member-form form');
    if (!form) return;

    const mode = (window.tpwMembersForm && typeof window.tpwMembersForm.mode === 'string')
        ? window.tpwMembersForm.mode
        : 'other';

    form.addEventListener('submit', function (e) {
        // Remove old errors
        form.querySelectorAll('.form-error').forEach(el => el.remove());

        let hasError = false;

        // Utility to show error message
        const showError = (field, message) => {
            const error = document.createElement('div');
            error.className = 'form-error';
            error.style.color = 'red';
            error.style.fontSize = '0.9em';
            error.textContent = message;
            field.insertAdjacentElement('afterend', error);
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            hasError = true;
        };

        // Email validation
        const emailField = form.querySelector('input[name="email"]');
        if (emailField) {
            const emailValue = emailField.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (mode === 'add' && emailValue === '') {
                showError(emailField, 'email is required.');
            } else if (emailValue !== '' && !emailRegex.test(emailValue)) {
                showError(emailField, 'Please enter a valid email address.');
            }
        }

        // Required fields
        // - username is required only on Add (imported records may have blank tpw_members.username)
        // - other fields remain required across Add/Edit
        const requiredFields = ['first_name', 'surname'];
        if (mode === 'add') requiredFields.unshift('username');
        for (const fieldName of requiredFields) {
            const field = form.querySelector(`input[name="${fieldName}"]`);
            if (field && field.value.trim() === '') {
                showError(field, `${fieldName.replace('_', ' ')} is required.`);
            }
        }

        // Date validation (dob must not be in the future)
        const dob = form.querySelector('input[name="dob"]');
        if (dob && dob.value) {
            const selectedDate = new Date(dob.value);
            const today = new Date();
            if (selectedDate > today) {
                showError(dob, 'Date of Birth cannot be in the future.');
            }
        }

        // Basic input sanitation
        const allInputs = form.querySelectorAll('input[type="text"]');
        allInputs.forEach(input => {
            if (/[<>]/.test(input.value)) {
                showError(input, 'Invalid characters are not allowed.');
            }
        });

        if (hasError) {
            e.preventDefault();
        }
    });
});