<?php
// api/eliminar_documento_profesor.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

// Solo admin puede borrar documentos
if (!has_permission([ROLE_ADMIN])) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;
$usuario_id = $_GET['usuario_id'] ?? null;

if (!$id || !$usuario_id) {
    die("Faltan parámetros.");
}

try {
    // Eliminamos el registro de la base de datos
    $stmt = $pdo->prepare("DELETE FROM profesorado_documentos WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: ../ficha_trabajador.php?id=$usuario_id&tab=profesorado&success=deleted");
    exit();
} catch (Exception $e) {
    die("Error al eliminar el documento: " . $e->getMessage());
}
