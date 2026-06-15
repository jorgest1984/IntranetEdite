<?php
// scratch/git_pull.php
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE GIT EN EL SERVIDOR ===\n\n";

echo "1. Ejecutando: git status\n";
echo shell_exec('git status 2>&1') . "\n";

echo "----------------------------------------\n";
echo "2. Ejecutando: git remote -v\n";
echo shell_exec('git remote -v 2>&1') . "\n";

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'pull') {
        echo "----------------------------------------\n";
        echo "3. Ejecutando: git pull\n";
        echo shell_exec('git pull 2>&1') . "\n";
    } elseif ($action === 'force') {
        echo "----------------------------------------\n";
        echo "3. Ejecutando: git fetch --all && git reset --hard origin/main\n";
        echo shell_exec('git fetch --all 2>&1') . "\n";
        echo shell_exec('git reset --hard origin/main 2>&1') . "\n";
    }
} else {
    echo "\nPara realizar acciones, accede con un parámetro:\n";
    echo "- Hacer Pull: ?action=pull\n";
    echo "- Forzar sincronización (descarta cambios locales en el servidor): ?action=force\n";
}
?>
