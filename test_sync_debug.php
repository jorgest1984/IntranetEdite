<?php
// test_sync_debug.php
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';
require_once 'includes/moodle_db.php';

header('Content-Type: text/plain; charset=utf-8');

$moodle = new MoodleAPI($pdo);
$moodleDb = new MoodleDB();

echo "1. Moodle API Configured: " . ($moodle->isConfigured() ? "SI" : "NO") . "\n";
echo "2. Moodle DB Connected: " . ($moodleDb->isConnected() ? "SI" : "NO") . "\n";
if (!$moodleDb->isConnected()) {
    echo "   DB Error: " . $moodleDb->getError() . "\n";
} else {
    echo "   DB Prefix: " . $moodleDb->getTablePrefix() . "\n";
}

$af_id = 22;
$stmt = $pdo->prepare("SELECT af.*, c.moodle_id as curso_moodle_id FROM acciones_formativas af LEFT JOIN cursos c ON af.curso_id = c.id WHERE af.id = ?");
$stmt->execute([$af_id]);
$af = $stmt->fetch();
$courseId = !empty($af['curso_moodle_id']) ? $af['curso_moodle_id'] : $af['id_plataforma'];
echo "3. AF ID: $af_id | Course Moodle ID: $courseId\n";

$stmtAlumnos = $pdo->prepare("SELECT a.* FROM matriculas m JOIN alumnos a ON m.alumno_id = a.id WHERE m.grupo_id IN (SELECT id FROM grupos WHERE accion_id = ?)");
$stmtAlumnos->execute([$af_id]);
$alumnos = $stmtAlumnos->fetchAll();
echo "4. Total alumnos en local: " . count($alumnos) . "\n";

foreach ($alumnos as $idx => $a) {
    echo "\n--- Alumno #" . ($idx + 1) . ": {$a['nombre']} ({$a['dni']}) ---\n";
    $username = strtolower(trim(str_replace([' ', '-', '.'], '', $a['dni'])));
    $lastname = trim(($a['primer_apellido'] ?? '') . ' ' . ($a['segundo_apellido'] ?? '')) ?: 'Sin apellidos';
    $email = $a['email'];
    $pass = 'Edite' . str_replace(['-', '.', ' '], '', $a['dni']) . '!';

    $userData = [
        'firstname' => $a['nombre'],
        'lastname' => $lastname,
        'email' => $email,
        'username' => $username,
        'password' => $pass
    ];

    try {
        $uId = $moodle->provisionStudent($courseId, null, $userData, 0);
        echo "   -> PROVISION RESULT User ID: " . var_export($uId, true) . "\n";
    } catch (Exception $e) {
        echo "   -> ERROR PROVISION: " . $e->getMessage() . "\n";
    }
}
