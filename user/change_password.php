<?php
/**
 * FinancePro - Change Password (logged-in users)
 * Location: /FinancePro/user/change_password.php
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password      = $_POST['new_password'] ?? '';
        $confirm_password  = $_POST['confirm_password'] ?? '';

        $stmt = $conn->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current_password, $row['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (!is_strong_password($new_password)) {
            $errors[] = 'New password must be at least 8 characters with uppercase, lowercase, and a number.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'New Password and Confirm Password do not match.';
        } elseif (password_verify($new_password, $row['password_hash'])) {
            $errors[] = 'New password must be different from your current password.';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $update = $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
            $update->bind_param('si', $new_hash, $_SESSION['user_id']);
            $update->execute();
            $update->close();
            $success = true;
            set_flash('success', 'Password changed successfully.');
        }
    }
}

$csrf_token = generate_csrf_token();
$page_title = 'Change Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .settings-card {
            max-width: 520px;
            margin: 60px auto;
            background: var(--fp-card-bg);
            border-radius: var(--fp-radius);
            box-shadow: var(--fp-shadow);
            padding: 40px;
        }
        body { background: var(--fp-bg); }
    </style>
</head>
<body>
<div class="container">
    <div class="settings-card">
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="dashboard.php" class="text-muted"><i class="fa-solid fa-arrow-left"></i></a>
            <h2 class="mb-0 fw-bold">Change Password</h2>
        </div>
        <p class="text-muted mb-4">Update your password regularly to keep your account secure.</p>

        <?php include '../includes/alerts.php'; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-fp">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="change_password.php" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

            <div class="mb-3">
                <label for="current_password" class="form-label">Current Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                    <button class="btn btn-toggle-pass" type="button" data-target="current_password"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">New Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="new_password" required minlength="8">
                    <button class="btn btn-toggle-pass" type="button" data-target="password"><i class="fa-solid fa-eye"></i></button>
                </div>
                <div class="password-strength"><div class="password-strength-bar" id="passwordStrengthBar"></div></div>
                <div class="form-hint" id="passwordStrengthLabel">Min 8 characters, 1 uppercase, 1 lowercase, 1 number.</div>
            </div>

            <div class="mb-4">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <button class="btn btn-toggle-pass" type="button" data-target="confirm_password"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>

            <button type="submit" class="btn btn-fp-primary w-100">Update Password</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/auth.js"></script>
<script src="../assets/js/app.js"></script>`r`n</body>
</html>
