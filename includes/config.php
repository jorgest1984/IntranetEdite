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
    
    // Moodle Overrides (Local defaults)
    define('MOODLE_URL_OVERRIDE', '');
    define('MOODLE_AULA_VIRTUAL_URL', 'https://aulavirtual.grupoefp.es/');
    
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
    define('DB_USER', 'pre_gestion');
    define('DB_PASS', 'Oy0v?ggswFBr6d0~');
    define('DB_NAME', 'pre_intranet_formacion');
    
    // Moodle Overrides (Preproduction values)
    define('MOODLE_URL_OVERRIDE', 'https://pre-aulavirtual.grupoefp.es');
    define('MOODLE_AULA_VIRTUAL_URL', 'https://pre-aulavirtual.grupoefp.es/');
    
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
    
    // Moodle Overrides (Production values)
    define('MOODLE_URL_OVERRIDE', 'https://aulavirtual.grupoefp.es');
    define('MOODLE_AULA_VIRTUAL_URL', 'https://aulavirtual.grupoefp.es/');
    
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Error de conexión en PRODUCCIÓN (Plesk): " . $e->getMessage());
    }
}

// Cabeceras de seguridad HTTP globales (ISO 27001)
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https://api.qrserver.com; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'none';");
    header("Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()");
}

// Inicializar Manejador de Sesiones en Base de Datos (debe hacerse ANTES de session_start)
require_once __DIR__ . '/SessionHandlerDB.php';
if (session_status() === PHP_SESSION_NONE) {
    // Configuración de cookies seguras de sesión
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Cookie secure sólo si se usa HTTPS
    $is_secure_conn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    if ($is_secure_conn) {
        ini_set('session.cookie_secure', 1);
    }
    
    // Configurar parámetros de cookie con SameSite Lax (compatible PHP 7.3+)
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $is_secure_conn,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(0, '/; HttpOnly; SameSite=Lax', '', $is_secure_conn, true);
    }
    
    session_set_save_handler(new SessionHandlerDB($pdo), true);
    session_start();

    // Vinculación de huella de sesión para evitar secuestro de sesión (Hijacking)
    if (isset($_SESSION['user_id'])) {
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $client_ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $client_ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        $client_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Migración retroactiva para sesiones activas que no tengan guardada la huella
        if (!isset($_SESSION['created_ip'])) {
            $_SESSION['created_ip'] = $client_ip;
        }
        if (!isset($_SESSION['created_ua'])) {
            $_SESSION['created_ua'] = $client_ua;
        }

        // Validar si la IP o el User-Agent han cambiado
        if ($_SESSION['created_ip'] !== $client_ip || $_SESSION['created_ua'] !== $client_ua) {
            session_unset();
            session_destroy();
            header("Location: index.php?timeout=2");
            exit();
        }

        // Regeneración periódica de ID de sesión (cada 15 minutos - 900s)
        if (!isset($_SESSION['session_created'])) {
            $_SESSION['session_created'] = time();
        } elseif (time() - $_SESSION['session_created'] > 900) {
            session_regenerate_id(true);
            $_SESSION['session_created'] = time();
        }
    }
}

// Inicializar token CSRF global para protección en formularios
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
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
