<?php
// scratch/read_debug.php
$file = dirname(__DIR__) . '/uploads/post_af_debug.txt';
if (file_exists($file)) {
    echo "=== CONTENIDO DEL POST REGISTRADO ===\n";
    echo file_get_contents($file);
} else {
    echo "El archivo de log temporal no existe. Comprueba que el formulario se esté enviando.";
}
?>
