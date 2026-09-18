<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== ACCIONES FORMATIVAS ===\n";
$stmt = $pdo->query("SELECT id, codigo, abreviatura, titulo, id_plataforma, num_accion FROM acciones_formativas WHERE titulo LIKE '%igualdad%' OR abreviatura LIKE '%ADG%' OR codigo LIKE '%ADG%' OR id IN (8, 29, 43)");
$acciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($acciones);

echo "\n=== GRUPOS Y MATRICULAS ===\n";
foreach ($acciones as $af) {
    $stmtG = $pdo->prepare("SELECT g.id, g.numero_grupo, g.expediente, g.accion_id, COUNT(m.id) as total_matriculas, SUM(CASE WHEN m.estado IS NULL OR UPPER(m.estado) != 'BAJA' THEN 1 ELSE 0 END) as active_matriculas FROM grupos g LEFT JOIN matriculas m ON m.grupo_id = g.id WHERE g.accion_id = ? GROUP BY g.id");
    $stmtG->execute([$af['id']]);
    $grupos = $stmtG->fetchAll(PDO::FETCH_ASSOC);
    echo "Accion ID {$af['id']} ({$af['abreviatura']} - {$af['titulo']}): " . count($grupos) . " grupos.\n";
    print_r($grupos);
}
