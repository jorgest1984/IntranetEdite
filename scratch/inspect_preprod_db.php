<?php
// scratch/inspect_preprod_db.php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== ENVIRONMENT ===\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
echo "DB_NAME: " . DB_NAME . "\n\n";

echo "=== ALUMNO 39 ===\n";
try {
    $stmt = $pdo->prepare("SELECT * FROM alumnos WHERE id = ?");
    $stmt->execute([39]);
    print_r($stmt->fetch());
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== MATRICULA 39 ===\n";
try {
    $stmt = $pdo->prepare("SELECT * FROM matriculas WHERE id = ?");
    $stmt->execute([39]);
    print_r($stmt->fetch());
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== TUTORIAS_SEGUIMIENTO 39 ===\n";
try {
    $stmt = $pdo->prepare("SELECT * FROM tutorias_seguimiento WHERE id = ?");
    $stmt->execute([39]);
    print_r($stmt->fetch());
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
