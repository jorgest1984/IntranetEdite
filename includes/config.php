<?php
// includes/config.php

// Detección de entorno (Local vs Preproducción vs Producción)
$host = $_SERVER['HTTP_HOST'] ?? '';
$is_local = in_array($host, ['localhost', '127.0.0.1', 'localhost:8000', 'localhost:3000']);
$is_preproduction = ($host === 'pre-gestion.grupoefp.es');

if ($is_local) {
    // Configuración Local (XAMPP/WAMP)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'intranet_formacion');
    
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Error de conexión LOCAL: " . $e->getMessage());
    }
} elseif ($is_preproduction) {
    // Configuración Preproducción (Plesk) con conexión local directa a MySQL
    define('DB_HOST', 'localhost');
    define('DB_USER', 'tu_usuario_preprod');    // <--- CAMBIAR POR EL USUARIO DE BD DE PREPRODUCCION
    define('DB_PASS', 'tu_clave_preprod');      // <--- CAMBIAR POR LA CLAVE DE BD DE PREPRODUCCION
    define('DB_NAME', 'tu_base_datos_preprod');  // <--- CAMBIAR POR EL NOMBRE DE LA BD DE PREPRODUCCION
    
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Error de conexión en PREPRODUCCIÓN (Plesk): " . $e->getMessage());
    }
} else {
    // Configuración para Producción (Plesk) con conexión local directa a MySQL
    define('DB_HOST', 'localhost');
    define('DB_USER', 'gestion.efp2026');
    define('DB_PASS', 'Oy0v?ggswFBr6d0~');
    define('DB_NAME', 'intranet_formacion');
    
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Error de conexión en PRODUCCIÓN (Plesk): " . $e->getMessage());
    }
}

// Inicializar Manejador de Sesiones en Base de Datos (debe hacerse ANTES de session_start)
require_once __DIR__ . '/SessionHandlerDB.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_save_handler(new SessionHandlerDB($pdo), true);
    session_start();
}

// Configuración de la aplicación
define('APP_NAME', 'Grupo EFP - Gestión Académica');

if ($is_local) {
    define('APP_URL', 'http://localhost/intranet');
} elseif ($is_preproduction) {
    define('APP_URL', 'https://pre-gestion.grupoefp.es');
} else {
    define('APP_URL', 'https://gestion.grupoefp.es');
}

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
function audit_log($pdo, $accion, $entidad, $entidad_id = null, $datos_antiguos = null, $datos_nuevos = null, $usuario_id = null) {
    $uid = $usuario_id;
    if ($uid === null && isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO audit_log (usuario_id, accion, entidad, entidad_id, datos_antiguos, datos_nuevos, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $old_json = $datos_antiguos ? json_encode($datos_antiguos) : null;
    $new_json = $datos_nuevos ? json_encode($datos_nuevos) : null;
    
    return $stmt->execute([
        $uid,
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
