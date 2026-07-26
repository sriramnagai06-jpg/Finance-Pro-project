<?php
/**
 * ONE-TIME SETUP SCRIPT
 * Visit this URL ONCE to fix config.php, then DELETE this file immediately.
 * URL: https://financepro.great-site.net/setup_config.php
 */

$configContent = '<?php
/**
 * FinancePro - Global Configuration
 * Handles: DB connection (mysqli, prepared-statement ready), session start,
 * error reporting, and site-wide constants.
 * Location: /FinancePro/config.php
 */

// ---- Error reporting (hide errors in production) ----
error_reporting(E_ALL);
ini_set(\'display_errors\', 0);
ini_set(\'log_errors\', 1);

// ---- Security Headers & HTTPS Enforcement ----
if (isset($_SERVER[\'HTTPS\']) && $_SERVER[\'HTTPS\'] === \'on\') {
    ini_set(\'session.cookie_secure\', 1);
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
} elseif (isset($_SERVER[\'HTTP_HOST\']) && strpos($_SERVER[\'HTTP_HOST\'], \'localhost\') === false && strpos($_SERVER[\'HTTP_HOST\'], \'127.0.0.1\') === false) {
    // Redirect HTTP to HTTPS for remote domains
    if (empty($_SERVER[\'HTTPS\']) || $_SERVER[\'HTTPS\'] === \'off\') {
        header("Location: https://" . $_SERVER[\'HTTP_HOST\'] . $_SERVER[\'REQUEST_URI\'], true, 301);
        exit;
    }
}

header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Content-Security-Policy: default-src \'self\' https: data: \'unsafe-inline\' \'unsafe-eval\';");

// ---- Secure session settings (must be set BEFORE session_start) ----
ini_set(\'session.cookie_httponly\', 1);   // JS cannot read session cookie
ini_set(\'session.use_strict_mode\', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Database credentials (InfinityFree Production) ----
define(\'DB_HOST\', \'sql213.infinityfree.com\');
define(\'DB_USER\', \'if0_42501853\');
define(\'DB_PASS\', \'BJ4OjCaHXt\');
define(\'DB_NAME\', \'if0_42501853_if0_42444507_financepro\');
define(\'DB_PORT\', 3306);

// ---- Site constants ----
define(\'SITE_NAME\', \'FinancePro\');
// Base URL for InfinityFree (files in root = \'/\', files in subfolder = \'/subfolder/\')
define(\'BASE_URL\', \'/\');
define(\'CURRENCY\', \'Rs.\');
define(\'UPLOAD_PROFILE_DIR\', __DIR__ . \'/uploads/profile/\');
define(\'UPLOAD_LOGO_DIR\', __DIR__ . \'/uploads/logos/\');

// ---- Database connection (mysqli - used with prepared statements) ----
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die(\'Database connection failed: \' . $conn->connect_error);
}
$conn->set_charset(\'utf8mb4\');

/**
 * Sanitize any output string to prevent XSS.
 * Use this whenever echoing user-supplied data back into HTML.
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, \'UTF-8\');
}
';

$targetFile = __DIR__ . '/config.php';
$result = file_put_contents($targetFile, $configContent);

if ($result !== false) {
    echo "<h1 style='color:green'>✅ config.php has been fixed!</h1>";
    echo "<p>Written " . $result . " bytes to config.php</p>";
    echo "<p><strong>IMPORTANT:</strong> Now delete this setup_config.php file from the server!</p>";
    echo "<p><a href='/login.php'>→ Go to Login Page</a></p>";
} else {
    echo "<h1 style='color:red'>❌ Failed to write config.php</h1>";
    echo "<p>Check file permissions on the server.</p>";
}
?>
