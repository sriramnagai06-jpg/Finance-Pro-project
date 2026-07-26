<?php
/**
 * FinancePro - Reset Password Page
 * Location: /FinancePro/reset_password.php
 * Accessed via the link generated in forgot_password.php (?token=...)
 */
require_once 'config.php';
require_once 'includes/functions.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = [];
$valid_token = false;
$user_id = null;

if ($token !== '') {
    $stmt = $conn->prepare('SELECT user_id, reset_token_expiry FROM users WHERE reset_token = ?');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && strtotime($row['reset_token_expiry']) > time()) {
        $valid_token = true;
        $user_id = $row['user_id'];
    } else {
        $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
    }
} else {
    $errors[] = 'No reset token provided.';
}

if ($valid_token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!is_strong_password($password)) {
            $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Password and Confirm Password do not match.';
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $update = $conn->prepare(
                'UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?'
            );
            $update->bind_param('si', $password_hash, $user_id);
            $update->execute();
            $update->close();

            set_flash('success', 'Your password has been reset. Please log in with your new password.');
            header('Location: ' . BASE_URL . 'login.php');
            exit;
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-brand-panel">
            <div class="logo mb-3"><i class="fa-solid fa-chart-pie"></i> FinancePro</div>
            <h1>Set a new password.</h1>
            <p>Choose a strong password you haven't used before to keep your financial data secure.</p>
        </div>

        <div class="auth-form-panel">
            <div class="logo"><i class="fa-solid fa-chart-pie"></i> FinancePro</div>
            <h2>Create new password</h2>
            <p class="subtitle">This link is valid for a limited time only.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-fp">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($valid_token): ?>
            <form method="POST" action="reset_password.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
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

                <button type="submit" class="btn btn-fp-primary w-100">Reset Password</button>
            </form>
            <?php else: ?>
                <a href="forgot_password.php" class="btn btn-fp-primary w-100">Request a New Link</a>
            <?php endif; ?>

            <div class="auth-switch-link">
                <a href="login.php">Back to login</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/auth.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
