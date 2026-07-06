<?php
// Test intranet database credentials
$host = 'localhost';
$user = 'gestion.efp2026';
$pass = 'Oy0v?ggswFBr6d0~';
$name = 'intranet_formacion';

echo "Testing Intranet DB Connection:\n";
echo "Host: $host\n";
echo "User: $user\n";
echo "DB Name: $name\n";

try {
    $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    echo "--> SUCCESS! Intranet DB connection works!\n";
} catch (PDOException $e) {
    echo "--> FAILED: " . $e->getMessage() . "\n";
}
