<?php
// scratch/test_smtp_socket.php
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

function test_smtp_send($to, $subject, $body) {
    $host = '127.0.0.1';
    $port = 25; // Conectar al puerto local 25 sin cifrado
    $from = 'intranet@grupoefp.es';

    echo "Conectando a local SMTP $host:$port...\n";
    $socket = @fsockopen($host, $port, $errno, $errstr, 15);
    
    if (!$socket) {
        echo "❌ No se pudo conectar a localhost:25. Error: $errstr ($errno).\n";
        return false;
    }
    
    echo "Connected! Leyendo banner...\n";
    
    $read = function() use ($socket) {
        $data = '';
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            echo "S: " . trim($str) . "\n";
            if (substr($str, 3, 1) == ' ') {
                break;
            }
        }
        return $data;
    };

    $write = function($cmd) use ($socket) {
        echo "C: " . $cmd . "\n";
        fputs($socket, $cmd . "\r\n");
    };

    $read(); // banner

    $write("EHLO localhost");
    $read();

    // No enviamos AUTH LOGIN, probamos envío directo como localhost de confianza
    $write("MAIL FROM:<" . $from . ">");
    $read();

    $write("RCPT TO:<" . $to . ">");
    $read();

    $write("DATA");
    $read();

    $headers = "From: <" . $from . ">\r\n";
    $headers .= "To: <" . $to . ">\r\n";
    $headers .= "Subject: " . $subject . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n\r\n";

    $write($headers . $body . "\r\n.");
    $read();

    $write("QUIT");
    $read();
    
    fclose($socket);
    echo "✅ Correo enviado con éxito como localhost!\n";
    return true;
}

test_smtp_send('sisqogr@gmail.com', 'Prueba SMTP Localhost Sin Auth', 'Hola Jorge, este correo ha sido enviado vía SMTP local sin autenticación desde el servidor.');
?>
