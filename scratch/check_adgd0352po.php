<?php
require_once __DIR__ . '/../includes/config.php';

echo "<pre>";
echo "=== BUSCANDO AF ADGD0352PO ===\n";
$stmt = $pdo->prepare("SELECT id, num_accion, titulo, abreviatura, curso_id FROM acciones_formativas WHERE num_accion LIKE '%ADGD0352PO%' OR titulo LIKE '%ADGD0352PO%' OR abreviatura LIKE '%ADGD0352PO%'");
$stmt->execute();
$afs = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($afs);

if (!empty($afs)) {
    foreach ($afs as $af) {
        $stmtG = $pdo->prepare("SELECT id, numero_grupo, expediente, fecha_inicio, fecha_fin FROM grupos WHERE accion_id = ? ORDER BY id DESC");
        $stmtG->execute([$af['id']]);
        $grupos = $stmtG->fetchAll(PDO::FETCH_ASSOC);
        echo "Grupos para AF ID " . $af['id'] . ":\n";
        print_r($grupos);
    }
} else {
    echo "NO SE ENCONTRÓ AF DIRECTA POR ADGD0352PO, BUSCANDO POR TITULO 'TRANSFORMACION DIGITAL'...\n";
    $stmt2 = $pdo->prepare("SELECT id, num_accion, titulo, abreviatura, curso_id FROM acciones_formativas WHERE titulo LIKE '%TRANSFORMACIÓN DIGITAL%' OR titulo LIKE '%TRANSFORMACION DIGITAL%'");
    $stmt2->execute();
    $afs2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    print_r($afs2);
    foreach ($afs2 as $af) {
        $stmtG = $pdo->prepare("SELECT id, numero_grupo, expediente, fecha_inicio, fecha_fin FROM grupos WHERE accion_id = ? ORDER BY id DESC");
        $stmtG->execute([$af['id']]);
        $grupos = $stmtG->fetchAll(PDO::FETCH_ASSOC);
        echo "Grupos para AF ID " . $af['id'] . ":\n";
        print_r($grupos);
    }
}
echo "</pre>";
