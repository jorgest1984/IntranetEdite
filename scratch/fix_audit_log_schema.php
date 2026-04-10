<?php
require_once 'includes/config.php';

echo "--- Iniciando migración de audit_log --- \n";

try {
    // Permitir NULL en usuario_id
    $sql = "ALTER TABLE audit_log MODIFY COLUMN usuario_id INT(11) NULL DEFAULT NULL";
    $pdo->prepare($sql)->execute();
    echo "✅ ÉXITO: Columna usuario_id ahora permite NULL.\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
