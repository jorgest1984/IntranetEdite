<?php
// api_unlink_moodle_course.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Verificar permisos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes para realizar esta operación.']);
    exit();
}

// 2. Verificar CSRF token
$csrf_token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Petición no autorizada: Token CSRF no válido o expirado.']);
    exit();
}

$af_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$af_id) {
    echo json_encode(['success' => false, 'error' => 'Identificador de acción formativa inválido.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Actualizar la acción formativa para quitar la vinculación con Moodle
    $stmtUpdateAF = $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = NULL, curso_id = 0 WHERE id = ?");
    $stmtUpdateAF->execute([$af_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Acción formativa desvinculada de Moodle con éxito."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => 'Error al desvincular la acción formativa de Moodle: ' . $e->getMessage()
    ]);
}
?>
