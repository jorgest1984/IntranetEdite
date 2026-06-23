<?php
define('NO_AUTH_CHECK', true);
try {
    $pdo = new PDO("mysql:host=localhost;dbname=pre_intranet_formacion;charset=utf8mb4", "pre_gestion", "Oy0v?ggswFBr6d0~");
    echo "--- MATRICULAS ---\n";
    $stmt = $pdo->query("DESCRIBE matriculas");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    echo "\n--- ALUMNOS ---\n";
    $stmt = $pdo->query("DESCRIBE alumnos");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo $e->getMessage(); }
