<?php
// clear_cache.php
header('Content-Type: text/plain; charset=utf-8');

echo "=== CACHE & DEPLOYMENT DIAGNOSTIC ===\n\n";

// 1. Clear OPcache
if (function_exists('opcache_reset')) {
    $res = opcache_reset();
    echo "OPcache Reset: " . ($res ? "SUCCESS (Cache cleared successfully!)" : "FAILED (Could not reset cache)") . "\n";
} else {
    echo "OPcache Reset: NOT AVAILABLE (OPcache extension is not enabled/loaded on this PHP server)\n";
}

// 2. Check if the updated moodle_api.php is actually on the disk of the live server
$filePath = __DIR__ . '/includes/moodle_api.php';
if (file_exists($filePath)) {
    $content = file_get_contents($filePath);
    
    echo "\n=== FILE INTEGRITY CHECK ===\n";
    echo "File path: $filePath\n";
    echo "File size: " . filesize($filePath) . " bytes\n";
    echo "Last modified: " . date("Y-m-d H:i:s", filemtime($filePath)) . "\n";
    
    // Check if new string exists
    if (strpos($content, 'Moodle API Error (" . $functionName') !== false) {
        echo "Status: UPDATED VERSION IS LOADED ON DISK! (contains function name logs)\n";
    } else {
        echo "Status: OLD VERSION IS STILL LOADED ON DISK! (does not contain function name logs)\n";
        echo "Suggestion: Plesk Git deployment might not have run yet, or the changes have not been fetched/pulled on the live server.\n";
    }
} else {
    echo "\nError: File not found at $filePath\n";
}
