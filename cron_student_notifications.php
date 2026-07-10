<?php
// cron_student_notifications.php
// Script autónomo programable vía cron/Plesk para enviar notificaciones automáticas y gestionar bajas del 25%

// Asegurar ejecución desde CLI o con permisos de administrador
if (php_sapi_name() !== 'cli') {
    require_once 'includes/auth.php';
    if (!has_permission([ROLE_ADMIN])) {
        die("Acceso no autorizado.");
    }
} else {
    require_once __DIR__ . '/includes/config.php';
}

require_once __DIR__ . '/includes/moodle_db.php';
require_once __DIR__ . '/includes/moodle_api.php';
require_once __DIR__ . '/includes/smtp_mailer.php';

echo "=== INICIANDO CRON DE NOTIFICACIONES Y GESTIÓN DE ACCESOS ===\n";
echo "Fecha actual: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. Auto-healing de base de datos: comprobar y añadir columnas de control si faltan en matriculas
    $stmtCols = $pdo->query("DESCRIBE matriculas");
    $existingCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

    $missingCols = [
        'notificacion_25_enviada' => 'TINYINT(1) DEFAULT 0',
        'notificacion_50_enviada' => 'TINYINT(1) DEFAULT 0',
        'notificacion_fin_enviada' => 'TINYINT(1) DEFAULT 0'
    ];

    foreach ($missingCols as $col => $definition) {
        if (!in_array($col, $existingCols)) {
            $pdo->exec("ALTER TABLE matriculas ADD COLUMN `$col` $definition");
            echo "   [BD] Añadida columna `$col` a la tabla matriculas.\n";
        }
    }

    // Instanciar conectores
    $moodleDb = new MoodleDB();
    $moodleApi = new MoodleAPI($pdo);
    if (!$moodleApi->isConfigured()) {
        die("ERROR: Moodle no está configurado.\n");
    }

    // 2. Obtener grupos activos o que hayan finalizado hace menos de 3 días para procesamiento robusto
    $stmtGrupos = $pdo->query("SELECT g.*, af.id_plataforma as course_moodle_id, c.nombre_largo as curso_titulo
                               FROM grupos g
                               JOIN acciones_formativas af ON g.accion_id = af.id
                               JOIN cursos c ON af.curso_id = c.id
                               WHERE g.fecha_fin IS NOT NULL 
                                 AND g.fecha_fin >= CURDATE() - INTERVAL 3 DAY");
    $grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

    if (empty($grupos)) {
        echo "No hay grupos activos para procesar.\n";
        exit;
    }

    $hoy = date('Y-m-d');
    echo "Procesando " . count($grupos) . " grupos...\n\n";

    foreach ($grupos as $grupo) {
        $grupo_id = $grupo['id'];
        $course_id = (int)($grupo['course_moodle_id'] ?: 0);
        
        if (!$course_id) {
            echo "Grupo {$grupo['numero_grupo']} (ID: $grupo_id) no tiene curso en Moodle asociado. Omitiendo.\n";
            continue;
        }

        echo "Grupo: {$grupo['numero_grupo']} (ID: $grupo_id) - Curso: '{$grupo['curso_titulo']}'\n";
        
        // Hitos del grupo
        $fecha_25 = $grupo['fecha_25'] ? date('Y-m-d', strtotime($grupo['fecha_25'])) : null;
        $fecha_mitad = ($grupo['fecha_mitad'] ?: $grupo['fecha_1_2_curso']) ? date('Y-m-d', strtotime($grupo['fecha_mitad'] ?: $grupo['fecha_1_2_curso'])) : null;
        $fecha_3_dias = ($grupo['fecha_3_dias_fin']) ? date('Y-m-d', strtotime($grupo['fecha_3_dias_fin'])) : ($grupo['fecha_fin'] ? date('Y-m-d', strtotime($grupo['fecha_fin'] . ' -3 days')) : null);
        $fecha_fin = $grupo['fecha_fin'] ? date('Y-m-d', strtotime($grupo['fecha_fin'])) : null;

        // Obtener alumnos del grupo
        $stmtAl = $pdo->prepare("SELECT m.id as matricula_id, m.estado as matricula_estado, 
                                        m.notificacion_25_enviada, m.notificacion_50_enviada, m.notificacion_fin_enviada,
                                        m.moodle_first_access,
                                        a.id as alumno_id, a.nombre, a.primer_apellido, a.email, a.dni, a.moodle_user_id
                                 FROM matriculas m
                                 JOIN alumnos a ON m.alumno_id = a.id
                                 WHERE m.grupo_id = ?");
        $stmtAl->execute([$grupo_id]);
        $alumnos = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

        if (empty($alumnos)) {
            echo "   -> Sin alumnos matriculados.\n\n";
            continue;
        }

        // Sincronizar accesos desde Moodle antes de evaluar para tener los datos más recientes
        if ($moodleDb->isConnected()) {
            $moodleUserIds = array_filter(array_column($alumnos, 'moodle_user_id'));
            if (!empty($moodleUserIds)) {
                try {
                    $stats = $moodleDb->fetchStudentStats($course_id, $moodleUserIds);
                    foreach ($alumnos as &$al) {
                        $muid = $al['moodle_user_id'];
                        if ($muid && isset($stats[$muid])) {
                            $first_acc = $stats[$muid]['first_access'];
                            $last_acc = $stats[$muid]['last_access'];
                            $conn_time = $stats[$muid]['connected_seconds'];
                            
                            // Actualizar localmente
                            $stmtUpd = $pdo->prepare("UPDATE matriculas SET moodle_first_access = ?, moodle_last_access = ?, moodle_connected_time = ?, moodle_last_sync = NOW() WHERE id = ?");
                            $stmtUpd->execute([$first_acc, $last_acc, $conn_time, $al['matricula_id']]);
                            
                            // Actualizar en el array
                            $al['moodle_first_access'] = $first_acc;
                        }
                    }
                } catch (Exception $syncEx) {
                    echo "   [!] Advertencia al sincronizar accesos: " . $syncEx->getMessage() . "\n";
                }
            }
        }

        // Evaluar hitos
        foreach ($alumnos as $alumno) {
            $nombre_completo = trim($alumno['nombre'] . ' ' . $alumno['primer_apellido']);
            $email = trim($alumno['email']);
            $moodle_uid = $alumno['moodle_user_id'];
            $first_access = $alumno['moodle_first_access'];
            $has_accessed = ($first_access && $first_access !== '0000-00-00 00:00:00' && $first_access !== '1970-01-01 00:00:00');

            if (empty($email)) {
                echo "   -> Alumno '{$nombre_completo}' no tiene email configurado. Omitiendo notificaciones.\n";
                continue;
            }

            // 1. Alerta del 25% (Día de antes de la fecha del 25%)
            if ($fecha_25 && !$has_accessed && $alumno['notificacion_25_enviada'] == 0) {
                $dia_antes_25 = date('Y-m-d', strtotime($fecha_25 . ' -1 day'));
                if ($hoy === $dia_antes_25) {
                    $subject = "⚠️ IMPORTANTE: Acceso pendiente a tu curso en el Aula Virtual";
                    $pass = 'Edite' . str_replace(['-', '.', ' '], '', $alumno['dni']) . '!';
                    $body = "Hola " . $alumno['nombre'] . ",\n\n"
                          . "Te recordamos que tu curso '" . $grupo['curso_titulo'] . "' se encuentra en marcha. Aún no registramos tu primer acceso a nuestra Aula Virtual.\n\n"
                          . "Es obligatorio que accedas como tarde mañana (" . date('d/m/Y', strtotime($fecha_25)) . "), ya que si no registras tu primer acceso antes de esa fecha límite, la plataforma te dará de baja de forma automática y perderás tu plaza en el curso.\n\n"
                          . "Por favor, accede cuanto antes haciendo clic aquí:\n" . MOODLE_AULA_VIRTUAL_URL . "\n\n"
                          . "Tus credenciales de acceso son:\n"
                          . "- Usuario: " . $alumno['dni'] . "\n"
                          . "- Contraseña: " . $pass . "\n\n"
                          . "Un saludo,\n"
                          . "Equipo de Formación Grupo EFP.";
                    
                    if (send_smtp_email($email, $subject, $body)) {
                        $pdo->prepare("UPDATE matriculas SET notificacion_25_enviada = 1 WHERE id = ?")->execute([$alumno['matricula_id']]);
                        echo "   -> Alerta 25% enviada a '{$nombre_completo}' ({$email})\n";
                    }
                }
            }

            // 2. Baja por falta de acceso del 25% (El día del 25% o posterior si sigue inscrito)
            if ($fecha_25 && !$has_accessed && $alumno['matricula_estado'] !== 'Baja') {
                if ($hoy >= $fecha_25) {
                    // Dar de baja localmente
                    $pdo->prepare("UPDATE matriculas SET estado = 'Baja' WHERE id = ?")->execute([$alumno['matricula_id']]);
                    
                    // Suspender matrícula en Moodle (status = 1) para bloquear el acceso sin borrar el historial
                    if ($moodle_uid) {
                        try {
                            $moodleApi->enrolUser($moodle_uid, $course_id, 5, 1);
                            $moodle_msg = "Bloqueado en Moodle";
                        } catch (Exception $moodleEx) {
                            $moodle_msg = "Error Moodle: " . $moodleEx->getMessage();
                        }
                    } else {
                        $moodle_msg = "Sin Moodle User ID";
                    }

                    // Enviar email de notificación de baja
                    $subject = "Baja automática del curso por falta de acceso";
                    $body = "Hola " . $alumno['nombre'] . ",\n\n"
                          . "Lamentamos informarte que, debido a que no has accedido al Aula Virtual antes de la fecha límite del 25% del curso (" . date('d/m/Y', strtotime($fecha_25)) . "), se ha procedido a darte de baja del curso '" . $grupo['curso_titulo'] . "' de manera automática.\n\n"
                          . "Si consideras que se trata de un error o deseas consultar opciones de reincorporación, por favor contacta con nosotros respondiendo a este email.\n\n"
                          . "Un saludo,\n"
                          . "Equipo de Formación Grupo EFP.";
                    
                    send_smtp_email($email, $subject, $body);
                    echo "   -> Alumno '{$nombre_completo}' dado de BAJA por falta de acceso ($moodle_msg).\n";
                }
            }

            // 3. Mensaje del 50% (Día de mitad de curso)
            if ($fecha_mitad && $alumno['notificacion_50_enviada'] == 0 && $alumno['matricula_estado'] !== 'Baja') {
                if ($hoy === $fecha_mitad) {
                    $subject = "📈 Progreso del curso: ¡Has alcanzado el 50%!";
                    $body = "Hola " . $alumno['nombre'] . ",\n\n"
                          . "Te informamos que hoy es la fecha del 50% (mitad de curso) de tu curso '" . $grupo['curso_titulo'] . "'.\n\n"
                          . "Te animamos a seguir ingresando regularmente al Aula Virtual y completando tus evaluaciones y actividades pendientes para asegurar el aprovechamiento del curso.\n\n"
                          . "Puedes acceder al Aula Virtual aquí:\n" . MOODLE_AULA_VIRTUAL_URL . "\n\n"
                          . "Un saludo,\n"
                          . "Equipo de Formación Grupo EFP.";
                    
                    if (send_smtp_email($email, $subject, $body)) {
                        $pdo->prepare("UPDATE matriculas SET notificacion_50_enviada = 1 WHERE id = ?")->execute([$alumno['matricula_id']]);
                        echo "   -> Notificación 50% enviada a '{$nombre_completo}' ({$email})\n";
                    }
                }
            }

            // 4. Mensaje de Fin de Curso (Faltan 3 días)
            if ($fecha_3_dias && $alumno['notificacion_fin_enviada'] == 0 && $alumno['matricula_estado'] !== 'Baja') {
                if ($hoy === $fecha_3_dias) {
                    $subject = "⏳ Faltan 3 días para finalizar tu curso";
                    $body = "Hola " . $alumno['nombre'] . ",\n\n"
                          . "Te recordamos que tu curso '" . $grupo['curso_titulo'] . "' finalizará en 3 días (el " . date('d/m/Y', strtotime($fecha_fin)) . ").\n\n"
                          . "Por favor, asegúrate de haber realizado todas las evaluaciones, exámenes y cuestionarios de calidad en el Aula Virtual antes del cierre del curso.\n\n"
                          . "Acceso al Aula Virtual:\n" . MOODLE_AULA_VIRTUAL_URL . "\n\n"
                          . "Un saludo,\n"
                          . "Equipo de Formación Grupo EFP.";
                    
                    if (send_smtp_email($email, $subject, $body)) {
                        $pdo->prepare("UPDATE matriculas SET notificacion_fin_enviada = 1 WHERE id = ?")->execute([$alumno['matricula_id']]);
                        echo "   -> Alerta 3 días fin enviada a '{$nombre_completo}' ({$email})\n";
                    }
                }
            }
        }
        echo "\n";
    }

    echo "=== PROCESO FINALIZADO CON ÉXITO ===\n";

} catch (Exception $e) {
    echo "ERROR CRÍTICO: " . $e->getMessage() . "\n";
}
