<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';

try {
    $moodle = new MoodleAPI($pdo);
    $info = $moodle->getSiteInfo();
    echo "SITE INFO SUCCESS!\n";
    if (isset($info['functions'])) {
        $funcs = array_map(function($f) { return $f['name']; }, $info['functions']);
        sort($funcs);
        echo "Available Functions (" . count($funcs) . "):\n";
        foreach ($funcs as $fn) {
            echo " - " . $fn . "\n";
        }
    } else {
        print_r($info);
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
