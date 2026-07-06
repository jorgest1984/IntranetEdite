<?php
// api_get_moodle_courses.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Verificar permisos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes para realizar esta operación.']);
    exit();
}

try {
    $moodle = new MoodleAPI($pdo);
    $courses = [];

    if ($moodle->isConfigured()) {
        try {
            $raw_courses = $moodle->getCourses();
            if (is_array($raw_courses)) {
                foreach ($raw_courses as $c) {
                    // Moodle getCourses returns an array of course objects
                    // Exclude site course (id = 1)
                    if (isset($c['id']) && $c['id'] != 1) {
                        $courses[] = [
                            'id' => $c['id'],
                            'fullname' => $c['fullname'] ?? '',
                            'shortname' => $c['shortname'] ?? ''
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Fallback en caso de error de conexión/API
            $courses = [
                ['id' => 901, 'fullname' => 'Curso de Ejemplo Moodle (Conexión Fallida)', 'shortname' => 'FAIL-1']
            ];
        }
    } else {
        // Fallback simulación
        $courses = [
            ['id' => 10001, 'fullname' => 'Curso de Demostración A', 'shortname' => 'DEMO-A'],
            ['id' => 10002, 'fullname' => 'Curso de Demostración B', 'shortname' => 'DEMO-B'],
            ['id' => 10003, 'fullname' => 'Curso de Demostración C', 'shortname' => 'DEMO-C']
        ];
    }

    echo json_encode(['success' => true, 'courses' => $courses]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
