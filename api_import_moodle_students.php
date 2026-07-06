<?php
// api_import_moodle_students.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';
require_once 'includes/moodle_db.php';

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

$af_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$af_id) {
    echo json_encode(['success' => false, 'error' => 'Identificador de acción formativa inválido.']);
    exit();
}

try {
    // 3. Obtener la Acción Formativa y el Curso Moodle vinculado
    $stmt = $pdo->prepare("SELECT af.*, c.moodle_id as curso_moodle_id, c.nombre_largo as titulo, c.nombre_corto 
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

    // 4. Consultar alumnos matriculados (Primero por base de datos, luego fallback a la API)
    $moodleUsers = [];
    $moodleDb = new MoodleDB();
    if ($moodleDb->isConnected()) {
        try {
            $mpdo = $moodleDb->getPDO();
            $prefix = defined('MOODLE_DB_PREFIX') ? MOODLE_DB_PREFIX : 'mdl_';
            $sqlUsers = "SELECT u.id, u.username, u.firstname, u.lastname, u.email
                         FROM {$prefix}user u
                         JOIN {$prefix}user_enrolments ue ON ue.userid = u.id
                         JOIN {$prefix}enrol e ON e.id = ue.enrolid
                         WHERE e.courseid = ? AND u.deleted = 0";
            $stmtUsers = $mpdo->prepare($sqlUsers);
            $stmtUsers->execute([$courseMoodleId]);
            $moodleUsers = $stmtUsers->fetchAll();
        } catch (Exception $dbEx) {
            $moodleUsers = [];
        }
    }

    // Fallback a la API si la consulta por BD no devolvió nada o no está conectada
    if (empty($moodleUsers)) {
        $moodleApi = new MoodleAPI($pdo);
        if ($moodleApi->isConfigured()) {
            $moodleUsers = $moodleApi->getEnrolledUsers($courseMoodleId);
        }
    }

    if (empty($moodleUsers) || !is_array($moodleUsers)) {
        echo json_encode(['success' => false, 'error' => 'No se encontraron alumnos matriculados en el curso de Moodle.']);
        exit();
    }

    // 5. Obtener Convocatoria ID asociada
    $stmtConv = $pdo->prepare("SELECT p.convocatoria_id 
                               FROM acciones_formativas af
                               LEFT JOIN planes p ON af.plan_id = p.id
                               WHERE af.id = ?");
    $stmtConv->execute([$af_id]);
    $convRow = $stmtConv->fetch();
    $convocatoria_id = $convRow && !empty($convRow['convocatoria_id']) ? (int)$convRow['convocatoria_id'] : 0;
    if (!$convocatoria_id) {
        // Fallback a la última convocatoria del sistema
        $stmtFallback = $pdo->query("SELECT id FROM convocatorias ORDER BY id DESC LIMIT 1");
        $fallbackRow = $stmtFallback->fetch();
        $convocatoria_id = $fallbackRow ? (int)$fallbackRow['id'] : 1;
    }

    // 6. Obtener o crear grupo local para esta Acción Formativa
    $stmtGroup = $pdo->prepare("SELECT id FROM grupos WHERE accion_id = ? ORDER BY id ASC LIMIT 1");
    $stmtGroup->execute([$af_id]);
    $grupoRow = $stmtGroup->fetch();

    if ($grupoRow) {
        $grupo_id = (int)$grupoRow['id'];
    } else {
        // Crear un grupo por defecto
        $numero_grupo = '1';
        $codigo_plataforma = 'GRUPO-1';
        $situacion = 'Programable';
        $modalidad = !empty($af['modalidad']) ? $af['modalidad'] : 'Teleformacion';
        $horas = !empty($af['duracion']) ? (int)$af['duracion'] : 0;

        // Inspección de columnas dinámica para inserción segura de grupo
        $grupo_cols_stmt = $pdo->query("DESCRIBE grupos");
        $grupo_columns = $grupo_cols_stmt->fetchAll(PDO::FETCH_COLUMN);

        $insert_fields = [];
        $insert_placeholders = [];
        $insert_params = [];

        $mapping = [
            'accion_id' => $af_id,
            'numero_grupo' => $numero_grupo,
            'codigo_plataforma' => $codigo_plataforma,
            'situacion' => $situacion,
            'modalidad' => $modalidad,
            'horas' => $horas
        ];

        foreach ($mapping as $col => $val) {
            if (in_array($col, $grupo_columns)) {
                $insert_fields[] = "`$col`";
                $insert_placeholders[] = "?";
                $insert_params[] = $val;
            }
        }

        $sqlGroup = "INSERT INTO grupos (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
        $pdo->prepare($sqlGroup)->execute($insert_params);
        $grupo_id = (int)$pdo->lastInsertId();
    }

    // 7. Procesar alumnos e insertar en las tablas `alumnos` y `matriculas`
    $importedCount = 0;
    $syncedUserIds = [];
    $matriculaMap = [];

    // Recuperar las columnas de las tablas para inserción dinámica y segura
    $alumnos_cols_stmt = $pdo->query("DESCRIBE alumnos");
    $alumnos_columns = $alumnos_cols_stmt->fetchAll(PDO::FETCH_COLUMN);

    $matriculas_cols_stmt = $pdo->query("DESCRIBE matriculas");
    $matriculas_columns = $matriculas_cols_stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($moodleUsers as $user) {
        // Filtrar solo alumnos (estudiante en Moodle) si la información de rol está disponible
        $is_student = true;
        if (isset($user['roles']) && is_array($user['roles'])) {
            $is_student = false;
            foreach ($user['roles'] as $r) {
                $roleName = strtolower($r['shortname'] ?? $r['name'] ?? '');
                if ($roleName === 'student' || $r['roleid'] == 5) {
                    $is_student = true;
                    break;
                }
            }
        }
        
        if (!$is_student) {
            continue;
        }

        // Buscar alumno localmente por moodle_user_id, email, o DNI (username)
        $stmtCheck = $pdo->prepare("SELECT id, moodle_user_id, email, dni FROM alumnos 
                                    WHERE moodle_user_id = ? OR email = ? OR (dni = ? AND dni IS NOT NULL AND dni != '')");
        $stmtCheck->execute([$user['id'], $user['email'], $user['username']]);
        $alumnoRow = $stmtCheck->fetch();

        $alumno_id = null;
        if ($alumnoRow) {
            $alumno_id = (int)$alumnoRow['id'];
            // Actualizar moodle_user_id si no lo tenía
            if (empty($alumnoRow['moodle_user_id']) || $alumnoRow['moodle_user_id'] != $user['id']) {
                $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?")->execute([$user['id'], $alumno_id]);
            }
        } else {
            // Crear el alumno
            $dni = !empty($user['username']) ? $user['username'] : ('M-' . $user['id']);
            $email = !empty($user['email']) ? $user['email'] : ($dni . '@example.com');
            $nombre = !empty($user['firstname']) ? $user['firstname'] : 'Alumno';
            
            $lastname = trim($user['lastname'] ?? '');
            $parts = explode(' ', $lastname, 2);
            $primer_apellido = $parts[0] ?? 'Sin apellido';
            $segundo_apellido = $parts[1] ?? '';
            $apellidos = $lastname ?: 'Sin apellido';

            $insert_fields = [];
            $insert_placeholders = [];
            $insert_params = [];

            $mapping = [
                'nombre' => $nombre,
                'dni' => $dni,
                'email' => $email,
                'moodle_user_id' => $user['id']
            ];

            if (in_array('primer_apellido', $alumnos_columns)) {
                $mapping['primer_apellido'] = $primer_apellido;
            }
            if (in_array('segundo_apellido', $alumnos_columns)) {
                $mapping['segundo_apellido'] = $segundo_apellido;
            }
            if (in_array('apellidos', $alumnos_columns)) {
                $mapping['apellidos'] = $apellidos;
            }

            foreach ($mapping as $col => $val) {
                if (in_array($col, $alumnos_columns)) {
                    $insert_fields[] = "`$col`";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $val;
                }
            }

            $sqlAlumno = "INSERT INTO alumnos (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
            $pdo->prepare($sqlAlumno)->execute($insert_params);
            $alumno_id = (int)$pdo->lastInsertId();
        }

        // Registrar matrícula
        $stmtMatCheck = $pdo->prepare("SELECT id FROM matriculas WHERE alumno_id = ? AND grupo_id = ?");
        $stmtMatCheck->execute([$alumno_id, $grupo_id]);
        $matRow = $stmtMatCheck->fetch();

        $matricula_id = null;
        if ($matRow) {
            $matricula_id = (int)$matRow['id'];
        } else {
            $insert_fields = [];
            $insert_placeholders = [];
            $insert_params = [];

            $mapping = [
                'convocatoria_id' => $convocatoria_id,
                'alumno_id' => $alumno_id,
                'grupo_id' => $grupo_id,
                'estado' => 'Inscrito',
                'fecha_matricula' => date('Y-m-d')
            ];

            foreach ($mapping as $col => $val) {
                if (in_array($col, $matriculas_columns)) {
                    $insert_fields[] = "`$col`";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $val;
                }
            }

            $sqlMat = "INSERT INTO matriculas (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
            $pdo->prepare($sqlMat)->execute($insert_params);
            $matricula_id = (int)$pdo->lastInsertId();
        }

        $importedCount++;
        $syncedUserIds[] = (int)$user['id'];
        $matriculaMap[(int)$user['id']] = $matricula_id;
    }

    // 8. Sincronizar estadísticas y tiempos de conexión de inmediato
    if (!empty($syncedUserIds)) {
        require_once 'includes/moodle_db.php';
        $moodleDb = new MoodleDB();
        $stats = $moodleDb->fetchStudentStats($courseMoodleId, $syncedUserIds);

        // Filtrar solo las columnas de moodle que existen en la tabla `matriculas`
        $stats_mapping = [
            'moodle_first_access' => 'first_access',
            'moodle_last_access' => 'last_access',
            'moodle_connected_time' => 'connected_seconds',
            'moodle_progress' => 'progress',
            'moodle_m1_completed' => 'm1_completed',
            'moodle_m2_completed' => 'm2_completed',
            'moodle_m3_completed' => 'm3_completed',
            'moodle_e1_completed' => 'e1_completed',
            'moodle_e2_completed' => 'e2_completed',
            'moodle_e3_completed' => 'e3_completed',
            'moodle_e1_grade' => 'e1_grade',
            'moodle_e2_grade' => 'e2_grade',
            'moodle_e3_grade' => 'e3_grade',
            'moodle_final_grade' => 'final_grade',
            'moodle_aptitud' => 'aptitud'
        ];

        $update_cols = [];
        foreach ($stats_mapping as $col => $stat_key) {
            if (in_array($col, $matriculas_columns)) {
                $update_cols[$col] = $stat_key;
            }
        }

        if (!empty($update_cols)) {
            $sql_parts = [];
            foreach ($update_cols as $col => $stat_key) {
                $sql_parts[] = "`$col` = ?";
            }
            if (in_array('moodle_last_sync', $matriculas_columns)) {
                $sql_parts[] = "`moodle_last_sync` = NOW()";
            }
            
            $sql_update = "UPDATE matriculas SET " . implode(', ', $sql_parts) . " WHERE id = ?";
            $updateStmt = $pdo->prepare($sql_update);

            $courseDuration = (int)$af['duracion'] > 0 ? (int)$af['duracion'] : 60;

            foreach ($stats as $moodleUserId => $data) {
                if (isset($matriculaMap[$moodleUserId])) {
                    $mId = $matriculaMap[$moodleUserId];
                    
                    $connectedSeconds = (int)$data['connected_seconds'];
                    $connectedHours = $connectedSeconds / 3600;
                    $progressPercent = min(100, max(0, round(($connectedHours / $courseDuration) * 100)));
                    $data['progress'] = $progressPercent;

                    $params = [];
                    foreach ($update_cols as $col => $stat_key) {
                        $params[] = $data[$stat_key];
                    }
                    $params[] = $mId;

                    $updateStmt->execute($params);
                }
            }
        }
    }

    // 9. Marcar como 'Baja' las matrículas locales que ya no están en Moodle (ej. profesores o bajas)
    if (!empty($syncedUserIds)) {
        $placeholders = implode(',', array_fill(0, count($syncedUserIds), '?'));
        $params = array_merge([$grupo_id], $syncedUserIds);
        
        $sqlNotInMoodle = "SELECT m.id 
                           FROM matriculas m 
                           JOIN alumnos a ON m.alumno_id = a.id
                           WHERE m.grupo_id = ? AND (a.moodle_user_id NOT IN ($placeholders) OR a.moodle_user_id IS NULL)";
        $stmtNotIn = $pdo->prepare($sqlNotInMoodle);
        $stmtNotIn->execute($params);
        $toUpdate = $stmtNotIn->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($toUpdate)) {
            $inPlaceholders = implode(',', array_fill(0, count($toUpdate), '?'));
            $pdo->prepare("UPDATE matriculas SET estado = 'Baja' WHERE id IN ($inPlaceholders)")->execute($toUpdate);
        }
    }

    // Registrar en auditoría
    audit_log($pdo, 'IMPORT_MOODLE_STUDENTS', 'acciones_formativas', $af_id, null, ['imported_count' => $importedCount]);

    echo json_encode([
        'success' => true,
        'message' => "Importación completada con éxito. Se han importado $importedCount alumnos.",
        'imported_count' => $importedCount
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Ocurrió un error al realizar la importación: ' . $e->getMessage()
    ]);
}
?>
