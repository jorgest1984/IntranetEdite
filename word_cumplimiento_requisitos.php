<?php
// word_cumplimiento_requisitos.php
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
    die("Database Error in word_cumplimiento_requisitos.php: " . $e->getMessage());
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
header("Content-Disposition: attachment;Filename=Cumplimiento_requisitos.doc");
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
    .detalle-text { font-size: 10.5pt; line-height: 1.5; text-align: justify; }
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

echo "<h1>CUMPLIMIENTO DE LOS REQUISITOS DE ACCESO DE LOS ALUMNOS</h1>";

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
echo "<p>Todos los participantes de esta acción formativa cumplen con los requisitos previstos en el artículo 7 de la Resolución, de la Dirección General del Servicio Público de Empleo Estatal, por la que se aprueba la convocatoria para la concesión de subvenciones públicas para la ejecución de programas de formación, de ámbito estatal, dirigidos prioritariamente a las personas ocupadas:</p>";

echo "<p>1. Son trabajadores/autónomos o desempleados y cuidadores no profesionales que atiende a personas en situación de dependencia.</p>";

echo "<p>2. Además pertenecen a algunos de los siguientes colectivos prioritarios:<br>";
echo "- Mujeres<br>";
echo "- menores de 30 años<br>";
echo "- Mayores de 45 años<br>";
echo "- Personas con discapacidad<br>";
echo "- Desempleados de larga duración. **<br>";
echo "- Trabajadores/as de baja cualificación. *<br>";
echo "- Trabajadores/as de Pyme<br>";
echo "- Trabajadores/as con contrato a tiempo parcial.<br>";
echo "- Trabajadores/as con contrato de duración determinada.<br>";
echo "- Trabajadores/as afectados por un ERTE.<br>";
echo "- Trabajadores/as afectadas por mecanismos de RED</p>";

echo "<p>*Se consideran trabajadores de baja cualificación aquellas personas que, en el momento del inicio del curso, estén incluidas en uno de los siguientes grupos de cotización: 06, 07, 09 ó 10. En el caso de personas desempleadas o trabajadores autónomos se considerarán aquellas que no estén en posesión de un carnet profesional, certificado de profesionalidad de nivel 2 ó 3, título de formación profesional o de una titulación universitaria.</p>";

echo "<p>** Se consideran desempleados de larga duración, aquellas personas que lleven inscritas como demandante de empleo en la oficina de empleo, al menos 12 meses en los 18 meses anteriores a la selección.</p>";

echo "<p>3. Los trabajadores desempleados están inscritos como demandantes de empleo en los Servicios Públicos de Empleo de las comunidades autónomas y en el Servicio Público de Empleo Estatal en Ceuta y Melilla.</p>";

echo "</div>";
echo "</div>";

echo "</body></html>";
