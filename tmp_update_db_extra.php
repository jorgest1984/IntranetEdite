<?php
// Script to update DB via Bridge - Add missing field
$bridge_url = 'https://gestion.grupoefp.es/api_bridge.php';
$token = 'dbbea329538b1694971d7ee66cc3e4673';

$sql = "ALTER TABLE acciones_formativas 
        ADD COLUMN IF NOT EXISTS material_extra_info TEXT";

$post = [
    'token' => $token,
    'sql' => $sql,
    'action' => 'execute'
];

$ch = curl_init($bridge_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

echo "Respuesta del Bridge: " . $response . "\n";
?>
