<?php
/**
 * FinancePro - Global Configuration
 * Handles: DB connection (mysqli, prepared-statement ready), session start,
 * error reporting, and site-wide constants.
 * Location: /config.php
 */

// ---- Error reporting (hide errors in production) ----
error_reporting(E_ALL);
ini_set('display_errors', 0);
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

// ---- Database credentials ----
// Check if running on Railway cloud environment
$isRailway = (!empty($_ENV['RAILWAY_ENVIRONMENT']) || !empty($_ENV['RAILWAY_PROJECT_ID']) || !empty(getenv('RAILWAY_ENVIRONMENT')) || !empty(getenv('RAILWAY_PROJECT_ID')));

if ($isRailway) {
    define('DB_HOST', 'mysql.railway.internal');
    define('DB_USER', 'root');
    define('DB_PASS', 'xvwuxAuOMvUItXEGUOzYWoDuIjPNGtjb');
    define('DB_NAME', 'railway');
    define('DB_PORT', 3306);
} else {
    // Local XAMPP development
    define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
    define('DB_USER', getenv('MYSQLUSER') ?: 'root');
    define('DB_PASS', getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : '');
    define('DB_NAME', getenv('MYSQLDATABASE') ?: 'financepro');
    define('DB_PORT', (int)(getenv('MYSQLPORT') ?: 3307));
}

// ---- Database connection (mysqli) ----
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error . ' [Host: ' . DB_HOST . ']');
}
$conn->set_charset('utf8mb4');

// ---- Secure session settings ----
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Site constants ----
define('SITE_NAME', 'FinancePro');

if (!defined('BASE_URL')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        $baseUrl = '/';
    } else {
        $dir = preg_replace('#/(user|admin|includes)$#i', '', $dir);
        $baseUrl = rtrim($dir, '/') . '/';
    }
    define('BASE_URL', $baseUrl);
}
define('CURRENCY', 'Rs.');
define('UPLOAD_PROFILE_DIR', __DIR__ . '/uploads/profile/');
define('UPLOAD_LOGO_DIR', __DIR__ . '/uploads/logos/');

/**
 * Sanitize any output string to prevent XSS.
 * Use this whenever echoing user-supplied data back into HTML.
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
