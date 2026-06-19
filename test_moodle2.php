<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    require 'includes/config.php';
    require 'includes/moodle_db.php';

    $moodleDb = new MoodleDB();
    if (!$moodleDb->isConnected()) {
        echo json_encode(['error' => 'Not connected to Moodle', 'msg' => $moodleDb->getError()]);
        exit;
    }

    $ref = new ReflectionClass($moodleDb);
    $prop = $ref->getProperty('mpdo');
    $prop->setAccessible(true);
    $mpdo = $prop->getValue($moodleDb);

    $courseMoodleId = 2; 

    $stmt = $pdo->prepare("SELECT c.moodle_id as curso_moodle_id, af.id_plataforma FROM acciones_formativas af JOIN cursos c ON af.curso_id = c.id WHERE af.id = 12");
    $stmt->execute();
    $af = $stmt->fetch();
    if ($af) {
        $courseMoodleId = $af['curso_moodle_id'] ?: $af['id_plataforma'];
    }

    $sqlModules = "SELECT cm.id as coursemoduleid, cm.section, 
                         COALESCE(a.name, p.name, r.name, q.name, f.name, b.name, s.name, 'Actividad') as name
                  FROM mdl_course_modules cm
                  JOIN mdl_modules m ON cm.module = m.id
                  LEFT JOIN mdl_assign a ON m.name = 'assign' AND cm.instance = a.id
                  LEFT JOIN mdl_page p ON m.name = 'page' AND cm.instance = p.id
                  LEFT JOIN mdl_resource r ON m.name = 'resource' AND cm.instance = r.id
                  LEFT JOIN mdl_quiz q ON m.name = 'quiz' AND cm.instance = q.id
                  LEFT JOIN mdl_forum f ON m.name = 'forum' AND cm.instance = f.id
                  LEFT JOIN mdl_book b ON m.name = 'book' AND cm.instance = b.id
                  LEFT JOIN mdl_scorm s ON m.name = 'scorm' AND cm.instance = s.id
                  WHERE cm.course = ?";

    $stmtMod = $mpdo->prepare($sqlModules);
    $stmtMod->execute([$courseMoodleId]);
    $moduleRows = $stmtMod->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['cmid' => $courseMoodleId, 'modules' => $moduleRows]);
} catch (Throwable $e) {
    echo json_encode(['error' => 'Exception', 'msg' => $e->getMessage()]);
}
