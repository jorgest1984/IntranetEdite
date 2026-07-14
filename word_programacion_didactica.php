<?php
// word_programacion_didactica.php
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
        af.num_accion, af.objetivos_especificos, af.contenidos,
        conv.codigo_expediente, 
        cu.nombre_corto as curso_codigo, cu.nombre_largo as curso_titulo, cu.duracion, cu.objetivos
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
$duracion = htmlspecialchars($data['duracion'] ?? '');

$fechaInicio = $data['fecha_inicio'] ? date('d/m/Y', strtotime($data['fecha_inicio'])) : '';
$fechaFin = $data['fecha_fin'] ? date('d/m/Y', strtotime($data['fecha_fin'])) : '';
$fechas = $fechaInicio . ' - ' . $fechaFin;

$objetivos = nl2br(htmlspecialchars($data['objetivos'] ?? ''));

// Send headers for Word document
header("Content-Type: application/vnd.ms-word; charset=UTF-8");
header("Content-Disposition: attachment;Filename=Programacion_didactica.doc");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

echo "<html>";
echo "<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
echo "<style>
    body { font-family: 'Arial', sans-serif; font-size: 11pt; color: #000; }
    h1 { text-align: center; font-size: 16pt; font-weight: bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
    table.header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    table.header-table td { padding: 8px 0; font-size: 10pt; vertical-align: top; }
    .label { font-weight: bold; text-transform: uppercase; }
    
    .obj-text { font-size: 10pt; text-align: justify; margin-bottom: 30px; }
    
    table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data-table th, table.data-table td { border: 1px solid #000; padding: 8px; font-size: 9pt; vertical-align: top; }
    table.data-table th { background-color: #e5e5e5; font-weight: bold; text-align: center; }
    
    .temas-col { width: 15%; }
    .tit-col { width: 65%; font-weight: bold; text-align: center; }
    .horas-lbl-col { width: 10%; text-align: center; background-color: #e5e5e5; font-weight: bold; }
    .horas-val-col { width: 10%; text-align: center; }

    .sub-th { font-weight: bold; text-align: center; background-color: #e5e5e5; }
    .sub-td { text-align: left; }
    ul { margin: 0; padding-left: 20px; }
    li { margin-bottom: 5px; }
</style>";
echo "</head>";
echo "<body>";

echo "<h1>Programación didáctica</h1>";

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

echo "<div class='obj-text'><strong>Objetivo general:</strong> " . $objetivos . "</div>";

// Main Table
echo "<table class='data-table'>";
// Header Row 1
echo "<tr>";
echo "<td class='temas-col sub-th'>Temas</td>";
echo "<td class='tit-col' colspan='2'>" . $cursoCodigoTitulo . "</td>";
echo "<td class='horas-lbl-col'>Horas</td>";
echo "<td class='horas-val-col'>" . $duracion . "</td>";
echo "</tr>";

// Header Row 2
echo "<tr>";
echo "<td class='sub-th' style='width: 25%;'>";
echo "<strong>Objetivos específicos</strong><br><br>";
echo "Logro de los resultados de aprendizaje expresados en las capacidades y criterios de evaluación";
echo "</td>";
echo "<td class='sub-th' style='width: 25%;'>Contenidos</td>";
echo "<td class='sub-th' style='width: 25%;'>Estrategias metodológicas, actividades de aprendizaje y recursos didácticos</td>";
echo "<td class='sub-th' style='width: 25%;' colspan='2'>Espacios, instalaciones y equipamiento</td>";
echo "</tr>";

// Data Row Placeholder
echo "<tr>";
echo "<td>";
echo nl2br($data['objetivos_especificos'] ?? '');
echo "</td>";

echo "<td>";
echo $data['contenidos'] ?? '';
echo "</td>";

echo "<td>";
echo "<ul>";
echo "<li>El estudio personalizado de los contenidos del curso (SCORM) a través de la plataforma, de manera secuencial</li>";
echo "<li>Ejercicios de autoevaluación insertos en cada una de las unidades interactivas (SCORM)</li>";
echo "<li>Recursos didácticos complementarios de consulta: enlaces web, videoteca, bibliografía, wiki y glosario.</li>";
echo "<li>Desarrollo de actividades de aprendizaje mediante caso/s práctico/s a lo largo del curso.</li>";
echo "</ul>";
echo "<strong>Convocatoria</strong><br>";
echo "Elementos de comunicación:<br>";
echo "<ul><li>Foros</li><li>Tablón de anuncios</li><li>Correo electrónico</li><li>Chats online</li></ul>";
echo "</td>";

echo "<td colspan='2'>";
echo "Esta especialidad formativa de modalidad teleformación se desarrolla a través de un Entorno Virtual de Aprendizaje (Aula virtual) que se encuentra alojado en el siguiente dominio:<br><br>";
echo "Su funcionamiento:<br>24 horas al día, los 7 días de la semana<br><br>";
echo "Dentro del Aula virtual encontrará el siguiente equipamiento:<br>";
echo "<ul>";
echo "<li>El contenido de la formación que se va a impartir se encuentra en formato SCORM.</li>";
echo "<li>Documentación para el alumno en formato electrónico:<br>";
echo "  <ul>";
echo "  <li>Hoja de bienvenida</li>";
echo "  <li>Guía Didáctica del alumno</li>";
echo "  <li>Guía de usuario del aula virtual</li>";
echo "  <li>Recibí material</li>";
echo "  </ul>";
echo "</li>";
echo "</ul>";
echo "</td>";

echo "</tr>";
echo "</table>";

echo "</body></html>";
