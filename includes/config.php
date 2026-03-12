<?php
// includes/config.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Configuración de base de datos (con soporte para variables de entorno de Vercel)
define('DB_HOST', getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost');
define('DB_USER', getenv('DB_USER') !== false ? getenv('DB_USER') : 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'intranet_formacion');

// Conexión PDO
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
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
