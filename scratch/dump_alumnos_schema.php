<?php
require_once 'includes/config.php';
$stmt = $pdo->query("DESCRIBE alumnos");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Field: " . $row['Field'] . " - Type: " . $row['Type'] . "\n";
}
