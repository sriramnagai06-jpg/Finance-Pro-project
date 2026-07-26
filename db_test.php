<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Railway DB Connection Diagnostic</h2><pre>";

$hosts = [
    'mysql.railway.internal',
    'localhost',
    '127.0.0.1'
];

foreach ($hosts as $host) {
    echo "\nTesting PDO connection to {$host}:3306...\n";
    try {
        $dsn = "mysql:host={$host};port=3306;dbname=railway;charset=utf8mb4";
        $pdo = new PDO($dsn, 'root', 'xvwuxAuOMvUItXEGUOzYWoDuIjPNGtjb', [
            PDO::ATTR_TIMEOUT => 3,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "SUCCESS (PDO)! Connected to {$host}.\n";
    } catch (Exception $e) {
        echo "PDO Error on {$host}: " . $e->getMessage() . "\n";
    }
}

echo "</pre>";
