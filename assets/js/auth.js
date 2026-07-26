/**
 * FinancePro - Auth Pages JavaScript
 * Location: /FinancePro/assets/js/auth.js
 * Handles: show/hide password, live password strength meter,
 * confirm-password match check, and lightweight client-side validation.
 * NOTE: This is a UX layer only. All real validation happens server-side in PHP.
 */

document.addEventListener('DOMContentLoaded', function () {

    /* ---------------- Show / hide password ---------------- */
    document.querySelectorAll('.btn-toggle-pass').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    /* ---------------- Password strength meter ---------------- */
    const passwordField = document.getElementById('password');
    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthLabel = document.getElementById('passwordStrengthLabel');

    if (passwordField && strengthBar) {
        passwordField.addEventListener('input', function () {
            const val = passwordField.value;
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[a-z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const levels = [
                { width: '0%',   color: '#e4e9f2', label: '' },
                { width: '20%',  color: '#e5484d', label: 'Very Weak' },
                { width: '40%',  color: '#e5484d', label: 'Weak' },
                { width: '60%',  color: '#f2a93b', label: 'Fair' },
                { width: '80%',  color: '#f2a93b', label: 'Good' },
                { width: '100%', color: '#17b978', label: 'Strong' }
            ];
            const lvl = levels[score];
            strengthBar.style.width = lvl.width;
            strengthBar.style.background = lvl.color;
            if (strengthLabel) strengthLabel.textContent = lvl.label;
        });
    }

    /* ---------------- Confirm password match ---------------- */
    const confirmField = document.getElementById('confirm_password');
    if (confirmField && passwordField) {
        const checkMatch = function () {
            if (confirmField.value.length === 0) {
                confirmField.setCustomValidity('');
                return;
            }
            if (confirmField.value !== passwordField.value) {
                confirmField.setCustomValidity('Passwords do not match');
            } else {
                confirmField.setCustomValidity('');
            }
        };
        confirmField.addEventListener('input', checkMatch);
        passwordField.addEventListener('input', checkMatch);
    }

    /* ---------------- Bootstrap-style client validation ---------------- */
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    /* ---------------- Auto-dismiss flash alerts ---------------- */
    document.querySelectorAll('.alert-fp[data-autohide]').forEach(function (alertEl) {
        setTimeout(function () {
            alertEl.classList.add('fade');
            setTimeout(() => alertEl.remove(), 400);
        }, 4000);
    });
});
