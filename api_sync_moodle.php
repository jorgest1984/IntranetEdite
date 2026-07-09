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

    // 1. Obtener datos de la Acción Formativa y su Convocatoria
    // Usamos af.id_plataforma como ID del curso en Moodle (se edita desde editar_af.php)
    $stmt = $pdo->prepare("SELECT af.*, c.nombre_largo as titulo, c.nombre_corto, conv.nombre as convocatoria_nombre
                           FROM acciones_formativas af 
                           JOIN cursos c ON af.curso_id = c.id 
                           LEFT JOIN planes p ON af.plan_id = p.id
                           LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
                           WHERE af.id = ?");
    $stmt->execute([$af_id]);
    $af = $stmt->fetch();

    if (!$af) throw new Exception("Acción Formativa no encontrada.");

    // El ID del curso en Moodle se guarda en acciones_formativas.id_plataforma
    $courseId = $af['id_plataforma'] ?: null;
    
    // Validar si el curso guardado realmente existe en Moodle
    if ($courseId) {
        if (!$moodle->courseExists($courseId)) {
            $courseId = null;
            // Limpiar localmente para forzar su recreación
            $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = NULL WHERE id = ?")->execute([$af_id]);
        }
    }
    
    // 2. Si la AF no tiene ID de Moodle, crear el curso
    if (!$courseId) {
        $categoryId = 1; // Por defecto, Miscellaneous
        if (!empty($af['convocatoria_nombre'])) {
            try {
                $categoryId = $moodle->getOrCreateCategory($af['convocatoria_nombre']);
            } catch (Exception $ex) {
                $categoryId = 1;
            }
        }

        $fullname = $af['titulo'];
        $shortname = $af['abreviatura'] ?: 'CURSO-' . $af_id;
        $moodleResult = $moodle->createCourse($fullname, $shortname, $categoryId);
        if (isset($moodleResult[0]['id'])) {
            $courseId = $moodleResult[0]['id'];
            // Guardar el nuevo ID en acciones_formativas.id_plataforma
            $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = ? WHERE id = ?")->execute([$courseId, $af_id]);
        } else {
            throw new Exception("No se pudo crear el curso en Moodle.");
        }
    }

    // 3. Obtener el Grupo local (o crearlo)
    $stmt = $pdo->prepare("SELECT id, id_plataforma, usuario_gestor, contrasena_gestor FROM grupos WHERE accion_id = ? LIMIT 1");
    $stmt->execute([$af_id]);
    $grupo = $stmt->fetch();
    $grupo_id_local = $grupo['id'];
    $moodleGroupId = $grupo['id_plataforma'];

    // Validar si el grupo guardado realmente existe en Moodle
    if ($moodleGroupId) {
        if (!$moodle->groupExists($moodleGroupId)) {
            $moodleGroupId = null;
            // Limpiar localmente para forzar su recreación
            $pdo->prepare("UPDATE grupos SET id_plataforma = NULL WHERE id = ?")->execute([$grupo_id_local]);
        }
    }

    // 4. Si el grupo no tiene ID de plataforma (Moodle) válido, lo creamos en Moodle
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

    // 6. Sincronizar el Usuario Gestor (ej: INSPECTOR SEPE)
    $raw_gestor = trim($grupo['usuario_gestor'] ?? '');
    $gestor_msg = '';
    if (!empty($raw_gestor)) {
        try {
            // Limpiar nombre de usuario para compatibilidad con Moodle
            $gestor_username = preg_replace('/[^a-z0-9_.-]/', '', strtolower(str_replace(' ', '_', $raw_gestor)));
            $gestor_email = $gestor_username . '@avefp.es';
            $gestor_pass = !empty($grupo['contrasena_gestor']) ? trim($grupo['contrasena_gestor']) : 'InspectorSepe2026!';
            
            $existingGestor = $moodle->getUsersByField('username', [$gestor_username]);
            $gestorUserId = null;
            if (!empty($existingGestor) && isset($existingGestor['users'][0])) {
                $gestorUserId = $existingGestor['users'][0]['id'];
            } else {
                $newGestor = $moodle->createUser(
                    $gestor_username,
                    $gestor_pass,
                    'Inspector',
                    'SEPE',
                    $gestor_email
                );
                if (!empty($newGestor) && isset($newGestor[0]['id'])) {
                    $gestorUserId = $newGestor[0]['id'];
                }
            }
            
            if ($gestorUserId) {
                // Matricular como Profesor sin permiso de edición (rol_id = 4)
                $moodle->enrolUser($gestorUserId, $courseId, 4);
                $gestor_msg = " | Usuario gestor '$gestor_username' sincronizado en Moodle";
            }
        } catch (Exception $gestorEx) {
            $gestor_msg = " | Error al sincronizar gestor: " . $gestorEx->getMessage();
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => "Sincronización exitosa. Curso ID: $courseId, Alumnos sincronizados: $syncCount" . $gestor_msg
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
