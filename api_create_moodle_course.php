<?php
// api_create_moodle_course.php
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

$af_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if (!$af_id) {
    echo json_encode(['success' => false, 'error' => 'Identificador de acción formativa inválido.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 3. Obtener la Acción Formativa
    $stmt = $pdo->prepare("SELECT af.*, c.moodle_id as curso_moodle_id, c.id as local_curso_id
                           FROM acciones_formativas af 
                           LEFT JOIN cursos c ON af.curso_id = c.id 
                           WHERE af.id = ?");
    $stmt->execute([$af_id]);
    $af = $stmt->fetch();

    if (!$af) {
        echo json_encode(['success' => false, 'error' => 'No se encontró la acción formativa especificada.']);
        exit();
    }

    // Verificar si ya tiene ID de plataforma / Moodle ID
    $existingMoodleId = !empty($af['curso_moodle_id']) ? $af['curso_moodle_id'] : $af['id_plataforma'];
    if (!empty($existingMoodleId)) {
        echo json_encode([
            'success' => false,
            'error' => "Esta acción formativa ya está vinculada a Moodle con el ID: {$existingMoodleId}."
        ]);
        exit();
    }

    // 4. Instanciar la API de Moodle
    $moodle = new MoodleAPI($pdo);
    $moodle_id = null;

    if ($moodle->isConfigured()) {
        // Crear curso en Moodle real
        $moodleResult = $moodle->createCourse($af['titulo'], $af['abreviatura']);
        if (!empty($moodleResult) && isset($moodleResult[0]['id'])) {
            $moodle_id = $moodleResult[0]['id'];
        } else {
            throw new Exception("Moodle no devolvió un ID de curso válido tras crearlo.");
        }
    } else {
        // Si no está configurado, simulamos la creación en Moodle (para pruebas/fallback de demostración)
        $moodle_id = 10000 + $af_id; // ID simulado
        $simulated = true;
    }

    // 5. Asegurar existencia de curso local
    $local_curso_id = $af['local_curso_id'];
    if (empty($local_curso_id)) {
        // Crear en tabla cursos
        $stmtCurso = $pdo->prepare("INSERT INTO cursos (nombre_largo, nombre_corto, visible, moodle_id) VALUES (?, ?, 1, ?)");
        $stmtCurso->execute([$af['titulo'], $af['abreviatura'], $moodle_id]);
        $local_curso_id = $pdo->lastInsertId();
    } else {
        // Actualizar moodle_id en curso existente
        $stmtCurso = $pdo->prepare("UPDATE cursos SET moodle_id = ? WHERE id = ?");
        $stmtCurso->execute([$moodle_id, $local_curso_id]);
    }

    // 6. Actualizar la acción formativa
    $stmtUpdateAF = $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = ?, curso_id = ? WHERE id = ?");
    $stmtUpdateAF->execute([$moodle_id, $local_curso_id, $af_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => isset($simulated) 
            ? "Simulación: Acción formativa registrada localmente con ID Moodle simulado: {$moodle_id}."
            : "Acción formativa creada correctamente en Moodle con ID: {$moodle_id}.",
        'moodle_id' => $moodle_id,
        'simulated' => isset($simulated)
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => 'Error al crear la acción formativa en Moodle: ' . $e->getMessage()
    ]);
}
?>
