<?php
// Enrutador Front Controller para Vercel
// Forzando un nuevo despliegue en Vercel tras actualizar a Pro
// Con el nuevo correo electronico
// Esto permite a Vercel tener su archivo dentro de la carpeta obligatoria "api"
// Actualización de marca: Grupo EFP - 2026-04-08

chdir(__DIR__ . '/..');

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Si se pide la raíz
if ($path === '/' || $path === '/api/' || $path === '') {
    require 'index.php';
    exit;
}

// Quitar la barra inicial
$file = ltrim($path, '/');

// Evitar path traversal
if (strpos($file, '..') !== false) {
    http_response_code(403);
    die('Acceso denegado');
}

// Si es un archivo .php válido, ejecutarlo
if (preg_match('/\.php$/', $file) && file_exists($file)) {
    require $file;
    exit;
}

// Si la ruta no es PHP, intentar servir si Vercel falló (Vercel debería manejar estáticos antes)
if (file_exists($file) && is_file($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mimes = [
        'css' => 'text/css',
        'js'  => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain'
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }
    readfile($file);
    exit;
}

// Por defecto 404
http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($file);
