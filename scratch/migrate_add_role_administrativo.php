<?php
// scratch/migrate_add_role_administrativo.php
$_SERVER['HTTP_HOST'] = 'localhost'; // For local config loading if run via CLI
require_once __DIR__ . '/../includes/config.php';

try {
    // 1. Insert role in DB
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (id, nombre, descripcion) VALUES (?, ?, ?)");
    $stmt->execute([7, 'Administrativo', 'Acceso exclusivo a contabilidad y área económica']);
    
    // Check if it exists
    $check = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $check->execute([7]);
    $role = $check->fetch();
    
    if ($role) {
        echo "SUCCESS: Rol Administrativo (ID 7) registrado correctamente en la Base de Datos.\n";
        print_r($role);
    } else {
        echo "ERROR: No se pudo verificar el registro del rol.\n";
    }
} catch (Exception $e) {
    echo "ERROR DE BD: " . $e->getMessage() . "\n";
}
