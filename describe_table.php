<?php
require_once 'includes/config.php';
try {
    $stmt = $pdo->query("DESCRIBE acciones_formativas");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
