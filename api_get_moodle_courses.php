<?php
// api_get_moodle_courses.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_db.php';
require_once 'includes/moodle_api.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Verificar permisos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes para realizar esta operación.']);
    exit();
}

try {
    // 2. Obtener los IDs de cursos de Moodle que ya están vinculados a alguna Acción Formativa
    $stmtLinked = $pdo->query("SELECT DISTINCT id_plataforma FROM acciones_formativas WHERE id_plataforma IS NOT NULL AND id_plataforma != 0");
    $linkedCourseIds = $stmtLinked->fetchAll(PDO::FETCH_COLUMN);
    $linkedCourseIds = array_map('intval', $linkedCourseIds);

    $courses = [];

    // 3. Intentar conectar directamente por Base de Datos a Moodle (Recomendado/Primario)
    $moodleDb = new MoodleDB();
    if ($moodleDb->isConnected()) {
        $mpdo = $moodleDb->getPDO();
        $prefix = defined('MOODLE_DB_PREFIX') ? MOODLE_DB_PREFIX : 'mdl_';
        
        // Consultar los cursos directamente de la base de datos de Moodle
        $stmtCourses = $mpdo->query("SELECT id, fullname, shortname FROM {$prefix}course WHERE id != 1 ORDER BY fullname ASC");
        $raw_courses = $stmtCourses->fetchAll();
        
        foreach ($raw_courses as $c) {
            $courseId = (int)$c['id'];
            // Filtrar: solo incluir si NO está ya vinculado
            if (!in_array($courseId, $linkedCourseIds)) {
                $courses[] = [
                    'id' => $courseId,
                    'fullname' => $c['fullname'] ?? '',
                    'shortname' => $c['shortname'] ?? ''
                ];
            }
        }
    } else {
        // 4. Fallback: Si no conecta por BD, intentamos con la API/Token
        $moodle = new MoodleAPI($pdo);
        if ($moodle->isConfigured()) {
            try {
                $raw_courses = $moodle->getCourses();
                if (is_array($raw_courses)) {
                    foreach ($raw_courses as $c) {
                        $courseId = isset($c['id']) ? (int)$c['id'] : 0;
                        if ($courseId && $courseId != 1) {
                            if (!in_array($courseId, $linkedCourseIds)) {
                                $courses[] = [
                                    'id' => $courseId,
                                    'fullname' => $c['fullname'] ?? '',
                                    'shortname' => $c['shortname'] ?? ''
                                ];
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Fallback de demostración si falla tanto BD como API
                $courses = [
                    ['id' => 901, 'fullname' => 'Curso de Ejemplo Moodle (Sin conexión a BD ni API)', 'shortname' => 'FAIL-1']
                ];
            }
        } else {
            // Fallback de demostración
            $courses = [
                ['id' => 10001, 'fullname' => 'Curso de Demostración A (Sin vincular)', 'shortname' => 'DEMO-A'],
                ['id' => 10002, 'fullname' => 'Curso de Demostración B (Sin vincular)', 'shortname' => 'DEMO-B']
            ];
        }
    }

    echo json_encode(['success' => true, 'courses' => $courses]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
