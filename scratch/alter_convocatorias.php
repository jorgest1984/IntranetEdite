<?php
require '../includes/config.php';

try {
    $pdo->exec("ALTER TABLE convocatorias ADD COLUMN texto_resolucion TEXT NULL");
    echo "Column added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
