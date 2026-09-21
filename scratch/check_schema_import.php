<?php
require_once __DIR__ . '/../includes/config.php';
echo "<pre>";
echo "=== ALUMNOS COLUMNS ===\n";
print_r($pdo->query("DESCRIBE alumnos")->fetchAll(PDO::FETCH_COLUMN));
echo "\n=== EMPRESAS COLUMNS ===\n";
print_r($pdo->query("DESCRIBE empresas")->fetchAll(PDO::FETCH_COLUMN));
echo "\n=== MATRICULAS COLUMNS ===\n";
print_r($pdo->query("DESCRIBE matriculas")->fetchAll(PDO::FETCH_COLUMN));
echo "</pre>";
