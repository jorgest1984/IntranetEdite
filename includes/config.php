<?php
// includes/config.php

// Detección de entorno (Local vs Producción)
$is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000', 'localhost:3000']);

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
        die("Error de conexión LOCAL: " . $e->getMessage());
    }
} else {
    /**
     * Clase DBBridge - Simula PDO a través de un puente HTTP
     * Esto evita abrir el puerto 3306.
     */
    class DBBridge {
        private $bridge_url;
        private $token;
        public $lastInsertId = null;

        public function __construct($url, $token) {
            $this->bridge_url = $url;
            $this->token = $token;
        }

        public function prepare($sql) { return new BridgeStatement($this->bridge_url, $this->token, $sql, $this); }
        
        public function query($sql) {
            $stmt = $this->prepare($sql);
            $stmt->execute();
            return $stmt;
        }

        public function lastInsertId() { return $this->lastInsertId; }
        
        // Métodos vacíos para compatibilidad simple
        public function setAttribute($a, $b) {}
        public function beginTransaction() {}
        public function commit() {}
        public function rollBack() {}
    }

    class BridgeStatement {
        private $url;
        private $token;
        private $sql;
        private $data = [];
        private $parent;

        public function __construct($url, $token, $sql, $parent) {
            $this->url = $url;
            $this->token = $token;
            $this->sql = $sql;
            $this->parent = $parent;
        }

        public function execute($params = []) {
            $post = [
                'token' => $this->token,
                'sql' => $this->sql,
                'params' => json_encode($params),
                'action' => (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $this->sql)) ? 'query' : 'execute'
            ];

            $ch = curl_init($this->url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix XAMPP SSL cert issues
            $response = curl_exec($ch);
            
            if ($response === false) {
                die("Error de Curl: " . curl_error($ch));
            }
            curl_close($ch);

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                die("Error de Bridge: No se pudo decodificar la respuesta JSON. <br>Respuesta recibida: <pre>" . htmlspecialchars($response) . "</pre><br>Error JSON: " . json_last_error_msg());
            }

            if (isset($result['error'])) {
                die("Error de Bridge: " . $result['error']);
            }
            
            $this->data = $result['data'] ?? [];
            if (isset($result['last_insert_id'])) {
                $this->parent->lastInsertId = $result['last_insert_id'];
            }
            return true;
        }

        public function fetchAll() { return $this->data; }
        public function fetch() { return array_shift($this->data); }
        public function rowCount() { return count($this->data); }
    }

    // Configuración para Producción (Vercel) usando el Puente
    // IMPORTANTE: Sube api_bridge.php a tu servidor y pon aquí la URL completa.
    $bridge_url = 'https://gestion.grupoefp.es/api_bridge.php'; 
    $bridge_token = getenv('BRIDGE_TOKEN') ?: 'dbbea329538b1694971d7ee66cc3e4673'; // Configúralo en Vercel

    $pdo = new DBBridge($bridge_url, $bridge_token);
}

// Inicializar Manejador de Sesiones en Base de Datos
require_once __DIR__ . '/SessionHandlerDB.php';
session_set_save_handler(new SessionHandlerDB($pdo), true);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

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
