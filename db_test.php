<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Railway DB Connection Diagnostic</h2><pre>";

$host = 'reseau.proxy.rlwy.net';
$port = 42902;
$user = 'root';
$pass = 'xvwuxAuOMvUItXEGUOzYWoDuIjPNGtjb';
$db   = 'railway';

echo "Testing mysqli connection to {$host}:{$port}...\n";
$conn = mysqli_init();
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
if (@$conn->real_connect($host, $user, $pass, $db, $port)) {
    echo "SUCCESS! Connected to MySQL database.\n";
    $res = $conn->query("SHOW TABLES");
    echo "Tables count: " . $res->num_rows . "\n";
    $conn->close();
} else {
    echo "Error (" . mysqli_connect_errno() . "): " . mysqli_connect_error() . "\n";
}

echo "</pre>";
