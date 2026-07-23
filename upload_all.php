<?php
$conn = ftp_connect("212.227.149.63");
ftp_login($conn, "nintranet", "Estacion.2025");
ftp_pasv($conn, true);
$files = [
    "includes/auth.php", 
    "includes/sidebar.php", 
    "centros.php", 
    "nuevo_centro.php", 
    "editar_centro.php", 
    "usuarios.php", 
    "ficha_trabajador.php", 
    "grupos.php", 
    "ficha_grupo_edicion.php", 
    "guardar_grupo.php", 
    "alumnos.php", 
    "pdf_hoja_bienvenida.php", 
    "nueva_af.php",
    "editar_af.php",
    "procesar_nueva_af.php",
    "guardar_accion.php",
    "scratch/migration_sedes_v2.php",
    "clear_opcache.php"
];
foreach ($files as $f) {
    if (file_exists(__DIR__ . "/" . $f)) {
        if (ftp_put($conn, "/httpdocs/" . $f, __DIR__ . "/" . $f, FTP_BINARY)) {
            echo "Uploaded $f\n";
        } else {
            echo "Error uploading $f\n";
        }
    }
}
ftp_close($conn);
echo "Upload Done.\n";

$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
echo file_get_contents('http://gestion.grupoefp.es/clear_opcache.php', false, $ctx);
?>
