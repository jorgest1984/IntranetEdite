<?php
require_once "../includes/config.php";
$alumnos = $pdo->query("SHOW COLUMNS FROM alumnos")->fetchAll(PDO::FETCH_COLUMN);
$matriculas = $pdo->query("SHOW COLUMNS FROM matriculas")->fetchAll(PDO::FETCH_COLUMN);
echo json_encode(["alumnos" => $alumnos, "matriculas" => $matriculas], JSON_PRETTY_PRINT);
?>
