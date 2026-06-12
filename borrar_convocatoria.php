<?php
// borrar_convocatoria.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/Papelera.php';

if (!has_permission([ROLE_ADMIN])) {
    die("No tiene permisos para eliminar convocatorias.");
}

if (empty($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'])) {
    die("Error: Token CSRF no válido o expirado.");
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header("Location: convocatorias.php?error=ID no proporcionado");
    exit;
}

try {
    // 1. Obtener la convocatoria a eliminar
    $stmtConv = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
    $stmtConv->execute([$id]);
    $convocatoria_raw = $stmtConv->fetch(PDO::FETCH_ASSOC);

    if (!$convocatoria_raw) {
        throw new Exception("La convocatoria no existe.");
    }

    // 2. Obtener los planes asociados
    $stmtPlanes = $pdo->prepare("SELECT * FROM planes WHERE convocatoria_id = ?");
    $stmtPlanes->execute([$id]);
    $planes_rows = $stmtPlanes->fetchAll(PDO::FETCH_ASSOC);

    // 3. Archivar en la papelera
    $datos = [
        'convocatorias' => $convocatoria_raw,
        'planes' => $planes_rows
    ];
    Papelera::archivar($pdo, 'convocatorias', $id, $convocatoria_raw['nombre'], $datos);

    // 4. Iniciar transacción y eliminar
    $pdo->beginTransaction();

    // Eliminar planes
    $stmtDelPlanes = $pdo->prepare("DELETE FROM planes WHERE convocatoria_id = ?");
    $stmtDelPlanes->execute([$id]);

    // Eliminar convocatoria
    $stmtDelConv = $pdo->prepare("DELETE FROM convocatorias WHERE id = ?");
    $stmtDelConv->execute([$id]);

    $pdo->commit();

    // 5. Registrar log de auditoría
    audit_log($pdo, 'DELETE_CONVOCATORIA', 'convocatorias', $id, $convocatoria_raw, null);

    $msg = "Convocatoria enviada a la papelera correctamente junto con sus planes asociados.";
    header("Location: convocatorias.php?msg=" . urlencode($msg));

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: convocatorias.php?error=" . urlencode($e->getMessage()));
}
exit;
