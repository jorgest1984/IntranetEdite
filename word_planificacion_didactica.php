<?php
// word_planificacion_didactica.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_ADMINISTRATIVO])) {
    header("Location: dashboard.php");
    exit();
}

$grupo_id = isset($_GET['grupo_id']) ? intval($_GET['grupo_id']) : 0;
if (!$grupo_id) {
    die("Se requiere el ID del grupo.");
}

// Fetch group and course info
$stmt = $pdo->prepare("
    SELECT 
        g.numero_grupo, g.fecha_inicio, g.fecha_fin,
        af.id as accion_id, af.num_accion, 
        conv.codigo_expediente, 
        cu.nombre_corto as curso_codigo, cu.nombre_largo as curso_titulo, cu.duracion
    FROM grupos g
    JOIN acciones_formativas af ON g.accion_id = af.id
    JOIN cursos cu ON af.curso_id = cu.id
    LEFT JOIN planes p ON af.plan_id = p.id
    LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
    WHERE g.id = ?
");
$stmt->execute([$grupo_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Grupo no encontrado.");
}

$cursoCodigoTitulo = strtoupper(htmlspecialchars($data['curso_codigo'] ?? '')) . ' - ' . mb_strtoupper(htmlspecialchars($data['curso_titulo'] ?? ''), 'UTF-8');
$expediente = strtoupper(htmlspecialchars($data['codigo_expediente'] ?? ''));
$accion = htmlspecialchars($data['num_accion'] ?? '');
$grupo = htmlspecialchars($data['numero_grupo'] ?? '');
$duracion = floatval($data['duracion'] ?? 0);

$fechaInicio = $data['fecha_inicio'] ? date('d/m/Y', strtotime($data['fecha_inicio'])) : '';
$fechaFin = $data['fecha_fin'] ? date('d/m/Y', strtotime($data['fecha_fin'])) : '';
$fechas = $fechaInicio . ' - ' . $fechaFin;

// Fetch units
$stmt = $pdo->prepare("SELECT * FROM unidades_didacticas WHERE accion_id = ? ORDER BY numero_unidad ASC");
$stmt->execute([$data['accion_id']]);
$unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate dates for units
$unit_rows = '';
if (count($unidades) > 0 && $data['fecha_inicio'] && $data['fecha_fin'] && $duracion > 0) {
    $start_ts = strtotime($data['fecha_inicio']);
    $end_ts = strtotime($data['fecha_fin']);
    $total_seconds = $end_ts - $start_ts;
    
    $current_start = $start_ts;
    foreach ($unidades as $index => $u) {
        $proportion = floatval($u['horas']) / $duracion;
        $unit_seconds = round($total_seconds * $proportion);
        
        $u_start = $current_start;
        // End is start + duration. If it's the last unit, just use the end date to avoid rounding errors.
        if ($index == count($unidades) - 1) {
            $u_end = $end_ts;
        } else {
            $u_end = $u_start + $unit_seconds;
        }
        
        $unit_rows .= "<tr>";
        $unit_rows .= "<td class='sub-td'>" . htmlspecialchars($u['numero_unidad']) . ". " . htmlspecialchars($u['titulo']) . "</td>";
        $unit_rows .= "<td class='center-col'>" . htmlspecialchars($u['horas']) . "</td>";
        $unit_rows .= "<td class='center-col'>" . date('d/m/Y', $u_start) . " al " . date('d/m/Y', $u_end) . "</td>";
        $unit_rows .= "</tr>";
        
        // Next unit starts the day after (or keeping it contiguous, let's just add 1 day to the end ts)
        // Wait, the screenshot says "02/07 to 15/07" and "16/07 to 29/07"
        $current_start = $u_end + 86400; // add one day for the next start
    }
} else {
    $unit_rows = "<tr><td colspan='3' style='text-align:center;'>No hay unidades definidas para esta Acción Formativa o faltan fechas/horas.</td></tr>";
}

// Send headers for Word document
header("Content-Type: application/vnd.ms-word; charset=UTF-8");
header("Content-Disposition: attachment;Filename=Planificacion_didactica.doc");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

echo "<html>";
echo "<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
echo "<style>
    body { font-family: 'Arial', sans-serif; font-size: 11pt; color: #000; }
    h1 { text-align: center; font-size: 16pt; font-weight: bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
    h2 { text-align: center; font-size: 12pt; font-weight: bold; margin-top: 30px; margin-bottom: 15px; }
    table.header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    table.header-table td { padding: 8px 0; font-size: 10pt; vertical-align: top; }
    .label { font-weight: bold; text-transform: uppercase; }
    
    table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data-table th, table.data-table td { border: 1px solid #000; padding: 10px; font-size: 10pt; vertical-align: middle; }
    table.data-table th { background-color: #d9d9d9; font-weight: bold; text-align: center; }
    
    .center-col { text-align: center; }
    .sub-td { text-align: left; }
    
</style>";
echo "</head>";
echo "<body>";

echo "<h1>Planificación didáctica</h1>";

echo "<table class='header-table'>";
echo "<tr><td colspan='3'><span class='label'>ESPECIALIDAD FORMATIVA:</span> " . $cursoCodigoTitulo . "</td></tr>";
echo "<tr>";
echo "<td><span class='label'>EXPEDIENTE:</span> " . $expediente . "</td>";
echo "<td><span class='label'>ACCIÓN:</span> " . $accion . "</td>";
echo "<td><span class='label'>GRUPO:</span> " . $grupo . "</td>";
echo "</tr>";
echo "<tr><td colspan='3'>&nbsp;</td></tr>"; // Spacer
echo "<tr>";
echo "<td colspan='2'><span class='label'>DURACIÓN DE LA ESPECIALIDAD FORMATIVA:</span> " . $duracion . " h</td>";
echo "<td><span class='label'>FECHAS DE IMPARTICIÓN:</span> " . $fechas . "</td>";
echo "</tr>";
echo "<tr>";
echo "<td colspan='2'><span class='label'>CENTRO DE FORMACIÓN:</span> MARSDIGITAL S. L.</td>";
echo "<td><span class='label'>DIRECCIÓN:</span> C/BENJAMIN FRANKLIN, 1</td>";
echo "</tr>";
echo "<tr>";
echo "<td colspan='2'><span class='label'>LOCALIDAD:</span> ARMILLA</td>";
echo "<td><span class='label'>PROVINCIA:</span> GRANADA</td>";
echo "</tr>";
echo "</table>";

echo "<h2>PLANIFICACIÓN DIDÁCTICA DEL CURSO COMPLETO</h2>";

echo "<table class='data-table'>";
echo "<thead>";
echo "<tr>";
echo "<th style='width: 50%;'>UNIDADES DIDÁCTICAS</th>";
echo "<th style='width: 15%;'>HORAS</th>";
echo "<th style='width: 35%;'>FECHAS</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

echo $unit_rows;

// Footer row
echo "<tr>";
echo "<td style='text-align: right; font-weight: bold;'>TOTAL</td>";
echo "<td class='center-col' style='font-weight: bold;'>" . $duracion . " h</td>";
echo "<td class='center-col' style='font-weight: bold;'>" . ($fechas ? $fechaInicio . " al " . $fechaFin : "") . "</td>";
echo "</tr>";

echo "</tbody>";
echo "</table>";

echo "</body></html>";
