<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once 'includes/config.php';

try {
    echo "--- COLUMNS OF tutorias_seguimiento ---\n";
    $stmt = $pdo->query("DESCRIBE tutorias_seguimiento");
    while($row = $stmt->fetch()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
