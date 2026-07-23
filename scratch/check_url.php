<?php
$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
echo file_get_contents('https://gestion.grupoefp.es/api_moodle_pdf.php?courseid=43&userid=3&tipo=recibi&ts=1784540416&token=45b9c41d47f3bbd4a3b2900dd4a127a97b7d3436f251f6dcf204f3d1325dc360', false, $ctx);
