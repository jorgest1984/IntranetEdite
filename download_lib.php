<?php
$url = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js';
$options = array(
    'http' => array(
        'method' => "GET",
        'header' => "User-Agent: Mozilla/5.0\r\n"
    ),
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
    )
);
$context = stream_context_create($options);
$data = file_get_contents($url, false, $context);

if ($data !== false) {
    if (!is_dir(__DIR__ . '/js')) {
        mkdir(__DIR__ . '/js', 0755, true);
    }
    file_put_contents(__DIR__ . '/js/html2pdf.bundle.min.js', $data);
    echo "Descarga exitosa.";
} else {
    echo "Error al descargar.";
}
