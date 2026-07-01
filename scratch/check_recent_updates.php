<?php
$_SERVER['HTTP_HOST'] = 'pre-gestion.grupoefp.es';
require_once __DIR__ . '/../includes/config.php';

try {
    $stmt = $pdo->query("SELECT id, plan_id, num_accion, observaciones_gestion, actualizado_en FROM acciones_formativas ORDER BY actualizado_en DESC LIMIT 10");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "LAST 10 UPDATED RECORDS IN PREPRODUCTION:\n";
    print_r($res);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
