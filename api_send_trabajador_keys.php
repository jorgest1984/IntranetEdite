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
    // Reemplazar saltos de línea para la vista HTML del correo
    $htmlBody = nl2br(htmlspecialchars($body));
    
    // Plantilla del correo estructurado
    $emailBodyHtml = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <img src='https://gestion.grupoefp.es/img/logo_efp.png' alt='Grupo EFP' style='max-height: 60px;'>
            </div>
            <div style='color: #1e293b; line-height: 1.6;'>
                $htmlBody
            </div>
            <div style='margin-top: 30px; padding-top: 15px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 0.8rem; color: #64748b;'>
                Este correo ha sido generado de forma automática. Por favor no responda directamente a este mensaje.
            </div>
        </div>
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
