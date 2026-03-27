<?php
// Create grupos table
$bridge_url = 'https://gestion.grupoefp.es/api_bridge.php';
$token = 'dbbea329538b1694971d7ee66cc3e4673';

$sql = "CREATE TABLE IF NOT EXISTS grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    accion_id INT NOT NULL,
    numero_grupo VARCHAR(50),
    codigo_plataforma VARCHAR(100),
    centro_id INT,
    tutor_id INT,
    fecha_inicio DATE,
    fecha_fin DATE,
    fecha_mitad DATE,
    fecha_7_dias DATE,
    modalidad VARCHAR(50),
    asignacion VARCHAR(50),
    situacion VARCHAR(50),
    horas INT,
    id_plataforma VARCHAR(100),
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
)";

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
