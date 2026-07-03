<?php
// scratch/get_preprod_tables.php
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

$res = call_bridge("SHOW TABLES");
if ($res && isset($res['data'])) {
    foreach ($res['data'] as $row) {
        echo array_values($row)[0] . "\n";
    }
} else {
    print_r($res);
}
?>
