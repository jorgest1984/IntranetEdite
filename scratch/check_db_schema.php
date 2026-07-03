<?php
require_once __DIR__ . '/../includes/config.php';

$stmt = $pdo->query("DESCRIBE matriculas");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Matriculas columns:\n";
print_r($cols);

$stmt2 = $pdo->query("DESCRIBE alumnos");
$cols2 = $stmt2->fetchAll(PDO::FETCH_COLUMN);
echo "Alumnos columns:\n";
print_r($cols2);
