<?php
// remote_inspect.php
// Fake HTTP_HOST to force prod config (bridge)
$_SERVER['HTTP_HOST'] = 'gestion.grupoefp.es';
require_once 'includes/config.php';

$tables = ['profesorado_detalles'];
foreach ($tables as $t) {
    echo "--- REMOTE TABLE: $t ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $t");
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            echo "{$row['Field']} ({$row['Type']})\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
