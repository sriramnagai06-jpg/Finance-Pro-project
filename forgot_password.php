<?php
/**
 * FinancePro - Forgot Password Page
 * Location: /FinancePro/forgot_password.php
 *
 * Generates a secure, time-limited reset token and stores it against the user.
 * NOTE: A default XAMPP install has no configured mail server, so instead of
 * silently failing on mail(), this page displays the reset link directly on
 * screen (clearly labeled as a DEMO/DEV behavior). In a real deployment,
 * swap the "reset link" block below for PHPMailer/SMTP delivery to $email.
 */
require_once 'config.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . (is_admin() ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit;
}

$errors = [];
$reset_link = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $email = clean_input($_POST['email'] ?? '');

        if (!is_valid_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            // Always show the same generic success message whether or not the
            // email exists, to avoid leaking which emails are registered.
            if ($user) {
                $token   = bin2hex(random_bytes(32));
                $expiry  = date('Y-m-d H:i:s', strtotime('+30 minutes'));

                $update = $conn->prepare('UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?');
                $update->bind_param('ssi', $token, $expiry, $user['user_id']);
                $update->execute();
                $update->close();

                $reset_link = BASE_URL . 'reset_password.php?token=' . $token;
            }

            set_flash('success', 'If an account exists with that email, a password reset link has been generated.');
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
    <title>Forgot Password - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-brand-panel">
            <div class="logo mb-3"><i class="fa-solid fa-chart-pie"></i> FinancePro</div>
            <h1>Forgot your password?</h1>
            <p>No problem. Enter the email tied to your account and we'll generate a secure link to reset it.</p>
            <ul class="list-unstyled feature-list mt-4">
                <li><i class="fa-solid fa-check"></i> Reset links expire after 30 minutes</li>
                <li><i class="fa-solid fa-check"></i> Tokens are single-use &amp; cryptographically random</li>
            </ul>
        </div>

        <div class="auth-form-panel">
            <div class="logo"><i class="fa-solid fa-chart-pie"></i> FinancePro</div>
            <h2>Reset your password</h2>
            <p class="subtitle">We'll email you a secure link. (Demo mode shows it below.)</p>

            <?php include 'includes/alerts.php'; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-fp">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($reset_link): ?>
                <div class="alert alert-info alert-fp">
                    <strong>Demo mode</strong> — no SMTP server configured on this XAMPP install, so here is your reset link:<br>
                    <a href="<?= e($reset_link) ?>" class="text-break"><?= e($reset_link) ?></a>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                <div class="mb-4">
                    <label for="email" class="form-label">Registered Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="you@example.com">
                    <div class="invalid-feedback">Please enter a valid email.</div>
                </div>

                <button type="submit" class="btn btn-fp-primary w-100">Send Reset Link</button>
            </form>

            <div class="auth-switch-link">
                Remembered your password? <a href="login.php">Back to login</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/auth.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
