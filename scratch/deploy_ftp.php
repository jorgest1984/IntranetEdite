<?php
// scratch/deploy_ftp.php
$server = "212.227.149.63";
$user = "nintranet";
$pass = "Estacion.2025";

$conn = ftp_connect($server);
if (!$conn) {
    die("Error: No se pudo conectar al servidor FTP $server\n");
}

if (!ftp_login($conn, $user, $pass)) {
    die("Error: Autenticación FTP fallida\n");
}

ftp_pasv($conn, true);

$files = [
    "includes/smtp_mailer.php",
    "includes/sidebar.php",
    "api_send_trabajador_keys.php",
    "home.php",
    "css/home.css",
    "procesar_nueva_af.php",
    "editar_af.php",
    "cursos.php",
    "includes/moodle_db.php",
    "informe_evaluaciones_grupo.php",
    "pdf_acta_evaluacion.php",
    "pdf_informe_evaluaciones.php",
    "diplomas.php",
    "pdf_informe_alumno.php",
    "scratch/debug_moodle_marialuisa.php",
    "scratch/test_marialuisa_sync.php",
    "scratch/sync_all_matriculas.php"
];

foreach ($files as $f) {
    $local_file = __DIR__ . "/../" . $f;
    $remote_file = "/httpdocs/" . $f;

    if (!file_exists($local_file)) {
        echo "Archivo local no encontrado: $local_file\n";
        continue;
    }

    if (ftp_put($conn, $remote_file, $local_file, FTP_BINARY)) {
        echo "✅ Subido correctamente: $f -> $remote_file\n";
    } else {
        echo "❌ Error al subir: $f\n";
    }
}

ftp_close($conn);
echo "Envío FTP finalizado.\n";

// Limpiar la caché de PHP (OPCache) en el servidor de producción
$ctx = stream_context_create([
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    'http' => ['timeout' => 10]
]);
$opcache_res = @file_get_contents('http://gestion.grupoefp.es/clear_opcache.php', false, $ctx);
echo "Resultado limpieza OPCache: " . trim($opcache_res) . "\n";
?>
