<?php
$_SERVER['SERVER_NAME']='localhost';
require 'includes/config.php';
$stmt = $pdo->query('SHOW COLUMNS FROM empresas');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
