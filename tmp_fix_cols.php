<?php
require_once 'includes/config.php';
$bridge_url = 'https://gestion.grupoefp.es/api_bridge.php';
$token = 'dbbea329538b1694971d7ee66cc3e4673';

$sql = "ALTER TABLE acciones_formativas 
        ADD COLUMN IF NOT EXISTS notas_gestion TEXT,
        ADD COLUMN IF NOT EXISTS notas_ejecucion TEXT,
        ADD COLUMN IF NOT EXISTS notas_instalacion TEXT";

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

echo "Resultado: " . $response . "\n";
?>
