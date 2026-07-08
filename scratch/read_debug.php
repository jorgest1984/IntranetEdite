<?php
// scratch/read_debug.php
header('Content-Type: text/plain; charset=utf-8');

$paths = [
    dirname(__DIR__) . '/uploads/post_debug.txt',
    __DIR__ . '/../post_debug.log',
    __DIR__ . '/post_debug.log',
    __DIR__ . '/../post_debug.txt'
];

$found = false;
foreach ($paths as $path) {
    if (file_exists($path)) {
        echo "--- Encontrado en: $path ---\n";
        echo file_get_contents($path);
        $found = true;
        break;
    }
}

if (!$found) {
    echo "El archivo de log no se encontró en ninguna de las rutas esperadas:\n";
    print_r($paths);
}
?>
