<?php
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

try {
    $api = new MoodleAPI($pdo);
    $courseId = 12; // Example
    $users = $api->call('core_enrol_get_enrolled_users', [
        'courseid' => 12,
        'options' => [
            ['name' => 'userfields', 'value' => 'id,username,firstname,lastname,email,roles']
        ]
    ]);
    print_r($users);
} catch (Exception $e) {
    echo $e->getMessage();
}
