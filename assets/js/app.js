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
function toggleSidebar(arg) {
    if (arg && typeof arg.preventDefault === 'function') {
        arg.preventDefault();
    }
    const sidebar = document.getElementById('fpSidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (!sidebar) return;

    const isOpen = sidebar.classList.contains('show') || sidebar.classList.contains('open');
    const forceState = (typeof arg === 'boolean') ? arg : !isOpen;

    if (forceState) {
        sidebar.classList.add('show', 'open');
        if (overlay) overlay.classList.add('show', 'open');
        document.body.classList.add('sidebar-open');
    } else {
        sidebar.classList.remove('show', 'open');
        if (overlay) overlay.classList.remove('show', 'open');
        document.body.classList.remove('sidebar-open');
    }
}

function closeSidebar() {
    toggleSidebar(false);
}

function openSidebar() {
    toggleSidebar(true);
}

// Expose globally on window for cross-script calls
window.toggleSidebar = toggleSidebar;
window.closeSidebar = closeSidebar;
window.openSidebar = openSidebar;
window.toggleTheme = toggleTheme;

// ---------------------------------------------------------------------
// Event Delegation & Global Event Listeners
// ---------------------------------------------------------------------
document.addEventListener('click', function (e) {
    // 1. Hamburger button / Toggle button click
    const toggleBtn = e.target.closest('.sidebar-toggle-btn');
    if (toggleBtn) {
        if (!toggleBtn.dataset.toggledInTick) {
            toggleBtn.dataset.toggledInTick = "true";
            setTimeout(() => { delete toggleBtn.dataset.toggledInTick; }, 100);
            e.preventDefault();
            toggleSidebar();
        }
        return;
    }

    // 2. Close button inside sidebar (X icon)
    const closeBtn = e.target.closest('.sidebar-close-btn');
    if (closeBtn) {
        e.preventDefault();
        closeSidebar();
        return;
    }

    // 3. Overlay click (outside sidebar click)
    const overlay = e.target.closest('.sidebar-overlay');
    if (overlay) {
        e.preventDefault();
        closeSidebar();
        return;
    }

    // 4. Sidebar Nav Link Click -> Close sidebar automatically after selecting an item
    const navLink = e.target.closest('#fpSidebar .sidebar-nav a, #fpSidebar .sidebar-footer a');
    if (navLink) {
        closeSidebar();
        return;
    }
});

// Reset sidebar state cleanly on page load and back/forward cache (bfcache) restore
function resetMobileNav() {
    const sidebar = document.getElementById('fpSidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (sidebar) sidebar.classList.remove('show', 'open');
    if (overlay) overlay.classList.remove('show', 'open');
    document.body.classList.remove('sidebar-open');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', resetMobileNav);
} else {
    resetMobileNav();
}

window.addEventListener('pageshow', function () {
    resetMobileNav();
});

// Accessibility: Pressing ESC closes the sidebar, pressing / toggles sidebar
document.addEventListener('keydown', function (e) {
    const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
    const isEditing = activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select' || document.activeElement.isContentEditable;

    if (e.key === 'Escape') {
        closeSidebar();
    } else if (e.key === '/' && !isEditing) {
        e.preventDefault();
        toggleSidebar();
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
