<?php
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

echo "--- Probando Conexión Moodle --- \n";

$moodle = new MoodleAPI($pdo);

if (!$moodle->isConfigured()) {
    die("❌ Moodle NO está configurado en la base de datos.\n");
}

try {
    $info = $moodle->getSiteInfo();
    echo "✅ Conexión EXITOSA!\n";
    echo "Sitio: " . ($info['sitename'] ?? 'Desconocido') . "\n";
    echo "URL: " . ($info['siteurl'] ?? 'Desconocida') . "\n";
    
} catch (Exception $e) {
    echo "❌ ERROR de Conexión: " . $e->getMessage() . "\n";
}
