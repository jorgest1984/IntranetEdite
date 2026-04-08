<?php
/**
 * send_mail.php
 * Este archivo debe subirse a: https://gestion.grupoefp.es/send_mail.php
 * Actúa como relay de correo seguro para Vercel (que no puede usar mail() directamente).
 */

// Token de seguridad - debe coincidir con el de config.php en Vercel
define('MAIL_TOKEN', 'dbbea329538b1694971d7ee66cc3e4673');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$token = $_POST['token'] ?? '';
if ($token !== MAIL_TOKEN) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$asunto     = strip_tags(trim($_POST['asunto'] ?? ''));
$descripcion = trim($_POST['descripcion'] ?? '');
$usuario    = strip_tags(trim($_POST['usuario'] ?? 'Desconocido'));

if (empty($asunto) || empty($descripcion)) {
    echo json_encode(['error' => 'Asunto y descripción son obligatorios']);
    exit;
}

$to      = 'jorge@estaciondiseno.es';
$subject = '[Intranet Grupo EFP] Sugerencia: ' . $asunto;
$body    = "Nueva sugerencia recibida desde la Intranet de Grupo EFP.\n\n";
$body   .= "Usuario: $usuario\n";
$body   .= "Asunto: $asunto\n\n";
$body   .= "Descripción:\n" . strip_tags($descripcion) . "\n\n";
$body   .= "---\nEnviado automáticamente desde la Intranet Grupo EFP.";

$headers  = "From: intranet@grupoefp.es\r\n";
$headers .= "Reply-To: intranet@grupoefp.es\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Sugerencia enviada correctamente']);
} else {
    echo json_encode(['error' => 'No se pudo enviar el correo. Por favor, inténtalo de nuevo.']);
}
