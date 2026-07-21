<?php
require __DIR__ . '/../includes/config.php';

try {
    $pdo->exec("ALTER TABLE grupos ADD COLUMN sede_id INT NULL");
    echo "Columna sede_id añadida a grupos.<br>\n";
} catch (Exception $e) {
    echo "Aviso grupos sede_id: " . $e->getMessage() . "<br>\n";
}

echo "Migración V2 completada exitosamente.<br>\n";
