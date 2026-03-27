<?php
// Script to update DB via Bridge
$bridge_url = 'https://gestion.grupoefp.es/api_bridge.php';
$token = 'dbbea329538b1694971d7ee66cc3e4673';

$sql = "ALTER TABLE acciones_formativas 
        ADD COLUMN IF NOT EXISTS hay_material TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS num_entregas INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS codigo_entregas VARCHAR(50),
        ADD COLUMN IF NOT EXISTS num_modulos INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS detalle_entregas TEXT,
        ADD COLUMN IF NOT EXISTS manual_curso TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS manual_sensibilizacion TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS carpeta_clasificadora TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS cuaderno_a4 TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS boligrafo TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS maletin TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS otros_materiales TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS otros_materiales_txt TEXT";

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
