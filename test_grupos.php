<?php
require_once 'includes/config.php';
header('Content-Type: application/json');

try {
    $stmtGrupos = $pdo->prepare("SELECT g.*, e.nombre as centro_nombre, CONCAT(a.nombre, ' ', a.apellidos) as tutor_nombre 
                                    FROM grupos g 
                                    LEFT JOIN empresas e ON g.centro_id = e.id 
                                    LEFT JOIN alumnos a ON g.tutor_id = a.id 
                                    WHERE g.accion_id = ? 
                                    ORDER BY g.creado_en DESC");
    $stmtGrupos->execute([12]);
    $grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'grupos' => $grupos]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
