<?php
// Test possible production Moodle DB combinations with the new password
$configs = [
    [
        'host' => 'localhost',
        'user' => 'aulavirtual',
        'pass' => '5g39zT!e4',
        'name' => 'bdaulavirtual'
    ],
    [
        'host' => 'localhost',
        'user' => 'grupoefp_aulavirtual',
        'pass' => '5g39zT!e4',
        'name' => 'grupoefp_bdaulavirtual'
    ],
    [
        'host' => 'localhost',
        'user' => 'grupoefp_moodle',
        'pass' => '5g39zT!e4',
        'name' => 'grupoefp_moodle'
    ],
    [
        'host' => 'localhost',
        'user' => 'grupoefp_moodle_prod',
        'pass' => '5g39zT!e4',
        'name' => 'grupoefp_moodle_prod'
    ],
    [
        'host' => 'localhost',
        'user' => 'pre-aulavirtual',
        'pass' => '5g39zT!e4',
        'name' => 'pre-bdaulavirtual'
    ],
    [
        'host' => 'localhost',
        'user' => 'moodle_prod',
        'pass' => '5g39zT!e4',
        'name' => 'moodle_prod'
    ]
];

foreach ($configs as $idx => $cfg) {
    echo "TESTING CONFIG " . ($idx + 1) . " (User: " . $cfg['user'] . ", DB: " . $cfg['name'] . ")...\n";
    try {
        $test_pdo = new PDO("mysql:host=" . $cfg['host'] . ";dbname=" . $cfg['name'] . ";charset=utf8mb4", $cfg['user'], $cfg['pass']);
        $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "--> SUCCESS CONNECTING! Config " . ($idx + 1) . " works!\n\n";
        exit();
    } catch (PDOException $e) {
        echo "--> FAILED: " . $e->getMessage() . "\n\n";
    }
}

echo "All connection tests failed.\n";
