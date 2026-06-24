<?php
require_once "../includes/config.php";
$fields = ["ultima_empresa_id", "colectivo", "ocupacion", "grupo_cotizacion", "contrato", "area_funcional", "categoria_profesional", "puesto_sepe", "antiguedad", "desempleado_larga_duracion", "parado_sepe", "conductor"];
$alumnos = $pdo->query("SHOW COLUMNS FROM alumnos")->fetchAll(PDO::FETCH_COLUMN);
$matriculas = $pdo->query("SHOW COLUMNS FROM matriculas")->fetchAll(PDO::FETCH_COLUMN);

$result = [];
foreach($fields as $f) {
    if (in_array($f, $alumnos)) $result[$f] = "alumnos";
    elseif (in_array($f, $matriculas)) $result[$f] = "matriculas";
    else $result[$f] = "NOT_FOUND";
}
echo json_encode($result, JSON_PRETTY_PRINT);
?>
