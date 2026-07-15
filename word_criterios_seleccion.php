<?php
// word_criterios_seleccion.php
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
    die("Database Error in word_criterios_seleccion.php: " . $e->getMessage());
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
header("Content-Disposition: attachment;Filename=Criterios_seleccion.doc");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

echo "<html>";
echo "<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
echo "<style>
    body { font-family: 'Arial', sans-serif; font-size: 11pt; color: #000; }
    h1 { text-align: left; font-size: 14pt; font-weight: bold; margin-top: 50px; margin-bottom: 40px; color: #555555; text-transform: uppercase; }
    table.info-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
    table.info-table td { padding: 8px 0; font-size: 11pt; vertical-align: top; }
    .label { font-weight: bold; }
    .detalle-box { margin-top: 50px; }
    .detalle-box h2 { font-size: 12pt; font-weight: bold; margin-bottom: 20px; }
    .detalle-text { font-size: 10.5pt; line-height: 1.8; text-align: justify; }
    .detalle-text p { margin-bottom: 30px; }
</style>";
echo "</head>";
echo "<body>";

// CABECERA LOGOS
echo "<table style='width: 100%; border: none; margin-bottom: 30px;' cellpadding='0' cellspacing='0'>";
echo "<tr>";
echo "<td style='text-align: left; vertical-align: middle; border: none; width: 33%;'>";
echo "<img src='https://gestion.grupoefp.es/img/logo_efp.png' width='220' height='45' alt='EFP'>";
echo "</td>";
echo "<td style='text-align: right; vertical-align: middle; border: none; width: 33%;'>";
echo "<img src='https://gestion.grupoefp.es/img/logo_fundae.png' width='160' height='40' alt='Fundae'>";
echo "</td>";
echo "<td style='text-align: right; vertical-align: middle; border: none; width: 33%;'>";
echo "<img src='https://gestion.grupoefp.es/img/logo_ministerio.png' width='220' height='55' alt='Ministerio SEPE'>";
echo "</td>";
echo "</tr>";
echo "</table>";

echo "<h1>CRITERIOS Y PROCESOS SEGUIDOS PARA LA SELECCIÓN DE PARTICIPANTES EN FUNCION DE REQUISITOS DE FORMACIÓN</h1>";

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
echo "<div class='detalle-text'>";

echo "<p>La selección ha atendido en primer lugar al cumplimiento de los requisitos de participación previstos en el art. 7 de la Resolución de la Dirección General del Servicio Público de Empleo Estatal, por la que se aprueba la convocatoria para la concesión de subvenciones públicas para la ejecución de programas de formación de ámbito estatal, dirigidos prioritariamente a las personas ocupadas.</p>";

echo "<p>Para esta acción formativa no es necesario que los participantes dispongan de unos requisitos específicos de formación, por lo que la selección ha atendido a las prioridades del plan de formación y a criterios de igualdad, equidad y de objetividad y a los colectivos previstos en el apartado 1 del artículo 7 de la convocatoria.<br>";
echo "Las personas que han solicitado esta acción formativa han sido seleccionadas atendiendo a que cumplan los requisitos mínimos y dando prioridad a los solicitantes que pertenezcan a alguno de los colectivos prioritarios, atendiendo al orden de llegada de su solicitud.</p>";

echo "</div>";
echo "</div>";

echo "</body></html>";
