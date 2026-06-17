<?php
// scratch/test_moodle_connection.php
header('Content-Type: text/plain; charset=utf-8');

// Detección de entorno
$host = $_SERVER['HTTP_HOST'] ?? '';
echo "=== PRUEBA DE CONEXIÓN A MOODLE DB ===\n";
echo "Host: {$host}\n";

require_once dirname(dirname(__FILE__)) . '/includes/config.php';

echo "\n--- Parámetros definidos ---\n";
echo "MOODLE_DB_HOST: " . (defined('MOODLE_DB_HOST') ? MOODLE_DB_HOST : 'NO DEFINIDO') . "\n";
echo "MOODLE_DB_PORT: " . (defined('MOODLE_DB_PORT') ? MOODLE_DB_PORT : 'NO DEFINIDO') . "\n";
echo "MOODLE_DB_USER: " . (defined('MOODLE_DB_USER') ? MOODLE_DB_USER : 'NO DEFINIDO') . "\n";
echo "MOODLE_DB_NAME: " . (defined('MOODLE_DB_NAME') ? MOODLE_DB_NAME : 'NO DEFINIDO') . "\n";

echo "\n--- Intentando conectar... ---\n";
try {
    $dbHost = MOODLE_DB_HOST;
    $dbPort = defined('MOODLE_DB_PORT') ? MOODLE_DB_PORT : '3306';
    $dbName = MOODLE_DB_NAME;
    $dbUser = MOODLE_DB_USER;
    $dbPass = defined('MOODLE_DB_PASS') ? MOODLE_DB_PASS : '';

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3
    ];

    $start = microtime(true);
    $conn = new PDO($dsn, $dbUser, $dbPass, $options);
    $end = microtime(true);
    echo "¡CONEXIÓN EXITOSA! Tiempo de respuesta: " . round($end - $start, 4) . " segundos.\n";
    
    // Consulta simple
    $stmt = $conn->query("SELECT count(*) as total_users FROM mdl_user");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Número de usuarios en mdl_user: " . $row['total_users'] . "\n";

} catch (PDOException $e) {
    echo "ERROR DE PDOException: " . $e->getMessage() . "\n";
    echo "Código de error: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "ERROR GENERAL: " . $e->getMessage() . "\n";
}
?>
