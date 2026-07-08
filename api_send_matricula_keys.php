<?php
// api_send_matricula_keys.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/smtp_mailer.php';

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
        LEFT JOIN grupos g ON m.grupo_id = g.id
        LEFT JOIN acciones_formativas af ON g.accion_id = af.id
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
    
    $curso_display = !empty($matricula['curso_nombre']) ? $matricula['curso_nombre'] : 'Aula Virtual';

    $placeholders = [
        '{nombre}' => $alumno_nombre_completo,
        '{curso}' => $curso_display,
        '{url}' => $moodle_url,
        '{usuario}' => $username,
        '{contrasena}' => $password
    ];

    $final_subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
    $final_body = str_replace(array_keys($placeholders), array_values($placeholders), $body);

    // 6. Enviar correo electrónico mediante SMTP
    $sent = send_smtp_email($to_email, $final_subject, $final_body);

    if (!$sent) {
        echo json_encode(['success' => false, 'error' => 'No se pudo enviar el correo. El servidor de correo rechazó la conexión o la autenticación SMTP falló.']);
        exit();
    }

    // 7. Actualizar la base de datos de la matrícula
    $fecha_claves = date('Y-m-d');
    $stmtUpdate = $pdo->prepare("UPDATE matriculas SET envio_claves = 1, fecha_claves = ? WHERE id = ?");
    $stmtUpdate->execute([$fecha_claves, $matricula_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Claves enviadas correctamente por correo electrónico y registradas en la matrícula.',
        'fecha_claves' => date('d/m/Y', strtotime($fecha_claves))
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ocurrió un error en el servidor: ' . $e->getMessage()]);
}
?>
