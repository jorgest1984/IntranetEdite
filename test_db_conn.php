<?php
// test_db_conn.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE CONEXIÓN A BASE DE DATOS ===\n\n";

echo "1. Cargando archivo de configuración...\n";
try {
    require_once __DIR__ . '/includes/config.php';
    echo "¡Configuración cargada correctamente!\n\n";
} catch (Exception $e) {
    echo "ERROR AL CARGAR CONFIGURACIÓN:\n";
    echo $e->getMessage() . "\n";
    exit();
}

echo "2. Comprobando variables de entorno en " . ($_SERVER['HTTP_HOST'] ?? 'Desconocido') . "...\n";
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'No definido') . "\n";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'No definido') . "\n";
echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'No definido') . "\n";
echo "APP_URL: " . (defined('APP_URL') ? APP_URL : 'No definido') . "\n\n";

echo "3. Comprobando objeto de conexión PDO...\n";
if (isset($pdo) && $pdo instanceof PDO) {
    echo "¡PDO está instanciado correctamente!\n";
    try {
        $stmt = $pdo->query("SELECT DATABASE()");
        $dbName = $stmt->fetchColumn();
        echo "¡Conexión establecida con éxito! Base de datos activa: " . ($dbName ?: 'Ninguna') . "\n";
    } catch (PDOException $e) {
        echo "ERROR al consultar la base de datos: " . $e->getMessage() . "\n";
    }
} else {
    echo "ERROR: La variable \$pdo no está disponible o no es una instancia de PDO.\n";
}
?>
