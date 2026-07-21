<?php
require __DIR__ . '/../includes/config.php';

try {
    // 1. Create centros table
    $pdo->exec("CREATE TABLE IF NOT EXISTS centros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        direccion VARCHAR(255) NULL,
        provincia VARCHAR(100) NULL,
        cp VARCHAR(10) NULL,
        telefono VARCHAR(50) NULL,
        email_contacto VARCHAR(150) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Tabla centros creada.\n";

    // 2. Alter usuarios
    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN centro_id INT NULL");
        $pdo->exec("ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_centro FOREIGN KEY (centro_id) REFERENCES centros(id) ON DELETE SET NULL");
        echo "Columna centro_id añadida a usuarios.\n";
    } catch (Exception $e) {
        echo "Aviso usuarios: " . $e->getMessage() . "\n";
    }

    // 3. Alter grupos
    try {
        $pdo->exec("ALTER TABLE grupos ADD COLUMN centro_id INT NULL");
        $pdo->exec("ALTER TABLE grupos ADD CONSTRAINT fk_grupos_centro FOREIGN KEY (centro_id) REFERENCES centros(id) ON DELETE SET NULL");
        echo "Columna centro_id añadida a grupos.\n";
    } catch (Exception $e) {
        echo "Aviso grupos: " . $e->getMessage() . "\n";
    }

    // 4. Alter acciones_formativas para modalidad
    try {
        $pdo->exec("ALTER TABLE acciones_formativas ADD COLUMN modalidad VARCHAR(50) NOT NULL DEFAULT 'Teleformación'");
        echo "Columna modalidad añadida a acciones_formativas.\n";
    } catch (Exception $e) {
        echo "Aviso acciones: " . $e->getMessage() . "\n";
    }

    echo "Migración completada exitosamente.\n";
} catch (PDOException $e) {
    echo "Error fatal: " . $e->getMessage() . "\n";
}
