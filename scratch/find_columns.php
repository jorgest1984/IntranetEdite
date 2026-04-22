<?php
require 'includes/config.php';
$stmt = $pdo->query('SHOW TABLES');
// FETCH_COLUMN should work with reset() if the bridge is being weird.
while ($row = $stmt->fetch()) {
    $table = reset($row);
    $st = $pdo->query("DESCRIBE `$table` ");
    $data = $st->fetchAll();
    foreach ($data as $c) {
        if (strpos(strtolower($c['Field']), 'motivo') !== false || strpos(strtolower($c['Field']), 'forma') !== false) {
            echo "Table: $table, Column: {$c['Field']}\n";
        }
    }
}
echo "Done.\n";
