<?php
// scratch/read_debug.php
date_default_timezone_set('Europe/Madrid');
echo "Hora actual del servidor: " . date('Y-m-d H:i:s') . "\n\n";

$file = dirname(__DIR__) . '/uploads/post_af_debug.txt';
if (file_exists($file)) {
    echo "=== CONTENIDO DEL POST REGISTRADO (Modificado: " . date('Y-m-d H:i:s', filemtime($file)) . ") ===\n";
    echo file_get_contents($file);
} else {
    echo "El archivo de log temporal de POST no existe.\n";
}

$file2 = dirname(__DIR__) . '/uploads/save_log.txt';
if (file_exists($file2)) {
    echo "\n=== RESULTADO DE GUARDAR (save_log.txt) (Modificado: " . date('Y-m-d H:i:s', filemtime($file2)) . ") ===\n";
    echo file_get_contents($file2);
} else {
    echo "\nEl archivo de log temporal de GUARDAR no existe.\n";
}
?>
