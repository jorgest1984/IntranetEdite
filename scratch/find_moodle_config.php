<?php
// scratch/find_moodle_config.php
header('Content-Type: text/plain; charset=utf-8');

$start_dir = '/var/www/vhosts/aulavirtual.grupoefp.es';
if (!is_dir($start_dir)) {
    $start_dir = '/var/www/vhosts';
}

echo "Buscando Moodle config.php en: $start_dir\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($start_dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

// Limitar profundidad para rapidez y evitar problemas de permisos
$iterator->setMaxDepth(3);

try {
    foreach ($iterator as $file) {
        try {
            if ($file->isFile() && $file->getFilename() === 'config.php') {
                $path = $file->getPathname();
                // Buscar específicamente el config de moodle
                if (strpos($path, 'aulavirtual') !== false && strpos($path, 'includes') === false) {
                    echo "Encontrado: $path\n";
                    
                    // Intentar leer la configuración de base de datos de Moodle para validar
                    $content = file_get_contents($path);
                    if (strpos($content, '$CFG') !== false) {
                        echo "  (Es un archivo config.php de Moodle válido)\n";
                    }
                }
            }
        } catch (Exception $inner) {
            // Ignorar errores de permisos en carpetas restringidas
        }
    }
} catch (Exception $e) {
    echo "Error de lectura: " . $e->getMessage() . "\n";
}
echo "Búsqueda finalizada.\n";
?>
