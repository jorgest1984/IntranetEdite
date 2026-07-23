<?php
require 'includes/config.php';

$stmt = $pdo->query('SHOW COLUMNS FROM acciones_formativas');
echo "acciones_formativas:\n";
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));

$stmt = $pdo->query('SHOW COLUMNS FROM grupos');
echo "\ngrupos:\n";
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
