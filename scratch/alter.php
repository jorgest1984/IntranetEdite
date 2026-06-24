<?php
require_once "../includes/config.php";

$cols = [
    "ocupacion" => "VARCHAR(255) NULL",
    "grupo_cotizacion" => "VARCHAR(100) NULL",
    "contrato" => "VARCHAR(100) NULL",
    "area_funcional" => "VARCHAR(100) NULL",
    "categoria_profesional" => "VARCHAR(100) NULL",
    "puesto_sepe" => "VARCHAR(100) NULL",
    "antiguedad" => "DATE NULL",
    "desempleado_larga_duracion" => "VARCHAR(10) NULL",
    "parado_sepe" => "VARCHAR(10) NULL",
    "conductor" => "VARCHAR(10) NULL"
];

$stmt = $pdo->query("DESCRIBE alumnos");
$existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

$added = [];
foreach ($cols as $col => $def) {
    if (!in_array($col, $existing)) {
        $pdo->exec("ALTER TABLE alumnos ADD COLUMN `$col` $def");
        $added[] = $col;
    }
}

echo json_encode(["success" => true, "added" => $added]);
?>
