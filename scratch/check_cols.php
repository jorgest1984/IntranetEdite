<?php
require 'includes/config.php';
$stmt = $pdo->query("SHOW COLUMNS FROM tutorias_seguimiento");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
