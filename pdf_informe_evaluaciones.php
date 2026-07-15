<?php
// pdf_informe_evaluaciones.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if (!$grupo_id) {
    die("ID de grupo no proporcionado.");
}

// 1. Obtener datos del grupo y su curso
$stmt = $pdo->prepare("SELECT g.*, af.num_accion, af.titulo as curso_titulo
                       FROM grupos g
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       WHERE g.id = ?");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch();

if (!$grupo) {
    die("Grupo no encontrado.");
}

// 2. Obtener alumnos con sus calificaciones y estados de Moodle
$stmtAl = $pdo->prepare("SELECT m.id as matricula_id, m.estado as matricula_estado, 
                                m.moodle_e1_grade, m.moodle_e2_grade, m.moodle_e3_grade, 
                                m.moodle_e1_completed, m.moodle_e2_completed, m.moodle_e3_completed,
                                m.moodle_final_grade, m.moodle_aptitud,
                                a.id as alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.moodle_user_id
                         FROM matriculas m
                         JOIN alumnos a ON m.alumno_id = a.id
                         WHERE m.grupo_id = ?
                         ORDER BY a.primer_apellido ASC, a.nombre ASC");
$stmtAl->execute([$grupo_id]);
$alumnos = $stmtAl->fetchAll();

// Función compatible con PHP 8.2+ para evitar deprecación de utf8_decode
function pdf_utf8_to_iso($str) {
    if ($str === null || $str === '') {
        return '';
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
        if ($converted !== false) {
            return $converted;
        }
    }
    return @utf8_decode($str);
}

// Generador de PDF
class EvaluationsReportPDF extends FPDF {
    private $grupo;
    
    public function setGrupoData($g) {
        $this->grupo = $g;
    }

    function Header() {
        if (file_exists('img/logo_efp.png')) {
            $this->Image('img/logo_efp.png', 10, 8, 25);
        } else {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(40, 10, 'GRUPO EFP', 0, 0);
        }
        
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(70, 80, 95);
        $this->Cell(0, 8, pdf_utf8_to_iso('INFORME DE CALIFICACIONES Y EVALUACIONES'), 0, 1, 'R');
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 4, pdf_utf8_to_iso('Curso: ' . $this->grupo['curso_titulo']), 0, 1, 'R');
        $this->Cell(0, 4, pdf_utf8_to_iso('Grupo: G' . $this->grupo['numero_grupo'] . ' | N. Acción: ' . $this->grupo['num_accion']), 0, 1, 'R');
        
        $this->Ln(4);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 110, 120);
        $this->Cell(0, 10, pdf_utf8_to_iso('Página ') . $this->PageNo() . '/{nb} - Generado el ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
}

$pdf = new EvaluationsReportPDF();
$pdf->setGrupoData($grupo);
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Cabeceras de la tabla
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(240, 246, 255);
$pdf->SetTextColor(0, 108, 228);

$pdf->Cell(60, 8, pdf_utf8_to_iso('Alumno'), 1, 0, 'L', true);
$pdf->Cell(24, 8, pdf_utf8_to_iso('Ev. Inicial'), 1, 0, 'C', true);
$pdf->Cell(24, 8, pdf_utf8_to_iso('Ev. Intermed.'), 1, 0, 'C', true);
$pdf->Cell(24, 8, pdf_utf8_to_iso('Ev. Final'), 1, 0, 'C', true);
$pdf->Cell(20, 8, pdf_utf8_to_iso('Comp. Tod.'), 1, 0, 'C', true);
$pdf->Cell(20, 8, pdf_utf8_to_iso('Media'), 1, 0, 'C', true);
$pdf->Cell(18, 8, pdf_utf8_to_iso('Aptitud'), 1, 1, 'C', true);

// Contenido de la tabla
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(50, 50, 50);

if (empty($alumnos)) {
    $pdf->Cell(190, 8, pdf_utf8_to_iso('No hay alumnos matriculados en este grupo.'), 1, 1, 'C');
} else {
    foreach ($alumnos as $alumno) {
        // Control de salto de página por fila
        if ($pdf->GetY() > 265) {
            $pdf->AddPage();
            
            // Redibujar cabeceras tras salto
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(240, 246, 255);
            $pdf->SetTextColor(0, 108, 228);
            $pdf->Cell(60, 8, pdf_utf8_to_iso('Alumno'), 1, 0, 'L', true);
            $pdf->Cell(24, 8, pdf_utf8_to_iso('Ev. Inicial'), 1, 0, 'C', true);
            $pdf->Cell(24, 8, pdf_utf8_to_iso('Ev. Intermed.'), 1, 0, 'C', true);
            $pdf->Cell(24, 8, pdf_utf8_to_iso('Ev. Final'), 1, 0, 'C', true);
            $pdf->Cell(20, 8, pdf_utf8_to_iso('Comp. Tod.'), 1, 0, 'C', true);
            $pdf->Cell(20, 8, pdf_utf8_to_iso('Media'), 1, 0, 'C', true);
            $pdf->Cell(18, 8, pdf_utf8_to_iso('Aptitud'), 1, 1, 'C', true);
            
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(50, 50, 50);
        }
        
        $apellidos = trim(($alumno['primer_apellido'] ?? '') . ' ' . ($alumno['segundo_apellido'] ?? ''));
        $nombre_completo = mb_strtoupper($apellidos . ', ' . $alumno['nombre']);
        
        $completed_all = ($alumno['moodle_e1_completed'] == 1 && $alumno['moodle_e2_completed'] == 1 && $alumno['moodle_e3_completed'] == 1) ? 'SI' : 'NO';
        
        // Calcular media
        $grades = [];
        if ($alumno['moodle_e1_grade'] !== null) $grades[] = (float)$alumno['moodle_e1_grade'];
        if ($alumno['moodle_e2_grade'] !== null) $grades[] = (float)$alumno['moodle_e2_grade'];
        if ($alumno['moodle_e3_grade'] !== null) $grades[] = (float)$alumno['moodle_e3_grade'];
        $media = count($grades) > 0 ? number_format(array_sum($grades) / count($grades), 2) : '---';
        
        $initial = $alumno['moodle_e1_grade'] !== null ? number_format($alumno['moodle_e1_grade'], 2) : '---';
        $intermediate = $alumno['moodle_e2_grade'] !== null ? number_format($alumno['moodle_e2_grade'], 2) : '---';
        $final = $alumno['moodle_e3_grade'] !== null ? number_format($alumno['moodle_e3_grade'], 2) : '---';
        $aptitud = mb_strtoupper(trim($alumno['moodle_aptitud'] ?: 'PENDIENTE'));
        
        $pdf->Cell(60, 7, pdf_utf8_to_iso($nombre_completo), 1, 0, 'L');
        $pdf->Cell(24, 7, $initial, 1, 0, 'C');
        $pdf->Cell(24, 7, $intermediate, 1, 0, 'C');
        $pdf->Cell(24, 7, $final, 1, 0, 'C');
        $pdf->Cell(20, 7, $completed_all, 1, 0, 'C');
        $pdf->Cell(20, 7, $media, 1, 0, 'C');
        $pdf->Cell(18, 7, pdf_utf8_to_iso($aptitud), 1, 1, 'C');
    }
}

// Limpiar buffer de salida para evitar "Some data has already been output"
if (ob_get_length()) {
    ob_clean();
}

$pdf->Output('I', "evaluaciones_grupo_" . $grupo_id . ".pdf");
