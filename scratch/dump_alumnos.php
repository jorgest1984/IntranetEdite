<?php
require 'includes/config.php';
$stmt = $pdo->query('DESCRIBE alumnos');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
