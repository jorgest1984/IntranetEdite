<?php
// api/sync_profesor_moodle.php
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

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';
require_once __DIR__ . '/../includes/moodle_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    echo json_encode(['success' => false, 'error' => 'No tienes permisos suficientes para realizar esta operación.']);
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID de usuario/profesor no especificado.']);
    exit();
}

try {
    // 1. Obtener datos del usuario/profesor
    $stmt = $pdo->prepare("SELECT u.*, p.titulacion FROM usuarios u LEFT JOIN profesorado_detalles p ON u.id = p.usuario_id WHERE u.id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Buscar en tabla alumnos si no estuviera en usuarios
        $stmtA = $pdo->prepare("SELECT a.*, a.primer_apellido as apellidos FROM alumnos a WHERE a.id = ?");
        $stmtA->execute([$id]);
        $user = $stmtA->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'No se encontró el usuario especificado.']);
        exit();
    }

    $email = trim($user['email'] ?? '');
    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'El profesor no tiene un correo electrónico configurado.']);
        exit();
    }

    $firstname = trim($user['nombre'] ?? 'Profesor');
    $lastname = trim($user['apellidos'] ?? '');
    if (empty($lastname)) {
        $lastname = 'Docente';
    }

    $raw_username = !empty($user['username']) ? $user['username'] : strtolower(explode('@', $email)[0]);
    $username = preg_replace('/[^a-z0-9_.-]/', '', strtolower($raw_username));
    if (empty($username)) {
        $username = 'profesor_' . $id;
    }

    $password = 'EditeTutor-2026*';

    $moodle = new MoodleAPI($pdo);
    if (!$moodle->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'La integración con Moodle no está configurada.']);
        exit();
    }

    // 2. Buscar si el usuario ya existe en Moodle (por email o username)
    $moodleUserId = null;
    $existingUsers = $moodle->getUsersByField('email', [$email]);
    if (!empty($existingUsers) && isset($existingUsers['users'][0]['id'])) {
        $moodleUserId = (int)$existingUsers['users'][0]['id'];
    }

    if (!$moodleUserId) {
        $existingByUsername = $moodle->getUsersByField('username', [$username]);
        if (!empty($existingByUsername) && isset($existingByUsername['users'][0]['id'])) {
            $moodleUserId = (int)$existingByUsername['users'][0]['id'];
        }
    }

    $wasCreated = false;
    // 3. Si no existe, crearlo
    if (!$moodleUserId) {
        $newUsers = $moodle->createUser($username, $password, $firstname, $lastname, $email);
        if (!empty($newUsers) && isset($newUsers[0]['id'])) {
            $moodleUserId = (int)$newUsers[0]['id'];
            $wasCreated = true;
        } else {
            throw new Exception("Moodle no devolvió un ID de usuario válido al crear el profesor.");
        }
    } else {
        // Si ya existe, actualizar sus datos básicos si procede
        try {
            $moodle->updateUser($moodleUserId, [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email
            ]);
        } catch (Exception $updateEx) {
            // Silencioso
        }
    }

    // 4. Guardar moodle_user_id en la base de datos local si existe la columna
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'moodle_user_id'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN moodle_user_id INT NULL");
        }
        $pdo->prepare("UPDATE usuarios SET moodle_user_id = ? WHERE id = ?")->execute([$moodleUserId, $id]);
    } catch (Exception $dbEx) {}

    // 4.5. Sincronizar foto de perfil con Moodle si existe localmente
    $photoSynced = false;
    $fotoPath = $user['foto'] ?? '';
    if (!empty($fotoPath) && file_exists(__DIR__ . '/../' . $fotoPath)) {
        try {
            $moodle->updateUserPicture($moodleUserId, __DIR__ . '/../' . $fotoPath);
            $photoSynced = true;
        } catch (Exception $photoEx) {
            // Silencioso
        }
    }

    // 5. Matricular como Profesor (rol 3) en todos los cursos que tenga asignados en grupos
    $cursosMatriculados = [];
    $stmtGrupos = $pdo->prepare("
        SELECT DISTINCT COALESCE(c.moodle_id, af.id_plataforma) as course_moodle_id, cu.nombre_largo as curso_titulo
        FROM grupos g
        JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN cursos c ON af.curso_id = c.id
        LEFT JOIN cursos cu ON af.curso_id = cu.id
        WHERE (g.tutor_id = ? OR g.tutor_id_2 = ? OR g.tutor_reserva_id = ?)
    ");
    $stmtGrupos->execute([$id, $id, $id]);
    $gruposProf = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($gruposProf as $gp) {
        $cMoodleId = (int)($gp['course_moodle_id'] ?? 0);
        if ($cMoodleId > 0) {
            try {
                $moodle->enrolUser($moodleUserId, $cMoodleId, 3); // rol 3 = Profesor
                $cursosMatriculados[] = "#{$cMoodleId}";
            } catch (Exception $enrolEx) {
                // Silencioso
            }
        }
    }

    $msgCourses = !empty($cursosMatriculados) 
        ? " y matriculado como docente en los cursos: " . implode(', ', $cursosMatriculados)
        : "";

    $actionVerb = $wasCreated ? "creado con éxito" : "actualizado correctamente";

    if (ob_get_level()) { ob_clean(); }
    echo json_encode([
        'success' => true,
        'message' => "Profesor {$firstname} {$lastname} {$actionVerb} en el Aula Virtual Moodle (ID Moodle: {$moodleUserId}){$msgCourses}.",
        'moodle_user_id' => $moodleUserId,
        'was_created' => $wasCreated
    ]);

} catch (Exception $e) {
    if (ob_get_level()) { ob_clean(); }
    echo json_encode([
        'success' => false,
        'error' => 'Error al sincronizar profesor con Moodle: ' . $e->getMessage()
    ]);
}
