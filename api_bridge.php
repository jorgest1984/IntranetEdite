<?php
/**
 * api_bridge.php - Puente Seguro para Vercel (Versión Robusta v2)
 * Este archivo DEBE subirse a tu servidor Plesk.
 */

// --- CONFIGURACIÓN DE SEGURIDAD ---
define('BRIDGE_SECRET_KEY', 'dbbea329538b1694971d7ee66cc3e4673');

// --- CONFIGURACIÓN DE BASE DE DATOS LOCAL ---
define('LOCAL_DB_HOST', 'localhost');
define('LOCAL_DB_NAME', 'intranet_formacion');
define('LOCAL_DB_USER', 'gestion.efp2026');
define('LOCAL_DB_PASS', 'Oy0v?ggswFBr6d0~');

// --- MANEJO DE ERRORES GLOBAL ---
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return;
    echo json_encode(['error' => "PHP Error [$errno]: $errstr in $errfile on line $errline"]);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        echo json_encode(['error' => "PHP Fatal Error: " . $error['message']]);
    }
});

header('Content-Type: application/json');

// 1. Validar Método y Token
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$provided_token = $headers['X-Bridge-Token'] ?? $headers['x-bridge-token'] ?? $_POST['token'] ?? '';

if ($provided_token !== BRIDGE_SECRET_KEY) {
    echo json_encode(['error' => 'Forbidden: Invalid Token']);
    exit;
}

// 2. Conectar a DB Local
try {
    $pdo = new PDO("mysql:host=" . LOCAL_DB_HOST . ";dbname=" . LOCAL_DB_NAME . ";charset=utf8mb4", LOCAL_DB_USER, LOCAL_DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
    exit;
}

// 3. Procesar Consulta
$sql = $_POST['sql'] ?? '';
$params = isset($_POST['params']) ? json_decode($_POST['params'], true) : [];
$action = $_POST['action'] ?? 'query';

if (empty($sql)) {
    echo json_encode(['error' => 'Empty SQL']);
    exit;
}

try {
    $stmt = $pdo->prepare($sql);
    
    // Vinculación robusta de parámetros
    if (is_array($params)) {
        foreach ($params as $key => $value) {
            $paramName = is_int($key) ? $key + 1 : $key;
            
            if ($value === null) {
                $stmt->bindValue($paramName, null, PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $stmt->bindValue($paramName, $value, PDO::PARAM_BOOL);
            } elseif (is_int($value)) {
                $stmt->bindValue($paramName, $value, PDO::PARAM_INT);
            } else {
                // Para fechas vacías que vengan como '' por error, forzamos NULL si el campo lo permite
                // Aunque lo ideal es que vengan como null desde el cliente.
                $stmt->bindValue($paramName, $value, PDO::PARAM_STR);
            }
        }
    }

    $stmt->execute();
    
    if ($action === 'query') {
        $data = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode([
            'success' => true, 
            'affected_rows' => $stmt->rowCount(), 
            'last_insert_id' => $pdo->lastInsertId()
        ]);
    }
} catch (Exception $e) {
    // Depuración: Enviar el SQL y parámetros en caso de error
    echo json_encode([
        'error' => 'SQL Error: ' . $e->getMessage(),
        'debug_sql' => $sql,
        'debug_params' => $params
    ]);
}
