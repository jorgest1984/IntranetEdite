<?php
// pdf_diploma.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_ADMINISTRATIVO])) {
    die("Acceso denegado.");
}

// Auto-crear columna contenidos_diploma si no existe (evita errores)
try {
    $pdo->exec("ALTER TABLE convocatorias ADD COLUMN contenidos_diploma TEXT DEFAULT NULL");
} catch (PDOException $e) {}

$alumno_id = intval($_GET['alumno_id'] ?? 0);
$grupo_id = intval($_GET['grupo_id'] ?? 0);
$accion_id = intval($_GET['accion_id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'certificado'; // 'certificado' o 'diploma'

if (!$alumno_id || !$grupo_id || !$accion_id) {
    die("Faltan parámetros requeridos.");
}

require_once 'includes/PdfGenerator.php';

try {
    PdfGenerator::generateDiplomaPdf($pdo, $alumno_id, $grupo_id, $accion_id, $tipo, 'I');
} catch (Exception $e) {
    die($e->getMessage());
}
