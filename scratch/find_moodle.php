<?php
// Find Moodle's config.php on the server to read correct database credentials
$possible_paths = [
    __DIR__ . '/../../aulavirtual/config.php',
    __DIR__ . '/../../../aulavirtual/config.php',
    '/var/www/vhosts/grupoefp.es/aulavirtual/config.php',
    '/var/www/vhosts/gestion.grupoefp.es/aulavirtual/config.php',
    '/var/www/vhosts/grupoefp.es/subdomains/aulavirtual/config.php'
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        echo "FOUND Moodle config.php at: " . $path . "\n";
        $content = file_get_contents($path);
        // Extract DB variables using regex
        preg_match('/\$CFG->dbhost\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $dbhost);
        preg_match('/\$CFG->dbname\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $dbname);
        preg_match('/\$CFG->dbuser\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $dbuser);
        preg_match('/\$CFG->dbpass\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $dbpass);
        preg_match('/\$CFG->prefix\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $prefix);

        echo "Moodle DB config:\n";
        echo "Host: " . ($dbhost[1] ?? 'not found') . "\n";
        echo "Name: " . ($dbname[1] ?? 'not found') . "\n";
        echo "User: " . ($dbuser[1] ?? 'not found') . "\n";
        echo "Pass: " . ($dbpass[1] ?? 'not found') . "\n";
        echo "Prefix: " . ($prefix[1] ?? 'not found') . "\n";
        exit();
    }
}

echo "Moodle config.php NOT found in typical paths.\n";
