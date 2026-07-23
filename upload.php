<?php
$server = "212.227.149.63";
$user = "nintranet";
$pass = "Estacion.2025";
$conn = ftp_connect($server) or die("Could not connect");
ftp_login($conn, $user, $pass);
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
    "scratch/migration_sedes_v2.php"
];
foreach ($files as $f) {
    if (ftp_put($conn, "/httpdocs/" . $f, __DIR__ . "/" . $f, FTP_BINARY)) {
        echo "Uploaded $f\n";
    } else {
        echo "Error uploading $f\n";
    }
}
ftp_close($conn);
echo "Done.";
?>
