<?php
// Test Moodle API course retrieval on the production server
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';

try {
    $moodle = new MoodleAPI($pdo);
    if (!$moodle->isConfigured()) {
        die("ERROR: Moodle is not configured.\n");
    }
    
    echo "Moodle configured: YES\n";
    $courses = $moodle->getCourses();
    echo "Retrieved " . count($courses) . " courses:\n";
    print_r(array_slice($courses, 0, 5));
} catch (Exception $e) {
    echo "ERROR calling Moodle API: " . $e->getMessage() . "\n";
}
