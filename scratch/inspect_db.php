<?php
require 'includes/config.php';
$stmt = $pdo->query('SHOW TABLES');
$data = $stmt->fetchAll();
foreach ($data as $row) {
    $table = reset($row); // Get the first value of the row
    echo "Table: $table\n";
    try {
        $st = $pdo->query("DESCRIBE `$table` ");
        $cols = $st->fetchAll();
        foreach ($cols as $c) {
            echo "  - {$c['Field']} ({$c['Type']})\n";
        }
    } catch (Exception $e) {
        echo "  - Error describing table: " . $e->getMessage() . "\n";
    }
}
