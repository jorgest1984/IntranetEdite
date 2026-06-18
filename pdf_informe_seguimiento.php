<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

// Solo personal autorizado
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    die("Acceso denegado.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("ID de acción formativa no proporcionado.");
}

// Fetch Action
$stmt = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id = ?");
$stmt->execute([$id]);
$accion = $stmt->fetch();

if (!$accion) {
    die("Acción formativa no encontrada.");
}

// Fetch Students
$stmtSeguimiento = $pdo->prepare("SELECT a.id as alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.email, a.moodle_user_id, 
                                    m.moodle_first_access, m.moodle_last_access, m.moodle_connected_time, m.moodle_progress, m.moodle_last_sync,
                                    m.moodle_m1_completed, m.moodle_m2_completed, m.moodle_m3_completed,
                                    m.moodle_e1_completed, m.moodle_e2_completed, m.moodle_e3_completed,
                                    m.moodle_e1_grade, m.moodle_e2_grade, m.moodle_e3_grade,
                                    m.moodle_final_grade, m.moodle_aptitud,
                                    g.numero_grupo, m.estado as matricula_estado
                                  FROM matriculas m
                                  JOIN alumnos a ON m.alumno_id = a.id
                                  JOIN grupos g ON m.grupo_id = g.id
                                  WHERE g.accion_id = ?
                                  ORDER BY a.primer_apellido ASC, a.nombre ASC");
$stmtSeguimiento->execute([$id]);
$alumnos = $stmtSeguimiento->fetchAll();

function format_connected_time($seconds) {
    if (!$seconds) return '0h 0m';
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return "{$hours}h {$minutes}m";
}

class PDF extends FPDF {
    function Header() {
        // Logo
        if (file_exists('img/logo_efp.png')) {
            $this->Image('img/logo_efp.png', 10, 8, 30);
        }
        
        // Arial bold 15
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(30, 58, 138); // #1e3a8a
        
        // Title
        $this->Cell(40); // Espaciado para el logo
        $this->Cell(0, 10, utf8_decode('INFORME DE SEGUIMIENTO MOODLE'), 0, 1, 'L');
        
        $this->Ln(5);
    }
    
    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        // Page number
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape mode para que quepan las columnas
$pdf->AliasNbPages();
$pdf->AddPage();

// Datos del curso
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(35, 8, utf8_decode('Curso: '), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, utf8_decode($accion['titulo']), 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(35, 8, utf8_decode('ID Plataforma: '), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, utf8_decode($accion['id_plataforma'] ? $accion['id_plataforma'] : 'No vinculado'), 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(35, 8, utf8_decode('Fecha Impresión: '), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, date('d/m/Y H:i'), 0, 1);

$pdf->Ln(10);

// Cabeceras de tabla
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(30, 58, 138); // Azul corporativo
$pdf->SetTextColor(255, 255, 255);

$w = array(60, 22, 15, 30, 25, 20, 25, 25, 20, 25); // Suma: 267 (Max 277 en A4 Landscape)
$header = array('Alumno', 'DNI', 'Grupo', 'Acceso (Prim/Ult)', 'Tiempo', '% Curso', 'Visualiz (M1-M3)', 'Eval (E1-E3)', 'Nota Media', 'Aptitud');

foreach($header as $i => $h) {
    $pdf->Cell($w[$i], 8, utf8_decode($h), 1, 0, 'C', true);
}
$pdf->Ln();

// Datos
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$fill = false;

if (empty($alumnos)) {
    $pdf->Cell(array_sum($w), 10, utf8_decode('No hay alumnos matriculados.'), 1, 0, 'C');
} else {
    foreach($alumnos as $al) {
        $nombreCompleto = $al['primer_apellido'] . ' ' . $al['segundo_apellido'] . ', ' . $al['nombre'];
        if (strlen($nombreCompleto) > 35) {
            $nombreCompleto = substr($nombreCompleto, 0, 32) . '...';
        }
        
        $acceso = "";
        if ($al['moodle_first_access']) {
            $acceso = date('d/m H:i', strtotime($al['moodle_first_access'])) . "\n" . date('d/m H:i', strtotime($al['moodle_last_access']));
        } else {
            $acceso = "---";
        }
        
        $m_text = ($al['moodle_m1_completed']?'V':'X') . "-" . ($al['moodle_m2_completed']?'V':'X') . "-" . ($al['moodle_m3_completed']?'V':'X');
        
        $e1 = $al['moodle_e1_grade'] !== null ? number_format($al['moodle_e1_grade'],1) : '-';
        $e2 = $al['moodle_e2_grade'] !== null ? number_format($al['moodle_e2_grade'],1) : '-';
        $e3 = $al['moodle_e3_grade'] !== null ? number_format($al['moodle_e3_grade'],1) : '-';
        $e_text = "$e1 / $e2 / $e3";
        
        $nota = $al['moodle_final_grade'] !== null ? number_format($al['moodle_final_grade'],2) : '---';
        $aptitud = $al['moodle_aptitud'] ? $al['moodle_aptitud'] : 'PENDIENTE';
        
        // Usaremos MultiCell para el acceso (porque tiene 2 líneas), así que calculamos la altura (Y) máxima
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        
        $pdf->SetFillColor(245, 245, 245);
        
        $pdf->Cell($w[0], 10, utf8_decode($nombreCompleto), 1, 0, 'L', $fill);
        $pdf->Cell($w[1], 10, utf8_decode($al['dni']), 1, 0, 'C', $fill);
        $pdf->Cell($w[2], 10, utf8_decode('G'.$al['numero_grupo']), 1, 0, 'C', $fill);
        
        $pdf->SetXY($x + $w[0] + $w[1] + $w[2], $y);
        if ($acceso === '---') {
            $pdf->Cell($w[3], 10, $acceso, 1, 0, 'C', $fill);
        } else {
            // MultiCell for Access
            $pdf->MultiCell($w[3], 5, utf8_decode($acceso), 1, 'C', $fill);
            $pdf->SetXY($x + $w[0] + $w[1] + $w[2] + $w[3], $y);
        }
        
        $pdf->Cell($w[4], 10, utf8_decode(format_connected_time($al['moodle_connected_time'])), 1, 0, 'C', $fill);
        $pdf->Cell($w[5], 10, utf8_decode((int)$al['moodle_progress'] . '%'), 1, 0, 'C', $fill);
        $pdf->Cell($w[6], 10, utf8_decode($m_text), 1, 0, 'C', $fill);
        $pdf->Cell($w[7], 10, utf8_decode($e_text), 1, 0, 'C', $fill);
        
        // Font bold for note and aptitud
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($w[8], 10, utf8_decode($nota), 1, 0, 'C', $fill);
        
        // Color based on aptitud
        if ($aptitud === 'APTO') {
            $pdf->SetTextColor(21, 128, 61);
        } elseif ($aptitud === 'NO APTO') {
            $pdf->SetTextColor(153, 27, 27);
        } else {
            $pdf->SetTextColor(75, 85, 99);
        }
        
        $pdf->Cell($w[9], 10, utf8_decode($aptitud), 1, 0, 'C', $fill);
        
        // Reset color and font for next row
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 8);
        
        $pdf->Ln(10);
        $fill = !$fill;
    }
}

$pdf->Output('I', 'informe_seguimiento_' . date('Ymd_Hi') . '.pdf');
?>
