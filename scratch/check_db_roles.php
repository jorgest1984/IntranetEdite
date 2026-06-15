<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../includes/config.php';

try {
    echo "--- ROLES ---\n";
    $roles = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
    print_r($roles);
    
    echo "--- USUARIOS (ID, USERNAME, ROL_ID) ---\n";
    $users = $pdo->query("SELECT id, username, rol_id FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
    print_r($users);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
