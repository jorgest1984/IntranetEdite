<?php
/**
 * api_bridge.php - Puente Seguro para Vercel
 * Este archivo DEBE subirse a tu servidor Plesk.
 * NO es necesario abrir el puerto 3306.
 */

// --- CONFIGURACIÓN DE SEGURIDAD ---
// Define una llave secreta. Esta misma llave debe estar en Vercel.
define('BRIDGE_SECRET_KEY', 'dbbea329538b1694971d7ee66cc3e4673');

// --- CONFIGURACIÓN DE BASE DE DATOS LOCAL ---
define('LOCAL_DB_HOST', 'localhost');
define('LOCAL_DB_NAME', 'intranet_formacion');
define('LOCAL_DB_USER', 'gestion.efp2026');
define('LOCAL_DB_PASS', 'Oy0v?ggswFBr6d0~');

// --- LÓGICA DEL PUENTE ---

// --- MANEJO DE ERRORES GLOBAL ---
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode(['error' => "PHP Error [$errno]: $errstr in $errfile on line $errline"]);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        echo json_encode(['error' => "PHP Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']]);
    }
});

header('Content-Type: application/json');

// 1. Validar Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// 2. Validar Token
$headers = function_exists('getallheaders') ? getallheaders() : [];
$provided_token = $headers['X-Bridge-Token'] ?? $headers['x-bridge-token'] ?? $_POST['token'] ?? '';

if ($provided_token !== BRIDGE_SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Invalid Token']);
    exit;
}

// 3. Conectar a DB Local
try {
    $pdo = new PDO("mysql:host=" . LOCAL_DB_HOST . ";dbname=" . LOCAL_DB_NAME . ";charset=utf8mb4", LOCAL_DB_USER, LOCAL_DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed locally: ' . $e->getMessage()]);
    exit;
}

// 4. Procesar Consulta
$sql = $_POST['sql'] ?? '';
$params = isset($_POST['params']) ? json_decode($_POST['params'], true) : [];
$action = $_POST['action'] ?? 'query'; // 'query' o 'execute'

if (empty($sql)) {
    echo json_encode(['error' => 'Empty SQL']);
    exit;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($action === 'query') {
        $data = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        $count = $stmt->rowCount();
        echo json_encode(['success' => true, 'affected_rows' => $count, 'last_insert_id' => $pdo->lastInsertId()]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'SQL Error: ' . $e->getMessage()]);
}

// Nota: Guarda este TOKEN para ponerlo en Vercel:
// TOKEN: <?php echo BRIDGE_SECRET_KEY; ?>
