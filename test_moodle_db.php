<?php
header('Content-Type: text/plain; charset=utf-8');

$host = 'localhost';
$user = 'pre-aulavirtual';
$pass = 'Js7~29e1t';

try {
    // Try to connect without specifying a database
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    echo "EXITO conectando al servidor MySQL con $user\n";
    
    // List databases
    $stmt = $pdo->query("SHOW DATABASES");
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Bases de datos accesibles para este usuario:\n";
    foreach ($dbs as $db) {
        echo "- $db\n";
    }
} catch (Exception $e) {
    echo "FALLO: " . $e->getMessage() . "\n";
}
