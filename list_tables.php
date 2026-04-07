<?php
require_once 'includes/config.php';
try {
    $stmt = $pdo->query("SHOW TABLES");
    $results = $stmt->fetchAll(PDO::FETCH_NUM);
    foreach ($results as $row) {
        echo $row[0] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
