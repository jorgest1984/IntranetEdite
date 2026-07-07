<?php
require_once dirname(__DIR__) . '/includes/config.php';
$stmt = $pdo->query("SELECT m.id, a.nombre, a.email FROM matriculas m JOIN alumnos a ON m.alumno_id = a.id ORDER BY m.id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
