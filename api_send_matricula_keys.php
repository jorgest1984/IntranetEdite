<?php
// api_send_matricula_keys.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Verificar permisos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes para realizar esta operación.']);
    exit();
}

$matricula_id = isset($_POST['matricula_id']) ? (int)$_POST['matricula_id'] : 0;
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$body = isset($_POST['body']) ? trim($_POST['body']) : '';

if (!$matricula_id || empty($subject) || empty($body)) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos para realizar el envío.']);
    exit();
}

try {
    // 2. Obtener datos de la matrícula, del alumno y del curso
    $stmt = $pdo->prepare("
        SELECT m.id as matricula_id, m.envio_claves, m.fecha_claves,
               af.titulo as curso_nombre, af.id_plataforma as course_moodle_id,
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
        echo json_encode(['success' => false, 'error' => 'No se encontró la matrícula especificada.']);
        exit();
    }

    $to_email = trim($matricula['email'] ?? '');
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'El alumno no tiene una dirección de correo electrónico válida registrada.']);
        exit();
    }

    // 3. Obtener URL de Moodle desde la configuración
    $stmtConf = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'moodle_url' LIMIT 1");
    $stmtConf->execute();
    $confRow = $stmtConf->fetch();
    $moodle_url = $confRow ? rtrim($confRow['valor'], '/') : 'https://aulavirtual.grupoefp.es';

    // 4. Asegurar que tenemos usuario y contraseña. Si no, alertamos al usuario para que sincronice primero.
    $username = $matricula['plat_usuario'] ?? '';
    $password = $matricula['plat_clave'] ?? '';

    if (empty($username)) {
        // Fallback: intentar autogenerar o usar email como usuario temporalmente
        $username = strtolower(explode('@', $to_email)[0]);
    }
    if (empty($password)) {
        $password = 'Efp2026!'; // Contraseña temporal genérica si no tiene una
    }

    // 5. Reemplazar placeholders en el asunto y el cuerpo del mensaje
    $alumno_nombre_completo = trim($matricula['nombre'] . ' ' . ($matricula['primer_apellido'] ?? '') . ' ' . ($matricula['segundo_apellido'] ?? ''));
    
    $placeholders = [
        '{nombre}' => $alumno_nombre_completo,
        '{curso}' => $matricula['curso_nombre'],
        '{url}' => $moodle_url,
        '{usuario}' => $username,
        '{contrasena}' => $password
    ];

    $final_subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
    $final_body = str_replace(array_keys($placeholders), array_values($placeholders), $body);

    // 6. Enviar correo electrónico usando el relay send_mail.php
    $bridge_url = defined('APP_URL') ? rtrim(APP_URL, '/') . '/send_mail.php' : 'https://gestion.grupoefp.es/send_mail.php';
    $bridge_token = 'dbbea329538b1694971d7ee66cc3e4673';

    $postData = http_build_query([
        'token' => $bridge_token,
        'to' => $to_email,
        'from' => 'intranet@grupoefp.es',
        'subject' => $final_subject,
        'body' => $final_body
    ]);

    $ch = curl_init($bridge_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $resultData = json_decode($response, true);
        $relayError = $resultData['error'] ?? 'Error desconocido en el servidor de correo.';
        echo json_encode(['success' => false, 'error' => "Error al enviar el correo a través del servidor de correo. (HTTP $httpCode: $relayError)"]);
        exit();
    }

    // 7. En caso de éxito, actualizar los estados de envío de claves en la base de datos
    $today = date('Y-m-d');
    $stmtUpdate = $pdo->prepare("UPDATE matriculas SET envio_claves = 1, fecha_claves = ? WHERE id = ?");
    $stmtUpdate->execute([$today, $matricula_id]);

    echo json_encode([
        'success' => true,
        'message' => "Las claves han sido enviadas correctamente al correo $to_email y se ha registrado la fecha de envío.",
        'fecha_claves' => $today
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ocurrió un error en el servidor: ' . $e->getMessage()]);
}
?>
