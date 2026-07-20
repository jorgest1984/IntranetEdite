<?php
// api_moodle_pdf.php
// Este archivo recibe peticiones firmadas desde Moodle para descargar PDFs
require_once 'includes/config.php';

global $moodle_bypass_auth;
$moodle_bypass_auth = true;

$secret = "EfpMoodleSecret2026!#";

$token = $_GET['token'] ?? '';
$moodle_course_id = (int)($_GET['courseid'] ?? 0);
$moodle_user_id = (int)($_GET['userid'] ?? 0);
$tipo = $_GET['tipo'] ?? '';
$ts = (int)($_GET['ts'] ?? 0);

if (!$token || !$moodle_course_id || !$moodle_user_id || !$tipo || !$ts) {
    die("Faltan parámetros.");
}

// Validar timestamp (validez de 5 minutos)
if (abs(time() - $ts) > 300) {
    die("El enlace ha caducado. Vuelve a intentarlo desde Moodle.");
}

// Validar token
$expected_token = hash_hmac('sha256', $moodle_course_id . '|' . $moodle_user_id . '|' . $tipo . '|' . $ts, $secret);
if (!hash_equals($expected_token, $token)) {
    die("Acceso denegado. Token inválido o firma incorrecta.");
}

// Mapear moodle_user_id -> Intranet alumno_id
$stmtUser = $pdo->prepare("SELECT id FROM alumnos WHERE moodle_user_id = ?");
$stmtUser->execute([$moodle_user_id]);
$alumno = $stmtUser->fetch();

if (!$alumno) {
    die("Alumno no encontrado en la Intranet. Asegúrate de que el alumno está sincronizado.");
}
$alumno_id = $alumno['id'];

// Mapear Moodle Course -> Intranet Accion Formativa
$stmtCourse = $pdo->prepare("SELECT id FROM acciones_formativas WHERE id_plataforma = ?");
$stmtCourse->execute([$moodle_course_id]);
$accion = $stmtCourse->fetch();

if (!$accion) {
    die("Acción formativa no encontrada en la Intranet para este curso de Moodle.");
}
$accion_id = $accion['id'];

if ($tipo === 'recibi') {
    // Generar PDF usando FPDF directamente
    $_GET['accion_id'] = $accion_id;
    $_GET['alumno_id'] = $alumno_id;
    require 'pdf_recibi_material.php';
    exit;

} elseif ($tipo === 'bienvenida') {
    // Generar PDF de bienvenida usando FPDF
    $_GET['accion_id'] = $accion_id;
    $_GET['alumno_id'] = $alumno_id;
    require 'pdf_hoja_bienvenida.php';
    exit;
} else {
    die("Tipo de documento no válido.");
}
