<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require 'includes/config.php';
$stmt = $pdo->query('SHOW COLUMNS FROM cursos');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
