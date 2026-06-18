<?php
header('Content-Type: text/plain; charset=utf-8');

$host = 'localhost';
$db = 'pre_moodle';
$user1 = 'pre_moodle';
$pass1 = 'Oy0v?ggswFBr6d0~';

$user2 = 'pre_gestion';
$pass2 = 'Oy0v?ggswFBr6d0~';

echo "Probando user1: $user1\n";
try {
    $pdo1 = new PDO("mysql:host=$host;dbname=$db", $user1, $pass1);
    echo "EXITO con $user1\n";
} catch (Exception $e) {
    echo "FALLO con $user1: " . $e->getMessage() . "\n";
}

echo "\nProbando user2: $user2\n";
try {
    $pdo2 = new PDO("mysql:host=$host;dbname=$db", $user2, $pass2);
    echo "EXITO con $user2\n";
} catch (Exception $e) {
    echo "FALLO con $user2: " . $e->getMessage() . "\n";
}

echo "\nProbando user2 con DB pre_intranet_formacion (debería funcionar):\n";
try {
    $pdo3 = new PDO("mysql:host=$host;dbname=pre_intranet_formacion", $user2, $pass2);
    echo "EXITO con $user2 en pre_intranet_formacion\n";
} catch (Exception $e) {
    echo "FALLO con $user2 en pre_intranet_formacion: " . $e->getMessage() . "\n";
}
