<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_db.php';
require_once __DIR__ . '/../includes/moodle_api.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== ACTUALIZANDO ID_PLATAFORMA Y SINCRONIZANDO COMM0023 ===\n\n";

// 1. Actualizar id_plataforma = 48 en acciones_formativas para id = 22
$stmtUp = $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = 48 WHERE id = 22");
$stmtUp->execute();
echo "Actualizada accion_formativa 22 con id_plataforma = 48.\n";

// 2. Conectar a Moodle DB y sincronizar los alumnos de Grupo 10
$moodleDb = new MoodleDB();
if (!$moodleDb->isConnected()) {
    echo "ERROR al conectar a Moodle DB: " . $moodleDb->getError() . "\n";
    exit;
}

$mpdo = $moodleDb->getPDO();
$prefix = $moodleDb->getTablePrefix();

// Obtener los moodle_user_id del grupo 10
$stmtM = $pdo->prepare("
    SELECT m.id as matricula_id, a.id as alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.moodle_user_id
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
    WHERE m.grupo_id = 10 AND (m.estado IS NULL OR UPPER(m.estado) != 'BAJA')
");
$stmtM->execute();
$matriculas = $stmtM->fetchAll(PDO::FETCH_ASSOC);

$uids = array_filter(array_column($matriculas, 'moodle_user_id'));
echo "Alumnos a sincronizar (" . count($uids) . " moodle_user_ids):\n";

// Ejecutar fetchStudentStats con courseId 48
$stats = $moodleDb->fetchStudentStats(48, $uids);
echo "Estadísticas obtenidas desde Moodle DB (Curso 48):\n";
print_r($stats);

// 3. Guardar las estadísticas en las matrículas
$stmtUpdateMat = $pdo->prepare("
    UPDATE matriculas SET
        moodle_connected_time = ?,
        moodle_progress = ?,
        moodle_first_access = ?,
        moodle_last_access = ?,
        moodle_m1_completed = ?,
        moodle_m2_completed = ?,
        moodle_m3_completed = ?,
        moodle_e1_completed = ?,
        moodle_e2_completed = ?,
        moodle_e3_completed = ?,
        moodle_e1_grade = ?,
        moodle_e2_grade = ?,
        moodle_e3_grade = ?,
        moodle_final_grade = ?,
        moodle_aptitud = ?,
        moodle_last_sync = NOW()
    WHERE id = ?
");

$updatedCount = 0;
foreach ($matriculas as $mat) {
    $uid = $mat['moodle_user_id'];
    if (!$uid || !isset($stats[$uid])) continue;
    $st = $stats[$uid];

    $stmtUpdateMat->execute([
        $st['connected_seconds'],
        $st['progress'],
        $st['first_access'],
        $st['last_access'],
        $st['m1_completed'] ? 1 : 0,
        $st['m2_completed'] ? 1 : 0,
        $st['m3_completed'] ? 1 : 0,
        $st['e1_completed'] ? 1 : 0,
        $st['e2_completed'] ? 1 : 0,
        $st['e3_completed'] ? 1 : 0,
        $st['e1_grade'],
        $st['e2_grade'],
        $st['e3_grade'],
        $st['final_grade'],
        $st['aptitud'],
        $mat['matricula_id']
    ]);
    $updatedCount++;
}

echo "\nSe han actualizado {$updatedCount} matrículas con los datos reales de Moodle.\n";

// Mostrar las matrículas actualizadas
$stmtCheck = $pdo->prepare("
    SELECT m.id, a.nombre, a.primer_apellido, m.moodle_connected_time, m.moodle_progress, m.moodle_m1_completed, m.moodle_e1_grade, m.moodle_final_grade, m.moodle_aptitud
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
    WHERE m.grupo_id = 10
");
$stmtCheck->execute();
print_r($stmtCheck->fetchAll(PDO::FETCH_ASSOC));
