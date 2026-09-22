<?php
// scratch/check_af13_group16.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';

echo "=== DIAGNÓSTICO AF 13 Y GRUPO 16 ===\n\n";

// 1. Accion Formativa
$stmtAF = $pdo->prepare("SELECT af.*, c.nombre_largo, c.nombre_corto, c.moodle_id FROM acciones_formativas af LEFT JOIN cursos c ON af.curso_id = c.id WHERE af.id = 13");
$stmtAF->execute();
$af = $stmtAF->fetch();
echo "--- ACCION FORMATIVA 13 ---\n";
print_r($af);

// 2. Grupos de AF 13
$stmtG = $pdo->prepare("SELECT * FROM grupos WHERE accion_id = 13 OR id = 16");
$stmtG->execute();
$grupos = $stmtG->fetchAll();
echo "\n--- GRUPOS EN INTRANET FOR AF 13 ---\n";
print_r($grupos);

// 3. Moodle Groups for this course
$moodle = new MoodleAPI($pdo);
if ($moodle->isConfigured()) {
    $courseId = $af['id_plataforma'] ?: ($af['moodle_id'] ?? null);
    echo "\n--- MOODLE COURSE ID: $courseId ---\n";
    if ($courseId) {
        $foundId = $moodle->findCourseId($courseId);
        echo "findCourseId($courseId) = " . var_export($foundId, true) . "\n";
        
        if (defined('MOODLE_DB_HOST')) {
            try {
                $mpdo = new PDO("mysql:host=".MOODLE_DB_HOST.";dbname=".MOODLE_DB_NAME.";charset=utf8mb4", MOODLE_DB_USER, MOODLE_DB_PASS);
                $prefix = MOODLE_DB_PREFIX;
                $stmtMGrp = $mpdo->prepare("SELECT * FROM {$prefix}groups WHERE courseid = ?");
                $stmtMGrp->execute([$foundId]);
                $mGroups = $stmtMGrp->fetchAll(PDO::FETCH_ASSOC);
                echo "\n--- MOODLE GROUPS IN MOODLE DB FOR COURSE $foundId ---\n";
                print_r($mGroups);
            } catch (Exception $e) {
                echo "Error Moodle DB: " . $e->getMessage() . "\n";
            }
        }
    }
}
