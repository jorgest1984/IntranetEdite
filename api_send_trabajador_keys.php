<?php
// api_send_trabajador_keys.php
require_once 'includes/auth.php';
require_once 'includes/smtp_mailer.php';

header('Content-Type: application/json; charset=utf-8');

if (!has_permission([ROLE_ADMIN])) {
    echo json_encode(['success' => false, 'error' => 'No tienes permisos para realizar esta acción.']);
    exit();
}

$id = $_POST['trabajador_id'] ?? null;
$password = $_POST['password'] ?? null;
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? 'Claves de acceso a la Intranet - Grupo EFP');
$body = trim($_POST['body'] ?? '');

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID de trabajador no especificado.']);
    exit();
}

try {
    // 1. Obtener datos del trabajador
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $trabajador = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trabajador) {
        echo json_encode(['success' => false, 'error' => 'Trabajador no encontrado.']);
        exit();
    }

    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'El correo electrónico del destinatario es obligatorio.']);
        exit();
    }

    // 2. Si se especificó una contraseña nueva, actualizarla en la base de datos
    if (!empty($password)) {
        $complexity = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#]).{12,}$/';
        if (!preg_match($complexity, $password)) {
            echo json_encode([
                'success' => false,
                'error' => 'La contraseña debe tener al menos 12 caracteres e incluir mayúscula, minúscula, número y algún carácter especial del grupo (@, $, !, %, *, ?, &, #).'
            ]);
            exit();
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmtUpdate = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        $stmtUpdate->execute([$password_hash, $id]);
    }

    // 3. Enviar correo electrónico usando SMTP autenticado
    // Reemplazar saltos de línea y convertir URLs en enlaces clicables
    $htmlBody = nl2br(htmlspecialchars($body));
    $htmlBody = preg_replace(
        '/(https?:\/\/[^\s<]+)/i',
        '<a href="$1" style="color: #2563eb; text-decoration: underline;" target="_blank">$1</a>',
        $htmlBody
    );
    
    // Plantilla del correo estructurado
    $emailBodyHtml = "
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin: 0; padding: 20px; background-color: #f8fafc; font-family: Arial, Helvetica, sans-serif; color: #1e293b;'>
    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>
        <div style='text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f1f5f9;'>
            <img src='https://gestion.grupoefp.es/img/logo_efp.png' alt='Grupo EFP' style='max-height: 60px; width: auto;'>
        </div>
        <div style='font-size: 15px; line-height: 1.6; color: #1e293b;'>
            {$htmlBody}
        </div>
        <div style='margin-top: 30px; padding-top: 15px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 0.8rem; color: #64748b;'>
            Este correo ha sido generado de forma automática. Por favor no responda directamente a este mensaje.
        </div>
    </div>
</body>
</html>
    ";

    $sent = send_smtp_email($email, $subject, $emailBodyHtml);

    if ($sent) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo enviar el correo electrónico. Verifique la configuración del servidor SMTP.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error en el servidor: ' . $e->getMessage()]);
}
?>
