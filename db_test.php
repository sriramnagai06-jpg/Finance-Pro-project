<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Railway DB Connection Diagnostic</h2><pre>";

$host = 'reseau.proxy.rlwy.net';
$port = 42902;
$user = 'root';
$pass = 'xvwuxAuOMvUItXEGUOzYWoDuIjPNGtjb';
$db   = 'railway';

echo "1. Testing PDO connection to {$host}:{$port}...\n";
try {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "SUCCESS (PDO)! Connected to database.\n";
} catch (Exception $e) {
    echo "PDO Error: " . $e->getMessage() . "\n";
}

echo "\n2. Testing mysqli with SSL flags to {$host}:{$port}...\n";
$conn = mysqli_init();
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
if (@$conn->real_connect($host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT)) {
    echo "SUCCESS (mysqli SSL)! Connected to database.\n";
    $conn->close();
} else {
    echo "mysqli SSL Error (" . mysqli_connect_errno() . "): " . mysqli_connect_error() . "\n";
}

echo "</pre>";
