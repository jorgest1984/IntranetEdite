<?php
require_once 'includes/config.php';
try {
    $stmt = $pdo->query("SELECT id, titulo, num_accion, abreviatura, modalidad, nivel FROM acciones_formativas LIMIT 5");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($data as $row) {
        print_r($row);
        echo "-------------------\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
