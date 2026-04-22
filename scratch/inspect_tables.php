<?php
require_once 'includes/config.php';
$tables = ['usuarios', 'profesorado_detalles', 'roles'];
foreach ($tables as $t) {
    echo "--- TABLE: $t ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $t");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} ({$row['Type']})\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
