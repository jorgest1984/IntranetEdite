<?php
// Check DB records for ID 1
try {
    $pdo_pre = new PDO("mysql:host=localhost;dbname=pre_intranet_formacion;charset=utf8mb4", "pre_gestion", "Oy0v?ggswFBr6d0~");
    $stmt = $pdo_pre->prepare("SELECT id, plan_id, num_accion, observaciones_gestion, actualizado_en FROM acciones_formativas WHERE id = ?");
    $stmt->execute([1]);
    echo "PREPRODUCTION ID 1:\n";
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "PREPRODUCTION ERROR: " . $e->getMessage() . "\n";
}

try {
    $pdo_prod = new PDO("mysql:host=localhost;dbname=intranet_formacion;charset=utf8mb4", "gestion.efp2026", "Oy0v?ggswFBr6d0~");
    $stmt = $pdo_prod->prepare("SELECT id, plan_id, num_accion, observaciones_gestion, actualizado_en FROM acciones_formativas WHERE id = ?");
    $stmt->execute([1]);
    echo "\nPRODUCTION ID 1:\n";
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "PRODUCTION ERROR: " . $e->getMessage() . "\n";
}
