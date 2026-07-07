<?php
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

$to = 'sisqogr@gmail.com';
$subject = 'Prueba Directa de Correo Intranet';
$body = "Hola Jorge,\n\nEsta es una prueba directa usando mail() de PHP desde el servidor.";
$from = 'intranet@grupoefp.es';

$headers  = "From: " . strip_tags($from) . "\r\n";
$headers .= "Reply-To: " . strip_tags($from) . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

echo "Enviando a $to...\n";
$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    echo "✅ mail() devolvió TRUE (Aceptado para envío).\n";
} else {
    echo "❌ mail() devolvió FALSE (Fallo al enviar).\n";
}
?>
