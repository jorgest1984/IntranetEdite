<?php
// scratch/test_update.php
require_once dirname(__DIR__) . '/includes/config.php';

$id = 13;
$moodle_id = 999;

try {
    $stmtC = $pdo->prepare("UPDATE cursos SET moodle_id = ? WHERE id = (SELECT curso_id FROM acciones_formativas WHERE id = ?)");
    $stmtC->execute([$moodle_id, $id]);
    echo "Filas afectadas: " . $stmtC->rowCount() . "\n";
    
    // Volver a consultar
    $stmt = $pdo->prepare("SELECT c.moodle_id FROM acciones_formativas af JOIN cursos c ON af.curso_id = c.id WHERE af.id = ?");
    $stmt->execute([$id]);
    echo "Nuevo moodle_id en DB: " . var_export($stmt->fetchColumn(), true) . "\n";
    
    // Restaurar a NULL
    $stmtC->execute([null, $id]);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
