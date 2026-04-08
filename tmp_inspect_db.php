<?php
require_once 'includes/config.php';
$tables = ['alumnos', 'profesorado_detalles'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    $stmt = $pdo->query("DESCRIBE $table");
    $cols = $stmt->fetchAll();
    foreach ($cols as $c) {
        $name = $c['Field'] ?? $c['field'] ?? 'unknown';
        $type = $c['Type'] ?? $c['type'] ?? 'unknown';
        echo "$name ($type)\n";
    }
    echo "\n";
}
?>
