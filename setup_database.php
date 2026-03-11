<?php
require_once 'includes/config.php';

try {
    // 1. Tabla de Cursos (Sincronizados de Moodle)
    $pdo->exec("CREATE TABLE IF NOT EXISTS cursos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        moodle_id INT UNIQUE NOT NULL,
        nombre_corto VARCHAR(100) NOT NULL,
        nombre_largo TEXT,
        categoria_id INT,
        visible TINYINT DEFAULT 1,
        fecha_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Tabla de Matrículas (Vínculo Alumno - Curso)
    $pdo->exec("CREATE TABLE IF NOT EXISTS matriculas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alumno_id INT NOT NULL,
        curso_id INT NOT NULL,
        moodle_enrol_id INT NULL,
        fecha_matricula TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE,
        FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Base de datos actualizada correctamente.";
} catch (PDOException $e) {
    die("Error al actualizar la base de datos: " . $e->getMessage());
}
?>
