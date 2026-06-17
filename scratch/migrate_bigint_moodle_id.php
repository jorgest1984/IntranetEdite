<?php
$_SERVER['HTTP_HOST'] = 'pre-gestion.grupoefp.es';
require '../includes/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== MIGRACIÓN: ALTER TABLE cursos MODIFY COLUMN moodle_id BIGINT ===\n\n";

try {
    // 1. Mostrar estructura antes
    echo "Estructura anterior:\n";
    $stmt1 = $pdo->query("DESCRIBE cursos");
    print_r($stmt1->fetchAll(PDO::FETCH_ASSOC));
    
    // 2. Modificar la columna
    echo "\nEjecutando ALTER TABLE...\n";
    $pdo->exec("ALTER TABLE cursos MODIFY COLUMN moodle_id BIGINT UNIQUE NOT NULL");
    echo "¡Columna modificada con éxito a BIGINT!\n";
    
    // 3. Mostrar estructura después
    echo "\nEstructura nueva:\n";
    $stmt2 = $pdo->query("DESCRIBE cursos");
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
    
} catch (Exception $e) {
    echo "Error durante la migración: " . $e->getMessage() . "\n";
}
?>
