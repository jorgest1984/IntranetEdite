<?php
// exportar_encuestas_excel.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    die("Acceso denegado.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("ID de acción formativa no proporcionado.");
}

// Fetch Action Title
$stmtAct = $pdo->prepare("SELECT titulo FROM acciones_formativas WHERE id = ?");
$stmtAct->execute([$id]);
$accion = $stmtAct->fetch();
if (!$accion) {
    die("Acción formativa no encontrada.");
}

// Fetch Surveys of this Action
try {
    $stmt = $pdo->prepare("
        SELECT er.*, m.id as matricula_id, af.num_accion, af.titulo as curso_nombre, af.modalidad,
               g.numero_grupo, co.codigo_expediente, a.moodle_user_id, a.id as alumno_id
        FROM encuestas_resultados er
        JOIN matriculas m ON er.matricula_id = m.id
        JOIN alumnos a ON m.alumno_id = a.id
        JOIN grupos g ON m.grupo_id = g.id
        JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN planes pl ON af.plan_id = pl.id
        LEFT JOIN convocatorias co ON pl.convocatoria_id = co.id
        WHERE af.id = ?
        ORDER BY er.fecha_realizacion ASC
    ");
    $stmt->execute([$id]);
    $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error al consultar las encuestas: " . $e->getMessage());
}

// Generar descarga de archivo CSV
$filename = "Encuestas_" . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $accion['titulo']) . "_" . date('Ymd') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '";');

// UTF-8 BOM para que Excel lo abra correctamente con acentos
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Cabeceras exactas del excel solicitado más las valoraciones
fputcsv($output, [
    'Código',
    'Expediente',
    'Nº Acción',
    'Nº Grupo',
    'Curso',
    'codins',
    'Modalidad',
    'Edad',
    'Sexo',
    'Titulación',
    'Otra',
    '1.1 Org', '1.2 Alumnos',
    '2.1 Cont', '2.2 TeoPrac',
    '3.1 Dur', '3.2 Horar',
    '4.1.F Form', '4.2.F FormD', '4.1.T Tut', '4.2.T TutD',
    '5.1 Docs', '5.2 Actua',
    '6.1 Aula', '6.2 Equip',
    '7.1 GuiaT', '7.2 Apoyo',
    '8.1 EvalP', '8.2 Acred',
    '9.1 Inser', '9.2 Habil', '9.3 Camb', '9.4 Progr', '9.5 Pers',
    '10.1 Global',
    'Comentarios'
], ';');

foreach ($surveys as $row) {
    // Modalidad char code
    $modChar = 'T';
    $modLower = strtolower($row['modalidad']);
    if (strpos($modLower, 'presencial') !== false) {
        $modChar = 'P';
    } elseif (strpos($modLower, 'mixta') !== false || strpos($modLower, 'blended') !== false) {
        $modChar = 'M';
    }

    // Sexo code (1 = Varón/Hombre, 2 = Mujer)
    $sexCode = ($row['sexo'] == 'Mujer') ? '2' : '1';

    // codins is Moodle user ID if present, fallback to student ID
    $codins = !empty($row['moodle_user_id']) ? $row['moodle_user_id'] : $row['alumno_id'];

    fputcsv($output, [
        $row['matricula_id'],
        $row['codigo_expediente'] ?? '---',
        $row['num_accion'] ?? '0',
        $row['numero_grupo'] ?? '1',
        $row['curso_nombre'],
        $codins,
        $modChar,
        $row['edad'],
        $sexCode,
        $row['titulacion'],
        $row['otra_titulacion'],
        
        $row['p1_1'], $row['p1_2'],
        $row['p2_1'], $row['p2_2'],
        $row['p3_1'], $row['p3_2'],
        $row['p4_1_f'], $row['p4_2_f'], $row['p4_1_t'], $row['p4_2_t'],
        $row['p5_1'], $row['p5_2'],
        $row['p6_1'], $row['p6_2'],
        $row['p7_1'], $row['p7_2'],
        $row['p8_1'], $row['p8_2'],
        $row['p9_1'], $row['p9_2'], $row['p9_3'], $row['p9_4'], $row['p9_5'],
        $row['p10_1'],
        $row['comentarios']
    ], ';');
}

fclose($output);
exit();
