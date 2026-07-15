<?php
// word_planificacion_evaluacion.php
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
try {
    $stmt = $pdo->prepare("
        SELECT 
            g.numero_grupo, g.fecha_inicio, g.fecha_fin,
            af.id as accion_id, af.num_accion, 
            conv.codigo_expediente, 
            af.abreviatura as curso_codigo, af.titulo as curso_titulo, af.duracion
        FROM grupos g
        JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN planes p ON af.plan_id = p.id
        LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
        WHERE g.id = ?
    ");
    $stmt->execute([$grupo_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database Error in word_planificacion_evaluacion.php: " . $e->getMessage());
}

if (!$data) {
    die("Grupo no encontrado.");
}

$cursoCodigoTitulo = strtoupper(htmlspecialchars($data['curso_codigo'] ?? '')) . ' - ' . mb_strtoupper(htmlspecialchars($data['curso_titulo'] ?? ''), 'UTF-8');
$expediente = strtoupper(htmlspecialchars($data['codigo_expediente'] ?? ''));
$accion = htmlspecialchars($data['num_accion'] ?? '');
$grupo = htmlspecialchars($data['numero_grupo'] ?? '');
$duracion = floatval($data['duracion'] ?? 0);

$fechaInicioObj = $data['fecha_inicio'] ? new DateTime($data['fecha_inicio']) : null;
$fechaFinObj = $data['fecha_fin'] ? new DateTime($data['fecha_fin']) : null;

$fechaInicio = $fechaInicioObj ? $fechaInicioObj->format('d/m/Y') : '';
$fechaFin = $fechaFinObj ? $fechaFinObj->format('d/m/Y') : '';
$fechas = $fechaInicio . ' - ' . $fechaFin;

// Calculate mid date
$fechaIntermedia = '';
if ($fechaInicioObj && $fechaFinObj) {
    $diff = $fechaInicioObj->diff($fechaFinObj)->days;
    $mid = clone $fechaInicioObj;
    $mid->modify('+' . floor($diff / 2) . ' days');
    $fechaIntermedia = $mid->format('d/m/Y');
}

// Fetch units to know how many
try {
    $stmt = $pdo->prepare("SELECT numero_unidad FROM unidades_didacticas WHERE accion_id = ? ORDER BY numero_unidad ASC");
    $stmt->execute([$data['accion_id']]);
    $unidades = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $unidades = [];
}

$totalUnidades = count($unidades);
$mitadUnidades = $totalUnidades > 0 ? ceil($totalUnidades / 2) : 1;

// Send headers for Word document
header("Content-Type: application/vnd.ms-word; charset=UTF-8");
header("Content-Disposition: attachment;Filename=Planificacion_evaluacion.doc");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

echo "<html>";
echo "<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
echo "<style>
    body { font-family: 'Arial', sans-serif; font-size: 10pt; color: #000; }
    h1 { text-align: center; font-size: 14pt; font-weight: bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
    table.header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    table.header-table td { padding: 5px 0; font-size: 10pt; vertical-align: top; }
    .label { font-weight: bold; text-transform: uppercase; }
    
    h2 { text-align: center; font-size: 12pt; font-weight: bold; margin-top: 30px; margin-bottom: 15px; }
    
    table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data-table th, table.data-table td { border: 1px solid #000; padding: 8px; font-size: 9pt; vertical-align: top; }
    
    .bg-dark { background-color: #a6a6a6; font-weight: bold; text-align: center; }
    .bg-light { background-color: #d9d9d9; font-weight: bold; text-align: center; }
    .center-col { text-align: center; }
    
    ul { margin: 5px 0 5px 20px; padding: 0; }
</style>";
echo "</head>";
echo "<body>";

echo "<h1>Planificación de la evaluación de aprendizaje</h1>";

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

echo "<h2>PLANIFICACIÓN DE LA EVALUACIÓN DEL APRENDIZAJE</h2>";

echo "<table class='data-table'>";
echo "<tr>";
echo "<th colspan='2' class='bg-dark'>DURANTE EL PROCESO DE APRENDIZAJE</th>";
echo "<th colspan='2' class='bg-dark'>Realización de la evaluación</th>";
echo "</tr>";
echo "<tr>";
echo "<th colspan='2' class='bg-light'>ACTIVIDADES DE APRENDIZAJE</th>";
echo "<th class='bg-light' style='width:15%;'>Espacios</th>";
echo "<th class='bg-light' style='width:25%;'>Fechas de evaluación</th>";
echo "</tr>";

echo "<tr>";
echo "<td style='width: 3%; text-align: center;'> - </td>";
echo "<td><strong>Evaluación Inicial (EV0):</strong> Cuestionario tipo test con 5 preguntas para evaluar los conocimientos previos del alumnado.</td>";
echo "<td>Aula virtual</td>";
echo "<td>Al comienzo de la acción formativa (" . $fechaInicio . ")</td>";
echo "</tr>";

echo "<tr>";
echo "<td style='text-align: center;'> - </td>";
echo "<td><strong>Caso práctico:</strong></td>";
echo "<td>Aula virtual</td>";
echo "<td>" . $fechaInicio . "</td>";
echo "</tr>";

echo "<tr>";
echo "<th colspan='2' class='bg-dark'>PRUEBA DE EVALUACIÓN FINAL (EF) DE LA UNIDAD FORMATIVA</th>";
echo "<th class='bg-dark'>Espacios</th>";
echo "<th class='bg-dark'>Fecha de evaluación</th>";
echo "</tr>";

echo "<tr>";
echo "<td colspan='2'>";
echo "Se establecen <strong>dos evaluaciones</strong> que permitirán comprobar el grado de aprovechamiento del curso por parte del alumno, siendo <strong>requisito imprescindible</strong> para la finalización del mismo:<br><br>";
echo "<ul>";
echo "<li><strong>Evaluación Intermedia (EV1):</strong> Correspondiente a las unidades 1 a " . $mitadUnidades . ".</li>";
if ($totalUnidades > $mitadUnidades) {
    echo "<li><strong>Evaluación Final (EV2):</strong> Correspondiente a las unidades " . ($mitadUnidades + 1) . " a " . $totalUnidades . ".</li>";
}
echo "</ul>";
echo "</td>";
echo "<td><br><br>Aula virtual</td>";
echo "<td>";
echo "<strong>Ev. Intermedia:</strong><br>";
echo "50% del curso.<br>";
echo "Fecha programada: " . $fechaIntermedia . "<br><br>";
echo "<strong>Ev. Final:</strong><br>";
echo "Final del curso.<br>";
echo "Fecha programada: " . $fechaFin;
echo "</td>";
echo "</tr>";

echo "</table>";

echo "</body></html>";
