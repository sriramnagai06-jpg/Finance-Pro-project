<?php
/**
 * FinancePro - Admin Audit Log (Phase 7 Bonus)
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_admin();

$page_num = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$search = clean_input($_GET['search'] ?? '');
$where = "WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $where .= " AND (u.full_name LIKE ? OR a.action_type LIKE ? OR a.table_name LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types = 'sss';
}

$total_sql = "SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON a.user_id = u.user_id $where";
$total_stmt = $conn->prepare($total_sql);
if ($types) $total_stmt->bind_param($types, ...$params);
$total_stmt->execute();
$total = $total_stmt->get_result()->fetch_row()[0];
$total_stmt->close();
$total_pages = ceil($total / $per_page);

$sql = "SELECT a.*, u.full_name, u.email FROM audit_log a LEFT JOIN users u ON a.user_id = u.user_id $where ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($types) {
    $types .= 'ii';
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$active_page = 'audit_log'; 
$page_title = 'Audit Log'; 
$page_subtitle = 'System activity history';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Audit Log - <?=e(SITE_NAME)?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
<div class="fp-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="fp-main">
        <?php include '../includes/header.php'; ?>
        <div class="fp-content">
            <div class="table-card">
                <div class="table-card-header">
                    <div><div class="tc-title">System Audit Log</div><div class="tc-sub"><?=$total?> records found</div></div>
                    <form method="GET" style="display:flex; gap:10px;">
                        <input type="text" name="search" class="fp-input" placeholder="Search logs..." value="<?=e($search)?>">
                        <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm">Search</button>
                    </form>
                </div>
                
                <table class="fp-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): 
                            $badge_class = 'income';
                            if ($log['action_type'] === 'delete') $badge_class = 'danger';
                            if (strpos($log['action_type'], 'failed') !== false) $badge_class = 'danger';
                            if ($log['action_type'] === 'update') $badge_class = 'warning';
                        ?>
                        <tr>
                            <td style="font-size:0.8rem; color:var(--fp-text-muted);"><?=date('d M Y, h:i A', strtotime($log['created_at']))?></td>
                            <td><?=e($log['full_name'] ?? 'System / Guest')?><br><small style="color:var(--fp-text-muted);"><?=e($log['email'] ?? '')?></small></td>
                            <td><span class="badge-fp badge-<?=$badge_class?>"><?=e(strtoupper($log['action_type']))?></span></td>
                            <td><?=e($log['table_name'])?> (ID: <?=e($log['record_id'])?>)</td>
                            <td style="font-size:0.85rem; max-width:250px; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;" title="<?=e($log['description'])?>"><?=e($log['description'])?></td>
                            <td style="font-size:0.8rem; color:var(--fp-text-muted);"><?=e($log['ip_address'])?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if($total_pages > 1): ?>
                <div style="padding:16px 20px; border-top:1px solid var(--fp-border); display:flex; gap:10px;">
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                    <a href="?page=<?=$i?>&search=<?=urlencode($search)?>" class="btn-fp btn-fp-sm <?= $i===$page_num ? 'btn-fp-primary' : 'btn-fp-outline' ?>"><?=$i?></a>
                    <?php endfor; ?>
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
