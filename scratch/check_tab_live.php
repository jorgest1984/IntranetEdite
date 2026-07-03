<?php
define('NO_AUTH_CHECK', true);
$ctx = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);
$content = file_get_contents("https://pre-gestion.grupoefp.es/ficha_matricula.php?id=47", false, $ctx);
echo "=== COMIENZO DE LA RESPUESTA ===\n";
echo substr($content, 0, 500) . "\n";
echo "=================================\n";
if (strpos($content, 'id="tab-curso"') !== false) {
    echo "EL TAB CURSO EXISTE EN EL HTML RENDERIZADO\n";
} else {
    echo "EL TAB CURSO NO EXISTE EN EL HTML RENDERIZADO\n";
}


