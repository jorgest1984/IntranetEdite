<?php
// api/inspect_moodle_config.php
require_once '../includes/auth.php';
require_once '../includes/config.php';

if (!has_permission([ROLE_ADMIN])) {
    die("Acceso denegado. Se requiere ser Administrador.");
}

$stmt = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('moodle_url', 'moodle_token')");
$config = [];
while ($row = $stmt->fetch()) {
    $config[$row['clave']] = $row['valor'];
}

$url = $config['moodle_url'] ?? '';
$token = $config['moodle_token'] ?? '';

echo "<h2>Inspección de Configuración Guardada en BD</h2>";
echo "<strong>URL Moodle:</strong> " . htmlspecialchars($url) . "<br>";
echo "<strong>Token Moodle (primeros y últimos 4 caracteres):</strong> ";
if (strlen($token) > 8) {
    echo htmlspecialchars(substr($token, 0, 4)) . "..." . htmlspecialchars(substr($token, -4));
} else {
    echo "Faltante o muy corto";
}
echo " (Longitud: " . strlen($token) . " caracteres)<br>";
