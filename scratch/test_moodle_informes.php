<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
$_SESSION['user_role'] = 'admin';

$_GET['cursoid'] = 43;
$_GET['grupo_id'] = 16;

ob_start();
include __DIR__ . '/../moodle_informes.php';
$html = ob_get_clean();

header('Content-Type: text/plain; charset=utf-8');
echo "HTML Length: " . strlen($html) . " bytes\n";
if (strpos($html, 'Grupo 2') !== false) {
    echo "SUCCESS: Grupo 2 tab and content rendered!\n";
} else {
    echo "FAILED to find Grupo 2 in rendered HTML.\n";
}
if (strpos($html, 'Seleccionar Grupo:') !== false) {
    echo "SUCCESS: Selector de grupos presente!\n";
}
