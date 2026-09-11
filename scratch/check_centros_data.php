<?php
// scratch/check_centros_data.php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== CENTROS EN BD ===\n";
$stmt = $pdo->query("SELECT * FROM centros");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== MODALIDADES EN ACCIONES FORMATIVAS ===\n";
$stmt2 = $pdo->query("SELECT DISTINCT modalidad FROM acciones_formativas");
print_r($stmt2->fetchAll(PDO::FETCH_COLUMN));

echo "\n=== CENTROS / SEDES EN GRUPOS O AF ===\n";
$stmt3 = $pdo->query("SELECT DISTINCT centro_id FROM grupos");
print_r($stmt3->fetchAll(PDO::FETCH_COLUMN));
