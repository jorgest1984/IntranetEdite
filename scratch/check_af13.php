<?php
// scratch/check_af13.php
require_once dirname(__DIR__) . '/includes/config.php';

$id = 13;
$stmt = $pdo->prepare("SELECT id, curso_id, duracion, modalidad FROM acciones_formativas WHERE id = ?");
$stmt->execute([$id]);
$af = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== ACCIÓN FORMATIVA #13 ===\n";
print_r($af);

if ($af && $af['curso_id']) {
    $stmtC = $pdo->prepare("SELECT id, nombre_corto, moodle_id FROM cursos WHERE id = ?");
    $stmtC->execute([$af['curso_id']]);
    $curso = $stmtC->fetch(PDO::FETCH_ASSOC);
    echo "\n=== CURSO ASOCIADO ===\n";
    print_r($curso);
} else {
    echo "\nNo tiene curso_id asociado.";
}
?>
