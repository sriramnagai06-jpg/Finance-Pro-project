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
        echo "Migration file not found: {$file}{$br}";
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "Failed to read migration file: {$file}{$br}";
        continue;
    }
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());

        if ($conn->error) {
            echo "Warning on " . basename($file) . ": " . $conn->error . "{$br}";
        } else {
            echo "Successfully executed: " . basename($file) . "{$br}";
        }
    } else {
        echo "Failed executing " . basename($file) . ": " . $conn->error . "{$br}";
    }
}
echo "All migrations processed successfully!{$br}";
if (!$isCli) {
    echo "</pre><p><a href='/login.php'>Go to Login Page</a></p>";
}
?>
