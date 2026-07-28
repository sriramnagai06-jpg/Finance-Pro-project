<?php
/**
 * FinancePro - Admin User Management
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_admin();

// Handle block/unblock
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $target_id = (int)$_GET['toggle'];
    if ($target_id !== $_SESSION['user_id']) { // can't block yourself
        $cur = $conn->prepare("SELECT status FROM users WHERE user_id=?");
        $cur->bind_param('i',$target_id); $cur->execute();
        $cur_status = $cur->get_result()->fetch_row()[0]; $cur->close();
        $new_status = $cur_status === 'active' ? 'blocked' : 'active';
        $upd = $conn->prepare("UPDATE users SET status=? WHERE user_id=?");
        $upd->bind_param('si',$new_status,$target_id); $upd->execute(); $upd->close();
        set_flash('success','User '.($new_status==='blocked'?'blocked':'unblocked').' successfully.');
    } else {
        set_flash('danger','You cannot block your own account.');
    }
    header('Location: users.php'); exit;
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    if ($del_id !== $_SESSION['user_id']) {
        $del = $conn->prepare("DELETE FROM users WHERE user_id=? AND role='user'");
        $del->bind_param('i',$del_id); $del->execute(); $del->close();
        set_flash('success','User deleted successfully.');
    }
    header('Location: users.php'); exit;
}

$search = clean_input($_GET['search'] ?? '');
$filter_status = clean_input($_GET['status'] ?? '');
$where = "WHERE 1=1";
$params=[]; $types='';
if ($search) { $like="%$search%"; $where.=" AND (full_name LIKE ? OR email LIKE ?)"; $params[]=$like; $params[]=$like; $types.='ss'; }
if ($filter_status) { $where.=" AND status=?"; $params[]=$filter_status; $types.='s'; }

$users = $conn->prepare("SELECT u.*, (SELECT COUNT(*) FROM income WHERE user_id=u.user_id) as inc_cnt, (SELECT COUNT(*) FROM expenses WHERE user_id=u.user_id) as exp_cnt FROM users u $where ORDER BY u.created_at DESC");
if ($types) $users->bind_param($types,...$params);
$users->execute();
$user_list = $users->get_result()->fetch_all(MYSQLI_ASSOC); $users->close();

$active_page='admin'; $page_title='Manage Users'; $page_subtitle='View, block, and manage all user accounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Manage Users - <?=e(SITE_NAME)?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
<div class="fp-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="fp-main">
        <?php include '../includes/header.php'; ?>
        <div class="fp-content">
            <?php include '../includes/alerts.php'; ?>

            <div class="table-card">
                <div class="table-card-header">
                    <div><div class="tc-title">All Users</div><div class="tc-sub"><?=count($user_list)?> user(s) found</div></div>
                    <a href="dashboard.php" class="btn-fp btn-fp-outline btn-fp-sm"><i class="fa-solid fa-arrow-left"></i> Admin Home</a>
                </div>
                <div style="padding:16px 20px; border-bottom:1px solid var(--fp-border);">
                    <form method="GET" class="filters-bar">
                        <input type="text" name="search" class="fp-input" placeholder="Search name or email..." value="<?=e($search)?>">
                        <select name="status" class="fp-select">
                            <option value="">All Status</option>
                            <option value="active"  <?=$filter_status==='active'?'selected':''?>>Active</option>
                            <option value="blocked" <?=$filter_status==='blocked'?'selected':''?>>Blocked</option>
                        </select>
                        <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-filter"></i> Filter</button>
                        <a href="users.php" class="btn-fp btn-fp-outline btn-fp-sm">Reset</a>
                    </form>
                </div>
                <?php if(empty($user_list)): ?>
                <div class="empty-state"><i class="fa-solid fa-users"></i><h3>No users found</h3></div>
                <?php else: ?>
                <table class="fp-table">
                    <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Income</th><th>Expenses</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($user_list as $u): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--fp-primary),var(--fp-accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.85rem;flex-shrink:0;">
                                    <?=strtoupper(substr($u['full_name'],0,1))?>
                                </div>
                                <span style="font-weight:600;"><?=e($u['full_name'])?></span>
                                <?php if($u['user_id']===$_SESSION['user_id']): ?><span style="font-size:0.68rem;color:var(--fp-primary);">(You)</span><?php endif; ?>
                            </div>
                        </td>
                        <td style="font-size:0.82rem;color:var(--fp-text-muted);"><?=e($u['email'])?></td>
                        <td><span class="badge-fp badge-<?=$u['role']==='admin'?'partial':'income'?>"><?=ucfirst($u['role'])?></span></td>
                        <td style="color:var(--fp-accent);font-weight:600;"><?=$u['inc_cnt']?> entries</td>
                        <td style="color:var(--fp-danger);font-weight:600;"><?=$u['exp_cnt']?> entries</td>
                        <td>
                            <span class="badge-fp" style="<?=$u['status']==='active'?'background:rgba(23,185,120,0.12);color:#0d8a5a;':'background:rgba(229,72,77,0.12);color:#c93a3f;'?>">
                                <?=ucfirst($u['status'])?>
                            </span>
                        </td>
                        <td style="font-size:0.82rem;color:var(--fp-text-muted);"><?=format_date($u['created_at'])?></td>
                        <td>
                            <?php if($u['user_id']!==$_SESSION['user_id']): ?>
                            <div style="display:flex;gap:6px;">
                                <a href="?toggle=<?=$u['user_id']?>" class="btn-fp btn-fp-sm <?=$u['status']==='active'?'btn-fp-danger':'btn-fp-success'?>" onclick="return confirm('<?=$u['status']==='active'?'Block':'Unblock'?> this user?')">
                                    <i class="fa-solid <?=$u['status']==='active'?'fa-ban':'fa-check'?>"></i>
                                    <?=$u['status']==='active'?'Block':'Unblock'?>
                                </a>
                                <?php if($u['role']==='user'): ?>
                                <a href="?delete=<?=$u['user_id']?>" class="btn-fp btn-fp-danger btn-fp-sm" onclick="return confirm('Permanently delete this user and ALL their data?')"><i class="fa-solid fa-trash"></i></a>
                                <?php endif; ?>
                            </div>
                            <?php else: ?><span style="font-size:0.78rem;color:var(--fp-text-muted);">—</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
