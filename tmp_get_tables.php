<?php
// Get all table names
$bridge_url = 'https://gestion.grupoefp.es/api_bridge.php';
$token = 'dbbea329538b1694971d7ee66cc3e4673';

$sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()";

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

$data = json_decode($response, true);
if (isset($data['data'])) {
    foreach ($data['data'] as $row) {
        echo $row['table_name'] . "\n";
    }
} else {
    echo "Error: " . $response . "\n";
}
?>
