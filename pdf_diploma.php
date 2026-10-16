<?php
// pdf_diploma.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/fpdf/fpdf.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_ADMINISTRATIVO, ROLE_TUTOR, ROLE_FORMADOR, ROLE_COMERCIAL, ROLE_JEFE_COMERCIAL, ROLE_PRACTICAS])) {
    header("Location: dashboard.php");
    exit();
}

// Auto-crear columna contenidos_diploma si no existe (evita errores)
try {
    $pdo->exec("ALTER TABLE convocatorias ADD COLUMN contenidos_diploma TEXT DEFAULT NULL");
} catch (PDOException $e) {}

$alumno_id = intval($_GET['alumno_id'] ?? 0);
$grupo_id = intval($_GET['grupo_id'] ?? 0);
$accion_id = intval($_GET['accion_id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'certificado'; // 'certificado' o 'diploma'

if ((!$alumno_id || !$grupo_id || !$accion_id) && (!empty($_GET['matricula_id']) || !empty($_GET['id']))) {
    $mId = intval($_GET['matricula_id'] ?? $_GET['id']);
    $stmtMat = $pdo->prepare("SELECT m.alumno_id, m.grupo_id, g.accion_id FROM matriculas m LEFT JOIN grupos g ON m.grupo_id = g.id WHERE m.id = ?");
    $stmtMat->execute([$mId]);
    if ($matRow = $stmtMat->fetch(PDO::FETCH_ASSOC)) {
        $alumno_id = $alumno_id ?: (int)$matRow['alumno_id'];
        $grupo_id = $grupo_id ?: (int)$matRow['grupo_id'];
        $accion_id = $accion_id ?: (int)$matRow['accion_id'];
    }
}

if (!$alumno_id || !$grupo_id || !$accion_id) {
    die("Faltan parámetros requeridos.");
}

require_once 'includes/PdfGenerator.php';

try {
    PdfGenerator::generateDiplomaPdf($pdo, $alumno_id, $grupo_id, $accion_id, $tipo, 'I');
} catch (Exception $e) {
    die($e->getMessage());
}
