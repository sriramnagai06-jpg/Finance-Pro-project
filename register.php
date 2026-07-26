<?php
/**
 * FinancePro - Registration Page
 * Location: /FinancePro/register.php
 */
require_once 'config.php';
require_once 'includes/functions.php';

// Already logged in? Skip to dashboard.
if (is_logged_in()) {
    header('Location: ' . BASE_URL . (is_admin() ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit;
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- CSRF check ----
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $full_name = clean_input($_POST['full_name'] ?? '');
    $email     = clean_input($_POST['email'] ?? '');
    $phone     = clean_input($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    $old = ['full_name' => $full_name, 'email' => $email, 'phone' => $phone];

    // ---- Server-side validation ----
    if ($full_name === '' || strlen($full_name) < 3) {
        $errors[] = 'Full name must be at least 3 characters.';
    }
    if (!is_valid_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($phone !== '' && !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'Phone number must be exactly 10 digits.';
    }
    if (!is_strong_password($password)) {
        $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and Confirm Password do not match.';
    }

    // ---- Duplicate email check (prepared statement) ----
    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        }
        $stmt->close();
    }

    // ---- Insert new user ----
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, status)
             VALUES (?, ?, ?, ?, "user", "active")'
        );
        $stmt->bind_param('ssss', $full_name, $email, $phone, $password_hash);

        if ($stmt->execute()) {
            $new_user_id = $stmt->insert_id;
            $stmt->close();

            // Create default settings row for the new user
            $settingsStmt = $conn->prepare('INSERT INTO user_settings (user_id) VALUES (?)');
            $settingsStmt->bind_param('i', $new_user_id);
            $settingsStmt->execute();
            $settingsStmt->close();

            // Create default system accounts (Cash, Bank, Card, Other Asset)
            $sysAccounts = [
                ['Cash', 'Asset'],
                ['Bank', 'Asset'],
                ['Card', 'Liability'],
                ['Other Asset', 'Asset']
            ];
            $sysStmt = $conn->prepare('INSERT IGNORE INTO accounts (user_id, account_name, account_type, is_system) VALUES (?, ?, ?, 1)');
            foreach ($sysAccounts as $acc) {
                $sysStmt->bind_param('iss', $new_user_id, $acc[0], $acc[1]);
                $sysStmt->execute();
            }
            $sysStmt->close();

            // Create accounts for all existing categories for this user
            $catResult = $conn->query('SELECT category_name, category_type FROM categories');
            if ($catResult && $catResult->num_rows > 0) {
                $catStmt = $conn->prepare('INSERT IGNORE INTO accounts (user_id, account_name, account_type, is_system) VALUES (?, ?, ?, 0)');
                while ($cat = $catResult->fetch_assoc()) {
                    $accType = ($cat['category_type'] === 'income') ? 'Revenue' : 'Expense';
                    $catStmt->bind_param('iss', $new_user_id, $cat['category_name'], $accType);
                    $catStmt->execute();
                }
                $catStmt->close();
            }

            set_flash('success', 'Account created successfully! Please log in.');
            header('Location: ' . BASE_URL . 'login.php');
            exit;
        } else {
            $errors[] = 'Something went wrong while creating your account. Please try again.';
            $stmt->close();
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
    <title>Create Account - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-wrapper">
        <!-- Brand / feature panel -->
        <div class="auth-brand-panel">
            <div class="logo mb-3"><i class="fa-solid fa-chart-pie"></i> FinancePro</div>
            <h1>Start managing your money smarter.</h1>
            <p>Join thousands of users tracking income, expenses, budgets, and invoices in one clean dashboard.</p>
            <ul class="list-unstyled feature-list mt-4">
                <li><i class="fa-solid fa-check"></i> Real-time income &amp; expense tracking</li>
                <li><i class="fa-solid fa-check"></i> Visual reports &amp; monthly trends</li>
                <li><i class="fa-solid fa-check"></i> Built-in GST calculator &amp; invoicing</li>
                <li><i class="fa-solid fa-check"></i> Bank-grade password security</li>
            </ul>
        </div>

        <!-- Form panel -->
        <div class="auth-form-panel">
            <div class="logo"><i class="fa-solid fa-chart-pie"></i> FinancePro</div>
            <h2>Create your account</h2>
            <p class="subtitle">It only takes a minute to get started.</p>

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

            <form method="POST" action="register.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           value="<?= e($old['full_name']) ?>" required minlength="3" placeholder="e.g. Priya Sharma">
                    <div class="invalid-feedback">Please enter your full name.</div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= e($old['email']) ?>" required placeholder="you@example.com">
                    <div class="invalid-feedback">Please enter a valid email.</div>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" class="form-control" id="phone" name="phone" maxlength="10"
                           value="<?= e($old['phone']) ?>" placeholder="10-digit mobile number">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                        <button class="btn btn-toggle-pass" type="button" data-target="password"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div class="password-strength"><div class="password-strength-bar" id="passwordStrengthBar"></div></div>
                    <div class="form-hint" id="passwordStrengthLabel">Min 8 characters, 1 uppercase, 1 lowercase, 1 number.</div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <button class="btn btn-toggle-pass" type="button" data-target="confirm_password"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div class="invalid-feedback">Passwords must match.</div>
                </div>

                <button type="submit" class="btn btn-fp-primary w-100">Create Account</button>
            </form>

            <div class="auth-switch-link">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/auth.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
