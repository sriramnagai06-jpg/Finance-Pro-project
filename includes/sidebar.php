<?php
/**
 * FinancePro - Reusable Sidebar
 * Include after config.php + functions.php on every inner page.
 * Expects: $active_page variable set in each page (e.g. 'dashboard', 'income', etc.)
 */
$active_page = $active_page ?? '';
<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" onclick="closeSidebar()"></div>

<nav class="fp-sidebar" id="fpSidebar">
    <div class="sidebar-brand">
        <div class="brand-logo"><i class="fa-solid fa-chart-pie"></i> FinancePro</div>
        <div class="brand-sub">Personal Finance Manager</div>
        <button class="sidebar-close-btn d-lg-none" aria-label="Close Navigation" style="position:absolute; top:15px; right:15px; background:none; border:none; color:#fff; font-size:1.5rem; cursor:pointer;">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?php if (!empty($_SESSION['profile_pic']) && file_exists(__DIR__ . '/../' . $_SESSION['profile_pic'])): ?>
                <img src="<?= e($_SESSION['profile_pic']) ?>" alt="Avatar">
            <?php else: ?>
                <?= $user_initial ?>
            <?php endif; ?>
        </div>
        <div>
            <div class="user-name"><?= e($_SESSION['full_name'] ?? 'User') ?></div>
            <div class="user-role"><?= e(ucfirst($_SESSION['role'] ?? 'user')) ?></div>
        </div>
    </div>

    <div class="sidebar-nav">
        <div class="sidebar-section-label">Main</div>
        <a href="<?= BASE_URL ?>user/dashboard.php" class="<?= $active_page==='dashboard' ? 'active':'' ?>">
            <i class="fa-solid fa-chart-line"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>user/income.php" class="<?= $active_page==='income' ? 'active':'' ?>">
            <i class="fa-solid fa-arrow-trend-up"></i> Income
        </a>
        <a href="<?= BASE_URL ?>user/expenses.php" class="<?= $active_page==='expenses' ? 'active':'' ?>">
            <i class="fa-solid fa-arrow-trend-down"></i> Expense
        </a>
        <a href="<?= BASE_URL ?>user/budget.php" class="<?= $active_page==='budget' ? 'active':'' ?>">
            <i class="fa-solid fa-bullseye"></i> Budget Planner
        </a>

        <div class="sidebar-section-label">Receipts & Payments</div>
        <a href="<?= BASE_URL ?>user/cash_receipts.php" class="<?= $active_page==='cash_receipts' ? 'active':'' ?>">
            <i class="fa-solid fa-money-bill-wave"></i> Cash Receipt
        </a>
        <a href="<?= BASE_URL ?>user/cash_payments.php" class="<?= $active_page==='cash_payments' ? 'active':'' ?>">
            <i class="fa-solid fa-wallet"></i> Cash Payment
        </a>
        <a href="<?= BASE_URL ?>user/online_receipts.php" class="<?= $active_page==='online_receipts' ? 'active':'' ?>">
            <i class="fa-solid fa-building-columns"></i> Online Receipt
        </a>
        <a href="<?= BASE_URL ?>user/online_payments.php" class="<?= $active_page==='online_payments' ? 'active':'' ?>">
            <i class="fa-solid fa-credit-card"></i> Online Payment
        </a>

        <div class="sidebar-section-label">Tools & Reports</div>
        <a href="<?= BASE_URL ?>user/invoices.php" class="<?= $active_page==='invoices' ? 'active':'' ?>">
            <i class="fa-solid fa-file-invoice"></i> Invoices
        </a>
        <a href="<?= BASE_URL ?>user/gst_calculator.php" class="<?= $active_page==='gst' ? 'active':'' ?>">
            <i class="fa-solid fa-percent"></i> GST Calculator
        </a>
        <a href="<?= BASE_URL ?>user/reports.php" class="<?= $active_page==='reports' ? 'active':'' ?>">
            <i class="fa-solid fa-chart-pie"></i> Reports
        </a>
        <a href="<?= BASE_URL ?>user/goals.php" class="<?= $active_page==='goals' ? 'active':'' ?>">
            <i class="fa-solid fa-star"></i> Financial Goals
        </a>
        <a href="<?= BASE_URL ?>user/savings_analytics.php" class="<?= $active_page==='savings' ? 'active':'' ?>">
            <i class="fa-solid fa-piggy-bank"></i> Savings Analytics
        </a>
        <a href="<?= BASE_URL ?>user/comparisons.php" class="<?= $active_page==='comparisons' ? 'active':'' ?>">
            <i class="fa-solid fa-chart-bar"></i> Comparisons
        </a>

        <div class="sidebar-section-label">Accounting</div>
        <a href="<?= BASE_URL ?>user/journal.php" class="<?= $active_page==='journal' ? 'active':'' ?>">
            <i class="fa-solid fa-book"></i> Journal Book
        </a>
        <a href="<?= BASE_URL ?>user/ledger.php" class="<?= $active_page==='ledger' ? 'active':'' ?>">
            <i class="fa-solid fa-book-open"></i> Ledger
        </a>
        <a href="<?= BASE_URL ?>user/trial_balance.php" class="<?= $active_page==='trial_balance' ? 'active':'' ?>">
            <i class="fa-solid fa-scale-balanced"></i> Trial Balance
        </a>
        <a href="<?= BASE_URL ?>user/trading_account.php" class="<?= $active_page==='trading' ? 'active':'' ?>">
            <i class="fa-solid fa-store"></i> Trading Account
        </a>
        <a href="<?= BASE_URL ?>user/profit_loss.php" class="<?= $active_page==='pl' ? 'active':'' ?>">
            <i class="fa-solid fa-chart-line"></i> Profit & Loss
        </a>
        <a href="<?= BASE_URL ?>user/balance_sheet.php" class="<?= $active_page==='balance_sheet' ? 'active':'' ?>">
            <i class="fa-solid fa-table-columns"></i> Balance Sheet
        </a>
        <a href="<?= BASE_URL ?>user/fund_flow.php" class="<?= $active_page==='fund_flow' ? 'active':'' ?>">
            <i class="fa-solid fa-water"></i> Fund Flow
        </a>

        <div class="sidebar-section-label">Account</div>
        <a href="<?= BASE_URL ?>user/profile.php" class="<?= $active_page==='profile' ? 'active':'' ?>">
            <i class="fa-solid fa-user-circle"></i> My Profile
        </a>
        <a href="<?= BASE_URL ?>user/settings.php" class="<?= $active_page==='settings' ? 'active':'' ?>">
            <i class="fa-solid fa-gear"></i> Settings
        </a>
        <a href="<?= BASE_URL ?>user/notifications.php" class="<?= $active_page==='notifications' ? 'active':'' ?>">
            <i class="fa-solid fa-bell"></i> Notifications
        </a>
        <a href="<?= BASE_URL ?>user/change_password.php" class="<?= $active_page==='password' ? 'active':'' ?>">
            <i class="fa-solid fa-lock"></i> Change Password
        </a>
        <?php if (is_admin()): ?>
        <div class="sidebar-section-label">Admin</div>
        <a href="<?= BASE_URL ?>admin/dashboard.php" class="<?= $active_page==='admin' ? 'active':'' ?>">
            <i class="fa-solid fa-shield-halved"></i> Admin Panel
        </a>
        <a href="<?= BASE_URL ?>admin/audit_log.php" class="<?= $active_page==='audit_log' ? 'active':'' ?>">
            <i class="fa-solid fa-clipboard-list"></i> Audit Log
        </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>logout.php">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>
