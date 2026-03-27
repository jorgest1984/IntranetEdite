<?php
require_once 'includes/config.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $rows = $stmt->fetchAll();
    echo "--- TABLE LIST ---\n";
    foreach ($rows as $row) {
        // Since the key is "Tables_in_...", we take the first value
        $tableName = reset($row);
        echo "Table: $tableName\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
