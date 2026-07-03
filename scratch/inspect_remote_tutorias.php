<?php
// scratch/inspect_remote_tutorias.php
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

echo "=== ALUMNOS NAMED JUAN ===\n";
print_r(call_bridge("SELECT * FROM alumnos WHERE nombre LIKE '%Juan%' OR primer_apellido LIKE '%García%'"));

echo "\n=== MATRICULA 39 ===\n";
print_r(call_bridge("SELECT * FROM matriculas WHERE id = 39"));

echo "\n=== ALUMNO 39 ===\n";
print_r(call_bridge("SELECT * FROM alumnos WHERE id = 39"));
?>
