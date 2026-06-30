<?php
require_once '../includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "--- ULTIMAS MATRICULAS Y SUS GRUPOS/ACCIONES ---\n\n";

try {
    $rows = $pdo->query("
        SELECT m.id as matricula_id, m.grupo_id, g.numero_grupo, g.accion_id, af.num_accion, af.abreviatura, af.curso_id, c.nombre_corto as curso_code
        FROM matriculas m
        LEFT JOIN grupos g ON m.grupo_id = g.id
        LEFT JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN cursos c ON af.curso_id = c.id
        ORDER BY m.id DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        print_r($row);
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
