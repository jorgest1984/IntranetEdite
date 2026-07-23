<?php
$conn = ftp_connect("212.227.149.63");
ftp_login($conn, "nintranet", "Estacion.2025");
ftp_pasv($conn, true);
print_r(ftp_nlist($conn, "/httpdocs/"));
?>
