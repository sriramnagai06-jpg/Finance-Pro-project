<?php
/**
 * FinancePro - User Profile (Module 9)
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];
$errors = [];

// Fetch current user
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->bind_param('i',$uid); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();

// Handle Update
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update') {
    if (!verify_csrf_token($_POST['csrf_token']??null)) { $errors[]='Invalid request.'; }
    else {
        $full_name = clean_input($_POST['full_name']??'');
        $phone     = clean_input($_POST['phone']??'');

        if (strlen($full_name)<3) $errors[]='Full name must be at least 3 characters.';
        if ($phone && !preg_match('/^[0-9]{10}$/',$phone)) $errors[]='Phone must be 10 digits.';

        // Handle profile picture upload
        $profile_pic = $user['profile_picture'];
        if (!empty($_FILES['profile_picture']['name'])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            if (!in_array($_FILES['profile_picture']['type'],$allowed)) {
                $errors[]='Only JPG, PNG, WebP images allowed.';
            } elseif ($_FILES['profile_picture']['size'] > 2*1024*1024) {
                $errors[]='Image must be under 2MB.';
            } else {
                $upload_dir = __DIR__.'/../uploads/profile/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $filename = 'user_'.$uid.'_'.time().'.'.$ext;
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir.$filename)) {
                    $profile_pic = 'uploads/profile/'.$filename;
                }
            }
        }

        if (empty($errors)) {
            $upd = $conn->prepare("UPDATE users SET full_name=?, phone=?, profile_picture=? WHERE user_id=?");
            $upd->bind_param('sssi',$full_name,$phone,$profile_pic,$uid);
            $upd->execute(); $upd->close();
            $_SESSION['full_name'] = $full_name;
            if ($profile_pic !== $user['profile_picture']) $_SESSION['profile_pic'] = $profile_pic;
            set_flash('success','Profile updated successfully!');
            header('Location: profile.php'); exit;
        }
    }
}

$csrf_token = generate_csrf_token();
$active_page = 'profile'; $page_title = 'My Profile'; $page_subtitle = 'Manage your account details';

// Fetch stats
$inc_count = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(amount),0) FROM income WHERE user_id=?");
$inc_count->bind_param('i',$uid); $inc_count->execute(); [$total_inc_rows,$total_inc_amt] = $inc_count->get_result()->fetch_row(); $inc_count->close();
$exp_count = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(amount),0) FROM expenses WHERE user_id=?");
$exp_count->bind_param('i',$uid); $exp_count->execute(); [$total_exp_rows,$total_exp_amt] = $exp_count->get_result()->fetch_row(); $exp_count->close();
$inv_count = $conn->prepare("SELECT COUNT(*) FROM invoices WHERE user_id=?");
$inv_count->bind_param('i',$uid); $inv_count->execute(); [$total_invs] = $inv_count->get_result()->fetch_row(); $inv_count->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Profile - <?=e(SITE_NAME)?></title>
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
            <?php if(!empty($errors)): ?><div class="fp-alert fp-alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?=implode(' &bull; ',array_map('e',$errors))?></div><?php endif; ?>

            <div style="display:grid; grid-template-columns:300px 1fr; gap:24px; align-items:start;">
                <!-- Profile Card -->
                <div class="form-card" style="text-align:center;">
                    <div class="profile-avatar-wrap" style="margin:0 auto 12px;">
                        <?php $pic_path = $user['profile_picture'] ?? ''; ?>
                        <?php if ($pic_path && file_exists(__DIR__.'/../'.$pic_path)): ?>
                        <img src="../<?=e($pic_path)?>" class="profile-avatar" alt="Profile">
                        <?php else: ?>
                        <div class="profile-avatar"><?=strtoupper(substr($user['full_name'],0,1))?></div>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:1.1rem; font-weight:700;"><?=e($user['full_name'])?></div>
                    <div style="font-size:0.82rem; color:var(--fp-text-muted); margin:4px 0 12px;"><?=e($user['email'])?></div>
                    <span class="badge-fp badge-<?=$user['role']==='admin'?'partial':'income'?>"><?=ucfirst($user['role'])?></span>
                    <div style="margin-top:20px; font-size:0.78rem; color:var(--fp-text-muted);">
                        Member since <?=date('M Y', strtotime($user['created_at']))?>
                    </div>

                    <!-- Quick Stats -->
                    <div style="margin-top:20px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
                        <div style="background:var(--fp-bg); border-radius:10px; padding:10px;">
                            <div style="font-size:1.1rem; font-weight:800; color:var(--fp-accent);"><?=$total_inc_rows?></div>
                            <div style="font-size:0.68rem; color:var(--fp-text-muted);">Income</div>
                        </div>
                        <div style="background:var(--fp-bg); border-radius:10px; padding:10px;">
                            <div style="font-size:1.1rem; font-weight:800; color:var(--fp-danger);"><?=$total_exp_rows?></div>
                            <div style="font-size:0.68rem; color:var(--fp-text-muted);">Expenses</div>
                        </div>
                        <div style="background:var(--fp-bg); border-radius:10px; padding:10px;">
                            <div style="font-size:1.1rem; font-weight:800; color:var(--fp-primary);"><?=$total_invs?></div>
                            <div style="font-size:0.68rem; color:var(--fp-text-muted);">Invoices</div>
                        </div>
                    </div>
                </div>

                <!-- Edit Form -->
                <div class="form-card">
                    <div class="form-section-title"><i class="fa-solid fa-user-pen" style="color:var(--fp-primary)"></i> Edit Profile</div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
                        <input type="hidden" name="action" value="update">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="fp-form-group"><label class="fp-label">Full Name</label><input type="text" name="full_name" class="fp-input" value="<?=e($user['full_name'])?>" required minlength="3"></div>
                            <div class="fp-form-group"><label class="fp-label">Email (read-only)</label><input type="email" class="fp-input" value="<?=e($user['email'])?>" disabled style="opacity:0.6;"></div>
                            <div class="fp-form-group"><label class="fp-label">Phone Number</label><input type="text" name="phone" class="fp-input" value="<?=e($user['phone']??'')?>" maxlength="10" placeholder="10-digit number"></div>
                            <div class="fp-form-group"><label class="fp-label">Profile Picture</label><input type="file" name="profile_picture" class="fp-input" accept="image/*" style="padding:6px;"></div>
                        </div>
                        <button type="submit" class="btn-fp btn-fp-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                        <a href="change_password.php" class="btn-fp btn-fp-outline" style="margin-left:10px;"><i class="fa-solid fa-lock"></i> Change Password</a>
                    </form>

                    <!-- Account Info -->
                    <div style="margin-top:24px; padding-top:20px; border-top:1px solid var(--fp-border);">
                        <div class="form-section-title">Account Summary</div>
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                            <div style="background:rgba(23,185,120,0.07); border-radius:12px; padding:16px;">
                                <div style="font-size:0.75rem; color:var(--fp-text-muted); font-weight:600; margin-bottom:4px;">Total Income</div>
                                <div style="font-size:1.1rem; font-weight:800; color:var(--fp-accent);"><?=format_currency($total_inc_amt)?></div>
                            </div>
                            <div style="background:rgba(229,72,77,0.07); border-radius:12px; padding:16px;">
                                <div style="font-size:0.75rem; color:var(--fp-text-muted); font-weight:600; margin-bottom:4px;">Total Expenses</div>
                                <div style="font-size:1.1rem; font-weight:800; color:var(--fp-danger);"><?=format_currency($total_exp_amt)?></div>
                            </div>
                            <div style="background:rgba(36,87,217,0.07); border-radius:12px; padding:16px;">
                                <div style="font-size:0.75rem; color:var(--fp-text-muted); font-weight:600; margin-bottom:4px;">Net Savings</div>
                                <div style="font-size:1.1rem; font-weight:800; color:var(--fp-primary);"><?=format_currency($total_inc_amt - $total_exp_amt)?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
