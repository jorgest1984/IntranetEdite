<?php
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_COMERCIAL])) {
    echo json_encode(['success' => false, 'error' => 'No tienes permisos']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$csrf_token = $_POST['csrf_token'] ?? '';

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit();
}

if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
    exit();
}

try {
    // Verificar si hay matriculas asociadas
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) as num_matriculas FROM matriculas WHERE id_grupo = ?");
    $stmtCheck->execute([$id]);
    $row = $stmtCheck->fetch();
    if ($row && $row['num_matriculas'] > 0) {
        // En lugar de fallar, vamos a informar o borrar en cascada? 
        // Mejor fallar e informar para evitar borrados accidentales de alumnos.
        echo json_encode([
            'success' => false, 
            'error' => 'No se puede borrar el grupo porque tiene ' . $row['num_matriculas'] . ' alumno(s) matriculado(s). Debes borrarlos o moverlos primero.'
        ]);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM grupos WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Grupo borrado correctamente']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
