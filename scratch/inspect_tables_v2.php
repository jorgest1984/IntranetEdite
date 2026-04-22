<?php
require 'includes/config.php';
$res = $pdo->query("SHOW TABLES");
$tables = $res->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    if (strpos($table, 'tutoria') !== false || strpos($table, 'seguimiento') !== false || strpos($table, 'llamada') !== false) {
        echo "Table: $table\n";
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - {$row['Field']} ({$row['Type']})\n";
        }
    }
}
