<?php
require_once __DIR__ . '/includes/config.php';
$stmt = $pdo->query('DESCRIBE alumnos');
while ($r = $stmt->fetch()) {
    echo $r['Field'] . ' | ' . $r['Type'] . PHP_EOL;
}
