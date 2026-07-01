<?php
$_SERVER['HTTP_HOST'] = 'pre-gestion.grupoefp.es';
require_once __DIR__ . '/../includes/config.php';

try {
    $stmt = $pdo->query("SELECT id, plan_id, num_accion, titulo, observaciones_gestion, actualizado_en FROM acciones_formativas ORDER BY id ASC");
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "ALL ACTIONS IN DB:\n";
    print_r($res);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
