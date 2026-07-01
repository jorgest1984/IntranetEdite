<?php
$_SERVER['HTTP_HOST'] = 'pre-gestion.grupoefp.es';
require_once __DIR__ . '/../includes/config.php';

try {
    $stmt = $pdo->prepare("SELECT id, plan_id, nuestra_check, prioritaria_check, cae_check, conexia_check, tutor1_activo, observaciones_gestion FROM acciones_formativas WHERE id = ?");
    $stmt->execute([12]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "DATABASE VALUE FOR ID 12:\n";
    print_r($res);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
