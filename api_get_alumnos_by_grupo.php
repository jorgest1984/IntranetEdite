<?php
// api_get_alumnos_by_grupo.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_FORMADOR])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;

if (!$grupo_id) {
    echo json_encode(['success' => false, 'message' => 'Falta el grupo_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.nombre, a.primer_apellido as apellidos, a.segundo_apellido, a.dni
        FROM matriculas m
        JOIN alumnos a ON m.alumno_id = a.id
        WHERE m.grupo_id = ? AND m.estado != 'Baja' AND m.estado != 'Cancelada'
        ORDER BY a.primer_apellido ASC, a.segundo_apellido ASC, a.nombre ASC
    ");
    $stmt->execute([$grupo_id]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Join apellidos
    foreach ($alumnos as &$al) {
        $apellidos = trim($al['apellidos'] . ' ' . $al['segundo_apellido']);
        $al['apellidos'] = $apellidos;
    }

    echo json_encode(['success' => true, 'alumnos' => $alumnos]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
}
