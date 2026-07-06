<?php
// Test possible production Moodle DB credentials with Plesk prefixes
$configs = [
    [
        'host' => 'localhost',
        'user' => 'grupoefp_aulavirtual',
        'pass' => 'Js7~29e1t',
        'name' => 'grupoefp_bdaulavirtual'
    ],
    [
        'host' => 'localhost',
        'user' => 'grupoefp_moodle',
        'pass' => 'Js7~29e1t',
        'name' => 'grupoefp_moodle'
    ],
    [
        'host' => 'localhost',
        'user' => 'grupoefp_moodle_prod',
        'pass' => 'Oy0v?ggswFBr6d0~',
        'name' => 'grupoefp_moodle_prod'
    ],
    [
        'host' => 'localhost',
        'user' => 'grupoefp_moodle_prod',
        'pass' => 'Js7~29e1t',
        'name' => 'grupoefp_moodle_prod'
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

echo "All prefix connection tests failed.\n";
