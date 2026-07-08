<?php
// scratch/read_debug.php
header('Content-Type: text/plain; charset=utf-8');
$file = dirname(__DIR__) . '/post_debug.log';
if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo "El archivo de log no existe en: $file";
}
?>
