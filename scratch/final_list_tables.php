<?php
require 'includes/config.php';
$stmt = $pdo->query('SHOW TABLES');
// Manually fetch and print
while ($row = $stmt->fetch()) {
    echo reset($row) . "\n";
}
