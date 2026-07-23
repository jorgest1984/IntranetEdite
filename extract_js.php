<?php
$html = file_get_contents('documentacion.php');
preg_match('/<script>(.*?)<\/script>/s', $html, $matches);
file_put_contents('temp.js', $matches[1]);
