<?php
require 'includes/config.php';
$stmt = $pdo->query("SELECT id, nombre, apellidos, rol_id FROM usuarios WHERE nombre LIKE '%Eva%' OR apellidos LIKE '%Alvarez%' OR apellidos LIKE '%Álvarez%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
