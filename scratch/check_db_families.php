<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once 'includes/config.php';
try {
    $stmt = $pdo->query("DESCRIBE alumnos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "=== COLUMNAS EN ALUMNOS ===\n";
    foreach ($columns as $c) {
        if (strtolower($c['Field']) === 'sexo') {
            echo "{$c['Field']} - {$c['Type']} - Nullable: {$c['Null']} - Default: {$c['Default']}\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
