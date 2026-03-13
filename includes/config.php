<?php
// includes/config.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Detección de entorno (Local vs Producción)
$is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000', 'localhost:3000']);

if ($is_local) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'intranet_formacion');
} else {
    define('DB_HOST', getenv('DB_HOST') ?: '81.43.76.167');
    define('DB_USER', getenv('DB_USER') ?: 'gestion.efp2026');
    define('DB_PASS', getenv('DB_PASS') ?: 'Oy0v?ggswFBr6d0~');
    define('DB_NAME', getenv('DB_NAME') ?: 'intranet_formacion');
}

// Limpiar DB_HOST de posibles puertos duplicados y forzar TCP/IP
$db_host = DB_HOST;
$db_port = '3306';
if (strpos($db_host, ':') !== false) {
    list($db_host, $db_port) = explode(':', $db_host);
}

// Si es localhost en entorno no-local (Vercel), forzamos 127.0.0.1
if ($db_host === 'localhost' && !$is_local) {
    $db_host = '127.0.0.1';
}

// Conexión PDO
try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if ($is_local) {
        die("Error de conexión LOCAL: " . $e->getMessage() . ". Asegúrate de que XAMPP esté encendido y que la base de datos 'intranet_formacion' esté creada.");
    } else {
        die("Error de conexión de PRODUCCIÓN: " . $e->getMessage());
    }
}

// Configuración de la aplicación
define('APP_NAME', 'Intranet Formación');
define('APP_URL', 'http://localhost/intranet');

// Tiempo de expiración de sesión (ej. 30 minutos para ISO 27001)
define('SESSION_TIMEOUT', 1800);

// Verificar expiración de sesión por inactividad
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header("Location: index.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Función para registrar logs de auditoría (ISO 27001)
function audit_log($pdo, $accion, $entidad, $entidad_id = null, $datos_antiguos = null, $datos_nuevos = null) {
    if (!isset($_SESSION['user_id'])) return false;
    
    $stmt = $pdo->prepare("INSERT INTO audit_log (usuario_id, accion, entidad, entidad_id, datos_antiguos, datos_nuevos, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $agent = $_SERVER['HTTP_USER_AGENT'];
    
    $old_json = $datos_antiguos ? json_encode($datos_antiguos) : null;
    $new_json = $datos_nuevos ? json_encode($datos_nuevos) : null;
    
    return $stmt->execute([
        $_SESSION['user_id'],
        $accion,
        $entidad,
        $entidad_id,
        $old_json,
        $new_json,
        $ip,
        $agent
    ]);
}
?>
