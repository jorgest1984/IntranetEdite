<?php
// test_db.php (V3)
require_once 'includes/config.php';

echo "--- INICIO DE PRUEBA DE BASE DE DATOS (V3) ---\n";
echo "Host real usado: " . ($db_host ?? 'No definido') . "\n";
echo "Puerto real usado: " . ($db_port ?? 'No definido') . "\n";
echo "Usuario: " . DB_USER . "\n";
echo "Base de Datos: " . DB_NAME . "\n";

try {
    $stmt = $pdo->query("SELECT 1");
    if ($stmt) {
        echo "\n✅ ¡CONEXIÓN EXITOSA!\n";
    }
} catch (PDOException $e) {
    echo "\n❌ FALLO EN LA CONEXIÓN:\n";
    echo $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), '2002') !== false) {
        echo "\nANÁLISIS: Todavía es un error de comunicación local. Si Host no es 127.0.0.1/localhost, el servidor DNS o el Host son incorrectos.\n";
    }
}
echo "\n--- FIN DE PRUEBA ---\n";
?>
