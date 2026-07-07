<?php
require_once dirname(__DIR__) . '/includes/config.php';
$stmt = $pdo->query("SELECT * FROM configuracion");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
