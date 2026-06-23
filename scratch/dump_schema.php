<?php
require_once 'includes/config.php';

$tables = ['matriculas', 'alumnos', 'empresas', 'acciones_formativas', 'cursos', 'convocatorias', 'planes', 'usuarios'];

$schema = [];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $schema[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $schema[$table] = $e->getMessage();
    }
}

file_put_contents('scratch/schema_dump.json', json_encode($schema, JSON_PRETTY_PRINT));
echo "Schema dumped.";
