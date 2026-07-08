<?php
// scratch/restore_courses.php
require_once dirname(__DIR__) . '/includes/config.php';

$stmt = $pdo->query("SELECT id, nombre_corto, nombre_largo, moodle_id FROM cursos");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== ESTADO DE CURSOS E IDS DE MOODLE ===\n";
foreach ($cursos as $c) {
    echo "ID: {$c['id']} | Código: {$c['nombre_corto']} | Moodle ID: " . ($c['moodle_id'] ?? 'NULL') . "\n";
}
