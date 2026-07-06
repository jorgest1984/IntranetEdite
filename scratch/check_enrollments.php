<?php
// Script designed to run on the preproduction server to list active student enrollments
$_SERVER['HTTP_HOST'] = 'pre-gestion.grupoefp.es';
require_once __DIR__ . '/../includes/config.php';

try {
    $stmt = $pdo->query("
        SELECT m.alumno_id, m.grupo_id, g.accion_id, a.nombre, a.primer_apellido, af.titulo
        FROM matriculas m
        JOIN alumnos a ON m.alumno_id = a.id
        JOIN grupos g ON m.grupo_id = g.id
        JOIN acciones_formativas af ON g.accion_id = af.id
        LIMIT 10
    ");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "data" => $res]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
