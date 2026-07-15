<?php
// word_justificante_comunicacion.php
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
            af.id as accion_id, af.num_accion, af.modalidad,
            conv.codigo_expediente, 
            af.abreviatura as curso_codigo, af.titulo as curso_titulo
        FROM grupos g
        JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN planes p ON af.plan_id = p.id
        LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
        WHERE g.id = ?
    ");
    $stmt->execute([$grupo_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database Error in word_justificante_comunicacion.php: " . $e->getMessage());
}

if (!$data) {
    die("Grupo no encontrado.");
}

$cursoCodigoTitulo = strtoupper(htmlspecialchars($data['curso_codigo'] ?? '')) . ' - ' . mb_strtoupper(htmlspecialchars($data['curso_titulo'] ?? ''), 'UTF-8');
$expediente = strtoupper(htmlspecialchars($data['codigo_expediente'] ?? ''));
$accion = htmlspecialchars($data['num_accion'] ?? '');
$grupo = htmlspecialchars($data['numero_grupo'] ?? '');
$modalidad = htmlspecialchars($data['modalidad'] ?? 'Teleformación');

// Send headers for Word document
header("Content-Type: application/vnd.ms-word; charset=UTF-8");
header("Content-Disposition: attachment;Filename=Justificante_comunicacion.doc");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

echo "<html>";
echo "<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
echo "<style>
    body { font-family: 'Arial', sans-serif; font-size: 11pt; color: #000; }
    h1 { text-align: left; font-size: 14pt; font-weight: bold; margin-top: 50px; margin-bottom: 40px; color: #555555; }
    table.info-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
    table.info-table td { padding: 8px 0; font-size: 11pt; vertical-align: top; }
    .label { font-weight: bold; }
    .detalle-box { margin-top: 50px; }
    .detalle-box h2 { font-size: 12pt; font-weight: bold; margin-bottom: 20px; }
    .detalle-text { font-size: 11pt; }
</style>";
echo "</head>";
echo "<body>";

echo "<h1>JUSTIFICANTE DE COMUNICACIÓN CON LA ADMINISTRACIÓN SOBRE LA PRESELECCIÓN DE ALUMNOS DESEMPLEADOS PARA LA ACCIÓN</h1>";

echo "<table class='info-table'>";
echo "<tr>";
echo "<td><span class='label'>Solicitante:</span> Marsdigital, S.L.</td>";
echo "<td><span class='label'>CIF:</span> B18579953</td>";
echo "</tr>";
echo "<tr>";
echo "<td><span class='label'>Expediente:</span> " . $expediente . "</td>";
echo "<td><span class='label'>Nº Acción:</span> " . $accion . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class='label'>Nº de Grupo:</span> " . $grupo . "</td>";
echo "</tr>";
echo "<tr>";
echo "<td colspan='2'><span class='label'>Denominación:</span> " . $cursoCodigoTitulo . "</td>";
echo "</tr>";
echo "<tr>";
echo "<td colspan='2'><span class='label'>Modalidad:</span> " . $modalidad . "</td>";
echo "</tr>";
echo "</table>";

echo "<div class='detalle-box'>";
echo "<h2>DETALLE:</h2>";
echo "<p class='detalle-text'>En este grupo no se ha contemplado la participación de alumnos desempleados.</p>";
echo "</div>";

echo "</body></html>";
