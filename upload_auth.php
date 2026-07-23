<?php
$conn = ftp_connect("212.227.149.63");
ftp_login($conn, "nintranet", "Estacion.2025");
ftp_pasv($conn, true);
$files = [
    "includes/auth.php", 
    "includes/sidebar.php"
];
foreach ($files as $f) {
    if (ftp_put($conn, "/httpdocs/" . $f, __DIR__ . "/" . $f, FTP_BINARY)) {
        echo "Uploaded $f\n";
    } else {
        echo "Error uploading $f\n";
    }
}
ftp_close($conn);

$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
echo file_get_contents('http://gestion.grupoefp.es/clear_opcache.php', false, $ctx);
?>
