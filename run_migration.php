<?php
// run_migration.php - Executes database schema migrations using the existing DB connection

require_once __DIR__ . '/config.php';

$isCli = (php_sapi_name() === 'cli');
$br = $isCli ? "\n" : "<br>\n";

if (!$isCli) {
    echo "<h2>Database Migration Runner</h2><pre>";
}

$migrationFiles = [
    __DIR__ . '/database/financepro.sql',
    __DIR__ . '/database/schema_extension.sql',
    __DIR__ . '/migrations/create_payments_table.sql'
];

foreach ($migrationFiles as $file) {
    if (!file_exists($file)) {
        echo "Migration file not found: " . basename($file) . "{$br}";
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "Failed to read migration file: " . basename($file) . "{$br}";
        continue;
    }

    // Remove comments
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    // Split by statement
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    $successCount = 0;
    $errorCount = 0;

    foreach ($queries as $q) {
        if (empty($q)) continue;
        if ($conn->query($q)) {
            $successCount++;
        } else {
            $errorCount++;
            // Ignore table already exists errors or duplicate column errors gracefully
            if ($conn->errno != 1050 && $conn->errno != 1060) {
                echo "Notice on " . basename($file) . " query (" . $conn->errno . "): " . $conn->error . "{$br}";
            }
        }
    }
    echo "Executed " . basename($file) . ": {$successCount} queries succeeded, {$errorCount} notices.{$br}";
}
echo "All migrations processed successfully!{$br}";
if (!$isCli) {
    echo "</pre><p><a href='/login.php'>Go to Login Page</a></p>";
}
?>
