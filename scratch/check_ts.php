<?php
require 'includes/config.php';
$stmt = $pdo->query("SHOW CREATE TABLE tutorias_seguimiento");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
