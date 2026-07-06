<?php
// Test the newly provided password for the production Moodle DB
require_once __DIR__ . '/../includes/config.php';

$host = 'localhost';
$user = 'moodle_prod';
$pass = '5g39zT!e4';
$name = 'moodle_prod';

echo "Testing connection with User: $user, DB: $name, Host: $host...\n";

try {
    $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "--> SUCCESS! The password works!\n";
} catch (PDOException $e) {
    echo "--> FAILED: " . $e->getMessage() . "\n";
}
