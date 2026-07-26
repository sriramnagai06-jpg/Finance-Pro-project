<?php
/**
 * FinancePro - Global Configuration
 * Handles: DB connection (mysqli, prepared-statement ready), session start,
 * error reporting, and site-wide constants.
 * Location: /FinancePro/config.php
 */

// ---- Error reporting ----
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// ---- Security Headers & HTTPS Enforcement ----
if ((!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) {
    $_SERVER['HTTPS'] = 'on';
    ini_set('session.cookie_secure', 1);
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
} elseif (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') === false && strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false) {
    // Redirect HTTP to HTTPS for remote domains
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
        exit;
    }
}

header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Content-Security-Policy: default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';");

// ---- Secure session settings (must be set BEFORE session_start) ----
ini_set('session.cookie_httponly', 1);   // JS cannot read session cookie
ini_set('session.use_strict_mode', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Database credentials ----
// Railway sets these env vars automatically when you add a MySQL service.
// Falls back to XAMPP defaults for local development.
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'financepro');
define('DB_PORT', (int)(getenv('MYSQLPORT') ?: 3306));

// ---- Site constants ----
define('SITE_NAME', 'FinancePro');
// Base URL for InfinityFree (files in root = '/', files in subfolder = '/subfolder/')
define('BASE_URL', '/');
define('CURRENCY', 'Rs.');
define('UPLOAD_PROFILE_DIR', __DIR__ . '/uploads/profile/');
define('UPLOAD_LOGO_DIR', __DIR__ . '/uploads/logos/');

// ---- Database connection (mysqli - used with prepared statements) ----
$conn = mysqli_init();
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
if (!@$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT)) {
    die('Database connection failed (' . mysqli_connect_errno() . '): ' . mysqli_connect_error());
}
$conn->set_charset('utf8mb4');

/**
 * Sanitize any output string to prevent XSS.
 * Use this whenever echoing user-supplied data back into HTML.
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
