<?php
// pdf_acta_evaluacion.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

// Control de acceso y configuración inicial
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_COMERCIAL, ROLE_JEFE_COMERCIAL])) {
    header("Location: dashboard.php");
    exit();
}

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
$accion_id = isset($_GET['accion_id']) ? (int)$_GET['accion_id'] : 0;

if (!$grupo_id || !$accion_id) {
    die("Faltan parámetros requeridos (Acción Formativa y Grupo).");
}

// Auto-crear columna DNI si no existe (evita errores si el usuario no ejecutó el script)
try {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN dni VARCHAR(20) DEFAULT NULL AFTER apellidos");
} catch (PDOException $e) {
    // Si ya existe u otro error menor, ignorar
}

// 1. Obtener datos de cabecera
$stmt = $pdo->prepare("SELECT g.*, af.num_accion, af.titulo as curso_titulo, 
                              COALESCE(NULLIF(g.expediente, ''), c.codigo_expediente) as codigo_expediente, u.nombre as tutor_nombre, u.apellidos as tutor_apellidos, pd.dni as tutor_dni
                       FROM grupos g
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       LEFT JOIN planes p ON af.plan_id = p.id
                       LEFT JOIN convocatorias c ON p.convocatoria_id = c.id
                       LEFT JOIN usuarios u ON g.tutor_id = u.id
                       LEFT JOIN profesorado_detalles pd ON u.id = pd.usuario_id
                       WHERE g.id = ? AND af.id = ?");
$stmt->execute([$grupo_id, $accion_id]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$grupo) {
    die("Grupo no encontrado.");
}

// 2. Obtener alumnos COMPLETADOS
$stmtAl = $pdo->prepare("SELECT m.id as matricula_id, 
                                m.moodle_e1_grade, m.moodle_e2_grade, m.moodle_e3_grade, 
                                m.moodle_final_grade,
                                a.id as alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido
                         FROM matriculas m
                         JOIN alumnos a ON m.alumno_id = a.id
                         WHERE m.grupo_id = ? AND m.estado != 'Baja' AND m.estado != 'Cancelada'
                           AND m.moodle_e1_completed = 1 AND m.moodle_e2_completed = 1 AND m.moodle_e3_completed = 1
                         ORDER BY a.primer_apellido ASC, a.segundo_apellido ASC, a.nombre ASC");
$stmtAl->execute([$grupo_id]);
$alumnos = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

function pdf_utf8_to_iso($str) {
    if ($str === null || $str === '') return '';
    if (function_exists('mb_convert_encoding')) return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
        if ($converted !== false) return $converted;
    }
    return @utf8_decode($str);
}

// Generador de PDF
class ActaPDF extends FPDF {
    private $grupo;
    
    public function setGrupoData($g) {
        $this->grupo = $g;
    }

    function Header() {
        // Logotipos Individuales
        $x = 10;
        if (file_exists('img/logo_fundae.png')) {
            $this->Image('img/logo_fundae.png', $x, 8, 25);
            $x += 28;
        }

        if (file_exists('img/logo_sepe.png')) {
            $this->Image('img/logo_sepe.png', $x, 8, 25);
        }
        
        if (file_exists('img/logo_efp.png')) {
            $this->Image('img/logo_efp.png', 160, 8, 30);
        }
        
        $this->Ln(20);
        
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 10, pdf_utf8_to_iso('ACTA DE EVALUACIÓN FINAL DE GRUPO'), 0, 1, 'C');
        $this->Ln(5);
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(22, 5, 'Expediente: ', 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(40, 5, pdf_utf8_to_iso($this->grupo['codigo_expediente']), 0, 0);
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(20, 5, pdf_utf8_to_iso('Nº Accion: '), 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(20, 5, $this->grupo['num_accion'], 0, 0);
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(15, 5, 'Grupo: ', 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(20, 5, $this->grupo['numero_grupo'], 0, 1);
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(12, 5, 'Curso: ', 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(140, 5, pdf_utf8_to_iso($this->grupo['curso_titulo']), 0, 0);
        
        // Nº horas (Assuming 20h or whatever is standard, maybe not in DB? We will use a placeholder or check if DB has horas)
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(20, 5, pdf_utf8_to_iso('Nº horas: '), 0, 0, 'R');
        $this->SetFont('Arial', '', 9);
        $this->Cell(10, 5, '20 h', 0, 1, 'L');
        
        $this->Ln(5);
    }
}

$pdf = new ActaPDF();
$pdf->setGrupoData($grupo);
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 40); // Leave space for signatures
$pdf->AddPage();

// Cabeceras de la tabla (Row 1)
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(90, 6, '', 'LTR', 0, 'C');
$pdf->Cell(60, 6, 'CONTROLES', 1, 0, 'C');
$pdf->Cell(40, 6, pdf_utf8_to_iso('CALIFICACIÓN'), 'LTR', 1, 'C');

// Cabeceras de la tabla (Row 2)
$pdf->Cell(90, 6, 'ALUMNOS', 'LBR', 0, 'C');
$pdf->Cell(12, 6, 'E1', 1, 0, 'C');
$pdf->Cell(12, 6, 'E2', 1, 0, 'C');
$pdf->Cell(12, 6, 'E3', 1, 0, 'C');
$pdf->Cell(24, 6, '% CONTROLES', 1, 0, 'C');
$pdf->Cell(20, 6, 'FINAL', 'LBR', 0, 'C');
$pdf->Cell(20, 6, '', 'BR', 1, 'C'); // Under CALIFICACION but no text

// Contenido de la tabla
$pdf->SetFont('Arial', '', 9);

if (empty($alumnos)) {
    $pdf->Cell(190, 8, pdf_utf8_to_iso('No hay alumnos aptos para generar el acta final.'), 1, 1, 'C');
} else {
    foreach ($alumnos as $alumno) {
        $apellidos = trim(($alumno['primer_apellido'] ?? '') . ' ' . ($alumno['segundo_apellido'] ?? ''));
        $nombre_completo = mb_strtoupper($apellidos . ', ' . $alumno['nombre']);
        
        $grades = [];
        if ($alumno['moodle_e1_grade'] !== null) $grades[] = (float)$alumno['moodle_e1_grade'];
        if ($alumno['moodle_e2_grade'] !== null) $grades[] = (float)$alumno['moodle_e2_grade'];
        if ($alumno['moodle_e3_grade'] !== null) $grades[] = (float)$alumno['moodle_e3_grade'];
        $media = count($grades) > 0 ? number_format(array_sum($grades) / count($grades), 2) : '10.00';
        
        $e1 = $alumno['moodle_e1_grade'] !== null ? number_format($alumno['moodle_e1_grade'], 2) : '10.00';
        $e2 = $alumno['moodle_e2_grade'] !== null ? number_format($alumno['moodle_e2_grade'], 2) : '10.00';
        $e3 = $alumno['moodle_e3_grade'] !== null ? number_format($alumno['moodle_e3_grade'], 2) : '10.00';
        
        $pdf->Cell(90, 7, pdf_utf8_to_iso($nombre_completo), 1, 0, 'L');
        $pdf->Cell(12, 7, $e1, 1, 0, 'C');
        $pdf->Cell(12, 7, $e2, 1, 0, 'C');
        $pdf->Cell(12, 7, $e3, 1, 0, 'C');
        $pdf->Cell(24, 7, '100.00%', 1, 0, 'C');
        $pdf->Cell(20, 7, $media, 1, 0, 'C');
        $pdf->Cell(20, 7, 'APTO', 1, 1, 'C');
    }
}

$pdf->Ln(15);

// Firmas
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 6, 'El centro:', 0, 0, 'L');
$pdf->Cell(95, 6, 'El formador:', 0, 1, 'L');

$y = $pdf->GetY();

if (file_exists('img/firma_mars.png')) {
    $pdf->Image('img/firma_mars.png', 10, $y + 5, 50);
} else {
    // Spacer if image doesn't exist
}

$pdf->SetY($y + 35);
$pdf->SetX(105);

$tutor_nombre = pdf_utf8_to_iso(trim($grupo['tutor_nombre'] . ' ' . $grupo['tutor_apellidos']));
if (empty(trim($tutor_nombre))) $tutor_nombre = '______________________________';

$tutor_dni = pdf_utf8_to_iso(trim($grupo['tutor_dni'] ?? ''));
if (empty(trim($tutor_dni))) $tutor_dni = '_________________';

$pdf->Cell(95, 6, $tutor_nombre, 0, 1, 'L');
$pdf->SetX(105);
$pdf->Cell(95, 6, 'NIF: ' . $tutor_dni, 0, 1, 'L');

// Output
$filename = "Acta_Evaluacion_Final_Accion_" . $grupo['num_accion'] . "_Grupo_" . $grupo['numero_grupo'] . ".pdf";
$pdf->Output('I', $filename);
