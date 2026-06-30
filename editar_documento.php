<?php
// editar_documento.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die("Error: Token CSRF no válido o expirado.");
}

$doc_id    = isset($_POST['doc_id'])       ? (int)$_POST['doc_id']  : 0;
$alumno_id = isset($_POST['alumno_id'])    ? (int)$_POST['alumno_id'] : 0;
$tipo_doc  = $_POST['tipo_documento']      ?? 'General';
$accion_id = isset($_POST['accion_id']) && $_POST['accion_id'] !== '' ? (int)$_POST['accion_id'] : null;
if ($accion_id <= 0) {
    $accion_id = null;
}

if ($doc_id <= 0 || $alumno_id <= 0) {
    die("Error: parámetros no válidos.");
}

// Verificar que el documento pertenece al alumno
$stmtCheck = $pdo->prepare("SELECT id FROM documentos_alumno WHERE id = ? AND alumno_id = ?");
$stmtCheck->execute([$doc_id, $alumno_id]);
if (!$stmtCheck->fetch()) {
    die("Error: Documento no encontrado o no pertenece a este alumno.");
}

// Comprobar si ya existe OTRO documento del mismo tipo+destino para evitar colisión
if ($accion_id) {
    $stmtDup = $pdo->prepare("SELECT id FROM documentos_alumno WHERE alumno_id = ? AND tipo_documento = ? AND accion_id = ? AND id != ?");
    $stmtDup->execute([$alumno_id, $tipo_doc, $accion_id, $doc_id]);
} else {
    $stmtDup = $pdo->prepare("SELECT id FROM documentos_alumno WHERE alumno_id = ? AND tipo_documento = ? AND accion_id IS NULL AND id != ?");
    $stmtDup->execute([$alumno_id, $tipo_doc, $doc_id]);
}
if ($stmtDup->fetch()) {
    // Hay un duplicado: avisar al usuario
    header("Location: ficha_alumno.php?id=$alumno_id&tab=documentacion&edit_error=dup");
    exit();
}

// Actualizar
$stmtUpdate = $pdo->prepare("UPDATE documentos_alumno SET tipo_documento = ?, accion_id = ? WHERE id = ? AND alumno_id = ?");
$stmtUpdate->execute([$tipo_doc, $accion_id, $doc_id, $alumno_id]);

audit_log($pdo, 'EDICION_DOC', 'documentos_alumno', $doc_id, null, ['tipo' => $tipo_doc]);

header("Location: ficha_alumno.php?id=$alumno_id&tab=documentacion&edit_success=1");
exit();
