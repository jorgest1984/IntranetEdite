<?php
// scratch/test_smtp_socket.php
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

function test_smtp_send($to, $subject, $body) {
    $host = 'ssl://grupoefp.es';
    $port = 465; // Puerto SSL seguro estándar
    $user = 'admin@grupoefp.es';
    $pass = 'Estacion.2025';
    $from = 'admin@grupoefp.es';

    echo "Conectando a $host:$port...\n";
    $socket = @fsockopen($host, $port, $errno, $errstr, 15);
    
    if (!$socket) {
        echo "❌ No se pudo conectar al puerto SSL $port. Error: $errstr ($errno).\n";
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

    $write("EHLO grupoefp.es");
    $read();

    // Iniciar Auth Login
    $write("AUTH LOGIN");
    $read();

    $write(base64_encode($user));
    $read();

    $write(base64_encode($pass));
    $resp = $read();
    
    if (strpos($resp, '235') === false) {
        echo "❌ Autenticación SMTP fallida.\n";
        $write("QUIT");
        fclose($socket);
        return false;
    }
    
    echo "✅ Autenticación SMTP exitosa!\n";

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
    echo "✅ Correo enviado e hilo cerrado con éxito!\n";
    return true;
}

test_smtp_send('sisqogr@gmail.com', 'Prueba SMTP SSL Autenticado Intranet', 'Hola Jorge, este correo ha sido enviado usando SMTP SSL autenticado por el puerto 465.');
?>
