<?php
require_once 'includes/config.php';
try {
    $stmt = $pdo->query("SELECT * FROM acciones_formativas LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($row);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
