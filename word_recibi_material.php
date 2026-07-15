<?php
// word_recibi_material.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_ADMINISTRATIVO])) {
    header("Location: dashboard.php");
    exit();
}

$accion_id = isset($_GET['accion_id']) ? intval($_GET['accion_id']) : 0;
$alumno_id = isset($_GET['alumno_id']) ? intval($_GET['alumno_id']) : 0;

if (!$accion_id) {
    die("Se requiere el ID de la acción formativa.");
}

// Fetch all students for this accion_id (or just one)
$query = "
    SELECT 
        a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni,
        g.numero_grupo, g.fecha_inicio, g.fecha_fin,
        af.num_accion, af.modalidad, af.abreviatura as curso_codigo, af.titulo as curso_titulo,
        conv.codigo_expediente
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
    JOIN grupos g ON m.grupo_id = g.id
    JOIN acciones_formativas af ON g.accion_id = af.id
    LEFT JOIN planes p ON af.plan_id = p.id
    LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
    WHERE g.accion_id = ? AND m.estado != 'Baja' AND m.estado != 'Cancelada'
";
$params = [$accion_id];

if ($alumno_id > 0) {
    $query .= " AND a.id = ?";
    $params[] = $alumno_id;
}

$query .= " ORDER BY a.primer_apellido, a.segundo_apellido, a.nombre ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error de base de datos: " . $e->getMessage());
}

if (empty($alumnos)) {
    die("No se encontraron alumnos para generar el documento.");
}

// Send headers for Word document
header("Content-Type: application/vnd.ms-word; charset=UTF-8");
$filename = $alumno_id > 0 ? "Recibi_Material_" . $alumnos[0]['dni'] . ".doc" : "Recibos_Material.doc";
header("Content-Disposition: attachment;Filename=$filename");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

echo "<html>";
echo "<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
echo "<style>
    body { font-family: 'Arial', sans-serif; font-size: 10pt; color: #000; }
    h1 { text-align: center; font-size: 14pt; font-weight: normal; margin-top: 40px; margin-bottom: 30px; }
    table.info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    table.info-table td { padding: 5px 0; font-size: 10pt; vertical-align: top; }
    .label { font-weight: bold; }
    .cert-text { margin-top: 20px; text-align: justify; line-height: 1.4; }
    .cert-text p { margin-bottom: 15px; }
    ul { margin-top: 5px; margin-bottom: 15px; padding-left: 20px; }
    li { margin-bottom: 5px; }
    .signature-area { margin-top: 40px; text-align: right; }
    .page-break { page-break-after: always; }
</style>";
echo "</head>";
echo "<body>";

$total = count($alumnos);
foreach ($alumnos as $index => $data) {
    
    $cursoCodigoTitulo = strtoupper(htmlspecialchars($data['curso_codigo'] ?? '')) . ' - ' . mb_strtoupper(htmlspecialchars($data['curso_titulo'] ?? ''), 'UTF-8');
    $expediente = strtoupper(htmlspecialchars($data['codigo_expediente'] ?? ''));
    $accion = htmlspecialchars($data['num_accion'] ?? '');
    $grupo = htmlspecialchars($data['numero_grupo'] ?? '');
    $modalidad = htmlspecialchars($data['modalidad'] ?? 'Teleformación');

    $fechaInicioObj = $data['fecha_inicio'] ? new DateTime($data['fecha_inicio']) : null;
    $fechaFinObj = $data['fecha_fin'] ? new DateTime($data['fecha_fin']) : null;
    $fechaInicio = $fechaInicioObj ? $fechaInicioObj->format('d/m/Y') : '';
    $fechaFin = $fechaFinObj ? $fechaFinObj->format('d/m/Y') : '';
    
    $nombreAlumno = htmlspecialchars(trim($data['nombre'] . ' ' . $data['primer_apellido'] . ' ' . $data['segundo_apellido']));
    $dniAlumno = htmlspecialchars($data['dni'] ?? '');
    
    $fechaActual = date('d/m/Y');
    
    // CABECERA LOGOS
    echo "<table style='width: 100%; border: none; margin-bottom: 20px;' cellpadding='0' cellspacing='0'>";
    echo "<tr>";
    echo "<td style='text-align: left; vertical-align: middle; border: none; width: 33%;'>";
    echo "<img src='https://gestion.grupoefp.es/img/logo_efp.png' width='200' height='40' alt='EFP'>";
    echo "</td>";
    echo "<td style='text-align: right; vertical-align: middle; border: none; width: 33%;'>";
    echo "<img src='https://gestion.grupoefp.es/img/logo_fundae.png' width='150' height='35' alt='Fundae'>";
    echo "</td>";
    echo "<td style='text-align: right; vertical-align: middle; border: none; width: 33%;'>";
    echo "<img src='https://gestion.grupoefp.es/img/logo_ministerio.png' width='200' height='50' alt='Ministerio SEPE'>";
    echo "</td>";
    echo "</tr>";
    echo "</table>";

    echo "<h1>RECIBÍ MATERIAL</h1>";

    echo "<table class='info-table'>";
    echo "<tr>";
    echo "<td colspan='3'><span class='label'>Denominación Acción Formativa:</span> " . $cursoCodigoTitulo . "</td>";
    echo "</tr>";
    echo "<tr>";
    echo "<td><span class='label'>Nº Expediente:</span> " . $expediente . "</td>";
    echo "<td><span class='label'>Nº AAFF:</span> " . $accion . "</td>";
    echo "<td><span class='label'>Grupo:</span> " . $grupo . "</td>";
    echo "</tr>";
    echo "<tr>";
    echo "<td><span class='label'>Modalidad:</span> " . $modalidad . "</td>";
    echo "<td><span class='label'>Fecha inicio:</span> " . $fechaInicio . "</td>";
    echo "<td><span class='label'>Fecha fin:</span> " . $fechaFin . "</td>";
    echo "</tr>";
    echo "</table>";

    echo "<div class='cert-text'>";
    echo "<p>D. <strong>" . $nombreAlumno . "</strong> con DNI nº <strong>" . $dniAlumno . "</strong> como participante de la acción formativa arriba indicada,</p>";
    
    echo "<p><span class='label'>CERTIFICO:</span></p>";
    
    echo "<p>Que he RECIBIDO el siguiente material didáctico correspondiente a la acción formativa indicada:</p>";
    echo "<ul>";
    echo "<li>Hoja de bienvenida al curso en formato electrónico, con la dirección Web de la plataforma de teleformación y claves para el acceso al curso.</li>";
    echo "<li>La siguiente documentación en formato electrónico disponible en la plataforma de teleformación:";
    echo "<ul style='list-style-type: circle;'>";
    echo "<li>Guía didáctica del alumno</li>";
    echo "<li>Guía de usuario del aula virtual</li>";
    echo "<li>Hoja informativa del SEPE sobre la situación de la demanda de empleo (sólo desempleados)</li>";
    echo "<li>Calendario</li>";
    echo "<li>Contenido del curso</li>";
    echo "<li>Evaluación de contenidos</li>";
    echo "<li>Cuestionario de evaluación de calidad</li>";
    echo "</ul></li>";
    echo "</ul>";

    echo "<p>Que he recibido la información correspondiente a la Financiación de la Acción Formativa y soy consciente de que la acción formativa en la que participo corresponde a la convocatoria para la concesión de subvenciones públicas para la ejecución de programas de formación en el ámbito estatal, dirigidos prioritariamente a las personas ocupadas, regulada por resolución del director general del Servicio Público de Empleo Estatal de 6 de agosto de 2024, no tiene coste ni para los trabajadores ni para las empresas donde trabajan.</p>";
    
    echo "<p>Que he sido informado/a de que según el art. 6.5 de la citada convocatoria, no podré realizar más de 180 horas de formación, con este o cualquier otro centro de formación, en el marco de esta convocatoria salvo que participe en una acción formativa cuya duración sea superior. En este caso realizaré una única acción. De igual forma, podrá superarse dicho límite cuando se participe en diferentes módulos formativos correspondientes a un mismo certificado de profesionalidad.</p>";
    
    echo "<p>Que estoy interesado/a en esta acción formativa comprometiéndome a realizarla y a cumplimentar, firmar personalmente todos los documentos que se me soliciten y a remitir/entregar los originales a la entidad beneficiaria, necesario para cumplir los requisitos de la subvención y justificar el derecho a realizar esta formación.</p>";
    
    echo "<p>En Granada, a " . $fechaActual . "</p>";
    
    echo "<div class='signature-area'>";
    echo "<p style='margin-bottom: 40px;'>Fdo.:</p>";
    echo "<p>DNI nº: " . $dniAlumno . "</p>";
    echo "</div>";
    
    $datetime = date('d/m/Y H:i:s');
    echo "<p style='font-size: 8pt; margin-top: 60px;'>Documento aceptado y leído por con DNI el día $datetime mediante aceptación expresa del contenido del presente documento. El firmante se ha autenticado en la plataforma de teleformación con su usuario y contraseña personal.</p>";
    
    echo "</div>"; // end cert-text

    if ($index < $total - 1) {
        echo "<div class='page-break'></div>";
    }
}

echo "</body></html>";
