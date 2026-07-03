<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require 'includes/config.php';
$stmt = $pdo->query('DESCRIBE cursos');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
