<?php
// pdf_calendario_grupo.php
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
$stmt = $pdo->prepare("SELECT g.*, af.num_accion, af.modalidad as af_modalidad, af.duracion as af_duracion, c.nombre_largo as curso_titulo
                       FROM grupos g
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       JOIN cursos c ON af.curso_id = c.id
                       WHERE g.id = ?");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch();

if (!$grupo) {
    die("Grupo no encontrado.");
}

// 2. Obtener días marcados como festivos/vacaciones en el rango del grupo
$stmtFestivos = $pdo->prepare("SELECT fecha FROM calendario_dias WHERE fecha BETWEEN ? AND ? AND (es_nacional = 1 OR es_vacacion = 1)");
$stmtFestivos->execute([$grupo['fecha_inicio'], $grupo['fecha_fin']]);
$festivos = $stmtFestivos->fetchAll(PDO::FETCH_COLUMN);

// 3. Procesar días lectivos y horas por mes
$dias_semana_map = [
    'monday' => $grupo['dias_lunes'],
    'tuesday' => $grupo['dias_martes'],
    'wednesday' => $grupo['dias_miercoles'],
    'thursday' => $grupo['dias_jueves'],
    'friday' => $grupo['dias_viernes'],
    'saturday' => $grupo['dias_sabado'],
    'sunday' => $grupo['dias_domingo']
];

$lectivos_por_mes = [];
$total_lectivos = 0;
$lectivos_list = []; // Lista plana de strings Y-m-d

$start_ts = strtotime($grupo['fecha_inicio']);
$end_ts = strtotime($grupo['fecha_fin']);
$curr_ts = $start_ts;

while ($curr_ts <= $end_ts) {
    $date_str = date('Y-m-d', $curr_ts);
    $day_name = strtolower(date('l', $curr_ts));
    
    $es_lectivo = false;
    if (isset($dias_semana_map[$day_name]) && $dias_semana_map[$day_name] == 1) {
        $es_lectivo = true;
    }
    
    // Excluir festivos
    if (in_array($date_str, $festivos)) {
        $es_lectivo = false;
    }
    
    if ($es_lectivo) {
        $m = (int)date('m', $curr_ts);
        $y = (int)date('Y', $curr_ts);
        $key = "$y-$m";
        if (!isset($lectivos_por_mes[$key])) {
            $lectivos_por_mes[$key] = 0;
        }
        $lectivos_por_mes[$key]++;
        $total_lectivos++;
        $lectivos_list[] = $date_str;
    }
    
    $curr_ts = strtotime("+1 day", $curr_ts);
}

// Horas totales y modalidad
$horas_totales = (float)($grupo['horas_af'] ?: $grupo['af_duracion']);
$modalidad = $grupo['modalidad'] ?: ($grupo['af_modalidad'] ?: 'Teleformación');

// Calcular horas por sesión
$horas_por_sesion = 0.0;
if (!empty($grupo['horario_desde']) && !empty($grupo['horario_hasta'])) {
    $t1 = strtotime($grupo['horario_desde']);
    $t2 = strtotime($grupo['horario_hasta']);
    if ($t2 > $t1) {
        $horas_por_sesion = ($t2 - $t1) / 3600.0;
    }
}
if ($horas_por_sesion <= 0) {
    $horas_por_sesion = $total_lectivos > 0 ? ($horas_totales / $total_lectivos) : 0;
}

// 4. Calcular lista de meses a dibujar (al menos 3 para que se vea balanceado)
$start_date = new DateTime($grupo['fecha_inicio']);
$end_date = new DateTime($grupo['fecha_fin']);
$interval = new DateInterval('P1M');
$period_end = clone $end_date;
$period_end->modify('first day of next month');
$period = new DatePeriod($start_date, $interval, $period_end);

$meses_a_dibujar = [];
foreach ($period as $dt) {
    $meses_a_dibujar[] = [
        'month' => (int)$dt->format('m'),
        'year' => (int)$dt->format('Y')
    ];
}

while (count($meses_a_dibujar) < 3) {
    $last = end($meses_a_dibujar);
    $last_m = $last['month'];
    $last_y = $last['year'];
    $next_m = $last_m + 1;
    $next_y = $last_y;
    if ($next_m > 12) {
        $next_m = 1;
        $next_y++;
    }
    $meses_a_dibujar[] = [
        'month' => $next_m,
        'year' => $next_y
    ];
}

// Traductores
$nombres_meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$dias_semana_cabecera = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

// Función de codificación limpia
function pdf_utf8($str) {
    if ($str === null || $str === '') return '';
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    }
    return @utf8_decode($str);
}

// 5. Generar PDF con FPDF
class CourseCalendarPDF extends FPDF {
    function Header() {
        if (file_exists('img/logo_efp.png')) {
            $this->Image('img/logo_efp.png', 10, 8, 25);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 108, 228);
        $this->Cell(0, 15, pdf_utf8('CALENDARIO DEL CURSO'), 0, 1, 'C');
        $this->Ln(2);
    }
}

$pdf = new CourseCalendarPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// ─── TABLA DE DATOS DEL CURSO ──────────────────────────────────────────
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(240, 246, 255);
$pdf->SetTextColor(0, 108, 228);

// Nombre curso
$pdf->Cell(25, 7, pdf_utf8('Nombre curso:'), 1, 0, 'L', true);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(165, 7, pdf_utf8($grupo['curso_titulo']), 1, 1, 'L');

// Fila 2: Días semana y Horario
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(0, 108, 228);
$pdf->Cell(25, 7, pdf_utf8('Días semana:'), 1, 0, 'L', true);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Arial', '', 8.5);

// Generar texto de días de la semana
$dias_select = [];
if ($grupo['dias_lunes']) $dias_select[] = 'lunes';
if ($grupo['dias_martes']) $dias_select[] = 'martes';
if ($grupo['dias_miercoles']) $dias_select[] = 'miércoles';
if ($grupo['dias_jueves']) $dias_select[] = 'jueves';
if ($grupo['dias_viernes']) $dias_select[] = 'viernes';
if ($grupo['dias_sabado']) $dias_select[] = 'sábado';
if ($grupo['dias_domingo']) $dias_select[] = 'domingo';

if (count($dias_select) == 5 && !in_array('sábado', $dias_select) && !in_array('domingo', $dias_select)) {
    $dias_semana_text = 'lunes a viernes';
} elseif (count($dias_select) == 7) {
    $dias_semana_text = 'lunes a domingo';
} else {
    $dias_semana_text = implode(', ', $dias_select);
}

$pdf->Cell(70, 7, pdf_utf8($dias_semana_text), 1, 0, 'L');

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(0, 108, 228);
$pdf->Cell(20, 7, pdf_utf8('Horario:'), 1, 0, 'L', true);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Arial', '', 8.5);
$horario_str = (!empty($grupo['horario_desde']) && !empty($grupo['horario_hasta'])) ? ($grupo['horario_desde'] . ' a ' . $grupo['horario_hasta']) : ($grupo['horario_info'] ?: '17:00 a 18:30');
$pdf->Cell(75, 7, pdf_utf8($horario_str), 1, 1, 'L');

// Fila 3: Horas totales, Modalidad
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(0, 108, 228);
$pdf->Cell(25, 7, pdf_utf8('Horas totales:'), 1, 0, 'L', true);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(25, 7, number_format($horas_totales, 2), 1, 0, 'C');

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(0, 108, 228);
$pdf->Cell(25, 7, pdf_utf8('Modalidad:'), 1, 0, 'L', true);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(115, 7, pdf_utf8($modalidad), 1, 1, 'L');

// Fila 4: Fecha de inicio, Fecha finalización
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(0, 108, 228);
$pdf->Cell(25, 7, pdf_utf8('Fecha de inicio:'), 1, 0, 'L', true);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(45, 7, date('d/m/Y', strtotime($grupo['fecha_inicio'])), 1, 0, 'C');

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(0, 108, 228);
$pdf->Cell(35, 7, pdf_utf8('Fecha finalización:'), 1, 0, 'L', true);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(85, 7, date('d/m/Y', strtotime($grupo['fecha_fin'])), 1, 1, 'C');

$pdf->Ln(6);

// ─── GRID DE CALENDARIOS (MESES) ──────────────────────────────────────────
$col_width = 58;
$col_gap = 8;
$row_height = 42;
$row_gap = 4;

$start_x = 10;
$start_y = $pdf->GetY();

foreach ($meses_a_dibujar as $index => $m_data) {
    $m = $m_data['month'];
    $y = $m_data['year'];
    
    // Posición en la cuadrícula (3 columnas)
    $col_idx = $index % 3;
    $row_idx = floor($index / 3);
    
    $pos_x = $start_x + ($col_idx * ($col_width + $col_gap));
    $pos_y = $start_y + ($row_idx * ($row_height + $row_gap));
    
    $pdf->SetXY($pos_x, $pos_y);
    
    // Dibujar bloque de mes
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetDrawColor(203, 213, 225); // Slate-300
    
    // Celda de título del mes
    $pdf->Cell($col_width, 5, pdf_utf8($nombres_meses[$m] . ' ' . $y), 'B', 1, 'C');
    
    // Días de la semana
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetX($pos_x);
    foreach ($dias_semana_cabecera as $ds) {
        $pdf->Cell($col_width / 7.0, 4, $ds, 0, 0, 'C');
    }
    $pdf->Ln(4);
    
    // Días del mes
    $first_day = mktime(0, 0, 0, $m, 1, $y);
    $days_in_month = date('t', $first_day);
    $start_day_of_week = date('N', $first_day); // 1 = Lunes, 7 = Domingo
    
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetX($pos_x);
    
    // Celdas vacías al inicio del mes
    for ($i = 1; $i < $start_day_of_week; $i++) {
        $pdf->Cell($col_width / 7.0, 4.2, '', 0, 0, 'C');
    }
    
    $current_dow = $start_day_of_week;
    for ($d = 1; $d <= $days_in_month; $d++) {
        $fecha_str = sprintf("%04d-%02d-%02d", $y, $m, $d);
        
        $is_lectivo = in_array($fecha_str, $lectivos_list);
        $is_weekend = ($current_dow == 6 || $current_dow == 7);
        
        // Configurar color de fondo según tipo de día
        if ($is_lectivo) {
            $pdf->SetFillColor(254, 240, 138); // Amarillo claro
            $pdf->SetFont('Arial', 'B', 7);
            $fill = true;
        } elseif ($is_weekend) {
            $pdf->SetFillColor(226, 232, 240); // Gris
            $pdf->SetFont('Arial', '', 7);
            $fill = true;
        } else {
            $pdf->SetFont('Arial', '', 7);
            $fill = false;
        }
        
        $pdf->Cell($col_width / 7.0, 4.2, $d, 0, 0, 'C', $fill);
        
        if ($current_dow == 7) {
            $pdf->Ln(4.2);
            if ($d < $days_in_month) {
                $pdf->SetX($pos_x);
            }
            $current_dow = 1;
        } else {
            $current_dow++;
        }
    }
}

// Calcular la Y máxima después del calendario para dibujar el pie
$max_rows = ceil(count($meses_a_dibujar) / 3);
$footer_start_y = $start_y + ($max_rows * ($row_height + $row_gap)) + 4;
$pdf->SetY($footer_start_y);

// ─── TEXTOS AVISOS E INSTRUCCIONES ──────────────────────────────────────────
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(71, 85, 105);

// Strip trailing ' h' if the DB field already includes it (avoids "h h" duplication)
$horario_clean = rtrim(trim($horario_str), 'h');
$horario_clean = rtrim($horario_clean); // trim trailing space left after stripping 'h'

$obs1 = "El horario de tutorias normalmente sera de " . $horario_clean . " h.";
$obs2 = "Los participantes tienen la opcion de conectarse a la plataforma las 24 horas del dia los 7 dias de la semana.";

$pdf->Cell(0, 4.5, pdf_utf8($obs1), 0, 1, 'L');
$pdf->Cell(0, 4.5, pdf_utf8($obs2), 0, 1, 'L');

$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(0, 5, pdf_utf8('* Observaciones:'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(100, 116, 139);
$pdf->MultiCell(190, 4, pdf_utf8($grupo['observaciones'] ?: 'Sin observaciones registradas.'), 0, 'L');

$pdf->Ln(6);

// ─── TABLA DE HORAS MENSUALES (RESUMEN) ──────────────────────────────────────────
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(0, 108, 228);
$pdf->Cell(35, 7, pdf_utf8('Suma horas'), 0, 0, 'L');

// Ancho de las celdas mensuales
$num_meses = count($meses_a_dibujar);
$cell_w = 155.0 / ($num_meses + 1); // Distribuido proporcionalmente

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(240, 246, 255);
$pdf->SetTextColor(0, 108, 228);

// Cabeceras de meses
foreach ($meses_a_dibujar as $m_data) {
    $m_lbl = substr($nombres_meses[$m_data['month']], 0, 4) . '.';
    $pdf->Cell($cell_w, 7, pdf_utf8($m_lbl), 1, 0, 'C', true);
}
$pdf->Cell($cell_w, 7, 'TOTAL', 1, 1, 'C', true);

// Fila de datos
$pdf->Cell(35, 7, pdf_utf8('mensuales:'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(30, 41, 59);

$total_check = 0.0;
foreach ($meses_a_dibujar as $m_data) {
    $key = $m_data['year'] . '-' . $m_data['month'];
    $days = isset($lectivos_por_mes[$key]) ? $lectivos_por_mes[$key] : 0;
    $hours = $days * $horas_por_sesion;
    $total_check += $hours;
    
    $pdf->Cell($cell_w, 7, $hours > 0 ? number_format($hours, 2) : '0.00', 1, 0, 'C');
}
$pdf->SetFont('Arial', 'B', 8);
// Cuidado con decimales de redondeo, mostramos el total teórico asignado
$pdf->Cell($cell_w, 7, number_format($horas_totales, 2), 1, 1, 'C');

// Limpiar buffer de salida para evitar "Some data has already been output"
if (ob_get_length()) {
    ob_clean();
}

$pdf->Output('I', "calendario_curso_" . $grupo_id . ".pdf");
