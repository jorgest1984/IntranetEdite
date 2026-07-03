<?php
require_once 'includes/config.php';

$stmt = $pdo->query("DESCRIBE matriculas");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode(['matriculas' => $cols]);
