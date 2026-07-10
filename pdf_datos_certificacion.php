<?php
// pdf_datos_certificacion.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Limpiar cualquier output previo
if (ob_get_level()) ob_end_clean();

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if (!$grupo_id) { die("ID de grupo no proporcionado."); }

// 1. Obtener datos del grupo
$stmt = $pdo->prepare("SELECT g.*, af.num_accion, c.nombre_largo as curso_titulo
                       FROM grupos g
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       JOIN cursos c ON af.curso_id = c.id
                       WHERE g.id = ?");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$grupo) { die("Grupo no encontrado."); }

// 2. Detectar qué columnas opcionales existen en la tabla alumnos
$existingAlumnoCols = [];
try {
    $descStmt = $pdo->query("DESCRIBE alumnos");
    $existingAlumnoCols = array_column($descStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
} catch (Exception $e) {}

$col_sexo      = in_array('sexo', $existingAlumnoCols)      ? 'a.sexo'      : 'NULL';
$col_colectivo = in_array('colectivo', $existingAlumnoCols) ? 'a.colectivo'  : 'NULL';
$col_dspld     = in_array('desempleado_larga_duracion', $existingAlumnoCols) ? 'a.desempleado_larga_duracion' : 'NULL';
$col_contrato  = in_array('contrato', $existingAlumnoCols)  ? 'a.contrato'   : 'NULL';
$col_provincia = in_array('provincia', $existingAlumnoCols) ? 'a.provincia'  : 'NULL';

// 3. Obtener alumnos
$stmtAl = $pdo->prepare("
    SELECT m.id as matricula_id,
           m.estado as matricula_estado,
           m.certificables,
           a.id as alumno_id,
           a.nombre, a.primer_apellido, a.segundo_apellido,
           a.dni, a.fecha_nacimiento,
           $col_sexo      as sexo,
           $col_colectivo as colectivo,
           $col_dspld     as desempleado_larga_duracion,
           $col_contrato  as contrato,
           $col_provincia as provincia,
           e.nombre as empresa_nombre
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
    LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
    WHERE m.grupo_id = ?
    ORDER BY a.primer_apellido ASC, a.nombre ASC
");
$stmtAl->execute([$grupo_id]);
$alumnos = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

// Helper: calcular edad
function cert_edad($fecha_nac) {
    if (!$fecha_nac || $fecha_nac === '0000-00-00') return '—';
    $dt = new DateTime($fecha_nac);
    return $dt->diff(new DateTime())->y;
}

// Helper: convertir UTF-8 a ISO-8859-1 sin funciones deprecadas
function cert_str($str) {
    if ($str === null || $str === '') return '';
    return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
}

// ─────────────── CLASE PDF ───────────────
class CertificacionPDF extends FPDF {
    public $grupo = [];

    function setGrupo($g) { $this->grupo = $g; }

    function Header() {
        // Cabecera roja centrada — título
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(180, 0, 0);
        $this->Cell(0, 5, cert_str('COMPROBACIÓN REQUISITOS CERTIFICACIÓN POR GRUPO'), 0, 1, 'C');

        // Línea de expediente
        $exp   = cert_str('Expediente: ' . ($this->grupo['num_accion'] ?? ''));
        $grupo = cert_str('Grupo ' . ($this->grupo['numero_grupo'] ?? '') . ', ADGD029 - ' . ($this->grupo['curso_titulo'] ?? ''));
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(150, 0, 0);
        $this->Cell(0, 4, $exp . ', ' . $grupo, 0, 1, 'C');

        // Fechas y horario
        $fi   = !empty($this->grupo['fecha_inicio']) ? date('d/m/Y', strtotime($this->grupo['fecha_inicio'])) : '—';
        $ff   = !empty($this->grupo['fecha_fin'])    ? date('d/m/Y', strtotime($this->grupo['fecha_fin']))    : '—';
        $hora = '';
        if (!empty($this->grupo['horario_desde']) && !empty($this->grupo['horario_hasta'])) {
            $hora = ', de ' . $this->grupo['horario_desde'] . ' a ' . $this->grupo['horario_hasta'];
        }
        $this->Cell(0, 4, cert_str($fi . ' al ' . $ff . $hora), 0, 1, 'C');

        // Modalidad
        $this->SetFont('Arial', 'B', 7);
        $this->Cell(0, 4, cert_str('Modalidad: ' . ($this->grupo['modalidad'] ?? '—')), 0, 1, 'C');

        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
        $this->SetDrawColor(180, 0, 0);
        $this->Line(8, $this->GetY(), 289, $this->GetY());
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, cert_str('Página ') . $this->PageNo() . '/{nb}   —   Generado el ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
}

// ─────────────── GENERAR PDF ───────────────
$pdf = new CertificacionPDF('L', 'mm', 'A4'); // Landscape para caber todas las columnas
$pdf->setGrupo($grupo);
$pdf->AliasNbPages();
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

// ── Cabecera de la tabla ──
// A4 landscape usable width = 297 - 8 - 8 = 281mm
// Column widths must total exactly 281mm
$pdf->SetFont('Arial', 'B', 6.5);
$pdf->SetFillColor(220, 230, 245);
$pdf->SetTextColor(0, 60, 140);
$pdf->SetDrawColor(180, 200, 230);

// Anchos de columna: total = 50+20+46+7+8+8+22+7+26+9+12+18+14+12+11+11 = 281mm ✓
// (A4 landscape: 297mm - 8mm left - 8mm right = 281mm usable)
$cols = [
    'Alumno'        => 50,
    'NIF'           => 20,
    'Empresa'       => 46,
    '<10'           =>  7,
    'Edad'          =>  8,
    'Sexo'          =>  8,
    'Estudios'      => 22,
    'Discap'        =>  7,
    'Tipo Contrato' => 26,
    'DSPLD'         =>  9,
    'Colectivo'     => 12,
    'Provincia'     => 18,
    'Estado'        => 14,
    'Col. Prior.'   => 12,
    'Certifica'     => 11,
    'Bloqueado'     => 11,
]; // = 281 ✓

$rowH = 6;
foreach ($cols as $label => $w) {
    $pdf->Cell($w, $rowH, cert_str($label), 1, 0, 'C', true);
}
$pdf->Ln();

// ── Filas de datos ──
$pdf->SetFont('Arial', '', 6);
$pdf->SetTextColor(30, 30, 30);
$fill = false;

foreach ($alumnos as $alumno) {
    $apellidos    = trim(($alumno['primer_apellido'] ?? '') . ' ' . ($alumno['segundo_apellido'] ?? ''));
    $nombre_comp  = mb_strtoupper($apellidos) . ' ' . ($alumno['nombre'] ?? '');
    $edad         = cert_edad($alumno['fecha_nacimiento']);
    $sexo         = mb_strtoupper(substr($alumno['sexo'] ?? '', 0, 1)) ?: '—';
    $colect       = mb_strtoupper($alumno['colectivo'] ?? '—');
    $estado       = mb_strtoupper($alumno['matricula_estado'] ?? '—');
    $certif       = $alumno['certificables'] ?? 'NO';
    $contrato     = $alumno['contrato'] ?? '—';
    $dspld        = ($alumno['desempleado_larga_duracion'] === 'SI' || $alumno['desempleado_larga_duracion'] === '1') ? 'SI' : 'NO';
    $empresa      = $alumno['empresa_nombre'] ?? '—';
    $provincia    = mb_strtoupper($alumno['provincia'] ?? '—');

    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 248 : 255, 255);

    $h = $rowH;
    $pdf->Cell($cols['Alumno'],        $h, cert_str(mb_substr($nombre_comp, 0, 34)),    1, 0, 'L', $fill);
    $pdf->Cell($cols['NIF'],           $h, cert_str($alumno['dni'] ?? '—'),             1, 0, 'C', $fill);
    $pdf->Cell($cols['Empresa'],       $h, cert_str(mb_substr($empresa, 0, 36)),        1, 0, 'L', $fill);
    $pdf->Cell($cols['<10'],           $h, '',                                          1, 0, 'C', $fill);
    $pdf->Cell($cols['Edad'],          $h, $edad,                                       1, 0, 'C', $fill);
    $pdf->Cell($cols['Sexo'],          $h, $sexo,                                       1, 0, 'C', $fill);
    $pdf->Cell($cols['Estudios'],      $h, '',                                          1, 0, 'L', $fill);
    $pdf->Cell($cols['Discap'],        $h, '',                                          1, 0, 'C', $fill);
    $pdf->Cell($cols['Tipo Contrato'], $h, cert_str(mb_substr($contrato, 0, 22)),       1, 0, 'L', $fill);
    $pdf->Cell($cols['DSPLD'],         $h, $dspld,                                      1, 0, 'C', $fill);
    $pdf->Cell($cols['Colectivo'],     $h, cert_str($colect),                           1, 0, 'C', $fill);
    $pdf->Cell($cols['Provincia'],     $h, cert_str(mb_substr($provincia, 0, 16)),      1, 0, 'L', $fill);
    $pdf->Cell($cols['Estado'],        $h, cert_str($estado),                           1, 0, 'C', $fill);
    $pdf->Cell($cols['Col. Prior.'],   $h, 'SI',                                        1, 0, 'C', $fill);
    $pdf->Cell($cols['Certifica'],     $h, cert_str($certif),                           1, 0, 'C', $fill);
    $pdf->Cell($cols['Bloqueado'],     $h, '',                                          1, 1, 'C', $fill);

    $fill = !$fill;
}

// Fila de totales
$pdf->SetFont('Arial', 'B', 6.5);
$pdf->SetFillColor(220, 230, 245);
$pdf->SetTextColor(0, 60, 140);
$total = count($alumnos);
$totalCertSI = 0;
foreach ($alumnos as $a) {
    if (($a['certificables'] ?? '') === 'SI') $totalCertSI++;
}
$anchoLabel = array_sum(array_values($cols)) - $cols['Certifica'] - $cols['Bloqueado'];
$pdf->Cell($anchoLabel,        $rowH, cert_str("Total alumnos: $total"), 1, 0, 'R', true);
$pdf->Cell($cols['Certifica'], $rowH, $totalCertSI,                      1, 0, 'C', true);
$pdf->Cell($cols['Bloqueado'], $rowH, '',                                1, 1, 'C', true);

// Output
$filename = 'Certificacion_Grupo_' . ($grupo['numero_grupo'] ?? $grupo_id) . '_' . date('Ymd') . '.pdf';
$pdf->Output('D', $filename);
exit;
