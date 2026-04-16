<?php
// api/get_planes.php
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

$convocatoria_id = intval($_GET['convocatoria_id'] ?? 0);

if ($convocatoria_id <= 0) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, nombre, codigo FROM planes WHERE convocatoria_id = ? AND activo = 1 ORDER BY nombre ASC");
    $stmt->execute([$convocatoria_id]);
    $planes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($planes);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al cargar planes: ' . $e->getMessage()]);
}
