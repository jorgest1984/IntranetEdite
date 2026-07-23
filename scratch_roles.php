<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/includes/config.php';
$stmt = $pdo->query("SELECT * FROM roles");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
