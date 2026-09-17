<?php
// scratch/register_adgd29_grupo2.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_db.php';
require_once __DIR__ . '/../includes/moodle_api.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== MATRICULANDO ALUMNOS DE GRUPO 2 EN GRUPO-6 MOODLE (ID MOODLE: 39) ===\n\n";

$moodleDb = new MoodleDB();
if (!$moodleDb->isConnected()) {
    echo "ERROR al conectar a Moodle DB: " . $moodleDb->getError() . "\n";
    exit;
}

$mpdo = $moodleDb->getPDO();
$prefix = $moodleDb->getTablePrefix();
$courseId = 43;
$moodleGroupId = 39; // ID de GRUPO-6 recién creado

// Intentar guardar en grupos si existe alguna columna relativa a Moodle
try {
    $stmtCols = $pdo->query("SHOW COLUMNS FROM grupos");
    $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
    foreach (['id_grupo_moodle', 'moodle_group_id', 'moodle_id', 'id_moodle'] as $colCandidate) {
        if (in_array($colCandidate, $cols)) {
            $pdo->prepare("UPDATE grupos SET {$colCandidate} = ? WHERE id = 16")->execute([$moodleGroupId]);
            echo "Guardado {$colCandidate} = {$moodleGroupId} en grupos.\n";
            break;
        }
    }
} catch (Exception $e) {}

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
