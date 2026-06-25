<?php
require_once '../includes/config.php';
header('Content-Type: text/plain');
try {
    $stmt = $pdo->query("DESCRIBE empresas");
    while($row = $stmt->fetch()) {
        echo $row['Field'] . " (" . $row['Type'] . ") default=" . $row['Default'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
