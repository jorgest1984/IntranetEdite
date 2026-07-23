<?php
$html = file_get_contents('documentacion.php');
preg_match('/<script>(.*?)<\/script>/s', $html, $matches);
$js = $matches[1];
$js = preg_replace('/<\?=.*?\?>/', '1', $js);
file_put_contents('temp.js', $js);
$output = shell_exec('node -c temp.js 2>&1');
echo "Node Output: " . $output; /*SO MIERDA*/
