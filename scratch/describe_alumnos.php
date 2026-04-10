<?php
require_once 'includes/config.php';
$stmt = $pdo->query("DESCRIBE alumnos");
while($row = $stmt->fetch()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
