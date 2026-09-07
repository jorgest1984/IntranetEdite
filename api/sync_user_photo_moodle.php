<?php
// api/sync_user_photo_moodle.php
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

header('Content-Type: application/json; charset=utf-8');

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    echo json_encode(['success' => false, 'error' => 'No tienes permisos suficientes para realizar esta operación.']);
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$type = isset($_POST['type']) ? trim($_POST['type']) : (isset($_GET['type']) ? trim($_GET['type']) : 'usuario');

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID de registro no especificado.']);
    exit();
}

try {
    $moodle = new MoodleAPI($pdo);
    if (!$moodle->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'La integración con Moodle no está configurada.']);
        exit();
    }

    $targetTable = ($type === 'alumno') ? 'alumnos' : 'usuarios';
    $stmt = $pdo->prepare("SELECT * FROM {$targetTable} WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        echo json_encode(['success' => false, 'error' => 'No se encontró el registro especificado.']);
        exit();
    }

    $fotoPath = $record['foto'] ?? '';
    if (empty($fotoPath) || !file_exists(__DIR__ . '/../' . $fotoPath)) {
        echo json_encode(['success' => false, 'error' => 'No hay ninguna foto de perfil guardada para este perfil. Por favor, sube una foto primero y actualiza los datos.']);
        exit();
    }

    $email = trim($record['email'] ?? '');
    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'El usuario no tiene una dirección de correo electrónico configurada.']);
        exit();
    }

    $moodleUserId = !empty($record['moodle_user_id']) ? (int)$record['moodle_user_id'] : null;

    // Buscar si no tenemos el ID
    if (!$moodleUserId) {
        $existing = $moodle->getUsersByField('email', [$email]);
        if (!empty($existing) && isset($existing['users'][0]['id'])) {
            $moodleUserId = (int)$existing['users'][0]['id'];
        }
    }

    // Si aún no existe en Moodle, crearlo
    if (!$moodleUserId) {
        $firstname = trim($record['nombre'] ?? 'Usuario');
        $lastname = ($type === 'alumno')
            ? trim(($record['primer_apellido'] ?? '') . ' ' . ($record['segundo_apellido'] ?? ''))
            : trim($record['apellidos'] ?? 'Personal');
        if (empty($lastname)) $lastname = 'EFP';

        $raw_user = !empty($record['username']) ? $record['username'] : (!empty($record['plat_usuario']) ? $record['plat_usuario'] : strtolower(explode('@', $email)[0]));
        $username = preg_replace('/[^a-z0-9_.-]/', '', strtolower($raw_user));
        if (empty($username)) $username = 'user_' . $id;

        $password = ($type === 'alumno') 
            ? (!empty($record['plat_clave']) ? $record['plat_clave'] : 'Efp2026!')
            : 'EditeTutor-2026*';

        $newUsers = $moodle->createUser($username, $password, $firstname, $lastname, $email);
        if (!empty($newUsers) && isset($newUsers[0]['id'])) {
            $moodleUserId = (int)$newUsers[0]['id'];
        } else {
            throw new Exception("No se pudo localizar ni crear la cuenta de usuario en Moodle.");
        }
    }

    // Actualizar moodle_user_id en la base de datos local si no estaba guardado
    if ($moodleUserId && ($record['moodle_user_id'] ?? null) != $moodleUserId) {
        try {
            $pdo->prepare("UPDATE {$targetTable} SET moodle_user_id = ? WHERE id = ?")->execute([$moodleUserId, $id]);
        } catch (Exception $e) {}
    }

    // Sincronizar la imagen con Moodle
    $fullLocalPath = __DIR__ . '/../' . $fotoPath;
    $syncResult = $moodle->updateUserPicture($moodleUserId, $fullLocalPath);

    if (ob_get_level()) { ob_clean(); }
    echo json_encode([
        'success' => true,
        'message' => '¡Foto de perfil actualizada con éxito en el Aula Virtual Moodle!',
        'moodle_user_id' => $moodleUserId
    ]);

} catch (Exception $e) {
    if (ob_get_level()) { ob_clean(); }
    echo json_encode([
        'success' => false,
        'error' => 'Error al sincronizar foto con Moodle: ' . $e->getMessage()
    ]);
}
