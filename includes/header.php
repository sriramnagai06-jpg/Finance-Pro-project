<?php
/**
 * FinancePro - Reusable Page Header (Topbar)
 * Expects: $page_title, $page_subtitle (optional)
 */
$page_subtitle = $page_subtitle ?? date('l, d F Y');
?>
<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" onclick="closeSidebar()"></div>

<header class="fp-topbar">
    <div class="topbar-title">
        <button class="sidebar-toggle-btn d-lg-none" onclick="toggleSidebar(event)" aria-label="Toggle Navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-title-text">
            <h1><?= e($page_title ?? 'FinancePro') ?></h1>
            <p><?= e($page_subtitle) ?></p>
        </div>
    </div>
    <div class="topbar-actions">
        <span class="topbar-date d-none d-md-inline-flex"><i class="fa-regular fa-calendar"></i> <?= date('d M Y') ?></span>
        
        <button onclick="toggleTheme()" class="btn-icon" title="Toggle Dark Mode" style="background:transparent; border:none; cursor:pointer;">
            <i class="fa-solid fa-moon"></i>
        </button>

        <?php $unread_count = get_unread_notifications_count($conn, $_SESSION['user_id'] ?? 0); ?>
        <a href="<?= BASE_URL ?>user/notifications.php" class="btn-icon" title="Notifications" style="position:relative;">
            <i class="fa-solid fa-bell"></i>
            <?php if($unread_count > 0): ?>
            <span style="position:absolute; top:-4px; right:-6px; background:var(--fp-danger); color:#fff; font-size:0.6rem; font-weight:bold; padding:2px 5px; border-radius:10px;">
                <?=$unread_count>9?'9+':$unread_count?>
            </span>
            <?php endif; ?>
        </a>

        <a href="<?= BASE_URL ?>user/profile.php" class="btn-icon" title="Profile">
            <i class="fa-solid fa-user"></i>
        </a>
        <a href="<?= BASE_URL ?>logout.php" class="btn-icon" title="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</header>
