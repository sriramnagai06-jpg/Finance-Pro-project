<?php
/**
 * FinancePro - Login Page
 * Location: /FinancePro/login.php
 */
require_once 'config.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . (is_admin() ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit;
}

$errors = [];
$old_email = '';

// ---- Basic brute-force throttle using session attempt counter ----
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_lock_until'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (time() < ($_SESSION['login_lock_until'] ?? 0)) {
        $wait = $_SESSION['login_lock_until'] - time();
        $errors[] = "Too many failed attempts. Please try again in {$wait} seconds.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $email    = clean_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $old_email = $email;

        if (!is_valid_email($email) || $password === '') {
            $errors[] = 'Please enter a valid email and password.';
        } else {
            $stmt = $conn->prepare('SELECT user_id, full_name, email, password_hash, role, status FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] === 'blocked') {
                    $errors[] = 'Your account has been blocked. Please contact the administrator.';
                    audit_log($conn, $user['user_id'], 'login_failed', 'users', $user['user_id'], 'Login attempt on blocked account');
                } else {
                    // ---- Successful login ----
                    session_regenerate_id(true); // prevent session fixation
                    $_SESSION['user_id']   = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email']     = $user['email'];
                    $_SESSION['role']      = $user['role'];
                    $_SESSION['login_attempts'] = 0;

                    // Audit and Notify
                    audit_log($conn, $user['user_id'], 'login_success', 'users', $user['user_id'], 'Successful login');
                    add_notification($conn, $user['user_id'], 'system', 'Login Successful', 'You have successfully logged in.');

                    set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                    header('Location: ' . BASE_URL . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php'));
                    exit;
                }
            } else {
                $_SESSION['login_attempts']++;
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_lock_until'] = time() + (15 * 60); // 15 minutes lock
                    $errors[] = 'Too many failed attempts. Please try again in 15 minutes.';
                } else {
                    $errors[] = 'Invalid email or password.';
                }
                if ($user) {
                    audit_log($conn, $user['user_id'], 'login_failed', 'users', $user['user_id'], 'Invalid password attempt');
                }
            }
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
    <title>Login - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-brand-panel">
            <div class="logo mb-3"><i class="fa-solid fa-chart-pie"></i> FinancePro</div>
            <h1>Welcome back.</h1>
            <p>Sign in to view your dashboard, track spending, and stay on top of your budget.</p>
            <ul class="list-unstyled feature-list mt-4">
                <li><i class="fa-solid fa-check"></i> Instant dashboard overview</li>
                <li><i class="fa-solid fa-check"></i> Income vs expense insights</li>
                <li><i class="fa-solid fa-check"></i> Secure, encrypted login</li>
            </ul>
            <div class="mt-4 p-3 rounded-3" style="background: rgba(255,255,255,0.1); font-size:0.82rem;">
                <strong>Demo credentials</strong><br>
                User: demo@financepro.com / Demo@123<br>
                Admin: admin@financepro.com / Admin@123
            </div>
        </div>

        <div class="auth-form-panel">
            <div class="logo"><i class="fa-solid fa-chart-pie"></i> FinancePro</div>
            <h2>Sign in to your account</h2>
            <p class="subtitle">Enter your credentials to continue.</p>

            <?php include 'includes/alerts.php'; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-fp">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= e($old_email) ?>" required placeholder="you@example.com">
                    <div class="invalid-feedback">Please enter a valid email.</div>
                </div>

                <div class="mb-2">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-toggle-pass" type="button" data-target="password"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div class="invalid-feedback">Please enter your password.</div>
                </div>

                <div class="d-flex justify-content-end mb-4">
                    <a href="forgot_password.php" class="small text-decoration-none">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-fp-primary w-100">Sign In</button>
            </form>

            <div class="auth-switch-link">
                Don't have an account? <a href="register.php">Create one</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/auth.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
