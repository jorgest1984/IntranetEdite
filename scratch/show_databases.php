<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $stmt = $pdo->query("SHOW DATABASES");
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "DATABASES:\n";
    print_r($dbs);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
