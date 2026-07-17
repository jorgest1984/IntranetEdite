<?php
require_once __DIR__ . '/../../../../IntranetEdite/includes/config.php';
$stmt = $pdo->query("SELECT g.accion_id, m.alumno_id FROM matriculas m JOIN alumnos a ON m.alumno_id = a.id JOIN grupos g ON m.grupo_id = g.id WHERE m.estado != 'Baja' AND m.estado != 'Cancelada' LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "Accion: " . $row['accion_id'] . ", Alumno: " . $row['alumno_id'] . "\n";
    $_GET['accion_id'] = $row['accion_id'];
    $_GET['alumno_id'] = $row['alumno_id'];
    $_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/../../../../IntranetEdite';
    // override auth
    $old_cwd = getcwd();
    chdir(__DIR__ . '/../../../../IntranetEdite');
    require 'api_anexo1_html.php';
    chdir($old_cwd);
} else {
    echo "No data found";
}
