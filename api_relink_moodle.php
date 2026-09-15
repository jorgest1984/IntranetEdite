<?php
// api_relink_moodle.php
require_once 'includes/auth.php';
require_once 'includes/moodle_api.php';

header('Content-Type: application/json; charset=utf-8');

if (!has_permission([ROLE_ADMIN])) {
    echo json_encode(['success' => false, 'error' => 'No tienes permisos suficientes para modificar la vinculación con Moodle.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'link';
$af_id = (int)($_POST['af_id'] ?? $_GET['af_id'] ?? 0);
$moodle_id = trim($_POST['moodle_id'] ?? $_GET['moodle_id'] ?? '');

if (!$af_id) {
    echo json_encode(['success' => false, 'error' => 'ID de Acción Formativa inválido.']);
    exit();
}

// Fetch AF details
$stmtAF = $pdo->prepare("SELECT af.*, c.id as curso_id, c.nombre_largo as titulo FROM acciones_formativas af LEFT JOIN cursos c ON af.curso_id = c.id WHERE af.id = ?");
$stmtAF->execute([$af_id]);
$af = $stmtAF->fetch(PDO::FETCH_ASSOC);

if (!$af) {
    echo json_encode(['success' => false, 'error' => 'Acción Formativa no encontrada.']);
    exit();
}

try {
    if ($action === 'unlink' || $moodle_id === '' || $moodle_id === '0') {
        // Desvincular curso de Moodle
        $pdo->beginTransaction();
        
        // 1. Limpiar id_plataforma en acciones_formativas
        $stmt1 = $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = NULL WHERE id = ?");
        $stmt1->execute([$af_id]);
        
        // 2. Limpiar moodle_id en tabla cursos si existe
        if (!empty($af['curso_id'])) {
            $stmt2 = $pdo->prepare("UPDATE cursos SET moodle_id = NULL WHERE id = ?");
            $stmt2->execute([$af['curso_id']]);
        }
        
        // 3. Resetear las referencias de grupo en Moodle para evitar conflictos futuros
        $stmt3 = $pdo->prepare("UPDATE grupos SET id_plataforma = NULL, codigo_plat = NULL WHERE accion_id = ?");
        $stmt3->execute([$af_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'El curso de Moodle ha sido desvinculado correctamente. Los grupos han quedado limpios para futuras vinculaciones.'
        ]);
        exit();
    } else {
        // Vincular o re-vincular a un nuevo ID de Moodle
        $new_moodle_id = (int)$moodle_id;
        if ($new_moodle_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Introduce un ID numérico de Moodle válido mayor que 0.']);
            exit();
        }

        // Verificar si el curso existe en Moodle (si la API está activa)
        $moodleApi = new MoodleAPI($pdo);
        $verifiedMoodleId = null;
        if ($moodleApi->isConfigured()) {
            try {
                $verifiedMoodleId = $moodleApi->findCourseId($new_moodle_id);
            } catch (Exception $e) {
                // En caso de fallo de red/API se permite usar el ID introducido por el usuario
            }
        }
        
        $effective_id = $verifiedMoodleId ? (int)$verifiedMoodleId : $new_moodle_id;

        $pdo->beginTransaction();

        // 1. Actualizar id_plataforma en la acción formativa
        $stmt1 = $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = ? WHERE id = ?");
        $stmt1->execute([$effective_id, $af_id]);

        // 2. Actualizar moodle_id en el curso asociado
        if (!empty($af['curso_id'])) {
            $stmt2 = $pdo->prepare("UPDATE cursos SET moodle_id = ? WHERE id = ?");
            $stmt2->execute([$effective_id, $af['curso_id']]);
        }

        // 3. Actualizar grupos: fijar codigo_plat al nuevo ID de Moodle y LIMPIAR id_plataforma
        // Esto es crucial: al poner id_plataforma = NULL en grupos, la sincronización re-creará o re-vinculará
        // los grupos en el nuevo curso de Moodle sin lanzar errores de duplicidad.
        $stmt3 = $pdo->prepare("UPDATE grupos SET codigo_plat = ?, id_plataforma = NULL WHERE accion_id = ?");
        $stmt3->execute([$effective_id, $af_id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Curso vinculado correctamente al ID #{$effective_id} en Moodle. Se han reseteado las IDs de grupo anteriores para prevenir duplicidades.",
            'new_moodle_id' => $effective_id
        ]);
        exit();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Error al procesar la vinculación: ' . $e->getMessage()]);
    exit();
}
