<?php
require_once 'includes/config.php';

$sql = "CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    data TEXT NOT NULL,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

try {
    $pdo->query($sql);
    echo "Tabla sessions creada/verificada con éxito.\n";
} catch (Exception $e) {
    echo "Error creando la tabla sessions: " . $e->getMessage() . "\n";
}
