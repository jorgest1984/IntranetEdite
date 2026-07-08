<?php
// api_sync_moodle.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

header('Content-Type: application/json');

$af_id = (int)($_GET['id'] ?? 0);
if (!$af_id) { echo json_encode(['success' => false, 'error' => 'ID no proporcionado']); exit; }

try {
    $moodle = new MoodleAPI($pdo);
    if (!$moodle->isConfigured()) {
        throw new Exception("Moodle no está configurado correctamente en la base de datos.");
    }

    // 1. Obtener datos de la Acción Formativa, su Curso Moodle y su Convocatoria
    $stmt = $pdo->prepare("SELECT af.*, c.moodle_id as curso_moodle_id, c.nombre_largo as titulo, c.nombre_corto, conv.nombre as convocatoria_nombre
                           FROM acciones_formativas af 
                           JOIN cursos c ON af.curso_id = c.id 
                           LEFT JOIN planes p ON af.plan_id = p.id
                           LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
                           WHERE af.id = ?");
    $stmt->execute([$af_id]);
    $af = $stmt->fetch();

    if (!$af) throw new Exception("Acción Formativa no encontrada.");

    $courseId = $af['curso_moodle_id'];
    
    // Validar si el curso guardado localmente realmente existe en Moodle
    if ($courseId) {
        if (!$moodle->courseExists($courseId)) {
            $courseId = null;
            // Limpiar localmente para forzar su recreación
            $pdo->prepare("UPDATE cursos SET moodle_id = NULL WHERE id = ?")->execute([$af['curso_id']]);
        }
    }
    
    // 2. Si el curso no tiene ID de Moodle, lo creamos
    if (!$courseId) {
        $categoryId = 1; // Por defecto, Miscellaneous
        if (!empty($af['convocatoria_nombre'])) {
            try {
                $categoryId = $moodle->getOrCreateCategory($af['convocatoria_nombre']);
            } catch (Exception $ex) {
                // Fallback gracioso si no se tienen permisos o funciones para crear categorías
                $categoryId = 1;
            }
        }

        $fullname = $af['titulo'];
        $shortname = $af['abreviatura'] ?: 'CURSO-' . $af['id'];
        $moodleResult = $moodle->createCourse($fullname, $shortname, $categoryId);
        if (isset($moodleResult[0]['id'])) {
            $courseId = $moodleResult[0]['id'];
            // Guardamos el ID en nuestra base de datos local para futuros usos
            $upd = $pdo->prepare("UPDATE cursos SET moodle_id = ? WHERE id = ?");
            $upd->execute([$courseId, $af['curso_id']]);
        } else {
            throw new Exception("No se pudo crear el curso en Moodle.");
        }
    }

    // 3. Obtener el Grupo local (o crearlo)
    $stmt = $pdo->prepare("SELECT id, id_plataforma FROM grupos WHERE accion_id = ? LIMIT 1");
    $stmt->execute([$af_id]);
    $grupo = $stmt->fetch();
    $grupo_id_local = $grupo['id'];
    $moodleGroupId = $grupo['id_plataforma'];

    // 4. Si el grupo no tiene ID de plataforma (Moodle), lo creamos en Moodle
    if (!$moodleGroupId) {
        $moodleGroupResult = $moodle->createGroup($courseId, "GRUPO-" . $grupo_id_local);
        if (isset($moodleGroupResult[0]['id'])) {
            $moodleGroupId = $moodleGroupResult[0]['id'];
            $pdo->prepare("UPDATE grupos SET id_plataforma = ? WHERE id = ?")->execute([$moodleGroupId, $grupo_id_local]);
        }
    }

    // 5. Obtener alumnos matriculados localmente
    $stmt = $pdo->prepare("SELECT a.* FROM matriculas m JOIN alumnos a ON m.alumno_id = a.id WHERE m.grupo_id = ?");
    $stmt->execute([$grupo_id_local]);
    $alumnos = $stmt->fetchAll();

    $syncCount = 0;
    foreach ($alumnos as $alumno) {
        $userData = [
            'firstname' => $alumno['nombre'],
            'lastname' => $alumno['apellidos'] ?? 'Sin apellidos',
            'email' => $alumno['email'],
            'username' => $alumno['dni'], // Usamos el DNI como username por defecto
            'password' => 'Edite' . str_replace(['-', '.', ' '], '', $alumno['dni']) . '!' // Password predecible: EditeDNI!
        ];

        // Sincronizar (Crear/Matricular/Meter en grupo)
        $moodleUserId = $moodle->provisionStudent($courseId, $moodleGroupId, $userData);
        
        // Actualizar el moodle_user_id local si no lo tenía
        if ($moodleUserId && $alumno['moodle_user_id'] != $moodleUserId) {
            $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?")->execute([$moodleUserId, $alumno['id']]);
        }
        $syncCount++;
    }

    echo json_encode([
        'success' => true, 
        'message' => "Sincronización exitosa. Curso ID: $courseId, Alumnos sincronizados: $syncCount"
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
