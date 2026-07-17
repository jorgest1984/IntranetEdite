<?php
// pdf_informe_alumno.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_FORMADOR])) {
    die("Acceso denegado.");
}

$accion_id = isset($_GET['accion_id']) ? (int)$_GET['accion_id'] : 0;
$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
$alumno_id = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : 0;

if (!$accion_id || !$grupo_id || !$alumno_id) {
    die("Faltan parámetros requeridos.");
}

function pdf_utf8_to_iso($str) {
    if ($str === null || $str === '') return '';
    return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
}

// Obtener datos
$stmt = $pdo->prepare("SELECT a.nombre, a.primer_apellido, a.segundo_apellido,
                              af.num_accion, af.titulo as curso_titulo, af.horas_teoricas, af.horas_practicas,
                              g.numero_grupo, c.codigo_expediente,
                              m.moodle_progress, m.moodle_e1_grade, m.moodle_e2_grade, m.moodle_e3_grade, m.moodle_final_grade,
                              m.moodle_e1_completed, m.moodle_e2_completed, m.moodle_e3_completed
                       FROM matriculas m
                       JOIN alumnos a ON m.alumno_id = a.id
                       JOIN grupos g ON m.grupo_id = g.id
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       LEFT JOIN planes p ON af.plan_id = p.id
                       LEFT JOIN convocatorias c ON p.convocatoria_id = c.id
                       WHERE m.grupo_id = ? AND m.alumno_id = ? AND af.id = ?");
$stmt->execute([$grupo_id, $alumno_id, $accion_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("No se encontraron datos para este alumno en este grupo.");
}

$nombre_completo = mb_strtoupper($data['nombre'] . ' ' . $data['primer_apellido'] . ' ' . $data['segundo_apellido']);
$expediente = strtoupper($data['codigo_expediente'] ?? '');
$num_accion = strtoupper($data['num_accion'] ?? '');
$numero_grupo = strtoupper($data['numero_grupo'] ?? '');
$curso = $num_accion . ' - ' . mb_strtoupper($data['curso_titulo']);
$horas = (int)($data['horas_teoricas'] ?? 0) + (int)($data['horas_practicas'] ?? 0);

$moodle_progress = $data['moodle_progress'] !== null ? number_format((float)$data['moodle_progress'], 2) : '0.00';

$e1 = $data['moodle_e1_grade'] !== null ? number_format((float)$data['moodle_e1_grade'], 2) : '____';
$e2 = $data['moodle_e2_grade'] !== null ? number_format((float)$data['moodle_e2_grade'], 2) : '____';
$e3 = $data['moodle_e3_grade'] !== null ? number_format((float)$data['moodle_e3_grade'], 2) : '____';
$final = $data['moodle_final_grade'] !== null ? number_format((float)$data['moodle_final_grade'], 2) : '0.00';

$apto = ((float)$data['moodle_final_grade'] >= 5) ? 'APTO' : 'NO APTO';

$controles_hechos = 0;
if ($data['moodle_e1_completed'] || $data['moodle_e1_grade'] !== null) $controles_hechos++;
if ($data['moodle_e2_completed'] || $data['moodle_e2_grade'] !== null) $controles_hechos++;
if ($data['moodle_e3_completed'] || $data['moodle_e3_grade'] !== null) $controles_hechos++;

$porcentaje_controles = number_format(($controles_hechos / 3) * 100, 2);

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();

// INFORME ALUMNO
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'INFORME ALUMNO', 0, 1, 'C');
$pdf->Ln(15);

// Cabecera de datos
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(15, 6, 'Alumno:', 0, 0);
$pdf->Cell(95, 6, pdf_utf8_to_iso($nombre_completo), 'B', 0);

$pdf->Cell(25, 6, 'Expediente:', 0, 0, 'R');
$pdf->Cell(30, 6, pdf_utf8_to_iso($expediente), 'B', 0);

$pdf->Cell(18, 6, pdf_utf8_to_iso('Nº Accion:'), 0, 0, 'R');
$pdf->Cell(10, 6, pdf_utf8_to_iso($num_accion), 'B', 0);

$pdf->Cell(12, 6, 'Grupo:', 0, 0, 'R');
$pdf->Cell(15, 6, pdf_utf8_to_iso($numero_grupo), 'B', 1);

$pdf->Ln(2);

$pdf->Cell(15, 6, 'Curso:', 0, 0);
$pdf->Cell(150, 6, pdf_utf8_to_iso($curso), 'B', 0);

$pdf->Cell(20, 6, pdf_utf8_to_iso('Nº horas:'), 0, 0, 'R');
$pdf->Cell(15, 6, $horas . ' h', 'B', 1, 'C');

$pdf->Ln(15);

// VISUALIZACIÓN CONTENIDOS
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, pdf_utf8_to_iso('VISUALIZACIÓN CONTENIDOS'), 0, 1);
$pdf->Ln(10);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(150, 6, pdf_utf8_to_iso('Porcentaje de conexión:'), 0, 0, 'R');
$pdf->Cell(20, 6, $moodle_progress . '%', 0, 1, 'R');

$pdf->Ln(15);

// CONTROLES EVALUACIÓN
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, pdf_utf8_to_iso('CONTROLES EVALUACIÓN'), 0, 1);
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(40, 6, '', 0, 0);
$pdf->Cell(20, 6, 'NOTA', 0, 1, 'C');

$pdf->Cell(40, 6, pdf_utf8_to_iso('Evaluación previa:'), 0, 0);
$pdf->Cell(20, 6, $e1, 'B', 1, 'C');
$pdf->Ln(2);

$pdf->Cell(40, 6, pdf_utf8_to_iso('Evaluación 1:'), 0, 0);
$pdf->Cell(20, 6, $e2, 'B', 1, 'C');
$pdf->Ln(2);

$pdf->Cell(40, 6, pdf_utf8_to_iso('Evaluación 2:'), 0, 0);
$pdf->Cell(20, 6, $e3, 'B', 1, 'C');
$pdf->Ln(2);

$pdf->Cell(40, 6, pdf_utf8_to_iso('Calificación:'), 0, 0, 'R');
$pdf->Cell(20, 6, $final, 'B', 0, 'C');

$pdf->Cell(20, 6, '', 0, 0);
$pdf->Cell(30, 6, $apto, 0, 0, 'C');

$pdf->Cell(10, 6, '', 0, 0);
$pdf->Cell(70, 6, pdf_utf8_to_iso('Ha realizado el ' . $porcentaje_controles . '% de los controles.'), 0, 1, 'L');

// Salida
$pdf->Output('I', 'INFORME_ALUMNO_' . $data['codigo_expediente'] . '_' . $alumno_id . '.pdf');
