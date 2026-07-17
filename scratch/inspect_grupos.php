<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once 'includes/config.php';
$stmt = $pdo->query("DESCRIBE grupos");
print_r($stmt->fetchAll());
