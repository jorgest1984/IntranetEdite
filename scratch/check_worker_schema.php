<?php
require_once 'includes/config.php';

function dump_table($pdo, $table) {
    echo "--- Table: $table ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} ({$row['Type']})\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

dump_table($pdo, 'profesorado_detalles');
dump_table($pdo, 'usuarios');
dump_table($pdo, 'alumnos');
dump_table($pdo, 'prof_asistencia');
dump_table($pdo, 'prof_formacion');
dump_table($pdo, 'prof_tareas');
