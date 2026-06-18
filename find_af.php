<?php
require_once 'includes/config.php';
header('Content-Type: application/json');
$stmt = $pdo->query("SELECT id, titulo, id_plataforma FROM acciones_formativas WHERE titulo LIKE '%ADGN0108%'");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res);
