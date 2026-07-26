// Dark mode toggle function
function toggleTheme() {
    const isDark = document.body.classList.toggle('dark-theme');
    localStorage.setItem('fp_theme', isDark ? 'dark' : 'light');
    updateThemeIcon(isDark);
}

function updateThemeIcon(isDark) {
    const icon = document.querySelector('.btn-icon[title="Toggle Dark Mode"] i');
    if (icon) {
        icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Toast Notifications (Auto-dismiss)
    const alerts = document.querySelectorAll('.fp-alert, .alert-fp');
    alerts.forEach(alert => {
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        closeBtn.style.cssText = 'background:transparent; border:none; color:inherit; opacity:0.7; cursor:pointer; position:absolute; right:12px; top:12px;';
        closeBtn.onclick = () => alert.remove();
        alert.style.position = 'relative';
        alert.appendChild(closeBtn);

        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // 2. Form Submission Spinner
    const forms = document.querySelectorAll('form:not([target="_blank"])');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                const originalHtml = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
                submitBtn.style.opacity = '0.75';
            }
        });
    });

    // 3. Dark Mode Init
    const currentTheme = localStorage.getItem('fp_theme') || 'light';
    if (currentTheme === 'dark') {
        document.body.classList.add('dark-theme');
        updateThemeIcon(true);
    }
});
