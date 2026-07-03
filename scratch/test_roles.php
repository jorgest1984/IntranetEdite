<?php
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

try {
    $api = new MoodleAPI($pdo);
    // Let's find an action that has Moodle course
    $stmt = $pdo->query("SELECT id_plataforma, curso_id FROM acciones_formativas WHERE id_plataforma IS NOT NULL AND id_plataforma != '' ORDER BY id DESC LIMIT 1");
    $af = $stmt->fetch();
    $courseId = $af['id_plataforma'];
    
    $users = $api->getEnrolledUsers($courseId);
    echo "Course ID: $courseId\n";
    foreach ($users as $u) {
        echo "User: {$u['firstname']} {$u['lastname']} ({$u['email']})\n";
        if (isset($u['roles'])) {
            foreach ($u['roles'] as $r) {
                echo "  Role: {$r['shortname']} ({$r['roleid']})\n";
            }
        } else {
            echo "  No roles returned!\n";
        }
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
