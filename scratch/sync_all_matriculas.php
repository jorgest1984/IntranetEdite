<?php
// scratch/sync_all_matriculas.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== RESINCRONIZACIÓN REAL DE TODAS LAS MATRÍCULAS EN LA INTRANET ===\n\n";

$moodleDb = new MoodleDB();
if (!$moodleDb->isConnected()) {
    echo "ERROR: No se pudo conectar a la base de datos de Moodle: " . $moodleDb->getError() . "\n";
    exit;
}

// 1. Obtener todas las Acciones Formativas vinculadas a Moodle
$stmtAF = $pdo->query("SELECT af.id, af.num_accion, af.titulo, af.duracion, af.id_plataforma, c.moodle_id as curso_moodle_id
                       FROM acciones_formativas af
                       LEFT JOIN cursos c ON af.curso_id = c.id");
$afs = $stmtAF->fetchAll(PDO::FETCH_ASSOC);

$totalUpdated = 0;

foreach ($afs as $af) {
    $af_id = (int)$af['id'];
    $courseMoodleId = !empty($af['curso_moodle_id']) ? (int)$af['curso_moodle_id'] : (int)($af['id_plataforma'] ?? 0);
    
    if (!$courseMoodleId) {
        $stmtG = $pdo->prepare("SELECT id_plataforma, codigo_plat FROM grupos WHERE accion_id = ? AND ((id_plataforma IS NOT NULL AND id_plataforma > 0) OR (codigo_plat IS NOT NULL AND codigo_plat != '')) ORDER BY id ASC LIMIT 1");
        $stmtG->execute([$af_id]);
        $gRow = $stmtG->fetch();
        if ($gRow) {
            $courseMoodleId = (int)($gRow['id_plataforma'] ?: $gRow['codigo_plat']);
        }
    }

    if (!$courseMoodleId) continue;

    // Obtener alumnos de este AF con moodle_user_id
    $stmtAl = $pdo->prepare("SELECT DISTINCT a.id as alumno_id, a.moodle_user_id, m.id as matricula_id 
                             FROM matriculas m 
                             JOIN alumnos a ON m.alumno_id = a.id 
                             JOIN grupos g ON m.grupo_id = g.id 
                             WHERE g.accion_id = ? AND a.moodle_user_id IS NOT NULL AND a.moodle_user_id > 0");
    $stmtAl->execute([$af_id]);
    $alumnos = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

    if (empty($alumnos)) continue;

    $moodleUserIds = [];
    $matriculaMap = [];
    foreach ($alumnos as $al) {
        $moodleUserIds[] = (int)$al['moodle_user_id'];
        $matriculaMap[(int)$al['moodle_user_id']] = (int)$al['matricula_id'];
    }

    // Descargar estadísticas REALES de Moodle
    $stats = $moodleDb->fetchStudentStats($courseMoodleId, $moodleUserIds);
    $courseDuration = (int)($af['duracion'] ?? 60);
    if ($courseDuration <= 0) $courseDuration = 60;

    $updateStmt = $pdo->prepare("UPDATE matriculas SET 
                                    moodle_first_access = ?, 
                                    moodle_last_access = ?, 
                                    moodle_connected_time = ?, 
                                    moodle_progress = ?, 
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
                                 WHERE id = ?");

    foreach ($stats as $mUid => $data) {
        if (isset($matriculaMap[$mUid])) {
            $matId = $matriculaMap[$mUid];
            $connectedSeconds = (int)$data['connected_seconds'];
            $connectedHours = $connectedSeconds / 3600;
            $progressPercent = min(100, max(0, round(($connectedHours / $courseDuration) * 100)));

            $updateStmt->execute([
                $data['first_access'],
                $data['last_access'],
                $connectedSeconds,
                $progressPercent,
                (int)$data['m1_completed'],
                (int)$data['m2_completed'],
                (int)$data['m3_completed'],
                (int)$data['e1_completed'],
                (int)$data['e2_completed'],
                (int)$data['e3_completed'],
                $data['e1_grade'],
                $data['e2_grade'],
                $data['e3_grade'],
                $data['final_grade'],
                $data['aptitud'],
                $matId
            ]);
            $totalUpdated++;
        }
    }
    echo "AF ID {$af_id} ({$af['num_accion']} - {$af['titulo']}): Sincronizadas " . count($stats) . " matrículas.\n";
}

echo "\n¡RESINCRONIZACIÓN REAL COMPLETADA! Total matrículas actualizadas: {$totalUpdated}\n";
