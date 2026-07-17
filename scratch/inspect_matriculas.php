<?php
require_once 'includes/config.php';
$stmt = $pdo->query("DESCRIBE matriculas");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
