<?php
require 'includes/config.php';
$stmt = $pdo->query("SHOW TABLES LIKE 'prof_%'");
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
    echo $t . "\n";
}
