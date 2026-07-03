<?php
// scratch/run_remote_requests.php

function fetch_url($url) {
    $ctx = stream_context_create([
        'http' => [
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    return file_get_contents($url, false, $ctx);
}

echo "1. Actualizando pre-gestion (pull)...\n";
$res1 = fetch_url('https://pre-gestion.grupoefp.es/scratch/git_pull.php?action=pull');
echo "Resultado pre-gestion:\n" . $res1 . "\n";

echo "--------------------------------------------------\n";
echo "2. Actualizando gestion (force pull)...\n";
$res2 = fetch_url('https://gestion.grupoefp.es/scratch/git_pull.php?action=force');
echo "Resultado gestion:\n" . $res2 . "\n";

echo "--------------------------------------------------\n";
echo "3. Diagnóstico de conexión Moodle en pre-gestion...\n";
$diag1 = fetch_url('https://pre-gestion.grupoefp.es/scratch/test_moodle_connection.php');
echo "Resultado diagnóstico pre-gestion:\n" . $diag1 . "\n";

echo "--------------------------------------------------\n";
echo "4. Diagnóstico de conexión Moodle en gestion...\n";
$diag2 = fetch_url('https://gestion.grupoefp.es/scratch/test_moodle_connection.php');
echo "Resultado diagnóstico gestion:\n" . $diag2 . "\n";
?>
