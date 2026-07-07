<?php
$pdo = new PDO("mysql:host=localhost;dbname=intranet_formacion;charset=utf8mb4", "gestion.efp2026", "Oy0v?ggswFBr6d0~");
$stmt = $pdo->query("SELECT m.id, a.nombre, a.email FROM matriculas m JOIN alumnos a ON m.alumno_id = a.id ORDER BY m.id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
