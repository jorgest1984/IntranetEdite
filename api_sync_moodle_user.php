<?php
// api_sync_moodle_user.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

header('Content-Type: application/json; charset=utf-8');

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes para realizar esta operación.']);
    exit();
}

$matricula_id = isset($_POST['matricula_id']) ? (int)$_POST['matricula_id'] : 0;
if (!$matricula_id) {
    echo json_encode(['success' => false, 'error' => 'ID de matrícula no válido.']);
    exit();
}

try {
    // 1. Obtener la matrícula del alumno y los datos del curso
    $stmt = $pdo->prepare("
        SELECT m.id as matricula_id, af.id_plataforma as course_moodle_id,
               a.id as alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.email,
               a.plat_usuario, a.plat_clave, a.moodle_user_id
        FROM matriculas m
        JOIN alumnos a ON m.alumno_id = a.id
        JOIN grupos g ON m.grupo_id = g.id
        JOIN acciones_formativas af ON g.accion_id = af.id
        WHERE m.id = ?
        LIMIT 1
    ");
    $stmt->execute([$matricula_id]);
    $matricula = $stmt->fetch();

    if (!$matricula) {
        echo json_encode(['success' => false, 'error' => 'No se encontró la matrícula.']);
        exit();
    }

    $moodle = new MoodleAPI($pdo);
    if (!$moodle->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'Moodle no está configurado en la Intranet.']);
        exit();
    }

    $muid = $matricula['moodle_user_id'];
    $email = trim($matricula['email'] ?? '');
    $firstname = trim($matricula['nombre'] ?? '');
    $lastname = trim(($matricula['primer_apellido'] ?? '') . ' ' . ($matricula['segundo_apellido'] ?? ''));

    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'El alumno no tiene un e-mail configurado.']);
        exit();
    }

    // 2. Buscar o crear usuario en Moodle
    $was_created = false;
    
    // Si no tenemos moodle_user_id localmente, lo buscamos en Moodle por email
    if (empty($muid)) {
        try {
            $mResult = $moodle->getUsersByField('email', [$email]);
            if (!empty($mResult) && isset($mResult['users'][0])) {
                $muid = $mResult['users'][0]['id'];
            }
        } catch (Exception $e) {
            // Silencioso, procedemos a intentar crearlo
        }
    }

    // Si aún no existe, lo creamos
    if (empty($muid)) {
        $username = !empty($matricula['plat_usuario']) ? $matricula['plat_usuario'] : strtolower(explode('@', $email)[0]);
        $password = !empty($matricula['plat_clave']) ? $matricula['plat_clave'] : 'Efp2026!';

        // Limpiar nombre de usuario (caracteres no permitidos en Moodle)
        $username = preg_replace('/[^a-z0-9_.-]/', '', strtolower($username));

        $newUsers = $moodle->createUser($username, $password, $firstname, $lastname, $email);
        if (!empty($newUsers) && isset($newUsers[0]['id'])) {
            $muid = $newUsers[0]['id'];
            $was_created = true;

            // Guardar credenciales generadas en la ficha local
            $pdo->prepare("UPDATE alumnos SET moodle_user_id = ?, plat_usuario = ?, plat_clave = ? WHERE id = ?")
                ->execute([$muid, $username, $password, $matricula['alumno_id']]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo crear el usuario en Moodle (la API no devolvió un ID válido).']);
            exit();
        }
    } else {
        // Si ya existe, actualizamos los datos básicos
        $moodle->updateUser($muid, [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email
        ]);
        
        // Guardar el id si no lo teníamos en local
        if ($matricula['moodle_user_id'] != $muid) {
            $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?")
                ->execute([$muid, $matricula['alumno_id']]);
        }
    }

    // 3. Matricular al alumno en el curso de Moodle si está definido
    $course_moodle_id = (int)($matricula['course_moodle_id'] ?? 0);
    $enrolled = false;
    if ($course_moodle_id > 0) {
        try {
            $moodle->enrolUser($muid, $course_moodle_id);
            $enrolled = true;
        } catch (Exception $enrolEx) {
            echo json_encode(['success' => true, 'message' => "Usuario sincronizado correctamente en Moodle, pero ocurrió un problema al matricularlo en el curso: " . $enrolEx->getMessage()]);
            exit();
        }
    }

    $msg = "Usuario sincronizado correctamente con Moodle.";
    if ($was_created) {
        $msg .= " Se ha creado una nueva cuenta.";
    } else {
        $msg .= " Sus datos han sido actualizados.";
    }
    if ($enrolled) {
        $msg .= " Y ha sido matriculado en el curso Moodle #$course_moodle_id.";
    }

    echo json_encode([
        'success' => true,
        'message' => $msg
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Moodle Sync Error: ' . $e->getMessage()]);
}
?>
