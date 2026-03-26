<?php
require 'includes/config.php';
try {
    $pdo->query("INSERT IGNORE INTO roles (id, nombre) VALUES (5, 'Comercial'), (6, 'Tutor')");
    echo "Roles insertados correctamente.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
