<?php
// Test newly provided Moodle database credentials for production
$host = 'localhost';
$user = 'aulavirtual_efp';
$pass = 'EfP@v1rtu4l2024!';
$name = 'aulavirtual_efpdb';

echo "Testing connection with User: $user, DB: $name...\n";

try {
    $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    echo "--> SUCCESS! Connected successfully to Moodle Database!\n";
} catch (PDOException $e) {
    echo "--> FAILED: " . $e->getMessage() . "\n";
}
