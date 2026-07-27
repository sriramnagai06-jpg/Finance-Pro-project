/**
 * FinancePro - Main Application JavaScript
 * Location: /assets/js/app.js
 * Handles Mobile Sidebar Navigation, Theme Toggle, Toast Alerts, and Form States.
 */

// ---------------------------------------------------------------------
// Theme Toggle Functions
// ---------------------------------------------------------------------
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

// ---------------------------------------------------------------------
// Global Mobile Sidebar Controls
// ---------------------------------------------------------------------
function toggleSidebar(forceState) {
    const sidebar = document.getElementById('fpSidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (!sidebar) return;

    const isOpen = sidebar.classList.contains('show');
    const shouldOpen = (typeof forceState === 'boolean') ? forceState : !isOpen;

    if (shouldOpen) {
        sidebar.classList.add('show');
        if (overlay) overlay.classList.add('show');
        document.body.classList.add('sidebar-open');
    } else {
        sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
        document.body.classList.remove('sidebar-open');
    }
}

function closeSidebar() {
    toggleSidebar(false);
}

function openSidebar() {
    toggleSidebar(true);
}

// Expose globally on window for inline event handlers and cross-script calls
window.toggleSidebar = toggleSidebar;
window.closeSidebar = closeSidebar;
window.openSidebar = openSidebar;
window.toggleTheme = toggleTheme;

// ---------------------------------------------------------------------
// Event Delegation & Global Event Listeners
// ---------------------------------------------------------------------
document.addEventListener('click', function (e) {
    // 1. Sidebar Nav Link Click -> Close sidebar automatically after selecting an item
    const navLink = e.target.closest('#fpSidebar .sidebar-nav a, #fpSidebar .sidebar-footer a');
    if (navLink) {
        closeSidebar();
        return;
    }

    // 2. Hamburger button / Toggle button click
    const toggleBtn = e.target.closest('.sidebar-toggle-btn');
    if (toggleBtn) {
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
        return;
    }

    // 3. Close button inside sidebar (X icon)
    const closeBtn = e.target.closest('.sidebar-close-btn');
    if (closeBtn) {
        e.preventDefault();
        e.stopPropagation();
        closeSidebar();
        return;
    }

    // 4. Overlay click (outside sidebar click)
    const overlay = e.target.closest('.sidebar-overlay');
    if (overlay) {
        e.preventDefault();
        closeSidebar();
        return;
    }
});

// Reset sidebar state on page load and back/forward cache (bfcache) restore
function resetMobileNav() {
    closeSidebar();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', resetMobileNav);
} else {
    resetMobileNav();
}

window.addEventListener('pageshow', function () {
    closeSidebar();
});

// Accessibility: Pressing ESC closes the sidebar
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeSidebar();
    }
});

// ---------------------------------------------------------------------
// Component Initializers
// ---------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    // 1. Toast Notifications (Auto-dismiss)
    const alerts = document.querySelectorAll('.fp-alert, .alert-fp');
    alerts.forEach(alert => {
        if (alert.dataset.hasCloseBtn) return;
        alert.dataset.hasCloseBtn = "true";

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
        form.addEventListener('submit', function () {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
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
