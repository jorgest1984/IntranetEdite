<?php
require_once 'includes/config.php';
$stmt = $pdo->query("SELECT * FROM configuracion");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['clave'] . " = " . $row['valor'] . "\n";
}
