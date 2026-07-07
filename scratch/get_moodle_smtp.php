<?php
// scratch/get_moodle_smtp.php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/moodle_db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $moodleDb = new MoodleDB();
    if (!$moodleDb->isConnected()) {
        die("No se pudo conectar a la base de datos de Moodle.");
    }
    
    $mpdo = $moodleDb->getPDO();
    $prefix = defined('MOODLE_DB_PREFIX') ? MOODLE_DB_PREFIX : 'avefp_';
    
    $keys = ['smtphosts', 'smtpuser', 'smtppass', 'smtpsecure', 'smtpport', 'noreplyaddress', 'supportemail'];
    
    echo "--- CONFIGURACIÓN SMTP EN MOODLE ---\n";
    foreach ($keys as $key) {
        $stmt = $mpdo->prepare("SELECT value FROM {$prefix}config WHERE name = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        echo "$key: " . ($val !== false ? $val : '[No definido]') . "\n";
    }
    echo "------------------------------------\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
