<?php
// includes/smtp_mailer.php

/**
 * Envía un correo electrónico de forma segura usando el SMTP autenticado del servidor.
 * 
 * @param string $to Correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $body Cuerpo del mensaje (texto plano o HTML)
 * @param string $from_name Nombre visible del remitente (opcional)
 * @param array $attachments Adjuntos (opcional)
 * @param bool $is_html Determina si se envía como HTML (por defecto true)
 * @return bool True si se envió correctamente, False en caso contrario
 */
function send_smtp_email($to, $subject, $body, $from_name = 'Intranet Grupo EFP', $attachments = [], $is_html = true) {
    $host = 'ssl://grupoefp.es';
    $port = 465;
    $user = 'admingrupoefp@grupoefp.es';
    $pass = 'Estacion.2025';
    $from_email = 'admingrupoefp@grupoefp.es';

    $socket = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$socket) {
        // Fallback para registrar error si fuera necesario
        error_log("SMTP Connection Error: $errstr ($errno)");
        return false;
    }

    $read = function() use ($socket) {
        $data = '';
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) == ' ') {
                break;
            }
        }
        return $data;
    };

    $write = function($cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    try {
        $read(); // banner inicial

        $write("EHLO grupoefp.es");
        $read();

        $write("AUTH LOGIN");
        $read();

        $write(base64_encode($user));
        $read();

        $write(base64_encode($pass));
        $resp = $read();

        if (strpos($resp, '235') === false) {
            error_log("SMTP Auth Error: $resp");
            $write("QUIT");
            fclose($socket);
            return false;
        }

        $write("MAIL FROM:<" . $from_email . ">");
        $read();

        $write("RCPT TO:<" . $to . ">");
        $read();

        $write("DATA");
        $read();

        $boundary = "----=_NextPart_" . md5(time() . rand());

        // Procesar cuerpo HTML / Texto plano
        if ($is_html) {
            // Si el texto no contiene etiquetas HTML estructuradas, envolverlo en plantilla HTML oficial
            if (strip_tags($body) === $body || (strpos($body, '<div') === false && strpos($body, '<table') === false && strpos($body, '<html') === false)) {
                $formatted_content = nl2br(htmlspecialchars($body));
                // Convertir enlaces URL a etiquetas <a> clicables
                $formatted_content = preg_replace(
                    '/(https?:\/\/[^\s<]+)/i',
                    '<a href="$1" style="color: #2563eb; text-decoration: underline;" target="_blank">$1</a>',
                    $formatted_content
                );

                $final_body = "
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
            {$formatted_content}
        </div>
        <div style='margin-top: 30px; padding-top: 15px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 0.8rem; color: #64748b;'>
            Este correo ha sido generado de forma automática. Por favor no responda directamente a este mensaje.
        </div>
    </div>
</body>
</html>";
            } else {
                $final_body = $body;
            }
            $content_type = "text/html; charset=UTF-8";
        } else {
            $final_body = $body;
            $content_type = "text/plain; charset=UTF-8";
        }

        // Codificación UTF-8 segura para Asunto y Nombre del Remitente
        $encoded_subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $encoded_from_name = "=?UTF-8?B?" . base64_encode($from_name) . "?=";

        // Cabeceras del mensaje
        $headers = "From: " . $encoded_from_name . " <" . $from_email . ">\r\n";
        $headers .= "To: <" . $to . ">\r\n";
        $headers .= "Subject: " . $encoded_subject . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        if (!empty($attachments)) {
            $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n\r\n";
            $message = "--" . $boundary . "\r\n";
            $message .= "Content-Type: " . $content_type . "\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $final_body . "\r\n\r\n";
            
            foreach ($attachments as $attachment) {
                $filename = $attachment['name'];
                $content = chunk_split(base64_encode($attachment['content']));
                $message .= "--" . $boundary . "\r\n";
                $message .= "Content-Type: application/pdf; name=\"" . $filename . "\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= "Content-Disposition: attachment; filename=\"" . $filename . "\"\r\n\r\n";
                $message .= $content . "\r\n\r\n";
            }
            $message .= "--" . $boundary . "--\r\n";
        } else {
            $headers .= "Content-Type: " . $content_type . "\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message = $final_body . "\r\n";
        }

        // Asegurar salto de línea correcto al final del cuerpo y punto de fin de mensaje
        $write($headers . $message . ".");
        $read();

        $write("QUIT");
        $read();

        fclose($socket);
        return true;

    } catch (Exception $e) {
        error_log("SMTP Send Exception: " . $e->getMessage());
        @fclose($socket);
        return false;
    }
}
?>
