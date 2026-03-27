<?php
require_once 'includes/config.php';
$bridge_url = 'https://gestion.grupoefp.es/api_bridge.php';
$token = 'dbbea329538b1694971d7ee66cc3e4673';

$sql = "ALTER TABLE acciones_formativas 
        ADD COLUMN IF NOT EXISTS resp_documentacion_id INT,
        ADD COLUMN IF NOT EXISTS resp_seguimiento_id INT,
        ADD COLUMN IF NOT EXISTS resp_dudas_id INT,
        ADD COLUMN IF NOT EXISTS tutor1_id INT,
        ADD COLUMN IF NOT EXISTS tutor1_activo TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS tutor2_id INT,
        ADD COLUMN IF NOT EXISTS tutor2_activo TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS mostrar_otras_consultoras TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS alumnos_otras_consultoras VARCHAR(255),
        ADD COLUMN IF NOT EXISTS teleformador_id INT,
        ADD COLUMN IF NOT EXISTS id_grupo_gestion VARCHAR(100),
        ADD COLUMN IF NOT EXISTS email_tutor_gestion VARCHAR(255),
        ADD COLUMN IF NOT EXISTS nuestra_check TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS prioritaria_check TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS num_evaluaciones INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS recibi_material1 TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS recibi_material2 TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS eval1_check TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS eval1_titulo VARCHAR(255),
        ADD COLUMN IF NOT EXISTS eval2_check TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS eval2_titulo VARCHAR(255),
        ADD COLUMN IF NOT EXISTS eval3_check TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS eval3_titulo VARCHAR(255),
        ADD COLUMN IF NOT EXISTS eval4_check TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS eval4_titulo VARCHAR(255),
        ADD COLUMN IF NOT EXISTS supuesto_practico VARCHAR(255),
        ADD COLUMN IF NOT EXISTS conexia_check TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS cae_check TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS edite_gestion_check TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS nivel_gestion INT DEFAULT 1,
        ADD COLUMN IF NOT EXISTS paquete_gestion VARCHAR(100),
        ADD COLUMN IF NOT EXISTS observaciones_gestion TEXT";

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
