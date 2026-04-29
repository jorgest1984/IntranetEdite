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

$to         = trim($_POST['to'] ?? '');
$from       = trim($_POST['from'] ?? 'intranet@grupoefp.es');
$subject    = trim($_POST['subject'] ?? 'Mensaje de la Intranet');
$body       = trim($_POST['body'] ?? '');

if (empty($to) || empty($subject) || empty($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'to, subject y body son obligatorios']);
    exit;
}

$headers  = "From: " . strip_tags($from) . "\r\n";
$headers .= "Reply-To: " . strip_tags($from) . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$sent = @mail($to, $subject, $body, $headers);

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Correo enviado correctamente']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo enviar el correo.']);
}
