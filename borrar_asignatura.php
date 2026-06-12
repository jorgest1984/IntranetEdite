<?php
// borrar_asignatura.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN])) {
    die("No tiene permisos para eliminar acciones formativas.");
}

if (empty($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'])) {
    die("Error: Token CSRF no válido o expirado.");
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: asignaturas.php?error=ID no proporcionado");
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM acciones_formativas WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: asignaturas.php?msg=Acción eliminada correctamente");
} catch (Exception $e) {
    header("Location: asignaturas.php?error=" . urlencode($e->getMessage()));
}
exit;
