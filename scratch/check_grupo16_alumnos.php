<?php
// scratch/check_grupo16_alumnos.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

echo "=== ALUMNOS EN INTRANET GRUPO 16 ===\n\n";

$stmt = $pdo->prepare("
    SELECT m.id as mat_id, m.estado as mat_estado, a.id as alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.email, a.moodle_user_id
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
    WHERE m.grupo_id = 16
    ORDER BY m.id ASC
");
$stmt->execute();
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total alumnos en Grupo 16: " . count($alumnos) . "\n\n";
foreach ($alumnos as $al) {
    echo "Matrícula ID: {$al['mat_id']} | Alumno ID: {$al['alumno_id']} | {$al['nombre']} {$al['primer_apellido']} {$al['segundo_apellido']} | DNI: {$al['dni']} | Moodle User ID: " . ($al['moodle_user_id'] ?? 'NULL') . "\n";
}
