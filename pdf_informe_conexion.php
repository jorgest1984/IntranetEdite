<?php
// pdf_informe_conexion.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_db.php';
require_once 'includes/fpdf/fpdf.php';

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if (!$grupo_id) {
    die("ID de grupo no proporcionado.");
}

// 1. Obtener datos del grupo y su curso
$stmt = $pdo->prepare("SELECT g.*, af.num_accion, c.nombre_largo as curso_titulo, af.id_plataforma as course_moodle_id
                       FROM grupos g
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       JOIN cursos c ON af.curso_id = c.id
                       WHERE g.id = ?");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch();

if (!$grupo) {
    die("Grupo no encontrado.");
}

// 2. Obtener alumnos del grupo
$stmtAl = $pdo->prepare("SELECT m.id as matricula_id, a.id as alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.moodle_user_id
                         FROM matriculas m
                         JOIN alumnos a ON m.alumno_id = a.id
                         WHERE m.grupo_id = ?
                         ORDER BY a.primer_apellido ASC, a.nombre ASC");
$stmtAl->execute([$grupo_id]);
$alumnos = $stmtAl->fetchAll();

// 3. Obtener logs desde Moodle
$user_logs = [];
$moodleUserIds = [];
foreach ($alumnos as $al) {
    if (!empty($al['moodle_user_id'])) {
        $moodleUserIds[] = (int)$al['moodle_user_id'];
    }
}

$courseMoodleId = (int)$grupo['course_moodle_id'];
$moodleDb = new MoodleDB();
$moodleConnected = $moodleDb->isConnected();

if ($moodleConnected && !empty($moodleUserIds)) {
    try {
        $mpdo = $moodleDb->getPDO();
        $prefix = defined('MOODLE_DB_PREFIX') ? MOODLE_DB_PREFIX : 'avefp_';
        
        $placeholders = implode(',', array_fill(0, count($moodleUserIds), '?'));
        $sqlLogs = "SELECT userid, timecreated 
                    FROM {$prefix}logstore_standard_log 
                    WHERE courseid = ? AND userid IN ($placeholders) 
                    ORDER BY userid ASC, timecreated ASC";
        $stmtLogs = $mpdo->prepare($sqlLogs);
        $params = array_merge([$courseMoodleId], $moodleUserIds);
        $stmtLogs->execute($params);
        
        while ($row = $stmtLogs->fetch(PDO::FETCH_ASSOC)) {
            $user_logs[(int)$row['userid']][] = (int)$row['timecreated'];
        }
    } catch (Exception $e) {
        // Fallback
    }
}

// Helpers
function pdf_get_day_of_week_es($timestamp) {
    $days = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
    return $days[date('w', $timestamp)];
}

function pdf_format_duration_hm($seconds) {
    $hours = floor($seconds / 3600);
    $mins = floor(($seconds % 3600) / 60);
    return "{$hours} h {$mins} min";
}

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

// 4. Calcular sesiones
$student_sessions = [];
foreach ($alumnos as $al) {
    $muid = (int)$al['moodle_user_id'];
    $times = isset($user_logs[$muid]) ? $user_logs[$muid] : [];
    $sessions = [];
    $total_seconds = 0;
    
    if (!empty($times)) {
        sort($times);
        $current_start = $times[0];
        $current_last = $times[0];
        $n = count($times);
        
        for ($i = 1; $i < $n; $i++) {
            $diff = $times[$i] - $current_last;
            if ($diff < 1800) {
                $current_last = $times[$i];
            } else {
                $approx = $current_last - $current_start + 120;
                $sessions[] = [
                    'date' => date('d/m/Y', $current_start),
                    'day' => pdf_get_day_of_week_es($current_start),
                    'start_time' => date('H:i', $current_start),
                    'approx' => $approx,
                    'adjusted' => $approx
                ];
                $total_seconds += $approx;
                
                $current_start = $times[$i];
                $current_last = $times[$i];
            }
        }
        $approx = $current_last - $current_start + 120;
        $sessions[] = [
            'date' => date('d/m/Y', $current_start),
            'day' => pdf_get_day_of_week_es($current_start),
            'start_time' => date('H:i', $current_start),
            'approx' => $approx,
            'adjusted' => $approx
        ];
        $total_seconds += $approx;
    }
    
    $student_sessions[$al['alumno_id']] = [
        'sessions' => $sessions,
        'total_seconds' => $total_seconds
    ];
}

// 5. Generar PDF
class ConnectionReportPDF extends FPDF {
    private $grupo;
    
    public function setGrupoData($g) {
        $this->grupo = $g;
    }

    function Header() {
        // Logo de la empresa si existe
        if (file_exists('img/logo_efp.png')) {
            $this->Image('img/logo_efp.png', 10, 8, 25);
        } else {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(40, 10, 'GRUPO EFP', 0, 0);
        }
        
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(70, 80, 95);
        $this->Cell(0, 8, pdf_utf8_to_iso('INFORME DE CONEXIÓN EN EL AULA VIRTUAL'), 0, 1, 'R');
        
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

$pdf = new ConnectionReportPDF();
$pdf->setGrupoData($grupo);
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Recorrer alumnos y volcar al PDF
foreach ($alumnos as $alumno) {
    // Si queda poco espacio en la página, insertar salto de página preventivo
    if ($pdf->GetY() > 220) {
        $pdf->AddPage();
    }
    
    $apellidos = trim(($alumno['primer_apellido'] ?? '') . ' ' . ($alumno['segundo_apellido'] ?? ''));
    $nombre_completo = mb_strtoupper($apellidos . ', ' . $alumno['nombre']);
    $stats = $student_sessions[$alumno['alumno_id']];
    $sessions = $stats['sessions'];
    $total_seconds = $stats['total_seconds'];
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(0, 108, 228); // Color primario
    $pdf->Cell(0, 8, pdf_utf8_to_iso($nombre_completo), 0, 1);
    
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(0, 6, pdf_utf8_to_iso('Tiempo de conexión: ' . pdf_format_duration_hm($total_seconds)), 0, 1);
    $pdf->Ln(2);
    
    // Tabla cabeceras
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetFillColor(240, 246, 255);
    $pdf->SetTextColor(0, 108, 228);
    
    $pdf->Cell(35, 7, pdf_utf8_to_iso('Fecha'), 1, 0, 'L', true);
    $pdf->Cell(45, 7, pdf_utf8_to_iso('Día de la semana'), 1, 0, 'L', true);
    $pdf->Cell(45, 7, pdf_utf8_to_iso('Hora inicio de actividad'), 1, 0, 'L', true);
    $pdf->Cell(32, 7, pdf_utf8_to_iso('Duración aprox.'), 1, 0, 'R', true);
    $pdf->Cell(33, 7, pdf_utf8_to_iso('Duración ajustada'), 1, 1, 'R', true);
    
    // Contenido tabla
    $pdf->SetFont('Arial', '', 8.5);
    $pdf->SetTextColor(50, 50, 50);
    
    if (empty($sessions)) {
        $pdf->Cell(190, 8, pdf_utf8_to_iso('No se registran accesos al Aula Virtual para este alumno.'), 1, 1, 'C');
        
        // Fila Total
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->Cell(125, 8, pdf_utf8_to_iso('Total'), 1, 0, 'L');
        $pdf->Cell(32, 8, '0 h 0 min', 1, 0, 'R');
        $pdf->Cell(33, 8, '0 h 0 min', 1, 1, 'R');
    } else {
        foreach ($sessions as $session) {
            // Salto preventivo por fila
            if ($pdf->GetY() > 265) {
                $pdf->AddPage();
                
                // Redibujar cabeceras tras salto
                $pdf->SetFont('Arial', 'B', 8.5);
                $pdf->SetFillColor(240, 246, 255);
                $pdf->SetTextColor(0, 108, 228);
                $pdf->Cell(35, 7, pdf_utf8_to_iso('Fecha'), 1, 0, 'L', true);
                $pdf->Cell(45, 7, pdf_utf8_to_iso('Día de la semana'), 1, 0, 'L', true);
                $pdf->Cell(45, 7, pdf_utf8_to_iso('Hora inicio de actividad'), 1, 0, 'L', true);
                $pdf->Cell(32, 7, pdf_utf8_to_iso('Duración aprox.'), 1, 0, 'R', true);
                $pdf->Cell(33, 7, pdf_utf8_to_iso('Duración ajustada'), 1, 1, 'R', true);
                
                $pdf->SetFont('Arial', '', 8.5);
                $pdf->SetTextColor(50, 50, 50);
            }
            
            $pdf->Cell(35, 7, pdf_utf8_to_iso($session['date']), 1, 0, 'L');
            $pdf->Cell(45, 7, pdf_utf8_to_iso($session['day']), 1, 0, 'L');
            $pdf->Cell(45, 7, pdf_utf8_to_iso($session['start_time']), 1, 0, 'L');
            $pdf->Cell(32, 7, pdf_utf8_to_iso(pdf_format_duration_hm($session['approx'])), 1, 0, 'R');
            $pdf->Cell(33, 7, pdf_utf8_to_iso(pdf_format_duration_hm($session['adjusted'])), 1, 1, 'R');
        }
        
        // Fila Total
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->Cell(125, 7, pdf_utf8_to_iso('Total'), 1, 0, 'L');
        $pdf->Cell(32, 7, pdf_utf8_to_iso(pdf_format_duration_hm($total_seconds)), 1, 0, 'R');
        $pdf->Cell(33, 7, pdf_utf8_to_iso(pdf_format_duration_hm($total_seconds)), 1, 1, 'R');
    }
    
    $pdf->Ln(8);
}

// Limpiar buffer de salida para evitar "Some data has already been output"
if (ob_get_length()) {
    ob_clean();
}

// Enviar al navegador
$pdf->Output('I', "informe_conexion_grupo_" . $grupo_id . ".pdf");
