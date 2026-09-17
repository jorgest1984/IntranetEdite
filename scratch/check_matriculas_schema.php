<?php
// scratch/check_matriculas_schema.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== COLUMNAS DE LA TABLA MATRICULAS ===\n\n";

$stmt = $pdo->query("SHOW COLUMNS FROM matriculas");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($cols as $c) {
    echo "- {$c['Field']} ({$c['Type']}) Null:{$c['Null']} Default:{$c['Default']}\n";
}
