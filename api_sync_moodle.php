<?php
// api_sync_moodle.php
ob_start();

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (ob_get_level()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Error crítico en el servidor: ' . $error['message'] . ' (línea ' . $error['line'] . ')'
        ]);
    }
});

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

$af_id = (int)($_GET['id'] ?? 0);
if (!$af_id) { 
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']); 
    exit; 
}

try {
    $moodle = new MoodleAPI($pdo);
    if (!$moodle->isConfigured()) {
        throw new Exception("Moodle no está configurado correctamente en la base de datos.");
    }

    // 1. Obtener datos de la Acción Formativa y su Convocatoria
    $stmt = $pdo->prepare("SELECT af.*, 
                                  COALESCE(c.nombre_largo, af.titulo) as titulo, 
                                  COALESCE(c.nombre_corto, af.abreviatura) as nombre_corto, 
                                  c.moodle_id as curso_moodle_id,
                                  conv.nombre as convocatoria_nombre
                           FROM acciones_formativas af 
                           LEFT JOIN cursos c ON af.curso_id = c.id 
                           LEFT JOIN planes p ON af.plan_id = p.id
                           LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
                           WHERE af.id = ?");
    $stmt->execute([$af_id]);
    $af = $stmt->fetch();

    if (!$af) throw new Exception("Acción Formativa no encontrada.");

    // 2. Obtener el Grupo local (o crearlo)
    $stmtG = $pdo->prepare("SELECT id, id_plataforma, codigo_plat, codigo_plataforma, usuario_gestor, contrasena_gestor, tutor_id, tutor_id_2, tutor_reserva_id, fecha_inicio, fecha_fin FROM grupos WHERE accion_id = ? ORDER BY id ASC LIMIT 1");
    $stmtG->execute([$af_id]);
    $grupo = $stmtG->fetch();
    
    if (!$grupo) {
        $stmtInsGroup = $pdo->prepare("INSERT INTO grupos (accion_id, numero_grupo, estado) VALUES (?, '1', 'En proceso')");
        $stmtInsGroup->execute([$af_id]);
        $grupo_id_local = $pdo->lastInsertId();
        $grupo = [
            'id' => $grupo_id_local,
            'id_plataforma' => null,
            'codigo_plat' => null,
            'codigo_plataforma' => null,
            'usuario_gestor' => null,
            'contrasena_gestor' => null,
            'tutor_id' => null,
            'tutor_id_2' => null,
            'tutor_reserva_id' => null,
            'fecha_inicio' => null,
            'fecha_fin' => null
        ];
    } else {
        $grupo_id_local = $grupo['id'];
    }

    // 3. Resolver el ID del curso de Moodle priorizando codigo_plat, id_plataforma y curso_moodle_id
    $candidates = [
        $grupo['codigo_plat'] ?? null,
        $grupo['codigo_plataforma'] ?? null,
        $af['id_plataforma'] ?? null,
        $af['curso_moodle_id'] ?? null,
        $af['nombre_corto'] ?? null,
        $af['abreviatura'] ?? null
    ];

    $courseId = null;
    foreach ($candidates as $cand) {
        if (!empty($cand)) {
            $foundId = $moodle->findCourseId($cand);
            if ($foundId) {
                $courseId = $foundId;
                break;
            }
        }
    }

    if ($courseId) {
        // Actualizar localmente para mantener consistencia
        $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = ? WHERE id = ?")->execute([$courseId, $af_id]);
        if ($grupo_id_local) {
            $pdo->prepare("UPDATE grupos SET codigo_plat = ? WHERE id = ?")->execute([$courseId, $grupo_id_local]);
        }
    } else {
        // Crear curso en Moodle si no existía ningún curso previo
        $categoryId = 1;
        if (!empty($af['convocatoria_nombre'])) {
            try {
                $categoryId = $moodle->getOrCreateCategory($af['convocatoria_nombre']);
            } catch (Exception $ex) {
                $categoryId = 1;
            }
        }

        $fullname = !empty($af['titulo']) ? $af['titulo'] : (!empty($af['num_accion']) ? 'Accion ' . $af['num_accion'] : 'Curso AF-' . $af_id);
        $shortname = !empty($af['nombre_corto']) ? $af['nombre_corto'] : (!empty($af['abreviatura']) ? $af['abreviatura'] : 'CURSO-' . $af_id);
        
        $moodleResult = $moodle->createCourse($fullname, $shortname, $categoryId);
        if (isset($moodleResult[0]['id'])) {
            $courseId = $moodleResult[0]['id'];
            $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = ? WHERE id = ?")->execute([$courseId, $af_id]);
            if ($grupo_id_local) {
                $pdo->prepare("UPDATE grupos SET codigo_plat = ? WHERE id = ?")->execute([$courseId, $grupo_id_local]);
            }
        } else {
            throw new Exception("No se pudo crear el curso en Moodle.");
        }
    }
    
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

    // Determinar si el grupo ya ha finalizado (al final del día de la fecha de fin)
    $is_finished = false;
    if (!empty($grupo['fecha_fin'])) {
        $fecha_fin_time = strtotime($grupo['fecha_fin']);
        if ($fecha_fin_time && time() > ($fecha_fin_time + 86399)) {
            $is_finished = true;
        }
    }
    $student_status = $is_finished ? 1 : 0;

    // 5. Obtener alumnos matriculados localmente para esta acción formativa
    $stmt = $pdo->prepare("SELECT a.*, m.id as mat_id, m.grupo_id as mat_grupo_id 
                           FROM matriculas m 
                           JOIN alumnos a ON m.alumno_id = a.id 
                           WHERE m.grupo_id IN (SELECT id FROM grupos WHERE accion_id = ?)");
    $stmt->execute([$af_id]);
    $alumnos = $stmt->fetchAll();

    $syncCount = 0;
    $student_errors = [];
    foreach ($alumnos as $alumno) {
        $lastname = trim(($alumno['primer_apellido'] ?? '') . ' ' . ($alumno['segundo_apellido'] ?? ''));
        if (empty($lastname)) {
            $lastname = !empty($alumno['apellidos']) ? trim($alumno['apellidos']) : 'Sin apellidos';
        }
        $cleanDni = !empty($alumno['dni']) ? strtolower(trim(str_replace([' ', '-', '.'], '', $alumno['dni']))) : '';
        $username = !empty($alumno['plat_usuario']) ? $alumno['plat_usuario'] : (!empty($cleanDni) ? $cleanDni : strtolower(explode('@', $alumno['email'])[0]));
        $password = !empty($alumno['plat_clave']) ? $alumno['plat_clave'] : (!empty($cleanDni) ? ('Edite' . str_replace(['-', '.', ' '], '', $alumno['dni']) . '!') : 'Efp2026!');

        $userData = [
            'firstname' => $alumno['nombre'],
            'lastname' => $lastname,
            'email' => $alumno['email'],
            'username' => $username,
            'password' => $password
        ];

        try {
            // Sincronizar (Crear/Matricular/Meter en grupo) con estado activo o suspendido
            $moodleUserId = $moodle->provisionStudent($courseId, $moodleGroupId, $userData, $student_status);
            
            if ($moodleUserId) {
                $pdo->prepare("UPDATE alumnos SET moodle_user_id = ?, plat_usuario = ?, plat_clave = ? WHERE id = ?")
                    ->execute([$moodleUserId, $username, $password, $alumno['id']]);
                $syncCount++;
            } else {
                $student_errors[] = "No se obtuvo ID para {$alumno['nombre']} {$lastname}";
            }
        } catch (Exception $studentEx) {
            $student_errors[] = "Error con {$alumno['nombre']}: " . $studentEx->getMessage();
        }
    }

    // 6. Sincronizar el Usuario Gestor (ej: INSPECTOR SEPE)
    $raw_gestor = trim($grupo['usuario_gestor'] ?? '');
    $gestor_msg = '';
    if (!empty($raw_gestor)) {
        try {
            $gestor_pass = !empty($grupo['contrasena_gestor']) ? trim($grupo['contrasena_gestor']) : 'InspectorSepe-2026*';
            $gestorUserId = $moodle->provisionInspector($courseId, $moodleGroupId, $raw_gestor, $gestor_pass);
            if ($gestorUserId) {
                $clean_username = preg_replace('/[^a-z0-9_.-]/', '', strtolower(str_replace(' ', '_', $raw_gestor)));
                $gestor_msg = " | Usuario gestor '$clean_username' sincronizado en Moodle";
            }
        } catch (Exception $gestorEx) {
            $gestor_msg = " | Error al sincronizar gestor: " . $gestorEx->getMessage();
        }
    }

    // 7. Sincronizar Tutores (ej: Profesor con rol_id = 3)
    $tutor_msg = '';
    $tutor_ids = array_filter([$grupo['tutor_id'] ?? null, $grupo['tutor_id_2'] ?? null, $grupo['tutor_reserva_id'] ?? null]);
    if (!empty($tutor_ids)) {
        $tutors_synced = [];
        foreach ($tutor_ids as $tid) {
            try {
                $stmtT = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmtT->execute([$tid]);
                $tutor_user = $stmtT->fetch();
                
                if ($tutor_user && !empty($tutor_user['email'])) {
                    $t_username = $tutor_user['username'] ?: strtolower(explode('@', $tutor_user['email'])[0]);
                    $t_username = preg_replace('/[^a-z0-9_.-]/', '', strtolower($t_username));
                    
                    $existingT = $moodle->getUsersByField('email', [$tutor_user['email']]);
                    $moodleTutorId = null;
                    if (!empty($existingT) && isset($existingT['users'][0])) {
                        $moodleTutorId = $existingT['users'][0]['id'];
                    } else {
                        $newT = $moodle->createUser(
                            $t_username,
                            'EditeTutor-2026*',
                            $tutor_user['nombre'],
                            $tutor_user['apellidos'] ?: 'Tutor',
                            $tutor_user['email']
                        );
                        if (isset($newT[0]['id'])) {
                            $moodleTutorId = $newT[0]['id'];
                        }
                    }
                    
                    if ($moodleTutorId) {
                        $moodle->enrolUser($moodleTutorId, $courseId, 3);
                        $tutors_synced[] = $tutor_user['nombre'];
                    }
                }
            } catch (Exception $tutorEx) {
                // Silencioso, continuamos con el siguiente tutor
            }
        }
        if (!empty($tutors_synced)) {
            $tutor_msg = " | Tutores: " . implode(', ', $tutors_synced);
        }
    }

    if ($syncCount === 0 && count($alumnos) > 0 && !empty($student_errors)) {
        throw new Exception("No se pudieron crear/matricular los alumnos en Moodle. " . implode(" | ", $student_errors));
    }

    $finished_suffix = $is_finished ? " (Curso finalizado: alumnos suspendidos en Moodle)" : " (Curso activo)";
    $err_suffix = !empty($student_errors) ? (" [Avisos: " . implode('; ', $student_errors) . "]") : "";
    
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true, 
        'message' => "Sincronización exitosa. Curso ID: $courseId, Alumnos sincronizados: $syncCount" . $finished_suffix . $err_suffix . $gestor_msg . $tutor_msg
    ]);

} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
