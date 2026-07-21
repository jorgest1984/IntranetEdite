<?php
$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
echo file_get_contents('https://gestion.grupoefp.es/scratch/migration_sedes.php', false, $ctx);
