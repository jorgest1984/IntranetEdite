<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require 'includes/config.php';
$stmt = $pdo->query('SELECT * FROM acciones_formativas LIMIT 1');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
