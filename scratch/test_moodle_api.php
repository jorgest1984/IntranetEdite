<?php
// scratch/test_moodle_api.php
define('HTTP_HOST', 'gestion.grupoefp.es');
$_SERVER['HTTP_HOST'] = 'gestion.grupoefp.es';

try {
    require_once dirname(__DIR__) . '/includes/config.php';
    require_once dirname(__DIR__) . '/includes/moodle_api.php';
    
    echo "=== MOODLE API TEST ===\n";
    
    $moodle = new MoodleAPI($pdo);
    if (!$moodle->isConfigured()) {
        echo "Moodle is not configured in the database!\n";
        exit;
    }
    
    // Get Moodle settings from DB to print securely
    $stmt = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('moodle_url', 'moodle_token')");
    while ($row = $stmt->fetch()) {
        $val = $row['valor'];
        if ($row['clave'] === 'moodle_token') {
            $val = substr($val, 0, 4) . '...' . substr($val, -4);
        }
        echo "Config: {$row['clave']} => {$val}\n";
    }
    
    // 1. Test getSiteInfo
    echo "\n1. Testing getSiteInfo (core_webservice_get_site_info)...\n";
    try {
        $siteInfo = $moodle->getSiteInfo();
        echo "SUCCESS! Site Name: " . ($siteInfo['sitename'] ?? 'Unknown') . "\n";
        echo "Username: " . ($siteInfo['username'] ?? 'Unknown') . "\n";
        if (isset($siteInfo['functions'])) {
            echo "Available Web Service Functions in this Token:\n";
            $allowedFunctions = array_column($siteInfo['functions'], 'name');
            foreach ($allowedFunctions as $func) {
                echo " - {$func}\n";
            }
        } else {
            echo "No functions listed in site info.\n";
        }
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
    
    // 2. Test getUsersByField with core_user_get_users
    echo "\n2. Testing getUsersByField (core_user_get_users) with dummy email...\n";
    try {
        $users = $moodle->getUsersByField('email', ['test_dummy_nonexistent@example.com']);
        echo "SUCCESS! getUsersByField call succeeded.\n";
        print_r($users);
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "Global Error: " . $e->getMessage() . "\n";
}
