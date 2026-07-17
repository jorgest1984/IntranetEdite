<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require 'includes/config.php';
$stmt = $pdo->query('SELECT * FROM matriculas LIMIT 1');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query('SELECT * FROM acciones_formativas LIMIT 1');
print_r($stmt2->fetch(PDO::FETCH_ASSOC));
