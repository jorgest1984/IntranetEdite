<?php
// borrar_af.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

if (!has_permission([ROLE_ADMIN])) {
    die("No tiene permisos para eliminar acciones formativas.");
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header("Location: acciones_formativas.php?error=ID no proporcionado");
    exit;
}

try {
    // 1. Obtener datos de la acción formativa y el moodle_id del curso antes de borrar
    $stmtInfo = $pdo->prepare("SELECT af.*, c.moodle_id FROM acciones_formativas af JOIN cursos c ON af.curso_id = c.id WHERE af.id = ?");
    $stmtInfo->execute([$id]);
    $af_data = $stmtInfo->fetch();
    
    if (!$af_data) {
        throw new Exception("La acción formativa no existe.");
    }
    
    $moodleCourseId = $af_data['moodle_id'];
    $cursoId = $af_data['curso_id'];
    
    // 2. Eliminar del Aula Virtual (Moodle) si tiene ID asociado
    $moodleDeleted = false;
    if ($moodleCourseId) {
        $moodle = new MoodleAPI($pdo);
        if ($moodle->isConfigured()) {
            try {
                $moodle->deleteCourse($moodleCourseId);
                $moodleDeleted = true;
            } catch (Exception $moodleEx) {
                // Silencioso: si el curso ya fue borrado en Moodle, continuamos para no bloquear la eliminación local
            }
        }
    }
    
    // 3. Limpiar la base de datos local de forma robusta (evitando fallos de foreign keys)
    $pdo->beginTransaction();
    
    // Obtener los grupos asociados a esta acción formativa
    $stmtGrupos = $pdo->prepare("SELECT id FROM grupos WHERE accion_id = ?");
    $stmtGrupos->execute([$id]);
    $grupoIds = $stmtGrupos->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($grupoIds)) {
        // Borrar las matrículas de estos grupos
        $inQuery = implode(',', array_fill(0, count($grupoIds), '?'));
        $stmtDelMat = $pdo->prepare("DELETE FROM matriculas WHERE grupo_id IN ($inQuery)");
        $stmtDelMat->execute($grupoIds);
        
        // Borrar los grupos
        $pdo->prepare("DELETE FROM grupos WHERE accion_id = ?")->execute([$id]);
    }
    
    // Eliminar la acción formativa
    $stmt = $pdo->prepare("DELETE FROM acciones_formativas WHERE id = ?");
    $stmt->execute([$id]);
    
    // Si se borró en Moodle, limpiamos el moodle_id en la tabla de cursos
    if ($moodleDeleted) {
        $pdo->prepare("UPDATE cursos SET moodle_id = NULL WHERE id = ?")->execute([$cursoId]);
    }
    
    $pdo->commit();
    
    // 4. Registrar log de auditoría
    audit_log($pdo, 'DELETE_ACCION_FORMATIVA', 'acciones_formativas', $id, $af_data, null);
    
    $msg = "Acción formativa eliminada correctamente" . ($moodleDeleted ? " (también eliminada de Moodle)" : "");
    header("Location: acciones_formativas.php?msg=" . urlencode($msg));
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: acciones_formativas.php?error=" . urlencode($e->getMessage()));
}
exit;
