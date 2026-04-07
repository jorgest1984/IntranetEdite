<?php
require_once 'includes/config.php';
try {
    $stmt = $pdo->query("DESCRIBE acciones_formativas");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $fields = array_column($columns, 'Field');
    $interesting = ['id', 'titulo', 'curso', 'accion', 'abreviatura', 'codigo', 'modalidad', 'nivel', 'horas'];
    foreach ($fields as $f) {
        foreach ($interesting as $i) {
            if (stripos($f, $i) !== false) {
                echo $f . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
