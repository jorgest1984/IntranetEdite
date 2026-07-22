<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

// Solo personal autorizado
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    die("Acceso denegado.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$moodle_id = isset($_GET['moodle_id']) ? (int)$_GET['moodle_id'] : 0;
$source = isset($_GET['source']) ? $_GET['source'] : '';

$accion = null;

if ($moodle_id) {
    $stmt = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id_plataforma = ?");
    $stmt->execute([$moodle_id]);
    $accion = $stmt->fetch();
} elseif ($source === 'moodle' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id_plataforma = ?");
    $stmt->execute([$id]);
    $accion = $stmt->fetch();
} elseif ($id) {
    $stmt = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id = ?");
    $stmt->execute([$id]);
    $accion = $stmt->fetch();
    
    // Fallback: si no se encuentra por ID interno, intentamos por id_plataforma
    if (!$accion) {
        $stmt = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id_plataforma = ?");
        $stmt->execute([$id]);
        $accion = $stmt->fetch();
    }
}

if (!$accion) {
    die("Acción formativa no encontrada.");
}

// Reasignar el ID real de la intranet para que el resto del script funcione igual
$id = (int)$accion['id'];

// Fetch Expediente (Priority: first group's expediente, Fallback: convocatoria)
$expediente = '---';
try {
    $stmtExp = $pdo->prepare("
        SELECT COALESCE(NULLIF(g.expediente, ''), co.codigo_expediente) as codigo_expediente
        FROM acciones_formativas af
        LEFT JOIN grupos g ON g.accion_id = af.id
        LEFT JOIN planes pl ON af.plan_id = pl.id
        LEFT JOIN convocatorias co ON pl.convocatoria_id = co.id
        WHERE af.id = ?
        ORDER BY g.id ASC
        LIMIT 1
    ");
    $stmtExp->execute([$id]);
    $expRow = $stmtExp->fetch();
    if ($expRow && !empty($expRow['codigo_expediente'])) {
        $expediente = $expRow['codigo_expediente'];
    }
} catch (Throwable $e) {}
$num_accion = !empty($accion['num_accion']) ? $accion['num_accion'] : '---';
$curso_nombre = ($accion['abreviatura'] ? $accion['abreviatura'] : $accion['id_plataforma']) . ' - ' . $accion['titulo'];
$horas = !empty($accion['duracion']) ? $accion['duracion'] . ' h' : '---';

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
                                  WHERE g.accion_id = ? AND m.estado != 'Baja'
                                  ORDER BY a.primer_apellido ASC, a.nombre ASC");
$stmtSeguimiento->execute([$id]);
$alumnos = $stmtSeguimiento->fetchAll();

$grupo_num = !empty($alumnos) ? $alumnos[0]['numero_grupo'] : '---';

function format_connected_time($seconds) {
    if (!$seconds) return '0 h 0 min 0 s';
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return "{$hours} h {$minutes} min {$secs} s";
}

class PDF extends FPDF {
    function Header() {
        // Arial bold 11
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(0, 0, 0);
        
        // Title centered
        $this->Cell(0, 10, utf8_decode('INFORME GRUPO'), 0, 1, 'C');
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, utf8_decode('Informe generado el ' . date('Y-m-d') . ' a las ' . date('H:i:s') . '.'), 0, 0, 'L');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape
$pdf->SetMargins(11, 10, 11); // Center the 275mm table on a 297mm page
$pdf->AliasNbPages();
$pdf->AddPage();

// Cabecera de datos
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(0, 0, 0);

// Row 1
$pdf->Cell(20, 6, utf8_decode('Expediente:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(45, 6, utf8_decode($expediente), 0, 0);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(20, 6, utf8_decode('Nº Acción:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(35, 6, utf8_decode($num_accion), 0, 0);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(15, 6, utf8_decode('Grupo:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(40, 6, utf8_decode($grupo_num), 0, 1);

// Row 2
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(15, 6, utf8_decode('Curso:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(140, 6, utf8_decode($curso_nombre), 0, 0);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(18, 6, utf8_decode('Nº horas:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(30, 6, utf8_decode($horas), 0, 1);

$pdf->Ln(5);

// Tabla Cabeceras
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(255, 255, 255); // Fondo blanco
$pdf->SetTextColor(0, 0, 0);

$w_alumno = 74;
$w_horas = 26;
$w_porc_curso = 20;
$w_m = 18; // x3 = 54
$w_e = 13; // x3 = 39
$w_porc_controles = 24;
$w_nota = 15;
$w_apto = 23;

$x = $pdf->GetX();
$y = $pdf->GetY();

// Nivel 1
$pdf->Cell($w_alumno, 10, utf8_decode('ALUMNOS'), 1, 0, 'C');
$pdf->Cell($w_horas + $w_porc_curso, 5, utf8_decode('TIEMPO CONEXIONES'), 1, 0, 'C');
$pdf->Cell($w_m * 3, 5, utf8_decode('VISUALIZACIÓN DE CONTENIDOS'), 1, 0, 'C');
$pdf->Cell($w_e * 3 + $w_porc_controles, 5, utf8_decode('CONTROLES'), 1, 0, 'C');
$pdf->Cell($w_nota + $w_apto, 5, utf8_decode('CALIFICACIÓN'), 'LTR', 1, 'C');

// Nivel 2
$pdf->SetXY($x + $w_alumno, $y + 5);
$pdf->Cell($w_horas, 5, utf8_decode('Nº HORAS'), 1, 0, 'C');
$pdf->Cell($w_porc_curso, 5, utf8_decode('% CURSO'), 1, 0, 'C');
$pdf->Cell($w_m, 5, 'M1', 1, 0, 'C');
$pdf->Cell($w_m, 5, 'M2', 1, 0, 'C');
$pdf->Cell($w_m, 5, 'M3', 1, 0, 'C');
$pdf->Cell($w_e, 5, 'E1', 1, 0, 'C');
$pdf->Cell($w_e, 5, 'E2', 1, 0, 'C');
$pdf->Cell($w_e, 5, 'E3', 1, 0, 'C');
$pdf->Cell($w_porc_controles, 5, utf8_decode('% CONTROLES'), 1, 0, 'C');
$pdf->Cell($w_nota + $w_apto, 5, utf8_decode('FINAL'), 'LBR', 1, 'C');

// Filas
$pdf->SetFont('Arial', '', 8);

if (empty($alumnos)) {
    $pdf->Cell(array_sum([$w_alumno, $w_horas, $w_porc_curso, $w_m*3, $w_e*3, $w_porc_controles, $w_nota, $w_apto]), 10, utf8_decode('No hay alumnos matriculados.'), 1, 0, 'C');
} else {
    foreach($alumnos as $al) {
        $nombreCompleto = $al['nombre'] . ' ' . $al['primer_apellido'] . ' ' . $al['segundo_apellido'];
        $nombreCompleto = strtoupper($nombreCompleto);
        if (strlen($nombreCompleto) > 36) {
            $nombreCompleto = substr($nombreCompleto, 0, 34) . '...';
        }
        
        $tiempo = format_connected_time($al['moodle_connected_time']);
        $porc_curso = number_format((float)$al['moodle_progress'], 2) . '%';
        
        $m1 = $al['moodle_m1_completed'] ? 'X' : '';
        $m2 = $al['moodle_m2_completed'] ? 'X' : '';
        $m3 = $al['moodle_m3_completed'] ? 'X' : '';
        
        $e1 = $al['moodle_e1_grade'] !== null ? number_format((float)$al['moodle_e1_grade'], 2) : '';
        $e2 = $al['moodle_e2_grade'] !== null ? number_format((float)$al['moodle_e2_grade'], 2) : '';
        $e3 = $al['moodle_e3_grade'] !== null ? number_format((float)$al['moodle_e3_grade'], 2) : '';
        
        // % Controles calculation
        $c_total = 3;
        $c_done = ($e1 !== '' ? 1 : 0) + ($e2 !== '' ? 1 : 0) + ($e3 !== '' ? 1 : 0);
        $porc_controles = number_format(($c_done / $c_total) * 100, 2) . '%';
        
        $nota = $al['moodle_final_grade'] !== null ? number_format((float)$al['moodle_final_grade'], 2) : '';
        $aptitud = strtoupper($al['moodle_aptitud'] ? $al['moodle_aptitud'] : '');
        
        $pdf->Cell($w_alumno, 6, utf8_decode($nombreCompleto), 1, 0, 'L');
        $pdf->Cell($w_horas, 6, utf8_decode($tiempo), 1, 0, 'R');
        $pdf->Cell($w_porc_curso, 6, utf8_decode($porc_curso), 1, 0, 'R');
        // M1
        if ($m1 === 'X') { $pdf->SetTextColor(22, 163, 74); $pdf->SetFont('Arial', 'B', 8); }
        $pdf->Cell($w_m, 6, utf8_decode($m1), 1, 0, 'C');
        $pdf->SetTextColor(0, 0, 0); $pdf->SetFont('Arial', '', 8);
        
        // M2
        if ($m2 === 'X') { $pdf->SetTextColor(22, 163, 74); $pdf->SetFont('Arial', 'B', 8); }
        $pdf->Cell($w_m, 6, utf8_decode($m2), 1, 0, 'C');
        $pdf->SetTextColor(0, 0, 0); $pdf->SetFont('Arial', '', 8);
        
        // M3
        if ($m3 === 'X') { $pdf->SetTextColor(22, 163, 74); $pdf->SetFont('Arial', 'B', 8); }
        $pdf->Cell($w_m, 6, utf8_decode($m3), 1, 0, 'C');
        $pdf->SetTextColor(0, 0, 0); $pdf->SetFont('Arial', '', 8);
        $pdf->Cell($w_e, 6, utf8_decode($e1), 1, 0, 'C');
        $pdf->Cell($w_e, 6, utf8_decode($e2), 1, 0, 'C');
        $pdf->Cell($w_e, 6, utf8_decode($e3), 1, 0, 'C');
        $pdf->Cell($w_porc_controles, 6, utf8_decode($porc_controles), 1, 0, 'C');
        if ($aptitud === 'PENDIENTE') {
            $pdf->Cell($w_nota + $w_apto, 6, utf8_decode('PENDIENTE'), 1, 1, 'C');
        } else {
            $pdf->Cell($w_nota, 6, utf8_decode($nota), 'LBT', 0, 'C');
            $pdf->Cell($w_apto, 6, utf8_decode($aptitud), 'RBT', 1, 'C');
        }
    }
}

$pdf->Output('I', 'informe_seguimiento_' . date('Ymd_Hi') . '.pdf');
?>
