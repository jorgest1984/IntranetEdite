<?php
require 'includes/config.php';
$stmt = $pdo->query("SELECT id, nombre, apellidos, rol_id FROM usuarios WHERE nombre LIKE '%Eva%' OR apellidos LIKE '%Alvarez%' OR apellidos LIKE '%Álvarez%'");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('scratch/eva_users.json', json_encode($data, JSON_PRETTY_PRINT));
