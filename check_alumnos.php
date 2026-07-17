<?php
require 'includes/config.php';
$stmt = $pdo->query('SHOW COLUMNS FROM alumnos');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
