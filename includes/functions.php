<?php
/**
 * FinancePro - Shared Helper Functions
 * Location: /FinancePro/includes/functions.php
 * Requires: config.php to be included first ($conn must exist)
 */

/* ---------------------------------------------------------------------
 * CSRF PROTECTION
 * ------------------------------------------------------------------ */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    return isset($_SESSION['csrf_token']) && $token !== null
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ---------------------------------------------------------------------
 * INPUT SANITIZATION / VALIDATION
 * ------------------------------------------------------------------ */
function clean_input(string $data): string {
    return trim(strip_tags($data));
}

function is_valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Enforces a reasonably strong password:
 * min 8 chars, at least 1 uppercase, 1 lowercase, 1 number.
 */
function is_strong_password(string $password): bool {
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

/* ---------------------------------------------------------------------
 * FLASH MESSAGES (one-time session alerts shown after redirect)
 * ------------------------------------------------------------------ */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/* ---------------------------------------------------------------------
 * AUTH GUARDS
 * ------------------------------------------------------------------ */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function is_admin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/** Redirect to login if not authenticated. Call at the top of protected pages. */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to continue.');
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/** Redirect non-admins away from admin-only pages. */
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'You do not have permission to access that page.');
        header('Location: ' . BASE_URL . 'user/dashboard.php');
        exit;
    }
}

/* ---------------------------------------------------------------------
 * MISC HELPERS
 * ------------------------------------------------------------------ */
function format_currency(float $amount): string {
    return CURRENCY . ' ' . number_format($amount, 2);
}

function format_date(string $date): string {
    return date('d M Y', strtotime($date));
}

/** Generates a unique invoice number like INV-2026-0007 */
function generate_invoice_number(mysqli $conn): string {
    $year = date('Y');
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM invoices WHERE YEAR(invoice_date) = $year");
    $row = $result->fetch_assoc();
    $next = $row['cnt'] + 1;
    return sprintf('INV-%s-%04d', $year, $next);
}

/* ---------------------------------------------------------------------
 * MODULE 14: SECURITY (PHASE 2) & LOGGING
 * ------------------------------------------------------------------ */
function audit_log($conn, $user_id, $action, $table, $record_id, $desc) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    // Supports both 'action_type' (legacy) and 'action' column names
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action_type, table_name, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('ississ', $user_id, $action, $table, $record_id, $desc, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// XSS Protection Wrapper
function sanitize_output($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize_output($value);
        }
        return $data;
    }
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------------------------------------
 * MODULE 11: NOTIFICATIONS (PHASE 3)
 * ------------------------------------------------------------------ */
function add_notification($conn, $user_id, $type, $title, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('isss', $user_id, $type, $title, $message);
        $stmt->execute();
        $stmt->close();
    }
}

function get_unread_notifications_count($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $count;
    }
    return 0;
}

/* ---------------------------------------------------------------------
 * MODULE 12: SETTINGS (PHASE 4)
 * ------------------------------------------------------------------ */
function get_user_settings($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id=?");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) return $res;
    }
    // Return defaults if none exist
    return [
        'currency_symbol' => 'Rs.',
        'currency_position' => 'prefix',
        'theme' => 'light',
        'large_expense_threshold' => 5000.00,
        'default_cgst' => 9.00,
        'default_sgst' => 9.00,
        'default_igst' => 18.00,
        'company_name' => '',
        'company_address' => '',
        'company_gstin' => '',
        'company_phone' => '',
        'company_email' => '',
        'invoice_logo' => null
    ];
}

function format_currency_custom($amount, $settings) {
    $symbol = $settings['currency_symbol'] ?? 'Rs.';
    $pos = $settings['currency_position'] ?? 'prefix';
    $formatted = number_format($amount, 2);
    return $pos === 'prefix' ? "$symbol $formatted" : "$formatted $symbol";
}
