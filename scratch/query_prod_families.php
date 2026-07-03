<?php
// scratch/query_prod_families.php

function query_endpoint($url) {
    $token = 'dbbea329538b1694971d7ee66cc3e4673';
    $sql = "DESCRIBE alumnos";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'token' => $token,
        'sql' => $sql,
        'action' => 'query'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error on ' . $url . ': ' . curl_error($ch) . "\n";
    } else {
        $data = json_decode($response, true);
        echo "=== COLUMNAS EN " . strtoupper(parse_url($url, PHP_URL_HOST)) . " ===\n";
        if (isset($data['data'])) {
            foreach ($data['data'] as $col) {
                // Mostrar solo ENUM, DATE, INT o similares
                if (stripos($col['Type'], 'enum') !== false || stripos($col['Type'], 'date') !== false || stripos($col['Type'], 'int') !== false) {
                    echo "{$col['Field']} - {$col['Type']} - Nullable: {$col['Null']}\n";
                }
            }
        } else {
            print_r($data);
        }
    }
    curl_close($ch);
}

query_endpoint('https://gestion.grupoefp.es/api_bridge.php');
