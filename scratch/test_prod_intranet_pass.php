<?php
// Test if the user-provided password works for the main Intranet database
$host = 'localhost';
$user = 'gestion.efp2026';
$pass = '5g39zT!e4';
$name = 'intranet_formacion';

echo "Testing connection with User: $user, DB: $name...\n";

try {
    $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    echo "--> SUCCESS! The password works for the Intranet DB!\n";
} catch (PDOException $e) {
    echo "--> FAILED: " . $e->getMessage() . "\n";
}
