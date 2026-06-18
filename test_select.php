<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 12;

try {
    $stmtSeguimiento = $pdo->prepare("SELECT a.id as alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.email, a.moodle_user_id, 
                                        m.moodle_first_access, m.moodle_last_access, m.moodle_connected_time, m.moodle_progress, m.moodle_last_sync,
                                        g.numero_grupo, m.estado as matricula_estado
                                      FROM matriculas m
                                      JOIN alumnos a ON m.alumno_id = a.id
                                      JOIN grupos g ON m.grupo_id = g.id
                                      WHERE g.accion_id = ?");
    $stmtSeguimiento->execute([$id]);
    $res = $stmtSeguimiento->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $res]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
