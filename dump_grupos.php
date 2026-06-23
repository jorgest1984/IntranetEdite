<?php
require_once 'includes/config.php';
try {
    $stmt = $pdo->query("DESCRIBE grupos");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
