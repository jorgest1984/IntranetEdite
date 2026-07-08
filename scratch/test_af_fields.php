<?php
// scratch/test_af_fields.php
require_once dirname(__DIR__) . '/includes/config.php';

$id = 13;
$fields = ['duracion', 'modalidad', 'prioridad', 'estado', 'familia_profesional', 'objetivos', 'contenidos', 'contenidos_breves', 'notas_gestion'];
$data = [20, 'TELEFORMACIÓN', 'Alta', 'ACTIVA', 'Administración y Gestión', 'test objetivos', 'test contenidos', 'test contenidos breves', 'test notas'];
$data[] = $id;

$sql = "UPDATE acciones_formativas SET ";
$sets = [];
foreach ($fields as $f) {
    $sets[] = "$f = ?";
}
$sql .= implode(", ", $sets) . " WHERE id = ?";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    echo "¡UPDATE de acciones_formativas exitoso!\n";
} catch (Exception $e) {
    echo "ERROR en UPDATE de acciones_formativas: " . $e->getMessage() . "\n";
}
?>
