<?php
// Search for tables with "accion"
$bridge_url = 'https://gestion.grupoefp.es/api_bridge.php';
$token = 'dbbea329538b1694971d7ee66cc3e4673';

$sql = "SHOW TABLES LIKE '%accion%'";

$post = [
    'token' => $token,
    'sql' => $sql,
    'action' => 'query'
];

$ch = curl_init($bridge_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

echo "Tablas de acciones: " . $response . "\n";
?>
