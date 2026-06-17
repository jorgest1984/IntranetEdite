<?php
$_SERVER['HTTP_HOST'] = 'pre-gestion.grupoefp.es';
require '../includes/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== ESTRUCTURA DE LA TABLA CURSOS ===\n";
try {
    $stmt = $pdo->query("DESCRIBE cursos");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== CURSOS ALMACENADOS ===\n";
try {
    $stmt = $pdo->query("SELECT * FROM cursos ORDER BY id DESC LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
