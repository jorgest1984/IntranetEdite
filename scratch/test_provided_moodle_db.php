<?php
// Find Moodle production DB table prefix
$host = 'localhost';
$user = 'aulavirtual_efp';
$pass = 'EfP@v1rtu4l2024!';
$name = 'aulavirtual_efpdb';

try {
    $test_pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    
    // Check for mdl_course
    try {
        $test_pdo->query("SELECT id FROM mdl_course LIMIT 1");
        echo "PREFIX IS: mdl_\n";
        exit();
    } catch (Exception $e) {}
    
    // Check for avefp_course
    try {
        $test_pdo->query("SELECT id FROM avefp_course LIMIT 1");
        echo "PREFIX IS: avefp_\n";
        exit();
    } catch (Exception $e) {}
    
    echo "PREFIX NOT FOUND AUTOMATICALLY.\n";
} catch (PDOException $e) {
    echo "--> FAILED: " . $e->getMessage() . "\n";
}
