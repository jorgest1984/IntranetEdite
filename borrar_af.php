<?php
// borrar_af.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN])) {
    die("No tiene permisos para eliminar acciones formativas.");
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header("Location: acciones_formativas.php?error=ID no proporcionado");
    exit;
}

try {
    // 1. Obtener datos antes de borrar para el log de auditoría
    $stmtInfo = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id = ?");
    $stmtInfo->execute([$id]);
    $af_data = $stmtInfo->fetch();
    
    if (!$af_data) {
        throw new Exception("La acción formativa no existe.");
    }
    
    // 2. Eliminar la acción formativa (las restricciones de base de datos se encargarán del cascade si existen, o fallará de forma segura)
    $stmt = $pdo->prepare("DELETE FROM acciones_formativas WHERE id = ?");
    $stmt->execute([$id]);
    
    // 3. Registrar log
    audit_log($pdo, 'DELETE_ACCION_FORMATIVA', 'acciones_formativas', $id, $af_data, null);
    
    header("Location: acciones_formativas.php?msg=" . urlencode("Acción formativa eliminada correctamente"));
} catch (Exception $e) {
    // Si falla por foreign key (por ejemplo, tiene matrículas o grupos vinculados y no está configurado en CASCADE)
    $errorMsg = $e->getMessage();
    if (strpos($errorMsg, 'a foreign key constraint fails') !== false) {
        $errorMsg = "No se puede eliminar la acción formativa porque tiene grupos o matrículas asociadas.";
    }
    header("Location: acciones_formativas.php?error=" . urlencode($errorMsg));
}
exit;
