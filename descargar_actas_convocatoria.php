<?php
// descargar_actas_convocatoria.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    die("Acceso denegado.");
}

$convocatoria_id = $_GET['id'] ?? null;
if (!$convocatoria_id) {
    die("ID de convocatoria no proporcionado.");
}

// Obtener datos de la convocatoria
$stmt = $pdo->prepare("SELECT codigo_expediente, nombre FROM convocatorias WHERE id = ?");
$stmt->execute([$convocatoria_id]);
$convocatoria = $stmt->fetch();

if (!$convocatoria) {
    die("Convocatoria no encontrada.");
}

// Crear un archivo temporal para el ZIP
$zipFile = tempnam(sys_get_temp_dir(), 'actas_');
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("No se pudo crear el archivo ZIP.");
}

// Obtener todos los grupos y alumnos de esta convocatoria
$sql = "
    SELECT 
        af.num_accion as af_codigo,
        af.titulo as af_nombre,
        g.numero_grupo as grupo_nombre,
        g.id as grupo_id,
        a.dni,
        a.nombre as alumno_nombre,
        a.apellidos as alumno_apellidos,
        m.estado as matricula_estado
    FROM convocatorias c
    JOIN planes p ON p.convocatoria_id = c.id
    JOIN acciones_formativas af ON af.plan_id = p.id
    JOIN grupos g ON g.accion_id = af.id
    JOIN matriculas m ON m.grupo_id = g.id
    JOIN alumnos a ON m.alumno_id = a.id
    WHERE c.id = ?
    ORDER BY p.id, af.id, g.id, a.apellidos, a.nombre
";

$stmtAlumnos = $pdo->prepare($sql);
$stmtAlumnos->execute([$convocatoria_id]);
$filas = $stmtAlumnos->fetchAll();

// Agrupar por grupo_id
$grupos = [];
foreach ($filas as $fila) {
    $grupo_id = $fila['grupo_id'];
    if (!isset($grupos[$grupo_id])) {
        $grupos[$grupo_id] = [
            'af_codigo' => $fila['af_codigo'],
            'af_nombre' => $fila['af_nombre'],
            'grupo_nombre' => $fila['grupo_nombre'],
            'alumnos' => []
        ];
    }
    $grupos[$grupo_id]['alumnos'][] = $fila;
}

// Si no hay grupos, crear un txt indicándolo
if (empty($grupos)) {
    $zip->addFromString('info.txt', "No hay grupos ni alumnos matriculados en esta convocatoria.");
} else {
    // Generar un CSV (acta) por cada grupo
    foreach ($grupos as $grupo_id => $grupoData) {
        $csvContent = "\xEF\xBB\xBF"; // BOM para UTF-8 en Excel
        
        // Cabecera del acta
        $csvContent .= "ACTA DE EVALUACIÓN\n";
        $csvContent .= "Acción Formativa:;" . str_replace(';', ',', $grupoData['af_codigo'] . ' - ' . $grupoData['af_nombre']) . "\n";
        $csvContent .= "Grupo:;" . str_replace(';', ',', $grupoData['grupo_nombre']) . "\n";
        $csvContent .= "\n";
        
        // Columnas de alumnos
        $csvContent .= "DNI/NIE;Apellidos;Nombre;Estado Matricula;Nota Teórica;Nota Práctica;Nota Final;Evaluación (Apto/No Apto)\n";
        
        foreach ($grupoData['alumnos'] as $alumno) {
            $csvContent .= sprintf(
                "%s;%s;%s;%s;;;;\n",
                $alumno['dni'],
                $alumno['alumno_apellidos'],
                $alumno['alumno_nombre'],
                $alumno['matricula_estado']
            );
        }
        
        // Nombre del archivo dentro del zip
        // Limpiamos el nombre para que no tenga caracteres raros
        $fileName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $grupoData['af_codigo'] . '_' . $grupoData['grupo_nombre']) . '.csv';
        
        $zip->addFromString($fileName, $csvContent);
    }
}

$zip->close();

$codigoLimpio = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $convocatoria['codigo_expediente']);
$zipName = "Actas_Evaluacion_" . $codigoLimpio . ".zip";

header('Content-Type: application/zip');
header('Content-disposition: attachment; filename=' . $zipName);
header('Content-Length: ' . filesize($zipFile));
readfile($zipFile);

// Eliminar el archivo temporal
unlink($zipFile);
exit();
