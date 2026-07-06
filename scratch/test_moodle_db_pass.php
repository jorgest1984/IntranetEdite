<?php
// Fully self-contained Moodle DB connection test on production
$host = 'localhost';
$user = 'moodle_prod';
$pass = '5g39zT!e4';
$name = 'moodle_prod';

echo "Testing connection to $name...\n";

try {
    $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    echo "--> SUCCESS! The password works!\n";
} catch (PDOException $e) {
    echo "--> FAILED: " . $e->getMessage() . "\n";
}
