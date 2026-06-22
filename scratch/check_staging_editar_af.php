<?php
header('Content-Type: text/plain');
echo shell_exec('git log -1');
echo "\n====================\n";
echo file_get_contents('editar_af.php');
