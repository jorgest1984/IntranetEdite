<?php
// scratch/find_moodle_config.php
header('Content-Type: text/plain; charset=utf-8');

$start_dir = dirname(__DIR__, 2);
echo "Buscando Moodle config.php en: $start_dir\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($start_dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

// Limitar profundidad para no saturar memoria
$iterator->setMaxDepth(3);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getFilename() === 'config.php') {
        $path = $file->getPathname();
        // Omitir el config.php de la propia intranet
        if (strpos($path, 'IntranetEdite') === false && strpos($path, 'includes') === false) {
            echo "Encontrado: $path\n";
        }
    }
}
echo "Búsqueda finalizada.\n";
?>
