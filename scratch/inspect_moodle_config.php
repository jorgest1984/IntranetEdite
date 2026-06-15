<?php
require_once 'includes/config.php';

echo "--- Moodle Configuration Inspection --- \n\n";

// Host and environment
$host = $_SERVER['HTTP_HOST'] ?? '';
echo "HTTP Host detected: " . ($host ?: 'CLI/None') . "\n";
echo "Is preproduction?: " . ($is_preproduction ? 'Yes' : 'No') . "\n";
echo "Is local?: " . ($is_local ? 'Yes' : 'No') . "\n\n";

try {
    $stmt = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('moodle_url', 'moodle_token')");
    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['clave']] = $row['valor'];
    }
    
    echo "Current DB Connection: " . DB_NAME . "\n";
    echo "moodle_url: " . ($config['moodle_url'] ?? 'Not set') . "\n";
    echo "moodle_token: " . (isset($config['moodle_token']) ? substr($config['moodle_token'], 0, 6) . '...' : 'Not set') . "\n";
    
} catch (Exception $e) {
    echo "Error querying DB: " . $e->getMessage() . "\n";
}
