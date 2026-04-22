<?php
require 'includes/config.php';
$tables = ['tutorias_seguimiento', 'usuarios', 'empresas', 'matriculas'];
foreach ($tables as $t) {
    echo "--- $t ---\n";
    $stmt = $pdo->query("DESCRIBE $t");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['Field']} ({$row['Type']})\n";
    }
}
