<?php
// Check both databases for ID 12

// Database 1: Preproduction
try {
    $pdo_pre = new PDO("mysql:host=localhost;dbname=pre_intranet_formacion;charset=utf8mb4", "pre_gestion", "Oy0v?ggswFBr6d0~");
    $stmt = $pdo_pre->prepare("SELECT id, plan_id, num_accion, observaciones_gestion, actualizado_en FROM acciones_formativas WHERE id = ?");
    $stmt->execute([12]);
    echo "PREPRODUCTION (pre_intranet_formacion) ID 12:\n";
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "PREPRODUCTION ERROR: " . $e->getMessage() . "\n";
}

// Database 2: Production
try {
    $pdo_prod = new PDO("mysql:host=localhost;dbname=intranet_formacion;charset=utf8mb4", "gestion.efp2026", "Oy0v?ggswFBr6d0~");
    $stmt = $pdo_prod->prepare("SELECT id, plan_id, num_accion, observaciones_gestion, actualizado_en FROM acciones_formativas WHERE id = ?");
    $stmt->execute([12]);
    echo "\nPRODUCTION (intranet_formacion) ID 12:\n";
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "PRODUCTION ERROR: " . $e->getMessage() . "\n";
}
