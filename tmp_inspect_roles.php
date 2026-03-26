<?php
require_once 'includes/config.php';
$stmt = $pdo->query("SELECT * FROM roles");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($roles, JSON_PRETTY_PRINT);
?>
