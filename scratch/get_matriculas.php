<?php
require_once dirname(__DIR__) . '/includes/config.php';
$stmt = $pdo->query("DESCRIBE matriculas");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
