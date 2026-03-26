<?php
require 'includes/config.php';
$stmt = $pdo->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_Column);
file_put_contents('dump_tables.json', json_encode($tables, JSON_PRETTY_PRINT));
echo "Done.";
