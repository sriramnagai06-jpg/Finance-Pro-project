<?php
// run_migration.php - Executes database schema migrations using the existing DB connection

require_once __DIR__ . '/config.php';

$migrationFiles = [
    __DIR__ . '/migrations/create_payments_table.sql',
    __DIR__ . '/database/schema_extension.sql'
];

foreach ($migrationFiles as $file) {
    if (!file_exists($file)) {
        echo "Migration file not found: {$file}\n";
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "Failed to read migration file: {$file}\n";
        continue;
    }
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());

        if ($conn->error) {
            echo "Warning on {$file}: " . $conn->error . "\n";
        } else {
            echo "Successfully executed: " . basename($file) . "\n";
        }
    } else {
        echo "Failed executing {$file}: " . $conn->error . "\n";
    }
}
echo "All migrations processed!\n";
?>
