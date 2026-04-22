<?php
require 'includes/config.php';
$stmt = $pdo->query('DESCRIBE prof_tutorias');
print_r($stmt->fetchAll());
