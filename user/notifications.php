<?php
/**
 * FinancePro - Notifications (Module 11)
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

// Handle Mark as Read / Delete
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $nid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($action === 'mark_read' && $nid) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $nid, $uid); $stmt->execute(); $stmt->close();
    } elseif ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param('i', $uid); $stmt->execute(); $stmt->close();
        set_flash('success', 'All notifications marked as read.');
    } elseif ($action === 'delete' && $nid) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $nid, $uid); $stmt->execute(); $stmt->close();
        set_flash('success', 'Notification deleted.');
    } elseif ($action === 'delete_all') {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->bind_param('i', $uid); $stmt->execute(); $stmt->close();
        set_flash('success', 'All notifications deleted.');
    }
    
    header('Location: notifications.php'); exit;
}

// Fetch all notifications for the user
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->bind_param('i', $uid);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$active_page = 'notifications'; 
$page_title = 'Notifications'; 
$page_subtitle = 'System alerts and updates';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Notifications - <?=e(SITE_NAME)?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <style>
        .notif-item { padding: 16px; border-bottom: 1px solid var(--fp-border); display: flex; gap: 16px; align-items: flex-start; transition: background 0.2s; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: rgba(0,0,0,0.02); }
        .notif-item.unread { background: rgba(36,87,217,0.04); }
        .notif-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .notif-icon.system { background: rgba(36,87,217,0.1); color: var(--fp-primary); }
        .notif-icon.budget_exceeded { background: rgba(229,72,77,0.1); color: var(--fp-danger); }
        .notif-icon.large_expense { background: rgba(245,158,11,0.1); color: var(--fp-warning); }
        .notif-content { flex: 1; }
        .notif-title { font-weight: 700; color: var(--fp-text); margin-bottom: 4px; display:flex; justify-content:space-between; }
        .notif-message { font-size: 0.9rem; color: var(--fp-text-muted); line-height: 1.4; }
        .notif-time { font-size: 0.75rem; color: #a1a1aa; }
        .notif-actions { opacity: 0; transition: opacity 0.2s; display:flex; gap:8px; }
        .notif-item:hover .notif-actions { opacity: 1; }
    </style>
</head>
<body>
<div class="fp-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="fp-main">
        <?php include '../includes/header.php'; ?>
        <div class="fp-content">
            <?php include '../includes/alerts.php'; ?>

            <div class="table-card">
                <div class="table-card-header" style="border-bottom:1px solid var(--fp-border); padding-bottom:16px;">
                    <div><div class="tc-title">Your Notifications</div></div>
                    <div style="display:flex; gap:10px;">
                        <a href="?action=mark_all_read" class="btn-fp btn-fp-outline btn-fp-sm"><i class="fa-solid fa-check-double"></i> Mark all as read</a>
                        <a href="?action=delete_all" class="btn-fp btn-fp-danger btn-fp-sm" onclick="return confirm('Delete all notifications?')"><i class="fa-solid fa-trash"></i> Clear All</a>
                    </div>
                </div>

                <?php if(empty($notifications)): ?>
                <div class="empty-state" style="padding: 60px 20px;">
                    <i class="fa-solid fa-bell-slash"></i>
                    <h3>No notifications</h3>
                    <p>You're all caught up!</p>
                </div>
                <?php else: ?>
                <div>
                    <?php foreach($notifications as $n): 
                        $icon = 'fa-info';
                        if ($n['type'] === 'budget_exceeded') $icon = 'fa-triangle-exclamation';
                        if ($n['type'] === 'large_expense') $icon = 'fa-money-bill-wave';
                    ?>
                    <div class="notif-item <?= $n['is_read'] ? 'read' : 'unread' ?>">
                        <div class="notif-icon <?=e($n['type'])?>"><i class="fa-solid <?=$icon?>"></i></div>
                        <div class="notif-content">
                            <div class="notif-title">
                                <span><?=e($n['title'])?> <?php if(!$n['is_read']): ?><span class="badge-fp badge-expense" style="font-size:0.6rem; margin-left:6px;">NEW</span><?php endif; ?></span>
                                <span class="notif-time"><?= date('d M, h:i A', strtotime($n['created_at'])) ?></span>
                            </div>
                            <div class="notif-message"><?=e($n['message'])?></div>
                        </div>
                        <div class="notif-actions">
                            <?php if(!$n['is_read']): ?>
                            <a href="?action=mark_read&id=<?=$n['notification_id']?>" class="btn-fp btn-fp-outline btn-fp-sm" title="Mark as read"><i class="fa-solid fa-check"></i></a>
                            <?php endif; ?>
                            <a href="?action=delete&id=<?=$n['notification_id']?>" class="btn-fp btn-fp-danger btn-fp-sm" title="Delete"><i class="fa-solid fa-xmark"></i></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
