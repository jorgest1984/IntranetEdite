<?php
require 'includes/config.php';
$stmt = $pdo->query('SHOW COLUMNS FROM acciones_formativas');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query('SHOW COLUMNS FROM cursos');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
