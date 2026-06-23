<?php
define('NO_AUTH_CHECK', true);
try {
    $pdo = new PDO("mysql:host=localhost;dbname=pre_intranet_formacion;charset=utf8mb4", "pre_gestion", "Oy0v?ggswFBr6d0~");
    $stmt = $pdo->query("DESCRIBE cursos");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo $e->getMessage(); }
