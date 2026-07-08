<?php
require_once dirname(__DIR__) . '/includes/config.php';

// Obtener el alumno asociado a la matrícula #20
$stmt = $pdo->query("SELECT alumno_id FROM matriculas WHERE id = 20");
$alumno_id = $stmt->fetchColumn();

if ($alumno_id) {
    $stmtAl = $pdo->prepare("SELECT id, nombre, primer_apellido, profesion, estudios FROM alumnos WHERE id = ?");
    $stmtAl->execute([$alumno_id]);
    print_r($stmtAl->fetch(PDO::FETCH_ASSOC));
} else {
    echo "Matrícula no encontrada.";
}
