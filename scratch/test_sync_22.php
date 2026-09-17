<?php
// scratch/test_sync_22.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== VERIFICANDO CONEXIÓN REAL Y QUERY ERROR EN MOODLE DB ===\n\n";

$moodleDb = new MoodleDB();
echo "Connected at start: " . ($moodleDb->isConnected() ? 'YES' : 'NO (' . $moodleDb->getError() . ')') . "\n";

$stmtAF = $pdo->query("SELECT id, num_accion, titulo, id_plataforma FROM acciones_formativas");
$afs = $stmtAF->fetchAll();

foreach ($afs as $af) {
    $cid = (int)$af['id_plataforma'];
    if (!$cid) continue;

    $stmtAl = $pdo->prepare("SELECT DISTINCT a.moodle_user_id FROM matriculas m JOIN alumnos a ON m.alumno_id = a.id JOIN grupos g ON m.grupo_id = g.id WHERE g.accion_id = ? AND a.moodle_user_id > 0");
    $stmtAl->execute([$af['id']]);
    $uids = $stmtAl->fetchAll(PDO::FETCH_COLUMN);

    if (empty($uids)) continue;

    echo "\nAF ID {$af['id']} (Curso Moodle {$cid}) - {$af['titulo']} (" . count($uids) . " alumnos):\n";
    $stats = $moodleDb->fetchStudentStats($cid, $uids);
    echo "  Connected after fetch: " . ($moodleDb->isConnected() ? 'YES' : 'NO - ERROR: ' . $moodleDb->getError()) . "\n";
    
    // Imprimir un alumno de muestra
    $sampleUid = reset($uids);
    if (isset($stats[$sampleUid])) {
        $st = $stats[$sampleUid];
        echo "  Muestras para Moodle User {$sampleUid}:\n";
        echo "    First access: " . ($st['first_access'] ?? 'null') . "\n";
        echo "    Last access: " . ($st['last_access'] ?? 'null') . "\n";
        echo "    Connected sec: {$st['connected_seconds']} (" . round($st['connected_seconds']/3600, 2) . "h)\n";
        echo "    M1: {$st['m1_completed']} | M2: {$st['m2_completed']} | M3: {$st['m3_completed']}\n";
        echo "    E1: {$st['e1_grade']} | E2: {$st['e2_grade']} | E3: {$st['e3_grade']} | Final: {$st['final_grade']} | Aptitud: {$st['aptitud']}\n";
    }
}
