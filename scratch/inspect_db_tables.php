<?php
$_SERVER['HTTP_HOST'] = 'pre-gestion.grupoefp.es'; // Connect to pre-prod DB!
require_once __DIR__ . '/../includes/config.php';

echo "Database: " . DB_NAME . "\n\n";

$tablesStmt = $pdo->query("SHOW TABLES");
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "========================================\n";
    echo "TABLE: $table\n";
    echo "========================================\n";
    $colsStmt = $pdo->query("DESCRIBE `$table`");
    $cols = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo sprintf("  %-25s %-15s %-5s %-3s %-10s %s\n", 
            $c['Field'] ?? $c['field'], 
            $c['Type'] ?? $c['type'], 
            $c['Null'] ?? $c['null'], 
            $c['Key'] ?? $c['key'], 
            $c['Default'] ?? $c['default'] ?? 'NULL', 
            $c['Extra'] ?? $c['extra']
        );
    }
    echo "\n";
}
