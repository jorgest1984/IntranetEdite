<?php
// scratch/register_adgd29_grupo2.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_db.php';
require_once __DIR__ . '/../includes/moodle_api.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO Y CREACIÓN DE GRUPO 6 PARA ADGD29 GRUPO 2 (ID: 16) ===\n\n";

// 1. Obtener Grupo 2 (ID: 16) y Grupo 1 de la Intranet
$stmtG2 = $pdo->prepare("SELECT g.*, af.titulo, af.num_accion, af.id_plataforma, c.moodle_id as curso_moodle_id
                         FROM grupos g 
                         JOIN acciones_formativas af ON g.accion_id = af.id
                         LEFT JOIN cursos c ON af.curso_id = c.id
                         WHERE g.id = 16");
$stmtG2->execute();
$g2 = $stmtG2->fetch(PDO::FETCH_ASSOC);

if (!$g2) {
    echo "ERROR: No se encontró el grupo 16 en la Intranet.\n";
    exit;
}

echo "Acción Formativa: {$g2['num_accion']} - {$g2['titulo']}\n";
echo "Grupo Intranet ID: {$g2['id']} (Número de grupo: {$g2['numero_grupo']})\n";
echo "AF id_plataforma: " . ($g2['id_plataforma'] ?? 'null') . "\n";
echo "Curso master moodle_id: " . ($g2['curso_moodle_id'] ?? 'null') . "\n";

// Resolver course_moodle_id
$moodleDb = new MoodleDB();
if (!$moodleDb->isConnected()) {
    echo "ERROR al conectar a Moodle DB: " . $moodleDb->getError() . "\n";
    exit;
}

$mpdo = $moodleDb->getPDO();
$prefix = $moodleDb->getTablePrefix();

$courseId = 0;
$candidates = array_filter([$g2['id_plataforma'], $g2['curso_moodle_id']]);
foreach ($candidates as $cand) {
    $cand = (int)$cand;
    if ($cand > 0) {
        $stmtC = $mpdo->prepare("SELECT id, fullname, shortname FROM {$prefix}course WHERE id = ?");
        $stmtC->execute([$cand]);
        $cRow = $stmtC->fetch();
        if ($cRow) {
            $courseId = (int)$cRow['id'];
            echo "\nCurso Moodle Encontrado: ID {$cRow['id']} - {$cRow['fullname']} ({$cRow['shortname']})\n";
            break;
        }
    }
}

if (!$courseId) {
    // Buscar por título en Moodle
    $stmtC = $mpdo->prepare("SELECT id, fullname, shortname FROM {$prefix}course WHERE fullname LIKE ? OR shortname LIKE ? LIMIT 5");
    $stmtC->execute(['%igualdad%', '%ADGD29%']);
    $courses = $stmtC->fetchAll();
    echo "\nCursos Moodle parecidos:\n";
    foreach ($courses as $c) {
        echo "- ID {$c['id']}: {$c['fullname']} ({$c['shortname']})\n";
        if (!$courseId) $courseId = (int)$c['id'];
    }
}

if (!$courseId) {
    echo "ERROR: No se pudo determinar el ID del curso de Moodle.\n";
    exit;
}

// 2. Inspeccionar Grupos Moodle para este curso
echo "\n=== GRUPOS MOODLE EXISTENTES EN EL CURSO {$courseId} ===\n";
$stmtGrps = $mpdo->prepare("SELECT g.id, g.name, COUNT(gm.id) as num_members 
                            FROM {$prefix}groups g 
                            LEFT JOIN {$prefix}groups_members gm ON g.id = gm.groupid 
                            WHERE g.courseid = ? 
                            GROUP BY g.id, g.name");
$stmtGrps->execute([$courseId]);
$moodleGroups = $stmtGrps->fetchAll(PDO::FETCH_ASSOC);

foreach ($moodleGroups as $mg) {
    echo "- Grupo Moodle ID {$mg['id']}: '{$mg['name']}' ({$mg['num_members']} miembros)\n";
}

// 3. Obtener los 30 alumnos de Grupo 2 (ID: 16)
$stmtAl = $pdo->prepare("SELECT a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.email, a.moodle_user_id
                         FROM matriculas m 
                         JOIN alumnos a ON m.alumno_id = a.id
                         WHERE m.grupo_id = 16");
$stmtAl->execute();
$alumnosG2 = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

echo "\nTotal alumnos matriculados en Grupo 2 (Intranet): " . count($alumnosG2) . "\n";
foreach ($alumnosG2 as $al) {
    echo "- Alumno: {$al['nombre']} {$al['primer_apellido']} | DNI: {$al['dni']} | Email: {$al['email']} | Moodle User ID: " . ($al['moodle_user_id'] ?: 'FALTA') . "\n";
}
