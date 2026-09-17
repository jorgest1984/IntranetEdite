<?php
// scratch/check_ofimatica.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO CURSO DE OFIMÁTICA Y ALUMNA AIDA DE LUÍS ===\n\n";

$moodleDb = new MoodleDB();
if (!$moodleDb->isConnected()) {
    echo "ERROR al conectar a Moodle DB: " . $moodleDb->getError() . "\n";
    exit;
}

$mpdo = $moodleDb->getPDO();
$prefix = $moodleDb->getTablePrefix();

// 1. Buscar AIDA DE LUÍS RODRÍGUEZ en Moodle (user 1193)
$stmtU = $mpdo->prepare("SELECT id, username, firstname, lastname, email, firstaccess, lastaccess, timecreated FROM {$prefix}user WHERE id = 1193 OR email LIKE '%aidadeluis%'");
$stmtU->execute();
$uRows = $stmtU->fetchAll(PDO::FETCH_ASSOC);

echo "Usuario Moodle:\n";
foreach ($uRows as $u) {
    echo "- ID {$u['id']}: {$u['firstname']} {$u['lastname']} | Email: {$u['email']} | User created: " . date('Y-m-d H:i:s', $u['timecreated']) . " | Firstaccess: " . date('Y-m-d H:i:s', $u['firstaccess']) . " | Lastaccess: " . date('Y-m-d H:i:s', $u['lastaccess']) . "\n";
}

// 2. Buscar cursos en Moodle donde está matriculada AIDA (1193)
$stmtEnr = $mpdo->prepare("SELECT c.id, c.fullname, c.shortname, ue.timecreated as enrol_time 
                           FROM {$prefix}enrol e 
                           JOIN {$prefix}user_enrolments ue ON ue.enrolid = e.id 
                           JOIN {$prefix}course c ON e.courseid = c.id 
                           WHERE ue.userid = 1193");
$stmtEnr->execute();
$enrCourses = $stmtEnr->fetchAll(PDO::FETCH_ASSOC);

echo "\nCursos Moodle en los que está matriculada (User 1193):\n";
foreach ($enrCourses as $ec) {
    echo "- Course ID {$ec['id']}: {$ec['fullname']} ({$ec['shortname']}) | Enrolled at: " . date('Y-m-d H:i:s', $ec['enrol_time']) . "\n";
}

// 3. Inspeccionar logs de AIDA (1193) agrupados por courseid
$stmtLogs = $mpdo->prepare("SELECT courseid, MIN(timecreated) as min_time, MAX(timecreated) as max_time, COUNT(*) as cnt 
                            FROM {$prefix}logstore_standard_log 
                            WHERE userid = 1193 
                            GROUP BY courseid");
$stmtLogs->execute();
$logGroups = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

echo "\nLogs de AIDA en Moodle por Course ID:\n";
foreach ($logGroups as $lg) {
    echo "- CourseID {$lg['courseid']}: {$lg['cnt']} eventos | De: " . date('Y-m-d H:i:s', $lg['min_time']) . " A: " . date('Y-m-d H:i:s', $lg['max_time']) . "\n";
}

// 4. Detalle de eventos de AIDA en el curso de Ofimática (averiguar el courseId)
foreach ($enrCourses as $ec) {
    $cid = $ec['id'];
    $stmtCLogs = $mpdo->prepare("SELECT id, eventname, component, action, target, timecreated FROM {$prefix}logstore_standard_log WHERE userid = 1193 AND courseid = ? ORDER BY timecreated ASC LIMIT 10");
    $stmtCLogs->execute([$cid]);
    $clogs = $stmtCLogs->fetchAll(PDO::FETCH_ASSOC);
    echo "\nPrimeros 10 logs de Course ID {$cid}:\n";
    foreach ($clogs as $l) {
        echo "  - [" . date('Y-m-d H:i:s', $l['timecreated']) . "] {$l['component']} - {$l['action']} - {$l['target']} ({$l['eventname']})\n";
    }
}
