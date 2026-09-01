<?php
// pdf_informe_conexion_inspector.php - INFORME PDF OFICIAL DE CONEXIÓN DE INSPECTOR SEPE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_db.php';
require_once 'includes/fpdf/fpdf.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR, ROLE_LECTURA])) {
    die("No tiene permisos para generar el PDF de inspección.");
}

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if (!$grupo_id) {
    die("ID de grupo no proporcionado.");
}

// 1. Obtener datos del grupo y su curso
$stmt = $pdo->prepare("SELECT g.*, COALESCE(g.numero_grupo, CONCAT('G-', g.id)) as codigo, af.num_accion, af.titulo as af_nombre, af.id_plataforma as af_moodle_id,
                              c.nombre_largo as curso_titulo, c.id as curso_id,
                              cen.nombre as centro_nombre
                       FROM grupos g
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       JOIN cursos c ON af.curso_id = c.id
                       LEFT JOIN centros cen ON g.centro_id = cen.id
                       WHERE g.id = ?");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$grupo) {
    die("Grupo no encontrado.");
}

$moodleCourseId = (int)($grupo['id_plataforma'] ?: $grupo['af_moodle_id']);
$gestorUser = trim($grupo['usuario_gestor'] ?? '');

$moodleDb = new MoodleDB();
$stats = $moodleDb->fetchInspectorStats($moodleCourseId, $gestorUser);

function pdf_utf8($str) {
    if ($str === null || $str === '') return '';
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    }
    return @utf8_decode($str);
}

function pdf_fmt_dur($seconds) {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return "{$h} h {$m} min";
}

class InspectorPDF extends FPDF {
    function Header() {
        if (file_exists('img/logo_efp.png')) {
            $this->Image('img/logo_efp.png', 10, 8, 38);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(128, 0, 0); // Granate Inspector
        $this->Cell(0, 8, pdf_utf8('INFORME OFICIAL DE CONEXIÓN DE INSPECTOR (SEPE)'), 0, 1, 'R');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 4, pdf_utf8('Acreditación de accesos al Aula Virtual de Formación Profesional'), 0, 1, 'R');
        $this->Ln(6);
        $this->SetDrawColor(128, 0, 0);
        $this->SetLineWidth(0.8);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(6);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, pdf_utf8('Página ') . $this->PageNo() . '/{nb} - Documento de Auditoría SEPE / Grupo EFP', 0, 0, 'C');
    }
}

$pdf = new InspectorPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// DATOS DEL CURSO Y GRUPO
$pdf->SetFillColor(254, 242, 242);
$pdf->SetDrawColor(220, 38, 38);
$pdf->Rect(10, $pdf->GetY(), 190, 32, 'DF');

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(128, 0, 0);
$pdf->SetXY(14, $pdf->GetY() + 3);
$pdf->Cell(0, 6, pdf_utf8('DATOS DEL GRUPO FORMATIVO'), 0, 1);

$pdf->SetFont('Arial', '', 9.5);
$pdf->SetTextColor(40, 40, 40);
$pdf->SetX(14);
$pdf->Cell(90, 5, pdf_utf8('Acción Formativa: ') . pdf_utf8($grupo['num_accion']) . ' - ' . pdf_utf8($grupo['af_nombre']), 0, 1);
$f_ini = (!empty($grupo['fecha_inicio']) && $grupo['fecha_inicio'] !== '0000-00-00') ? date('d/m/Y', strtotime((string)$grupo['fecha_inicio'])) : 'Pendiente';
$f_fin = (!empty($grupo['fecha_fin']) && $grupo['fecha_fin'] !== '0000-00-00') ? date('d/m/Y', strtotime((string)$grupo['fecha_fin'])) : 'Pendiente';

$pdf->SetX(14);
$pdf->Cell(90, 5, pdf_utf8('Código de Grupo: ') . pdf_utf8($grupo['codigo']), 0, 0);
$pdf->Cell(90, 5, pdf_utf8('Fechas: ') . $f_ini . ' - ' . $f_fin, 0, 1);
$pdf->SetX(14);
$pdf->Cell(0, 5, pdf_utf8('Curso Moodle ID: ') . $moodleCourseId . ' | ' . pdf_utf8($grupo['curso_titulo']), 0, 1);

$pdf->Ln(8);

// RESUMEN DEL INSPECTOR
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 6, pdf_utf8('RESUMEN DE INSPECCIÓN Y CREDENCIALES'), 0, 1);
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(241, 245, 249);
$pdf->SetTextColor(50, 50, 50);

$pdf->Cell(50, 7, pdf_utf8('Usuario Inspector SEPE'), 1, 0, 'C', true);
$pdf->Cell(45, 7, pdf_utf8('Nº Sesiones'), 1, 0, 'C', true);
$pdf->Cell(45, 7, pdf_utf8('Último Acceso'), 1, 0, 'C', true);
$pdf->Cell(50, 7, pdf_utf8('Tiempo Acumulado'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9.5);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(50, 8, pdf_utf8($gestorUser ?: 'No asignado'), 1, 0, 'C');
$pdf->Cell(45, 8, $stats['session_count'], 1, 0, 'C');
$pdf->Cell(45, 8, $stats['last_access'] ? date('d/m/Y H:i', strtotime($stats['last_access'])) : 'Sin accesos', 1, 0, 'C');
$pdf->Cell(50, 8, pdf_utf8(pdf_fmt_dur($stats['total_seconds'])), 1, 1, 'C');

$pdf->Ln(8);

// DESGLOSE DE SESIONES
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 6, pdf_utf8('DESGLOSE DE SESIONES DE INSPECCIÓN REGISTRADAS'), 0, 1);
$pdf->Ln(2);

if (empty($stats['sessions'])) {
    $pdf->SetFont('Arial', 'I', 9.5);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 8, pdf_utf8('No se registran accesos del inspector en el Aula Virtual para este grupo.'), 0, 1);
} else {
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetFillColor(128, 0, 0);
    $pdf->SetTextColor(255, 255, 255);

    $pdf->Cell(40, 7, pdf_utf8('Fecha'), 1, 0, 'C', true);
    $pdf->Cell(35, 7, pdf_utf8('Hora Inicio'), 1, 0, 'C', true);
    $pdf->Cell(35, 7, pdf_utf8('Hora Fin'), 1, 0, 'C', true);
    $pdf->Cell(80, 7, pdf_utf8('Duración Estimada'), 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(30, 30, 30);
    $fill = false;

    foreach ($stats['sessions'] as $s) {
        $pdf->SetFillColor($fill ? 250 : 255, $fill ? 250 : 255, $fill ? 250 : 255);
        $pdf->Cell(40, 6.5, $s['date'], 1, 0, 'C', true);
        $pdf->Cell(35, 6.5, $s['start_time'], 1, 0, 'C', true);
        $pdf->Cell(35, 6.5, $s['end_time'], 1, 0, 'C', true);
        $pdf->Cell(80, 6.5, pdf_utf8(pdf_fmt_dur($s['duration'])), 1, 1, 'C', true);
        $fill = !$fill;
    }
}

$pdf->Ln(8);

// IPS REGISTRADAS
if (!empty($stats['ips'])) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 5, pdf_utf8('Direcciones IP utilizadas por el Inspector:'), 0, 1);
    $pdf->SetFont('Arial', '', 8.5);
    $pdf->SetTextColor(70, 70, 70);
    $pdf->Cell(0, 5, implode('   |   ', $stats['ips']), 0, 1);
}

$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(128, 128, 128);
$pdf->Cell(0, 4, pdf_utf8('Este informe ha sido generado automáticamente por la Intranet de Gestión Académica desde los logs del Aula Virtual.'), 0, 1, 'C');

$pdf->Output('I', "informe_inspector_grupo_" . $grupo_id . ".pdf");
