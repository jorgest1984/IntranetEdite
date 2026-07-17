<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../includes/config.php';
$stmt = $pdo->query("DESCRIBE planes");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    if (in_array($col['Field'], ['tope_horas_alumno', 'grupo_sector', 'nombre', 'codigo'])) {
        echo "{$col['Field']}: Type={$col['Type']}, Null={$col['Null']}, Default={$col['Default']}\n";
    }
}
