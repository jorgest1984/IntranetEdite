<?php
require_once 'includes/config.php';

try {
    // 1. Añadir columnas a convocatorias
    $columns = [
        'abreviatura' => "ALTER TABLE convocatorias ADD COLUMN abreviatura VARCHAR(50) AFTER nombre",
        'anio' => "ALTER TABLE convocatorias ADD COLUMN anio VARCHAR(10) AFTER abreviatura",
        'ambito' => "ALTER TABLE convocatorias ADD COLUMN ambito VARCHAR(100) AFTER organismo",
        'solicitante' => "ALTER TABLE convocatorias ADD COLUMN solicitante VARCHAR(255) AFTER ambito",
        'url' => "ALTER TABLE convocatorias ADD COLUMN url VARCHAR(255) AFTER solicitante",
        'url_aula_virtual' => "ALTER TABLE convocatorias ADD COLUMN url_aula_virtual VARCHAR(255) AFTER url",
        'activa' => "ALTER TABLE convocatorias ADD COLUMN activa TINYINT(1) DEFAULT 1 AFTER url_aula_virtual",
        'descripcion' => "ALTER TABLE convocatorias ADD COLUMN descripcion TEXT AFTER activa",
        'requisitos' => "ALTER TABLE convocatorias ADD COLUMN requisitos TEXT AFTER descripcion"
    ];

    foreach ($columns as $col => $sql) {
        // Verificar si la columna existe antes de añadirla
        $check = $pdo->query("SHOW COLUMNS FROM convocatorias LIKE '$col'");
        if ($check->rowCount() == 0) {
            $pdo->exec($sql);
            echo "Columna '$col' añadida.<br>";
        } else {
            echo "Columna '$col' ya existe.<br>";
        }
    }

    // 2. Crear tabla planes
    $sqlPlanes = "CREATE TABLE IF NOT EXISTS planes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        convocatoria_id INT NOT NULL,
        codigo VARCHAR(50) NOT NULL,
        nombre VARCHAR(255) NOT NULL,
        activo TINYINT(1) DEFAULT 1,
        FOREIGN KEY (convocatoria_id) REFERENCES convocatorias(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sqlPlanes);
    echo "Tabla 'planes' verificada/creada.<br>";

    echo "Migración completada con éxito.";
} catch (Exception $e) {
    echo "Error en la migración: " . $e->getMessage();
}
