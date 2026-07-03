<?php
define('NO_AUTH_CHECK', true);
$content = file_get_contents("ficha_matricula.php");
if (strpos($content, 'id="tab-curso"') !== false) {
    echo "EL TAB CURSO EXISTE EN EL ARCHIVO LOCAL\n";
} else {
    echo "EL TAB CURSO NO EXISTE EN EL ARCHIVO LOCAL\n";
}
