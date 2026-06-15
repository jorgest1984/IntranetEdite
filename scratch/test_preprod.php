<?php
$_SERVER['HTTP_HOST'] = 'pre-gestion.grupoefp.es';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';

echo "--- Probando Conexión Moodle en PREPRODUCCIÓN --- \n";
echo "Host detectado: " . ($_SERVER['HTTP_HOST'] ?? 'Ninguno') . "\n";
echo "Entorno Preproducción: " . ($is_preproduction ? 'SÍ' : 'NO') . "\n";
echo "DB Host: " . DB_HOST . "\n";
echo "DB User: " . DB_USER . "\n";
echo "DB Name: " . DB_NAME . "\n";
echo "MOODLE_URL_OVERRIDE: " . (defined('MOODLE_URL_OVERRIDE') ? MOODLE_URL_OVERRIDE : 'No definido') . "\n";

try {
    $stmt = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('moodle_url', 'moodle_token')");
    $config = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $val = $row['valor'];
        if ($row['clave'] === 'moodle_token') {
            $val = substr($val, 0, 5) . '...' . substr($val, -5);
        }
        echo "Config DB - {$row['clave']}: {$val}\n";
    }
} catch (Exception $e) {
    echo "Error consultando DB: " . $e->getMessage() . "\n";
}

$moodle = new MoodleAPI($pdo);

if (!$moodle->isConfigured()) {
    die("❌ Moodle NO está configurado.\n");
}

try {
    $info = $moodle->getSiteInfo();
    echo "✅ Conexión Moodle EXITOSA!\n";
    echo "Sitio: " . ($info['sitename'] ?? 'Desconocido') . "\n";
    echo "URL: " . ($info['siteurl'] ?? 'Desconocida') . "\n";
    
} catch (Exception $e) {
    echo "❌ ERROR de Conexión a Moodle: " . $e->getMessage() . "\n";
}
