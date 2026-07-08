<?php
// includes/smtp_mailer.php

/**
 * Envía un correo electrónico de forma segura usando el SMTP autenticado del servidor.
 * 
 * @param string $to Correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $body Cuerpo del mensaje (texto plano o HTML según cabeceras)
 * @param string $from_name Nombre visible del remitente (opcional)
 * @return bool True si se envió correctamente, False en caso contrario
 */
function send_smtp_email($to, $subject, $body, $from_name = 'Intranet Grupo EFP') {
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

        // Cabeceras del mensaje
        $headers = "From: \"" . $from_name . "\" <" . $from_email . ">\r\n";
        $headers .= "To: <" . $to . ">\r\n";
        $headers .= "Subject: " . $subject . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "MIME-Version: 1.0\r\n\r\n";

        // Asegurar salto de línea correcto al final del cuerpo y punto de fin de mensaje
        $write($headers . $body . "\r\n.");
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
