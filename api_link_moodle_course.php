<?php
// api_link_moodle_course.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Verificar permisos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes para realizar esta operación.']);
    exit();
}

// 2. Verificar CSRF token
$csrf_token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Petición no autorizada: Token CSRF no válido o expirado.']);
    exit();
}

$af_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$moodle_course_id = isset($_POST['moodle_course_id']) ? (int)$_POST['moodle_course_id'] : 0;

if (!$af_id || !$moodle_course_id) {
    echo json_encode(['success' => false, 'error' => 'Parámetros obligatorios faltantes o inválidos.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 3. Obtener la Acción Formativa
    $stmt = $pdo->prepare("SELECT af.*, c.id as local_curso_id
                           FROM acciones_formativas af 
                           LEFT JOIN cursos c ON af.curso_id = c.id 
                           WHERE af.id = ?");
    $stmt->execute([$af_id]);
    $af = $stmt->fetch();

    if (!$af) {
        echo json_encode(['success' => false, 'error' => 'No se encontró la acción formativa especificada.']);
        exit();
    }

    // 4. Intentar buscar detalles del curso desde Moodle para guardarlos con el nombre real
    $moodle = new MoodleAPI($pdo);
    $fullname = $af['titulo'];
    $shortname = $af['abreviatura'];

    if ($moodle->isConfigured()) {
        try {
            $raw_courses = $moodle->getCourses();
            if (is_array($raw_courses)) {
                foreach ($raw_courses as $c) {
                    if (isset($c['id']) && $c['id'] == $moodle_course_id) {
                        $fullname = $c['fullname'] ?? $fullname;
                        $shortname = $c['shortname'] ?? $shortname;
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            // Ignoramos error de llamada para continuar con valores locales
        }
    }

    // 5. Registrar/Actualizar el curso local
    $local_curso_id = $af['local_curso_id'];
    if (empty($local_curso_id)) {
        // Crear en tabla cursos
        $stmtCurso = $pdo->prepare("INSERT INTO cursos (nombre_largo, nombre_corto, visible, moodle_id) VALUES (?, ?, 1, ?)");
        $stmtCurso->execute([$fullname, $shortname, $moodle_course_id]);
        $local_curso_id = $pdo->lastInsertId();
    } else {
        // Actualizar moodle_id en curso existente
        $stmtCurso = $pdo->prepare("UPDATE cursos SET moodle_id = ? WHERE id = ?");
        $stmtCurso->execute([$moodle_course_id, $local_curso_id]);
    }

    // 6. Actualizar la acción formativa
    $stmtUpdateAF = $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = ?, curso_id = ? WHERE id = ?");
    $stmtUpdateAF->execute([$moodle_course_id, $local_curso_id, $af_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Acción formativa vinculada correctamente al curso de Moodle ID: {$moodle_course_id}.",
        'moodle_id' => $moodle_course_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => 'Error al vincular la acción formativa en Moodle: ' . $e->getMessage()
    ]);
}
?>
