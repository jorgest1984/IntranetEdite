<?php
require 'includes/config.php';
$stmt = $pdo->query('SELECT * FROM cursos LIMIT 1');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
