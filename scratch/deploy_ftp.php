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
    "includes/auth.php",
    "procesar_nueva_af.php",
    "cursos.php",
    "guardar_accion.php",
    "importar_matriculas_rapido.php",
    "gestion_matriculas.php",
    "ficha_alumno.php",
    "ficha_matricula.php",
    "api_import_moodle_students.php",
    "matriculas.php",
    "editar_plan.php",
    "includes/moodle_db.php",
    "scratch/debug_tiktok_modules.php",
    "scratch/sync_tiktok_now.php",
    "scratch/check_schema.php",
    "scratch/check_centros_data.php",
    "scratch/check_matriculas_cols.php",
    "scratch/remote_check_tiktok.php",
    "ficha_grupo_edicion.php"
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

// Limpiar OPCache invocando el endpoint de limpieza
$ch = curl_init("https://gestion.grupoefp.es/upload_and_clear.php?clean_opcache=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
echo "Resultado limpieza OPCache: " . strip_tags($res) . "\n";
