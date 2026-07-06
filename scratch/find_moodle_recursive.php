<?php
// Find any config.php under /var/www/vhosts/
$directory = new RecursiveDirectoryIterator('/var/www/vhosts/');
$iterator = new RecursiveIteratorIterator($directory);
$iterator->setMaxDepth(4);

echo "Searching for config.php...\n";

foreach ($iterator as $info) {
    $filename = $info->getFilename();
    $path = $info->getPathname();
    if ($filename === 'config.php' && strpos($path, 'aulavirtual') !== false) {
        echo "FOUND: " . $path . "\n";
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
    }
}
echo "Search completed.\n";
