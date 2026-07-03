<?php
require_once 'includes/config.php';
try {
    $stmt = $pdo->query("DESCRIBE planes");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
