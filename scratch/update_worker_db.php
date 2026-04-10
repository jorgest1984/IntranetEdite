<?php
require_once 'includes/config.php';

$tables = [
    'profesorado_detalles',
    'prof_asistencia',
    'prof_formacion',
    'prof_experiencia',
    'prof_idiomas',
    'prof_informatica',
    'prof_tutorias',
    'prof_formacion_interna',
    'prof_tareas'
];

foreach ($tables as $table) {
    echo "Updating $table...\n";
    try {
        // Comprobar si ya existe usuario_id
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'usuario_id'");
        if ($stmt->rowCount() == 0) {
            $pdo->prepare("ALTER TABLE $table ADD COLUMN usuario_id INT(11) NULL AFTER id, ADD INDEX (usuario_id)")->execute();
            echo "Added usuario_id to $table\n";
        } else {
            echo "usuario_id already exists in $table\n";
        }
    } catch (Exception $e) {
        echo "Error updating $table: " . $e->getMessage() . "\n";
    }
}

// También necesitamos tablas para departamentos y perfiles linked to usuarios
$new_tables = [
    "CREATE TABLE IF NOT EXISTS usuario_departamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        departamento VARCHAR(100) NOT NULL,
        INDEX(usuario_id)
    )",
    "CREATE TABLE IF NOT EXISTS usuario_perfiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        perfil VARCHAR(100) NOT NULL,
        INDEX(usuario_id)
    )"
];

foreach ($new_tables as $sql) {
    try {
        $pdo->prepare($sql)->execute();
        echo "Ensured table exists.\n";
    } catch (Exception $e) {
        echo "Error creating table: " . $e->getMessage() . "\n";
    }
}
