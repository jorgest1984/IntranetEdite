<?php
require_once 'includes/config.php';

echo "--- Probando registro de log anónimo --- \n";

try {
    // Intentar registrar un log sin usuario_id (debe usar NULL y no fallar por FK)
    $res = audit_log($pdo, 'TEST_ANONYMOUS', 'test', null, null, ['test' => true], null);
    
    if ($res) {
        echo "✅ ÉXITO: Log anónimo registrado correctamente.\n";
    } else {
        echo "❌ FALLO: No se pudo registrar el log.\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
