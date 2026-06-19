<?php
// test_moodle.php
require 'includes/config.php';
require 'includes/moodle_db.php';

header('Content-Type: application/json');

$moodleDb = new MoodleDB();
if (!$moodleDb->isConnected()) {
    echo json_encode(['error' => 'Not connected', 'msg' => $moodleDb->getError()]);
    exit;
}

// Reflection to get mpdo
$ref = new ReflectionClass($moodleDb);
$prop = $ref->getProperty('mpdo');
$prop->setAccessible(true);
$mpdo = $prop->getValue($moodleDb);

// Course ID 
$courseMoodleId = 3; // Let's guess it's 2 or 3. We can query action 12.
$stmt = $pdo->prepare("SELECT c.moodle_id as curso_moodle_id, af.id_plataforma FROM acciones_formativas af JOIN cursos c ON af.curso_id = c.id WHERE af.id = 12");
$stmt->execute();
$af = $stmt->fetch();
$cmid = $af['curso_moodle_id'] ?: $af['id_plataforma'];

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
$stmtMod->execute([$cmid]);
$moduleRows = $stmtMod->fetchAll();

echo json_encode(['cmid' => $cmid, 'modules' => $moduleRows]);
