<?php
// scratch/test_post_call.php

$cookie_file = __DIR__ . '/cookies.txt';
if (file_exists($cookie_file)) { unlink($cookie_file); }

// Step 1: GET login page to obtain CSRF token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://pre-gestion.grupoefp.es/index.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token" value="([^"]+)"/', $html, $matches);
$csrf_token = $matches[1] ?? '';
echo "Extracted CSRF token: " . $csrf_token . "\n";

if (!$csrf_token) {
    die("Error: Could not extract CSRF token.\n");
}

// Step 2: POST login
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://pre-gestion.grupoefp.es/index.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'admin',
    'password' => 'Admin123!',
    'login' => '1',
    'csrf_token' => $csrf_token
]));
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$response = curl_exec($ch);
curl_close($ch);

echo "--- LOGIN RESPONSE HEADERS ---\n";
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
echo substr($response, 0, $header_size) . "\n";

// Step 3: POST save call
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://pre-gestion.grupoefp.es/ficha_llamada.php?id=39');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'action_save_call' => '1',
    'fecha' => '2026-06-24',
    'hora' => '12:00',
    'motivo' => 'Seguimiento',
    'quien_contacta' => 'Nosotros',
    'forma' => 'Teléfono',
    'modulacion' => '',
    'horarios_pref' => '',
    'resultado' => 'Interesado',
    'asunto' => 'Prueba curl asunto',
    'notas' => 'Notas curl de prueba'
]));
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$response_call = curl_exec($ch);
$header_size_call = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

echo "--- SAVE CALL RESPONSE HEADERS ---\n";
echo substr($response_call, 0, $header_size_call) . "\n";

echo "--- SAVE CALL RESPONSE BODY (snippets) ---\n";
$body = substr($response_call, $header_size_call);
if (preg_match('/class="alert[^"]*">([^<]+)/', $body, $alert_matches)) {
    echo "Alert message found in HTML: " . trim($alert_matches[1]) . "\n";
} else {
    echo "No alert message found.\n";
}

// Clean up cookies
if (file_exists($cookie_file)) { unlink($cookie_file); }
?>
