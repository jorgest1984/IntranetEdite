<?php
// scratch/get_remote_schema.php
function call_bridge($sql) {
    $url = 'https://pre-gestion.grupoefp.es/api_bridge.php';
    $token = 'dbbea329538b1694971d7ee66cc3e4673';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'token' => $token,
        'sql' => $sql,
        'action' => 'query'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error: ' . curl_error($ch) . "\n";
        return null;
    }
    curl_close($ch);
    return json_decode($response, true);
}

echo "=== COLUMNAS DE MATRICULAS ===\n";
$res1 = call_bridge("DESCRIBE matriculas");
if ($res1 && isset($res1['data'])) {
    foreach ($res1['data'] as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} else {
    print_r($res1);
}

echo "\n=== COLUMNAS DE ALUMNOS ===\n";
$res2 = call_bridge("DESCRIBE alumnos");
if ($res2 && isset($res2['data'])) {
    foreach ($res2['data'] as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} else {
    print_r($res2);
}

echo "\n=== MATRICULAS FOR ALUMNO 39 ===\n";
$res3 = call_bridge("SELECT * FROM matriculas WHERE alumno_id = 39");
print_r($res3);

$res4 = call_bridge("SELECT m.*, g.id as gid, g.fecha_inicio, g.fecha_fin 
                       FROM matriculas m 
                       LEFT JOIN grupos g ON m.grupo_id = g.id 
                       WHERE m.alumno_id = 39");
print_r($res4);
?>



