<?php
// scratch/setup_test_data.php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once 'includes/config.php';

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE grupos");
    $pdo->exec("INSERT INTO grupos (id, accion_id, numero_grupo, codigo_plataforma, fecha_inicio, fecha_fin) VALUES 
        (1, 9, 'GR-001', 'PLAT-001', '2026-07-01', '2026-08-01'), 
        (2, 11, 'GR-002', 'PLAT-002', '2026-07-15', '2026-08-15')");
        
    $pdo->exec("UPDATE matriculas SET grupo_id = 1 WHERE id = 1");
    $pdo->exec("UPDATE matriculas SET grupo_id = 2 WHERE id = 2");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Mock groups setup complete!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
