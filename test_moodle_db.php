<?php
header('Content-Type: text/plain; charset=utf-8');

$host = 'localhost';

$user = 'pre-aulavirtual';
$pass = 'Js7~29e1t';

echo "Probando user: $user con DB: pre_moodle\n";
try {
    $pdo1 = new PDO("mysql:host=$host;dbname=pre_moodle", $user, $pass);
    echo "EXITO con $user en pre_moodle\n";
} catch (Exception $e) {
    echo "FALLO con $user en pre_moodle: " . $e->getMessage() . "\n";
}

$user2 = 'pre_aulavirtual';
echo "\nProbando user: $user2 con DB: pre_moodle\n";
try {
    $pdo2 = new PDO("mysql:host=$host;dbname=pre_moodle", $user2, $pass);
    echo "EXITO con $user2 en pre_moodle\n";
} catch (Exception $e) {
    echo "FALLO con $user2 en pre_moodle: " . $e->getMessage() . "\n";
}

echo "\nProbando user: $user2 con DB: pre_aulavirtual\n";
try {
    $pdo3 = new PDO("mysql:host=$host;dbname=pre_aulavirtual", $user2, $pass);
    echo "EXITO con $user2 en pre_aulavirtual\n";
} catch (Exception $e) {
    echo "FALLO con $user2 en pre_aulavirtual: " . $e->getMessage() . "\n";
}

echo "\nProbando user: $user con DB: pre_aulavirtual\n";
try {
    $pdo4 = new PDO("mysql:host=$host;dbname=pre_aulavirtual", $user, $pass);
    echo "EXITO con $user en pre_aulavirtual\n";
} catch (Exception $e) {
    echo "FALLO con $user en pre_aulavirtual: " . $e->getMessage() . "\n";
}
