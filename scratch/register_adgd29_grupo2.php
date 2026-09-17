<?php
// scratch/register_adgd29_grupo2.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_db.php';
require_once __DIR__ . '/../includes/moodle_api.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== CREANDO GRUPO 6 EN MOODLE PARA ADGD29 GRUPO 2 (ID INTRANET: 16) ===\n\n";

$moodleDb = new MoodleDB();
if (!$moodleDb->isConnected()) {
    echo "ERROR al conectar a Moodle DB: " . $moodleDb->getError() . "\n";
    exit;
}

$mpdo = $moodleDb->getPDO();
$prefix = $moodleDb->getTablePrefix();
$courseId = 43;

// 1. Comprobar si existe GRUPO-6 o Grupo 6 en Moodle para el curso 43
$groupName = 'GRUPO-6';
$stmtG = $mpdo->prepare("SELECT id, name FROM {$prefix}groups WHERE courseid = ? AND (name = ? OR name = 'Grupo 6' OR name = 'GRUPO 6') LIMIT 1");
$stmtG->execute([$courseId, $groupName]);
$moodleGroup = $stmtG->fetch(PDO::FETCH_ASSOC);

if ($moodleGroup) {
    $moodleGroupId = (int)$moodleGroup['id'];
    echo "Grupo Moodle existente encontrado: ID {$moodleGroupId} - '{$moodleGroup['name']}'\n";
} else {
    // Crear el grupo en Moodle
    $now = time();
    $stmtInsG = $mpdo->prepare("INSERT INTO {$prefix}groups (courseid, name, description, descriptionformat, timecreated, timemodified) 
                                VALUES (?, ?, '', 1, ?, ?)");
    $stmtInsG->execute([$courseId, $groupName, $now, $now]);
    $moodleGroupId = (int)$mpdo->lastInsertId();
    echo "¡GRUPO CREADO EN MOODLE CON ÉXITO! ID Moodle: {$moodleGroupId} - '{$groupName}'\n";
}

// Actualizar id_grupo_moodle en Intranet para el grupo 16
$stmtUpdIntra = $pdo->prepare("UPDATE grupos SET id_grupo_moodle = ? WHERE id = 16");
$stmtUpdIntra->execute([$moodleGroupId]);
echo "Actualizado grupos.id_grupo_moodle = {$moodleGroupId} para el Grupo Intranet 16.\n";

// 2. Obtener la enrol instance manual para el curso 43 en Moodle
$stmtEnrol = $mpdo->prepare("SELECT id FROM {$prefix}enrol WHERE courseid = ? AND enrol = 'manual' LIMIT 1");
$stmtEnrol->execute([$courseId]);
$enrolRow = $stmtEnrol->fetch();
$enrolId = $enrolRow ? (int)$enrolRow['id'] : 0;

// Obtener contextid del curso
$stmtCtx = $mpdo->prepare("SELECT id FROM {$prefix}context WHERE contextlevel = 50 AND instanceid = ? LIMIT 1");
$stmtCtx->execute([$courseId]);
$ctxRow = $stmtCtx->fetch();
$contextId = $ctxRow ? (int)$ctxRow['id'] : 0;

// Role ID de estudiante en Moodle (normalmente 5)
$stmtRole = $mpdo->prepare("SELECT id FROM {$prefix}role WHERE shortname = 'student' LIMIT 1");
$stmtRole->execute();
$roleRow = $stmtRole->fetch();
$studentRoleId = $roleRow ? (int)$roleRow['id'] : 5;

// 3. Matricular y meter en el Grupo Moodle a los 30 alumnos de Grupo 2
$stmtAl = $pdo->prepare("SELECT a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.email, a.moodle_user_id
                         FROM matriculas m 
                         JOIN alumnos a ON m.alumno_id = a.id
                         WHERE m.grupo_id = 16");
$stmtAl->execute();
$alumnosG2 = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

$addedCount = 0;
$enrolledCount = 0;
$now = time();

foreach ($alumnosG2 as $al) {
    $uid = (int)$al['moodle_user_id'];
    if (!$uid) continue;

    // A. Verificar matrícula en Moodle
    if ($enrolId) {
        $stmtUE = $mpdo->prepare("SELECT id FROM {$prefix}user_enrolments WHERE enrolid = ? AND userid = ? LIMIT 1");
        $stmtUE->execute([$enrolId, $uid]);
        if (!$stmtUE->fetch()) {
            $stmtInsUE = $mpdo->prepare("INSERT INTO {$prefix}user_enrolments (status, enrolid, userid, timestart, timeend, modifierid, timecreated, timemodified) 
                                         VALUES (0, ?, ?, ?, 0, 2, ?, ?)");
            $stmtInsUE->execute([$enrolId, $uid, $now, $now, $now]);
            $enrolledCount++;
        }
    }

    // B. Asignación de rol student en contexto si no existe
    if ($contextId) {
        $stmtRA = $mpdo->prepare("SELECT id FROM {$prefix}role_assignments WHERE roleid = ? AND contextid = ? AND userid = ? LIMIT 1");
        $stmtRA->execute([$studentRoleId, $contextId, $uid]);
        if (!$stmtRA->fetch()) {
            $stmtInsRA = $mpdo->prepare("INSERT INTO {$prefix}role_assignments (roleid, contextid, userid, timemodified, modifierid) 
                                         VALUES (?, ?, ?, ?, 2)");
            $stmtInsRA->execute([$studentRoleId, $contextId, $uid, $now]);
        }
    }

    // C. Pertenencia al grupo Moodle
    $stmtGM = $mpdo->prepare("SELECT id FROM {$prefix}groups_members WHERE groupid = ? AND userid = ? LIMIT 1");
    $stmtGM->execute([$moodleGroupId, $uid]);
    if (!$stmtGM->fetch()) {
        $stmtInsGM = $mpdo->prepare("INSERT INTO {$prefix}groups_members (groupid, userid, timeadded, component, itemid) 
                                     VALUES (?, ?, ?, '', 0)");
        $stmtInsGM->execute([$moodleGroupId, $uid, $now]);
        $addedCount++;
    }
}

echo "\n=== RESUMEN DE ACCIONES ===";
echo "\n- Alumnos recién matriculados en Curso Moodle 43: {$enrolledCount}";
echo "\n- Alumnos añadidos al GRUPO-6 de Moodle (ID {$moodleGroupId}): {$addedCount}";
echo "\n- Total alumnos en Grupo 2 de Intranet: " . count($alumnosG2);
echo "\n\n¡PROCESO COMPLETADO CON ÉXITO!\n";
