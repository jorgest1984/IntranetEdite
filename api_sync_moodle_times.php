<?php
// api_sync_moodle_times.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_db.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Verificar permisos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes para realizar esta operación.']);
    exit();
}

// 2. Verificar CSRF token
$csrf_token = $_GET['csrf_token'] ?? '';
if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Petición no autorizada: Token CSRF no válido.']);
    exit();
}

$af_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$af_id) {
    echo json_encode(['success' => false, 'error' => 'Identificador de acción formativa inválido.']);
    exit();
}

try {
    // 3. Obtener la Acción Formativa, su correspondiente id_plataforma (ID del curso en Moodle) y la duración en horas
    $stmt = $pdo->prepare("SELECT af.*, c.moodle_id as curso_moodle_id 
                           FROM acciones_formativas af 
                           JOIN cursos c ON af.curso_id = c.id 
                           WHERE af.id = ?");
    $stmt->execute([$af_id]);
    $af = $stmt->fetch();

    if (!$af) {
        echo json_encode(['success' => false, 'error' => 'No se encontró la acción formativa especificada.']);
        exit();
    }

    $courseMoodleId = (int)$af['curso_moodle_id'];
    if (!$courseMoodleId) {
        $courseMoodleId = (int)$af['id_plataforma'];
    }

    if (!$courseMoodleId) {
        echo json_encode([
            'success' => false, 
            'error' => 'Esta acción formativa no está vinculada a ningún curso de Moodle (id_plataforma vacío).'
        ]);
        exit();
    }

    // 4. Obtener todos los alumnos matriculados en cualquier grupo de esta Acción Formativa
    // que posean un moodle_user_id válido.
    $stmtAlumnos = $pdo->prepare("SELECT DISTINCT a.id as alumno_id, a.moodle_user_id, m.id as matricula_id 
                                  FROM matriculas m 
                                  JOIN alumnos a ON m.alumno_id = a.id 
                                  JOIN grupos g ON m.grupo_id = g.id 
                                  WHERE g.accion_id = ? AND a.moodle_user_id IS NOT NULL AND a.moodle_user_id > 0");
    $stmtAlumnos->execute([$af_id]);
    $alumnos = $stmtAlumnos->fetchAll();

    if (empty($alumnos)) {
        echo json_encode([
            'success' => true,
            'count' => 0,
            'message' => 'No se encontraron alumnos con Moodle ID vinculados a esta acción formativa.'
        ]);
        exit();
    }

    // Mapeo de moodle_user_id a la matricula_id local para poder guardar los datos
    $moodleUserIds = [];
    $matriculaMap = [];
    foreach ($alumnos as $al) {
        $moodleUserIds[] = (int)$al['moodle_user_id'];
        $matriculaMap[(int)$al['moodle_user_id']] = (int)$al['matricula_id'];
    }

    // 5. Instanciar el conector Moodle y descargar estadísticas
    $moodleDb = new MoodleDB();
    $stats = $moodleDb->fetchStudentStats($courseMoodleId, $moodleUserIds);
    $simulated = !$moodleDb->isConnected();

    // 6. Actualizar las matrículas locales con los datos de Moodle
    $updateStmt = $pdo->prepare("UPDATE matriculas SET 
                                    moodle_first_access = ?, 
                                    moodle_last_access = ?, 
                                    moodle_connected_time = ?, 
                                    moodle_progress = ?, 
                                    moodle_m1_completed = ?,
                                    moodle_m2_completed = ?,
                                    moodle_m3_completed = ?,
                                    moodle_e1_completed = ?,
                                    moodle_e2_completed = ?,
                                    moodle_e3_completed = ?,
                                    moodle_e1_grade = ?,
                                    moodle_e2_grade = ?,
                                    moodle_e3_grade = ?,
                                    moodle_final_grade = ?,
                                    moodle_aptitud = ?,
                                    moodle_last_sync = NOW() 
                                 WHERE id = ?");

    $updatedCount = 0;
    $courseDuration = (int)$af['duracion'] > 0 ? (int)$af['duracion'] : 60; // Duración en horas (default 60)

    // Detectar si el usuario ha indicado en la Intranet (Ajustes) que M1 y M2 van juntos
    $ajustesTexto = strtoupper(($af['contenidos'] ?? '') . ' ' . ($af['contenidos_breves'] ?? '') . ' ' . ($af['objetivos'] ?? ''));
    $m1m2_agrupados = (strpos($ajustesTexto, 'M1-M2') !== false || strpos($ajustesTexto, 'M1 Y M2') !== false || strpos($ajustesTexto, 'M1/M2') !== false);

    foreach ($stats as $moodleUserId => $data) {
        if (isset($matriculaMap[$moodleUserId])) {
            $matriculaId = $matriculaMap[$moodleUserId];
            
            // Regla especial solicitada por el usuario: si M1 y M2 comparten bloque, marcar M2 automáticamente al marcar M1
            if ($m1m2_agrupados && $data['m1_completed'] === 1) {
                $data['m2_completed'] = 1;
            }

            // Calcular % curso basándose en la duración de la intranet
            $connectedSeconds = (int)$data['connected_seconds'];
            $connectedHours = $connectedSeconds / 3600;
            $progressPercent = min(100, max(0, round(($connectedHours / $courseDuration) * 100)));

            $updateStmt->execute([
                $data['first_access'],
                $data['last_access'],
                $connectedSeconds,
                $progressPercent,
                (int)$data['m1_completed'],
                (int)$data['m2_completed'],
                (int)$data['m3_completed'],
                (int)$data['e1_completed'],
                (int)$data['e2_completed'],
                (int)$data['e3_completed'],
                $data['e1_grade'],
                $data['e2_grade'],
                $data['e3_grade'],
                $data['final_grade'],
                $data['aptitud'],
                $matriculaId
            ]);
            $updatedCount++;
        }
    }

    echo json_encode([
        'success' => true,
        'simulated' => $simulated,
        'count' => $updatedCount,
        'message' => "Sincronización finalizada correctamente. Alumnos actualizados: $updatedCount." . ($simulated ? " (Modo Simulación)" : ""),
        'error_detail' => $simulated ? $moodleDb->getError() : ''
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Ocurrió un error al procesar la sincronización: ' . $e->getMessage()
    ]);
}
?>
