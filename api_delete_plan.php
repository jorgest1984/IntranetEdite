<?php
// api_delete_plan.php
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    echo json_encode(['success' => false, 'error' => 'No tienes permisos']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$plan_id = isset($data['id']) ? (int)$data['id'] : 0;

if (!$plan_id) {
    echo json_encode(['success' => false, 'error' => 'ID de plan no proporcionado']);
    exit();
}

try {
    // 1. Verificar si el plan existe
    $stmt = $pdo->prepare("SELECT id, nombre, convocatoria_id FROM planes WHERE id = ?");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();

    if (!$plan) {
        echo json_encode(['success' => false, 'error' => 'El plan no existe']);
        exit();
    }

    // 2. Comprobar si tiene acciones formativas
    $stmtAcciones = $pdo->prepare("SELECT COUNT(*) as cnt FROM acciones_formativas WHERE plan_id = ?");
    $stmtAcciones->execute([$plan_id]);
    $accionesCount = $stmtAcciones->fetchColumn();

    if ($accionesCount > 0) {
        echo json_encode([
            'success' => false, 
            'error' => "No se puede borrar el plan porque tiene $accionesCount acciones formativas asociadas. Debes eliminarlas o reasignarlas primero."
        ]);
        exit();
    }

    // 3. Borrar el plan
    $stmtDel = $pdo->prepare("DELETE FROM planes WHERE id = ?");
    $stmtDel->execute([$plan_id]);

    // Registrar en auditoría
    audit_log($pdo, 'DELETE_PLAN', 'planes', $plan_id, $plan['nombre'], null);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Error al borrar plan: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'No se ha podido eliminar porque tiene datos vinculados.']);
}
