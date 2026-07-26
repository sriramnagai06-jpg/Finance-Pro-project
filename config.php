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
$db_host = getenv('MYSQLHOST') ?: 'localhost';
$db_user = getenv('MYSQLUSER') ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: '';
$db_name = getenv('MYSQLDATABASE') ?: 'financepro';
$db_port = (int)(getenv('MYSQLPORT') ?: 3306);

// Railway environment auto-detection
if (getenv('RAILWAY_ENVIRONMENT') || getenv('RAILWAY_PROJECT_ID')) {
    if (empty($db_host) || strpos($db_host, 'proxy.rlwy.net') !== false || $db_host === 'localhost') {
        $db_host = 'mysql.railway.internal';
        $db_port = 3306;
    }
    if (empty($db_pass)) {
        $db_pass = 'xvwuxAuOMvUItXEGUOzYWoDuIjPNGtjb';
    }
    if ($db_name === 'financepro') {
        $db_name = 'railway';
    }
}

define('DB_HOST', $db_host);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_NAME', $db_name);
define('DB_PORT', $db_port);

// ---- Site constants ----
define('SITE_NAME', 'FinancePro');
// Base URL (files in root = '/')
define('BASE_URL', '/');
define('CURRENCY', 'Rs.');
define('UPLOAD_PROFILE_DIR', __DIR__ . '/uploads/profile/');
define('UPLOAD_LOGO_DIR', __DIR__ . '/uploads/logos/');

// ---- Database connection (mysqli) ----
$conn = mysqli_init();
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
if (!@$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT)) {
    die('Database connection failed (' . mysqli_connect_errno() . '): ' . mysqli_connect_error() . ' [Host: ' . DB_HOST . ':' . DB_PORT . ']');
}
$conn->set_charset('utf8mb4');

/**
 * Sanitize any output string to prevent XSS.
 * Use this whenever echoing user-supplied data back into HTML.
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
