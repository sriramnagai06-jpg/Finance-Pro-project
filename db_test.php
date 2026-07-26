<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Railway DB Connection Diagnostic</h2><pre>";

$hosts = [
    'mysql.railway.internal',
    'MySQL.railway.internal',
    getenv('MYSQLHOST'),
    'reseau.proxy.rlwy.net'
];

foreach ($hosts as $host) {
    if (empty($host)) continue;
    echo "\nTesting Host: {$host}\n";
    $ip = @gethostbyname($host);
    echo "Resolved IP: {$ip}\n";
    
    $port = ($host === 'reseau.proxy.rlwy.net') ? 42902 : 3306;
    echo "Connecting to {$host}:{$port}...\n";
    
    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    if (@$conn->real_connect($host, getenv('MYSQLUSER'), getenv('MYSQLPASSWORD'), getenv('MYSQLDATABASE'), $port)) {
        echo "SUCCESSFULLY CONNECTED TO {$host}!\n";
        $conn->close();
        break;
    } else {
        echo "Failed ({$conn->connect_errno}): {$conn->connect_error}\n";
    }
}

echo "</pre>";
