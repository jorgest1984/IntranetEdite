<?php
// scratch_test_moodle.php
$_SERVER['HTTP_HOST'] = 'pre-gestion.grupoefp.es';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $moodle = new MoodleAPI($pdo);
    if (!$moodle->isConfigured()) {
        echo "Error: Moodle not configured.\n";
        exit;
    }
    $info = $moodle->getSiteInfo();
    echo "Site: " . $info['sitename'] . "\n";
    echo "URL: " . $info['siteurl'] . "\n";
    echo "Functions:\n";
    $funcs = array_column($info['functions'] ?? [], 'name');
    sort($funcs);
    foreach ($funcs as $f) {
        echo " - $f\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
