<?php
// cron_suspend_finished_courses.php
// Script autónomo programable vía cron/Plesk para suspender matrículas de alumnos en cursos finalizados

// Asegurar ejecución desde CLI o con permisos
if (php_sapi_name() !== 'cli') {
    // Si se accede vía web, requerir autenticación o clave secreta por seguridad
    require_once 'includes/auth.php';
    if (!has_permission([ROLE_ADMIN])) {
        die("Acceso no autorizado.");
    }
} else {
    // Si es por CLI, cargar configuración básica manualmente
    require_once __DIR__ . '/includes/config.php';
}

require_once __DIR__ . '/includes/moodle_api.php';

echo "=== INICIANDO SUSPENSIÓN DE ALUMNOS EN CURSOS FINALIZADOS ===\n";
echo "Fecha actual: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $moodle = new MoodleAPI($pdo);
    if (!$moodle->isConfigured()) {
        die("ERROR: Moodle no está configurado.\n");
    }

    // 1. Buscar todos los grupos que hayan finalizado (fecha_fin anterior a hoy)
    // y que tengan un curso Moodle asociado (id_plataforma en acciones_formativas)
    $stmt = $pdo->query("SELECT g.id as grupo_id, g.numero_grupo, g.fecha_fin, af.id_plataforma as course_moodle_id, c.nombre_largo as curso_titulo
                          FROM grupos g 
                          JOIN acciones_formativas af ON g.accion_id = af.id
                          JOIN cursos c ON af.curso_id = c.id
                          WHERE g.fecha_fin IS NOT NULL 
                            AND g.fecha_fin < CURDATE() 
                            AND af.id_plataforma IS NOT NULL 
                            AND af.id_plataforma != ''");
    $grupos_finalizados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($grupos_finalizados)) {
        echo "No hay grupos finalizados para procesar.\n";
        exit;
    }

    echo "Se han encontrado " . count($grupos_finalizados) . " grupos finalizados. Procesando...\n\n";

    foreach ($grupos_finalizados as $grupo) {
        $grupo_id = $grupo['grupo_id'];
        $course_id = (int)$grupo['course_moodle_id'];
        
        echo "Grupo: {$grupo['numero_grupo']} (ID: $grupo_id) - Curso: '{$grupo['curso_titulo']}' (Moodle Course ID: $course_id) - Fecha Fin: {$grupo['fecha_fin']}\n";
        
        // Obtener alumnos matriculados en este grupo
        $stmtAl = $pdo->prepare("SELECT a.id, a.nombre, a.apellidos, a.moodle_user_id, a.email 
                                 FROM matriculas m 
                                 JOIN alumnos a ON m.alumno_id = a.id 
                                 WHERE m.grupo_id = ?");
        $stmtAl->execute([$grupo_id]);
        $alumnos = $stmtAl->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($alumnos)) {
            echo "   -> Sin alumnos matriculados.\n\n";
            continue;
        }

        $suspended_count = 0;
        foreach ($alumnos as $alumno) {
            $moodle_user_id = $alumno['moodle_user_id'];
            
            // Si no tenemos el moodle_user_id local, intentar buscarlo en Moodle por email
            if (empty($moodle_user_id) && !empty($alumno['email'])) {
                try {
                    $existing = $moodle->getUsersByField('email', [$alumno['email']]);
                    if (!empty($existing) && isset($existing['users'][0])) {
                        $moodle_user_id = $existing['users'][0]['id'];
                        // Guardar localmente
                        $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?")->execute([$moodle_user_id, $alumno['id']]);
                    }
                } catch (Exception $ex) {}
            }

            if (!empty($moodle_user_id)) {
                try {
                    // Enrol student as suspended (role ID 5, status 1)
                    $moodle->enrolUser($moodle_user_id, $course_id, 5, 1);
                    echo "   -> Alumno '{$alumno['nombre']} {$alumno['apellidos']}' [ID: $moodle_user_id] SUSPENDIDO.\n";
                    $suspended_count++;
                } catch (Exception $enrolEx) {
                    echo "   -> ERROR al suspender alumno '{$alumno['nombre']}': " . $enrolEx->getMessage() . "\n";
                }
            } else {
                echo "   -> ALUMNO '{$alumno['nombre']}': Sin Moodle User ID y no se encontró por email.\n";
            }
        }
        echo "   -> Completado: $suspended_count alumnos suspendidos en este curso.\n\n";
    }

    echo "=== PROCESO FINALIZADO CON ÉXITO ===\n";

} catch (Exception $e) {
    echo "ERROR CRÍTICO: " . $e->getMessage() . "\n";
}
